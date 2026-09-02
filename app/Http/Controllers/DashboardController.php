<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\GovernmentEntity;
use App\Models\StateCorporation;
use App\Support\WasteCategories;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Legacy free-form submissions recorded weight directly in kg columns.
     * Used on its own only where the figure is specifically about that old
     * Paper/Metal/Plastic/... taxonomy (materialsIndex) - Lot categories
     * aren't "materials" in that scheme, so folding Lot data in there would
     * inflate the denominator against numerators that never include it.
     */
    private const TOTAL_KG_SQL = '(paper_kg + metal_kg + plastic_kg + furniture_kg + ewaste_kg + other_kg)';

    /**
     * Legacy kg + Lot rows whose unit is genuinely kg - the only two things
     * that can be safely added together into one "kg" figure. A liter of
     * liquid waste or a ton of solid waste is a different quantity and must
     * never be folded into this.
     */
    private const COMBINED_KG_SQL = '('.self::TOTAL_KG_SQL.")  + (CASE WHEN unit = 'kg' THEN quantity ELSE 0 END)";

    private const LEGACY_COLOR = '#7a4fa0';

    public function index(): View
    {
        $totalSubmissions = Collection::count();

        $ministryParticipating = Collection::whereNotNull('ministry_id')->distinct('ministry_id')->count('ministry_id');
        $ministryTotal = GovernmentEntity::ministries()->count();

        $stateCorpTotal = StateCorporation::count();
        $stateCorpPhase1 = StateCorporation::phaseOne()->count();
        $stateCorpPhase2 = StateCorporation::phaseTwo()->count();

        $materialItemCount = collect(WasteCategories::lots())->sum(fn (array $lot) => count($lot['categories']));

        $recent = Collection::query()
            ->orderByDesc('collection_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('dashboard', [
            'totalSubmissions' => $totalSubmissions,
            'ministryParticipating' => $ministryParticipating,
            'ministryTotal' => $ministryTotal,
            'stateCorpTotal' => $stateCorpTotal,
            'stateCorpPhase1' => $stateCorpPhase1,
            'stateCorpPhase2' => $stateCorpPhase2,
            'materialItemCount' => $materialItemCount,
            'recent' => $recent,
        ]);
    }

    /**
     * Full "Participating Entities" breakdown - every entity that has ever
     * submitted a collection, not just the homepage's top-10 preview. Each
     * row links into the filtered submissions list for that entity.
     */
    public function entitiesIndex(): View
    {
        $entities = Collection::query()
            ->selectRaw('entity_name, COUNT(*) as submissions, MAX(collection_date) as last_collection_date, '.self::entityQuantitySql())
            ->groupBy('entity_name')
            ->orderByDesc('submissions')
            ->get();

        return view('entities.index', [
            'entities' => $entities,
        ]);
    }

    /**
     * Full "Ministries" breakdown - every ministry from the master
     * hierarchy (now 24: the 22 named ministries plus Council of Governors
     * and the Presidency), not just the ones with submissions so far, so
     * this reads as coverage against the whole government rather than just
     * a list of participants. Each row links into the filtered submissions
     * list, and carries its assigned Coordinator (see
     * DistributeMinistries) so the boss can see who owns what.
     *
     * The search box matches the ministry's own name plus every state
     * department and institution nested under it - loaded eagerly and
     * filtered in memory since the whole tree is only a few hundred rows,
     * the same trade-off RmDashboardController::ministryTree() already
     * makes for the same data.
     */
    public function ministriesIndex(Request $request): View
    {
        $search = $request->string('q')->toString() ?: null;
        $needle = $search ? mb_strtolower($search) : null;

        $ministries = GovernmentEntity::ministries()
            ->orderBy('id')
            ->with(['assignedRm', 'children.children'])
            ->get()
            ->filter(function (GovernmentEntity $ministry) use ($needle) {
                if ($needle === null) {
                    return true;
                }

                if (str_contains(mb_strtolower($ministry->name), $needle)) {
                    return true;
                }

                foreach ($ministry->children as $department) {
                    if (str_contains(mb_strtolower($department->name), $needle)) {
                        return true;
                    }
                    foreach ($department->children as $institution) {
                        if (str_contains(mb_strtolower($institution->name), $needle)) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->map(function (GovernmentEntity $ministry) {
                $row = Collection::where('ministry_id', $ministry->id)
                    ->selectRaw('COUNT(*) as submissions, '.self::entityQuantitySql())
                    ->first();

                return array_merge(
                    [
                        'id' => $ministry->id,
                        'name' => $ministry->name,
                        'coordinator' => $ministry->assignedRm?->name,
                        'submissions' => (int) $row->submissions,
                    ],
                    self::unitTotalsArray($row)
                );
            })
            ->values();

        return view('ministries.index', [
            'ministries' => $ministries,
            'totalMinistries' => GovernmentEntity::ministries()->count(),
            'search' => $search,
        ]);
    }

    /**
     * One ministry's breakdown by State Department - card per department
     * (including the ones with zero submissions, same "full coverage"
     * reasoning as the ministries index), each card linking into that
     * department's own institution breakdown.
     */
    public function ministryShow(GovernmentEntity $ministry): View
    {
        abort_unless($ministry->level === GovernmentEntity::LEVEL_MINISTRY, 404);

        $ministry->load('assignedRm');

        $overall = Collection::where('ministry_id', $ministry->id)
            ->selectRaw('COUNT(*) as submissions, '.self::entityQuantitySql())
            ->first();

        $departments = $ministry->children()->orderBy('id')->get()
            ->map(fn (GovernmentEntity $dept) => self::withCounts($dept, ['state_department_id' => $dept->id]));

        return view('ministries.show', [
            'ministry' => $ministry,
            'overall' => $overall,
            'departments' => $departments,
        ]);
    }

    /**
     * One state department's breakdown by Institution, plus the full
     * filterable submissions table underneath (terminal level - an
     * institution has no further breakdown). Submissions recorded against
     * the department but with no institution picked (the field is
     * optional, since some departments have no institutions listed at all)
     * get their own "Not specified" card rather than being hidden.
     */
    public function departmentShow(GovernmentEntity $ministry, GovernmentEntity $department, Request $request): View
    {
        abort_unless($ministry->level === GovernmentEntity::LEVEL_MINISTRY, 404);
        abort_unless(
            $department->level === GovernmentEntity::LEVEL_STATE_DEPARTMENT && $department->parent_id === $ministry->id,
            404
        );

        $overall = Collection::where('state_department_id', $department->id)
            ->selectRaw('COUNT(*) as submissions, '.self::entityQuantitySql())
            ->first();

        $institutions = $department->children()->orderBy('id')->get()
            ->map(fn (GovernmentEntity $inst) => self::withCounts($inst, ['institution_id' => $inst->id]));

        $unspecifiedCount = Collection::where('state_department_id', $department->id)->whereNull('institution_id')->count();
        if ($unspecifiedCount > 0) {
            $institutions->push([
                'id' => null,
                'name' => 'Not specified',
                'submissions' => $unspecifiedCount,
            ]);
        }

        // 'none' is a distinct sentinel from "no filter" - it means "only
        // submissions with no institution picked", not "show everything".
        $institutionParam = $request->string('institution')->toString() ?: null;
        $institutionId = ($institutionParam && $institutionParam !== 'none') ? (int) $institutionParam : null;
        $filterUnspecified = $institutionParam === 'none';

        $collections = Collection::where('state_department_id', $department->id)
            ->when($institutionId, fn ($q, $v) => $q->where('institution_id', $v))
            ->when($filterUnspecified, fn ($q) => $q->whereNull('institution_id'))
            ->orderByDesc('collection_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('ministries.departments.show', [
            'ministry' => $ministry,
            'department' => $department,
            'overall' => $overall,
            'institutions' => $institutions,
            'collections' => $collections,
            'filters' => ['institution' => $institutionParam],
        ]);
    }

    /**
     * "State Corporations" (the boss's Level 2 parastatal category, what
     * this tracker used to loosely call "institutions") - a flat,
     * standalone registry seeded from the official 348-corporation list
     * (see database/data/state_corporations.json), tagged Phase 1 (the
     * boss's named pilot clients) or Phase 2 (everything else). Filterable
     * by phase and by name/cluster search.
     */
    public function stateCorporationsIndex(Request $request): View
    {
        $phase = $request->integer('phase') ?: null;
        $search = $request->string('q')->toString() ?: null;

        $classification = $request->string('classification')->toString() ?: null;

        $corporations = StateCorporation::query()
            ->with('ministry')
            ->when($phase, fn ($q, $v) => $q->where('phase', $v))
            ->when($classification, fn ($q, $v) => $q->where('classification', $v))
            ->when($search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->orderBy('phase')
            ->orderBy('cluster')
            ->orderBy('name')
            ->get();

        return view('state-corporations.index', [
            'corporations' => $corporations,
            'phase1Count' => StateCorporation::phaseOne()->count(),
            'phase2Count' => StateCorporation::phaseTwo()->count(),
            'classifications' => StateCorporation::query()
                ->select('classification')->distinct()->orderBy('classification')->pluck('classification'),
            'filters' => ['phase' => $phase, 'q' => $search, 'classification' => $classification],
        ]);
    }

    /**
     * One client's (state corporation / county / polytechnic / etc.) full
     * detail page - classification, ministry, cluster/class, assigned RM,
     * and every submission recorded against it. Mirrors the ministry
     * show/department pattern, collapsed to one page since a client has no
     * further sub-hierarchy the way a ministry has departments.
     */
    public function stateCorporationShow(StateCorporation $stateCorporation): View
    {
        $stateCorporation->load(['ministry', 'assignedRm']);

        $overall = Collection::where('state_corporation_id', $stateCorporation->id)
            ->selectRaw('COUNT(*) as submissions, '.self::entityQuantitySql())
            ->first();

        $collections = Collection::where('state_corporation_id', $stateCorporation->id)
            ->orderByDesc('collection_date')
            ->orderByDesc('id')
            ->paginate(25);

        return view('state-corporations.show', [
            'corporation' => $stateCorporation,
            'overall' => $overall,
            'collections' => $collections,
        ]);
    }

    /**
     * "Material Items" - the Lot 1 / Lot 2 catalog itself (every category,
     * subcategory and the units each one is valid in), not submission
     * data. Each row also carries its own submission count as a light
     * cross-reference into how much that catalog entry has actually been
     * used, without turning this into a duplicate of the weight/lot
     * breakdowns already on the homepage.
     */
    public function materialItemsIndex(): View
    {
        $lots = collect(WasteCategories::lots())->map(function (array $lot, int $lotKey) {
            $categories = collect($lot['categories'])->map(function (array $meta, string $categoryKey) use ($lotKey, $lot) {
                if (! empty($lot['has_subcategories']) && isset($meta['subcategories'])) {
                    $subcategories = collect($meta['subcategories'])->map(function (array $sub, string $subKey) use ($lotKey, $categoryKey) {
                        return [
                            'label' => $sub['label'],
                            'units' => array_map(fn ($u) => WasteCategories::UNIT_LABELS[$u] ?? $u, $sub['units']),
                            'submissions' => Collection::where('lot', $lotKey)->where('category', $categoryKey)->where('subcategory', $subKey)->count(),
                        ];
                    })->values();

                    return [
                        'label' => $meta['label'],
                        'subcategories' => $subcategories,
                        'units' => [],
                        'submissions' => Collection::where('lot', $lotKey)->where('category', $categoryKey)->count(),
                    ];
                }

                return [
                    'label' => $meta['label'],
                    'subcategories' => collect(),
                    'units' => array_map(fn ($u) => WasteCategories::UNIT_LABELS[$u] ?? $u, $meta['units']),
                    'submissions' => Collection::where('lot', $lotKey)->where('category', $categoryKey)->count(),
                ];
            })->values();

            return [
                'lot' => $lotKey,
                'label' => $lot['label'],
                'has_subcategories' => ! empty($lot['has_subcategories']),
                'categories' => $categories,
            ];
        })->values();

        return view('material-items.index', ['lots' => $lots]);
    }

    /**
     * "Feasibility Study" - the RM-submitted survey data itself (what the
     * homepage's old "Total Submissions" card pointed at), browsable by
     * Client or Ministry per the boss's request (2026-09-02) to trim this
     * down from its earlier 4-way agent/materials/ministries/client split -
     * those two other breakdowns are still reachable via their own pages
     * (collections index filters, Material Items), just not tabs here.
     * "By Client" will read as all-zero until the Account Manager
     * availability-survey flow (Level 4) actually writes
     * state_corporation_id on a Collection - the column exists (see the
     * 2026_08_31_000002 migration) but nothing populates it yet, so this
     * view is honestly empty rather than broken.
     */
    public function feasibilityStudyIndex(Request $request): View
    {
        $view = $request->string('view')->toString();
        $view = in_array($view, ['ministries', 'state-corporation'], true) ? $view : 'state-corporation';

        $search = $request->string('q')->toString() ?: null;

        $rows = match ($view) {
            'ministries' => GovernmentEntity::ministries()
                ->when($search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
                ->orderBy('id')->get()
                ->map(function (GovernmentEntity $ministry) {
                    $row = Collection::where('ministry_id', $ministry->id)
                        ->selectRaw('COUNT(*) as submissions, '.self::entityQuantitySql())
                        ->first();

                    return array_merge(
                        ['id' => $ministry->id, 'label' => $ministry->name, 'submissions' => (int) $row->submissions],
                        self::unitTotalsArray($row)
                    );
                })->sortByDesc('submissions')->values(),
            'state-corporation' => StateCorporation::query()
                ->when($search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
                ->orderBy('name')->get()
                ->map(function (StateCorporation $corp) {
                    $row = Collection::where('state_corporation_id', $corp->id)
                        ->selectRaw('COUNT(*) as submissions, '.self::entityQuantitySql())
                        ->first();

                    return array_merge(
                        ['id' => $corp->id, 'label' => $corp->name, 'submissions' => (int) $row->submissions],
                        self::unitTotalsArray($row)
                    );
                })->sortByDesc('submissions')->values(),
        };

        return view('feasibility-study.index', [
            'view' => $view,
            'rows' => $rows,
            'totalSubmissions' => Collection::count(),
            'search' => $search,
        ]);
    }

    /**
     * @return array{id: int, name: string, submissions: int}
     */
    private static function withCounts(GovernmentEntity $entity, array $where): array
    {
        return [
            'id' => $entity->id,
            'name' => $entity->name,
            'submissions' => Collection::where($where)->count(),
        ];
    }

    /**
     * Per-entity totals, split by unit rather than blended into one number -
     * kg, litres, tonnes, pieces, units, cartons, sets and m3 are all
     * different quantities and must never be added together. Legacy kg
     * columns fold into total_kg since they genuinely are kg.
     */
    private static function entityQuantitySql(): string
    {
        $parts = ['SUM('.self::COMBINED_KG_SQL.') as total_kg'];

        foreach (array_keys(WasteCategories::UNIT_LABELS) as $unit) {
            if ($unit === 'kg') {
                continue;
            }
            $parts[] = "SUM(CASE WHEN unit = '{$unit}' THEN quantity ELSE 0 END) as total_{$unit}";
        }

        return implode(', ', $parts);
    }

    /**
     * Same per-unit split as entityQuantitySql(), but without folding
     * legacy kg into total_kg - used for Lot-scoped breakdowns where legacy
     * rows (lot IS NULL) never appear anyway, so the merge would be a no-op
     * at best and confusing to read at worst.
     */
    private static function unitBreakdownSql(): string
    {
        $parts = [];

        foreach (array_keys(WasteCategories::UNIT_LABELS) as $unit) {
            $parts[] = "SUM(CASE WHEN unit = '{$unit}' THEN quantity ELSE 0 END) as total_{$unit}";
        }

        return implode(', ', $parts);
    }

    /**
     * Flattens an entityQuantitySql() row into a plain ['total_kg' => ...,
     * 'total_tonnes' => ..., ...] array covering every unit - used where a
     * row needs to be rebuilt into a new array (e.g. ministriesIndex()
     * merging in id/name/submissions) rather than passed straight through.
     *
     * @return array<string, float>
     */
    private static function unitTotalsArray(object $row): array
    {
        $out = ['total_kg' => (float) $row->total_kg];

        foreach (array_keys(WasteCategories::UNIT_LABELS) as $unit) {
            if ($unit === 'kg') {
                continue;
            }
            $out["total_{$unit}"] = (float) ($row->{"total_{$unit}"} ?? 0);
        }

        return $out;
    }

    /**
     * @return array<int, array{unit: string, label: string, quantity: float}>
     */
    private static function nonZeroUnits(object $row): array
    {
        $out = [];

        foreach (WasteCategories::UNIT_LABELS as $key => $label) {
            $value = (float) ($row->{"total_{$key}"} ?? 0);
            if ($value > 0) {
                $out[] = ['unit' => $key, 'label' => $label, 'quantity' => $value];
            }
        }

        return $out;
    }

    /**
     * Full weight breakdown - the homepage shows the same chart, but this
     * adds share-of-total and how many distinct entities reported each
     * category, and links each row into the filtered submissions list.
     */
    public function materialsIndex(): View
    {
        $byWeight = self::byWeightCategories();
        $totalKg = (float) $byWeight->sum('kg');

        $byWeight = $byWeight->map(fn (array $row) => $row + [
            'share' => $totalKg > 0 ? $row['kg'] / $totalKg * 100 : 0,
            'entities' => $row['key'] === 'legacy'
                ? Collection::whereNull('lot')->whereRaw(self::TOTAL_KG_SQL.' > 0')->distinct('entity_name')->count('entity_name')
                : Collection::where('lot', $row['lot'])->where('category', $row['category'])
                    ->when($row['subcategory'], fn ($q, $v) => $q->where('subcategory', $v))
                    ->distinct('entity_name')->count('entity_name'),
        ]);

        return view('materials.index', [
            'byWeight' => $byWeight,
            'totalKg' => $totalKg,
        ]);
    }

    /**
     * Every category/subcategory actually recorded in kg - the old legacy
     * Paper/Metal/Plastic/... submissions folded into one "Legacy
     * Submissions" bucket (they predate the Lot/Category structure and
     * were never broken out that way). Data-driven rather than reading a
     * fixed unit off the category config, since a category can now be
     * recorded in more than one unit (e.g. Scrap Materials in kg or
     * Tonnes) - only the rows an RM actually entered in kg count here.
     * Litres, tonnes, m3, pieces, units, cartons and sets are deliberately
     * excluded - they are not the same quantity and don't belong in a
     * weight chart.
     *
     * @return \Illuminate\Support\Collection<int, array{key: string, lot: ?int, category: ?string, subcategory: ?string, label: string, kg: float, color: string}>
     */
    private static function byWeightCategories(): \Illuminate\Support\Collection
    {
        $rows = collect();

        $legacyKg = (float) Collection::whereNull('lot')->selectRaw('SUM('.self::TOTAL_KG_SQL.') as total')->value('total');
        if ($legacyKg > 0) {
            $rows->push(['key' => 'legacy', 'lot' => null, 'category' => null, 'subcategory' => null, 'label' => 'Legacy Submissions', 'kg' => $legacyKg, 'color' => self::LEGACY_COLOR]);
        }

        $kgRows = Collection::whereNotNull('lot')
            ->where('unit', 'kg')
            ->selectRaw('lot, category, subcategory, SUM(quantity) as kg')
            ->groupBy('lot', 'category', 'subcategory')
            ->having('kg', '>', 0)
            ->get();

        foreach ($kgRows as $row) {
            $lot = (int) $row->lot;
            $label = WasteCategories::subcategoryLabel($lot, $row->category, $row->subcategory)
                ?? WasteCategories::categoryLabel($lot, $row->category)
                ?? $row->category;
            $fullLabel = $label.' ('.WasteCategories::shortLotLabel($lot).')';

            $rows->push([
                'key' => $row->category.'|'.($row->subcategory ?? ''),
                'lot' => $lot,
                'category' => $row->category,
                'subcategory' => $row->subcategory,
                'label' => $fullLabel,
                'kg' => (float) $row->kg,
                'color' => self::colorFor($fullLabel),
            ]);
        }

        return $rows->sortByDesc('kg')->values();
    }

    /**
     * Deterministic color per label (same label always -> same color) via a
     * hue rotation - the category/subcategory list is now large and
     * data-driven (dozens of possible combinations), so a hand-picked fixed
     * palette isn't practical here the way it is for the small, fixed set
     * of homepage stat tiles.
     */
    private static function colorFor(string $label): string
    {
        $hue = crc32($label) % 360;

        return sprintf('hsl(%d, 55%%, 38%%)', $hue);
    }
}
