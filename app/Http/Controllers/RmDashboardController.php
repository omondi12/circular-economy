<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Collection;
use App\Models\GovernmentEntity;
use App\Models\Lso;
use App\Models\StateCorporation;
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
            'assignedStateDepartments' => $user->assignedStateDepartments()->with('parent')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $user = Auth::user();
        $assignedMinistryIds = $user->assignedMinistries()->pluck('id')->all();
        $assignedStateDepartmentIds = $user->assignedStateDepartments()->pluck('id')->all();

        return view('rm.create', [
            'lots' => WasteCategories::lots(),
            'ministries' => self::ministryTree($assignedMinistryIds, $assignedStateDepartmentIds),
            'restrictedToOwnMinistries' => $assignedMinistryIds !== [] || $assignedStateDepartmentIds !== [],
            'clients' => $user->effectiveStateCorporations()->orderBy('name')->get(['id', 'name']),
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
     *
     * Scoped to what the RM can actually submit for: a ministry they're
     * assigned wholesale ($allowedMinistryIds) shows every department and
     * institution underneath it, while a ministry they're NOT assigned
     * wholesale still appears if one of its departments is in
     * $allowedStateDepartmentIds - but only that department (and its
     * institutions), not its siblings. Both empty means "no assignment on
     * record" (demo accounts, admins browsing the RM form) and falls back
     * to showing everything rather than locking the form to zero options.
     */
    private static function ministryTree(array $allowedMinistryIds = [], array $allowedStateDepartmentIds = []): \Illuminate\Support\Collection
    {
        $isRestricted = $allowedMinistryIds !== [] || $allowedStateDepartmentIds !== [];

        return GovernmentEntity::ministries()
            ->orderBy('id')
            ->when($isRestricted, fn ($q) => $q->where(function ($q2) use ($allowedMinistryIds, $allowedStateDepartmentIds) {
                $q2->whereIn('id', $allowedMinistryIds)
                    ->orWhereHas('children', fn ($q3) => $q3->whereIn('id', $allowedStateDepartmentIds));
            }))
            ->with([
                'children' => function ($q) use ($isRestricted, $allowedMinistryIds, $allowedStateDepartmentIds) {
                    $q->orderBy('id');
                    if ($isRestricted) {
                        $q->where(function ($q2) use ($allowedMinistryIds, $allowedStateDepartmentIds) {
                            $q2->whereIn('parent_id', $allowedMinistryIds)
                                ->orWhereIn('id', $allowedStateDepartmentIds);
                        });
                    }
                },
                'children.children' => fn ($q) => $q->orderBy('id'),
            ])
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
        $user = Auth::user();
        $assignedMinistryIds = $user->assignedMinistries()->pluck('id')->all();
        $assignedStateDepartmentIds = $user->assignedStateDepartments()->pluck('id')->all();

        $data = $request->validate([
            'entity_type' => ['required', Rule::in(['ministry', 'county', 'commission', 'client'])],
            'entity_name' => ['nullable', 'string', 'max:255'],
            'client_id' => ['required_if:entity_type,client', 'nullable', 'integer', 'exists:state_corporations,id'],
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
            'lso_reference_number' => ['required_if:lot,'.WasteCategories::LOT_SALE, 'nullable', 'string', 'max:100', 'unique:lsos,reference_number'],
            'lso_amount' => ['required_if:lot,'.WasteCategories::LOT_SALE, 'nullable', 'integer', 'min:1'],
            'lso_document' => ['required_if:lot,'.WasteCategories::LOT_SALE, 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $lsoReferenceNumber = $data['lso_reference_number'] ?? null;
        $lsoAmount = $data['lso_amount'] ?? null;
        unset($data['lso_reference_number'], $data['lso_amount'], $data['lso_document']);

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
        $stateCorporationId = null;

        switch ($data['entity_type']) {
            case 'ministry':
                $ministry = $data['ministry_id'] ? GovernmentEntity::find($data['ministry_id']) : null;
                $stateDepartment = $data['state_department_id'] ? GovernmentEntity::find($data['state_department_id']) : null;
                $institution = $data['institution_id'] ? GovernmentEntity::find($data['institution_id']) : null;

                $validator = validator($data, [])->after(function (Validator $validator) use ($ministry, $stateDepartment, $institution, $assignedMinistryIds, $assignedStateDepartmentIds) {
                    if ($ministry === null) {
                        $validator->errors()->add('ministry_id', 'Choose a ministry.');
                    } elseif ($assignedMinistryIds !== [] || $assignedStateDepartmentIds !== []) {
                        $hasWholeMinistryAccess = in_array($ministry->id, $assignedMinistryIds, true);
                        $hasMatchingDepartmentAccess = $stateDepartment !== null && in_array($stateDepartment->id, $assignedStateDepartmentIds, true);

                        if (! $hasWholeMinistryAccess && ! $hasMatchingDepartmentAccess) {
                            $validator->errors()->add('ministry_id', 'You are only able to submit collections for the ministries/state departments assigned to you.');
                        }
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

            case 'client':
                $client = StateCorporation::find($data['client_id']);

                if ($client === null || ! $user->effectiveStateCorporations()->where('id', $client->id)->exists()) {
                    return back()->withInput()->withErrors(['client_id' => 'Choose one of your assigned clients.']);
                }

                // A client's ministry_id can point at any level (Ministry,
                // State Department, or Institution - same as elsewhere in
                // this app, see StateCorporation::ministryDisplay()), so
                // resolve the full chain from whichever level it's set to.
                $clientEntity = $client->ministry;
                $institution = $clientEntity?->level === GovernmentEntity::LEVEL_INSTITUTION ? $clientEntity : null;
                $stateDepartment = $client->stateDepartmentEntity();
                $ministry = match (true) {
                    $clientEntity?->level === GovernmentEntity::LEVEL_MINISTRY => $clientEntity,
                    $stateDepartment !== null => $stateDepartment->parent,
                    default => null,
                };

                $stateCorporationId = $client->id;
                $data['entity_name'] = $client->name;
                $data['county'] = null;
                $data['commission'] = null;
                break;
        }

        $collection = Collection::create([
            ...$data,
            'ministry_id' => $ministry?->id,
            'state_department_id' => $stateDepartment?->id,
            'institution_id' => $institution?->id,
            'state_corporation_id' => $stateCorporationId,
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

        // Lot 1 (Sale) materials are handed over against an LSO stating
        // their worth - Lot 2 (Disposal) has no monetary value, so no LSO
        // is created. The LSO record (reference number, value, document)
        // is what Finance/Admin's existing payment-confirmation ledger
        // and RM Targets already run on - this just feeds it from the
        // collection form instead of a separate "My LSOs" entry screen.
        if ($lot === WasteCategories::LOT_SALE) {
            $document = $request->file('lso_document');
            $documentPath = $document->store('lso-documents', 'local');

            $lso = Lso::create([
                'reference_number' => $lsoReferenceNumber,
                'user_id' => $user->id,
                'customer_name' => $collection->entity_name,
                'customer_contact' => $collection->contact_person_number,
                'ministry_id' => $collection->ministry_id,
                'state_department_id' => $collection->state_department_id,
                'institution_id' => $collection->institution_id,
                'original_amount_minor' => $lsoAmount * 100,
                'issue_date' => $collection->collection_date,
                'document_path' => $documentPath,
                'document_original_filename' => $document->getClientOriginalName(),
                'document_mime' => $document->getClientMimeType(),
                'created_by' => $user->id,
            ]);

            $collection->update(['lso_id' => $lso->id]);

            AuditLog::record('lso.created', $lso, [
                'reference_number' => $lso->reference_number,
                'customer_name' => $lso->customer_name,
                'original_amount' => $lso->original_amount_minor / 100,
            ]);
        }

        return redirect()->route('rm.dashboard')->with('status', 'Collection recorded successfully.');
    }
}
