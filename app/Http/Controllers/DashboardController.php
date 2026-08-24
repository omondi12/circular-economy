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

        $byMaterial = collect(Collection::MATERIALS)
            ->map(fn (string $label, string $column) => [
                'key' => $column,
                'label' => $label,
                'kg' => (float) Collection::sum($column),
            ])
            ->sortByDesc('kg')
            ->values();

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
            'byMaterial' => $byMaterial,
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
     * Full material-type breakdown - the homepage shows the same bars, but
     * this adds share-of-total and how many distinct entities have reported
     * each material, and links each row into the filtered submissions list.
     */
    public function materialsIndex(): View
    {
        $totalKg = (float) Collection::selectRaw('SUM('.self::TOTAL_KG_SQL.') as total')->value('total');

        $byMaterial = collect(Collection::MATERIALS)
            ->map(function (string $label, string $column) use ($totalKg) {
                $kg = (float) Collection::sum($column);

                return [
                    'key' => $column,
                    'label' => $label,
                    'kg' => $kg,
                    'share' => $totalKg > 0 ? $kg / $totalKg * 100 : 0,
                    'entities' => Collection::where($column, '>', 0)->distinct('entity_name')->count('entity_name'),
                ];
            })
            ->sortByDesc('kg')
            ->values();

        return view('materials.index', [
            'byMaterial' => $byMaterial,
            'totalKg' => $totalKg,
        ]);
    }
}
