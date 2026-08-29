<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Collection;
use App\Models\GovernmentEntity;
use App\Support\EntityDirectory;
use App\Support\WasteCategories;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The RM's own small dashboard - their submissions only, plus the entry
 * form. Separate from CollectionController (which serves the public,
 * unfiltered-by-user views) so an RM's "my work" scope never leaks into the
 * public URLs and vice versa.
 */
class RmDashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $submissions = Collection::where('user_id', $user->id)
            ->orderByDesc('collection_date')
            ->orderByDesc('id')
            ->paginate(15);

        $totalSubmissions = Collection::where('user_id', $user->id)->count();
        $totalQuantity = Collection::where('user_id', $user->id)->sum('quantity');

        $byLot = collect(WasteCategories::lots())->map(function (array $lot, int $lotKey) use ($user) {
            return [
                'label' => $lot['short_label'],
                'count' => Collection::where('user_id', $user->id)->where('lot', $lotKey)->count(),
            ];
        })->values();

        return view('rm.dashboard', [
            'submissions' => $submissions,
            'totalSubmissions' => $totalSubmissions,
            'totalQuantity' => $totalQuantity,
            'byLot' => $byLot,
        ]);
    }

    public function create(): View
    {
        return view('rm.create', [
            'lots' => WasteCategories::lots(),
            'ministries' => self::ministryTree(),
            'counties' => EntityDirectory::counties(),
            'countyDepartments' => EntityDirectory::countyDepartments(),
            'commissions' => EntityDirectory::commissions(),
        ]);
    }

    /**
     * Nested Ministry -> State Department -> Institution, shaped for the
     * form's cascading selects (same @json-embed pattern already used for
     * Lot -> Category). Small enough (a few hundred rows total) to embed
     * directly rather than adding an AJAX endpoint this app has no other
     * use for.
     */
    private static function ministryTree(): \Illuminate\Support\Collection
    {
        return GovernmentEntity::ministries()
            ->orderBy('id')
            ->with(['children' => fn ($q) => $q->orderBy('id'), 'children.children' => fn ($q) => $q->orderBy('id')])
            ->get()
            ->map(fn (GovernmentEntity $ministry) => [
                'id' => $ministry->id,
                'name' => $ministry->name,
                'departments' => $ministry->children->map(fn (GovernmentEntity $dept) => [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'institutions' => $dept->children->map(fn (GovernmentEntity $inst) => [
                        'id' => $inst->id,
                        'name' => $inst->name,
                    ])->values(),
                ])->values(),
            ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', Rule::in(['ministry', 'county', 'commission'])],
            'entity_name' => ['nullable', 'string', 'max:255'],
            'ministry_id' => ['nullable', 'integer', 'exists:government_entities,id'],
            'state_department_id' => ['nullable', 'integer', 'exists:government_entities,id'],
            'institution_id' => ['nullable', 'integer', 'exists:government_entities,id'],
            'county' => ['nullable', 'string', 'max:255'],
            'commission' => ['nullable', 'string', 'max:255'],
            'state_department' => ['nullable', 'string', 'max:255'],
            'department_agency' => ['nullable', 'string', 'max:255'],
            'location_office' => ['nullable', 'string', 'max:255'],
            'contact_person_name' => ['required', 'string', 'max:255'],
            'contact_person_number' => ['required', 'string', 'max:50'],
            'lot' => ['required', Rule::in([WasteCategories::LOT_SALE, WasteCategories::LOT_DISPOSAL])],
            'category' => ['required', 'string'],
            'subcategory' => ['nullable', 'string'],
            'unit' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'collection_date' => ['required', 'date'],
        ]);

        $lot = (int) $data['lot'];

        if (! WasteCategories::isValidCategory($lot, $data['category'])) {
            return back()->withInput()->withErrors(['category' => 'Choose a category that belongs to the selected lot.']);
        }

        if (WasteCategories::hasSubcategories($lot)) {
            if (! WasteCategories::isValidSubcategory($lot, $data['category'], $data['subcategory'])) {
                return back()->withInput()->withErrors(['subcategory' => 'Choose a subcategory that belongs to the selected category.']);
            }
        } else {
            $data['subcategory'] = null;
        }

        if (! WasteCategories::isValidUnit($lot, $data['category'], $data['subcategory'], $data['unit'])) {
            return back()->withInput()->withErrors(['unit' => 'Choose a unit of measure that is valid for the selected category/subcategory.']);
        }

        $ministry = null;
        $stateDepartment = null;
        $institution = null;

        switch ($data['entity_type']) {
            case 'ministry':
                $ministry = $data['ministry_id'] ? GovernmentEntity::find($data['ministry_id']) : null;
                $stateDepartment = $data['state_department_id'] ? GovernmentEntity::find($data['state_department_id']) : null;
                $institution = $data['institution_id'] ? GovernmentEntity::find($data['institution_id']) : null;

                $validator = validator($data, [])->after(function (Validator $validator) use ($ministry, $stateDepartment, $institution) {
                    if ($ministry === null) {
                        $validator->errors()->add('ministry_id', 'Choose a ministry.');
                    }
                    if ($stateDepartment !== null && $stateDepartment->parent_id !== $ministry?->id) {
                        $validator->errors()->add('state_department_id', 'That state department does not belong to the selected ministry.');
                    }
                    if ($institution !== null && $institution->parent_id !== $stateDepartment?->id) {
                        $validator->errors()->add('institution_id', 'That institution does not belong to the selected state department.');
                    }
                });
                $validator->validate();

                $data['entity_name'] = ($institution ?? $stateDepartment ?? $ministry)->name;
                $data['county'] = null;
                $data['commission'] = null;
                break;

            case 'county':
                if (! EntityDirectory::isValidCounty($data['county'])) {
                    return back()->withInput()->withErrors(['county' => 'Choose a county.']);
                }
                if (! EntityDirectory::isValidCountyDepartment($data['department_agency'])) {
                    return back()->withInput()->withErrors(['department_agency' => 'Choose a department for the selected county.']);
                }
                $data['entity_name'] = $data['county'];
                $data['commission'] = null;
                break;

            case 'commission':
                if (! EntityDirectory::isValidCommission($data['commission'])) {
                    return back()->withInput()->withErrors(['commission' => 'Choose a commission or body.']);
                }
                if (! EntityDirectory::isValidCommissionDepartment($data['commission'], $data['department_agency'])) {
                    return back()->withInput()->withErrors(['department_agency' => 'Choose a department/directorate that belongs to the selected commission.']);
                }
                $data['entity_name'] = $data['commission'];
                $data['county'] = null;
                break;
        }

        $user = Auth::user();

        $collection = Collection::create([
            ...$data,
            'ministry_id' => $ministry?->id,
            'state_department_id' => $stateDepartment?->id,
            'institution_id' => $institution?->id,
            'user_id' => $user->id,
            'relationship_manager' => $user->name,
            'collected_by' => $user->name,
        ]);

        AuditLog::record('collection.created', $collection, [
            'entity_name' => $collection->entity_name,
            'lot' => $collection->lotLabel(),
            'category' => $collection->categoryLabel(),
            'subcategory' => $collection->subcategoryLabel(),
            'quantity' => $collection->quantity,
            'unit' => $collection->unitLabel(),
        ]);

        return redirect()->route('rm.dashboard')->with('status', 'Collection recorded successfully.');
    }
}
