<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;

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
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
        ];

        $collections = Collection::query()
            ->when($filters['entity'], fn ($q, $v) => $q->where('entity_name', 'like', "%{$v}%"))
            ->when(
                $filters['material'] && array_key_exists($filters['material'], Collection::MATERIALS),
                fn ($q) => $q->where($filters['material'], '>', 0)
            )
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
        ]);
    }

    public function create(): View
    {
        return view('collections.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entity_name' => ['required', 'string', 'max:255'],
            'relationship_manager' => ['nullable', 'string', 'max:255'],
            'state_department' => ['nullable', 'string', 'max:255'],
            'department_agency' => ['nullable', 'string', 'max:255'],
            'location_office' => ['nullable', 'string', 'max:255'],
            'contact_person_name' => ['required', 'string', 'max:255'],
            'contact_person_number' => ['required', 'string', 'max:50'],
            'paper_kg' => ['nullable', 'numeric', 'min:0'],
            'metal_kg' => ['nullable', 'numeric', 'min:0'],
            'plastic_kg' => ['nullable', 'numeric', 'min:0'],
            'furniture_kg' => ['nullable', 'numeric', 'min:0'],
            'ewaste_kg' => ['nullable', 'numeric', 'min:0'],
            'other_material_name' => ['nullable', 'string', 'max:255'],
            'other_kg' => ['nullable', 'numeric', 'min:0'],
            'collection_date' => ['required', 'date'],
            'collected_by' => ['nullable', 'string', 'max:255'],
        ]);

        $validator = validator($data, [])->after(function (Validator $validator) use ($request) {
            $materials = ['paper_kg', 'metal_kg', 'plastic_kg', 'furniture_kg', 'ewaste_kg', 'other_kg'];
            $hasAny = collect($materials)->contains(fn ($field) => (float) $request->input($field, 0) > 0);

            if (! $hasAny) {
                $validator->errors()->add('paper_kg', 'Enter at least one material quantity.');
            }
        });

        $validator->validate();

        foreach (['paper_kg', 'metal_kg', 'plastic_kg', 'furniture_kg', 'ewaste_kg', 'other_kg'] as $field) {
            $data[$field] = $data[$field] ?? 0;
        }

        $collection = Collection::create($data);

        return redirect()
            ->route('collections.show', $collection)
            ->with('status', 'Collection recorded successfully.');
    }

    public function show(Collection $collection): View
    {
        return view('collections.show', ['collection' => $collection]);
    }
}
