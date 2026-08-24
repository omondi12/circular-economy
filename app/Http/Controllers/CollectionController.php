<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Support\WasteCategories;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * View-only now - public/boss-facing browsing of submissions. Actual data
 * entry moved behind login to RmDashboardController (RMs enter their own
 * collections; entry is no longer an open public form).
 */
class CollectionController extends Controller
{
    /**
     * Full, filterable submissions list - the homepage's "Recent
     * Submissions" is an unfiltered preview of the same data; this is what
     * the Total Submissions / This Month stat cards drill into.
     */
    public function index(Request $request): View
    {
        $filters = [
            'entity' => $request->string('entity')->toString() ?: null,
            'material' => $request->string('material')->toString() ?: null,
            'lot' => $request->string('lot')->toString() ?: null,
            'category' => $request->string('category')->toString() ?: null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
        ];

        $collections = Collection::query()
            ->when($filters['entity'], fn ($q, $v) => $q->where('entity_name', 'like', "%{$v}%"))
            ->when(
                $filters['material'] && array_key_exists($filters['material'], Collection::MATERIALS),
                fn ($q) => $q->where($filters['material'], '>', 0)
            )
            ->when($filters['lot'], fn ($q, $v) => $q->where('lot', $v))
            ->when($filters['category'], fn ($q, $v) => $q->where('category', $v))
            ->when($filters['from'], fn ($q, $v) => $q->whereDate('collection_date', '>=', $v))
            ->when($filters['to'], fn ($q, $v) => $q->whereDate('collection_date', '<=', $v))
            ->orderByDesc('collection_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('collections.index', [
            'collections' => $collections,
            'filters' => $filters,
            'materials' => Collection::MATERIALS,
            'lots' => WasteCategories::lots(),
        ]);
    }

    public function show(Collection $collection): View
    {
        return view('collections.show', ['collection' => $collection]);
    }
}
