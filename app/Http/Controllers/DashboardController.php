<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Support\WasteCategories;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

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

    /**
     * Fixed, distinct color per slot so a category's color stays stable
     * across page loads instead of being reassigned as data changes.
     */
    private const CATEGORY_COLORS = [
        'legacy' => '#7a4fa0',
        'equipment' => '#0f7a3d',
        'motor_vehicles' => '#1a9650',
        'assets' => '#0093b3',
        'stores' => '#b2491f',
        'scrap' => '#8a5c00',
        'tires' => '#556b2f',
        'other_sale' => '#9c8f00',
        'medical_industrial' => '#c98500',
        'ewaste' => '#b23a78',
        'other_disposal' => '#4f3268',
    ];

    public function index(): View
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $totalSubmissions = Collection::count();
        $totalKg = (float) Collection::selectRaw('SUM('.self::COMBINED_KG_SQL.') as total')->value('total');

        $thisMonthKg = (float) Collection::whereBetween('collection_date', [$monthStart, $monthEnd])
            ->selectRaw('SUM('.self::COMBINED_KG_SQL.') as total')->value('total');

        $thisMonthSubmissions = Collection::whereBetween('collection_date', [$monthStart, $monthEnd])->count();

        $entityCount = Collection::distinct('entity_name')->count('entity_name');

        $byWeight = self::byWeightCategories();

        $byLot = collect(WasteCategories::lots())->map(function (array $lot, int $lotKey) {
            $categories = collect($lot['categories'])->map(function (array $meta, string $key) use ($lotKey) {
                return [
                    'key' => $key,
                    'label' => $meta['label'],
                    'unit' => $meta['unit'],
                    'quantity' => (float) Collection::where('lot', $lotKey)->where('category', $key)->sum('quantity'),
                    'submissions' => Collection::where('lot', $lotKey)->where('category', $key)->count(),
                ];
            })->values();

            return [
                'lot' => $lotKey,
                'label' => $lot['short_label'],
                'submissions' => Collection::where('lot', $lotKey)->count(),
                'categories' => $categories,
            ];
        })->values();

        $byEntity = Collection::query()
            ->selectRaw('entity_name, COUNT(*) as submissions, '.self::entityQuantitySql())
            ->groupBy('entity_name')
            ->orderByDesc('submissions')
            ->limit(10)
            ->get();

        $recent = Collection::query()
            ->orderByDesc('collection_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('dashboard', [
            'totalSubmissions' => $totalSubmissions,
            'totalKg' => $totalKg,
            'thisMonthKg' => $thisMonthKg,
            'thisMonthSubmissions' => $thisMonthSubmissions,
            'entityCount' => $entityCount,
            'byWeight' => $byWeight,
            'byLot' => $byLot,
            'byEntity' => $byEntity,
            'recent' => $recent,
            'monthRangeHref' => route('collections.index', [
                'from' => $monthStart->toDateString(),
                'to' => $monthEnd->toDateString(),
            ]),
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
     * Per-entity totals, split by unit rather than blended into one number -
     * a submission's kg, liters and tons are three different quantities and
     * must never be added together. Legacy kg columns fold into total_kg
     * since they genuinely are kg.
     */
    private static function entityQuantitySql(): string
    {
        return 'SUM('.self::COMBINED_KG_SQL.') as total_kg, '
            ."SUM(CASE WHEN unit = 'ltr' THEN quantity ELSE 0 END) as total_ltr, "
            ."SUM(CASE WHEN unit = 'ton' THEN quantity ELSE 0 END) as total_ton";
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
                : Collection::where('lot', $row['lot'])->where('category', $row['key'])->distinct('entity_name')->count('entity_name'),
        ]);

        return view('materials.index', [
            'byWeight' => $byWeight,
            'totalKg' => $totalKg,
        ]);
    }

    /**
     * Every category whose unit is genuinely kg - the old legacy
     * Paper/Metal/Plastic/... submissions folded into one "Legacy
     * Submissions" bucket (they predate the Lot/Category structure and
     * were never broken out that way), plus every Lot 1/Lot 2 category
     * whose unit is kg. Liters and tons are deliberately excluded - they
     * are not the same quantity and don't belong in a weight chart.
     *
     * @return \Illuminate\Support\Collection<int, array{key: string, label: string, kg: float, color: string, lot?: int}>
     */
    private static function byWeightCategories(): \Illuminate\Support\Collection
    {
        $rows = collect();

        $legacyKg = (float) Collection::whereNull('lot')->selectRaw('SUM('.self::TOTAL_KG_SQL.') as total')->value('total');
        if ($legacyKg > 0) {
            $rows->push(['key' => 'legacy', 'label' => 'Legacy Submissions', 'kg' => $legacyKg, 'color' => self::CATEGORY_COLORS['legacy']]);
        }

        foreach (WasteCategories::lots() as $lotKey => $lot) {
            foreach ($lot['categories'] as $categoryKey => $meta) {
                if ($meta['unit'] !== 'kg') {
                    continue;
                }

                $kg = (float) Collection::where('lot', $lotKey)->where('category', $categoryKey)->sum('quantity');

                $rows->push([
                    'key' => $categoryKey,
                    'lot' => $lotKey,
                    'label' => $meta['label'].' ('.$lot['short_label'].')',
                    'kg' => $kg,
                    'color' => self::CATEGORY_COLORS[$categoryKey] ?? '#898781',
                ]);
            }
        }

        return $rows->sortByDesc('kg')->values();
    }
}
