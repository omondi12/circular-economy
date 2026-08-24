<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Support\WasteCategories;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    private const TOTAL_KG_SQL = '(paper_kg + metal_kg + plastic_kg + furniture_kg + ewaste_kg + other_kg)';

    public function index(): View
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $totalSubmissions = Collection::count();
        $totalKg = (float) Collection::selectRaw('SUM('.self::TOTAL_KG_SQL.') as total')->value('total');

        $thisMonthKg = (float) Collection::whereBetween('collection_date', [$monthStart, $monthEnd])
            ->selectRaw('SUM('.self::TOTAL_KG_SQL.') as total')->value('total');

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
            ->selectRaw('entity_name, COUNT(*) as submissions, SUM('.self::TOTAL_KG_SQL.') as total_kg')
            ->groupBy('entity_name')
            ->orderByDesc('total_kg')
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
            ->selectRaw('entity_name, COUNT(*) as submissions, SUM('.self::TOTAL_KG_SQL.') as total_kg, MAX(collection_date) as last_collection_date')
            ->groupBy('entity_name')
            ->orderByDesc('total_kg')
            ->get();

        return view('entities.index', [
            'entities' => $entities,
            'grandTotalKg' => (float) $entities->sum('total_kg'),
        ]);
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
