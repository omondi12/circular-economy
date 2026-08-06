<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    private const TOTAL_KG_SQL = '(paper_kg + metal_kg + plastic_kg + furniture_kg + ewaste_kg + other_kg)';

    public function index(): View
    {
        $totalSubmissions = Collection::count();
        $totalKg = (float) Collection::selectRaw('SUM('.self::TOTAL_KG_SQL.') as total')->value('total');

        $thisMonthKg = (float) Collection::whereBetween('collection_date', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        ])->selectRaw('SUM('.self::TOTAL_KG_SQL.') as total')->value('total');

        $entityCount = Collection::distinct('entity_name')->count('entity_name');

        $byMaterial = collect(Collection::MATERIALS)
            ->map(fn (string $label, string $column) => [
                'label' => $label,
                'kg' => (float) Collection::sum($column),
            ])
            ->sortByDesc('kg')
            ->values();

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
            'entityCount' => $entityCount,
            'byMaterial' => $byMaterial,
            'byEntity' => $byEntity,
            'recent' => $recent,
        ]);
    }
}
