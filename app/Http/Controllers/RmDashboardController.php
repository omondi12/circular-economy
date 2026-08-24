<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Collection;
use App\Support\WasteCategories;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
        return view('rm.create', ['lots' => WasteCategories::lots()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entity_name' => ['required', 'string', 'max:255'],
            'state_department' => ['nullable', 'string', 'max:255'],
            'department_agency' => ['nullable', 'string', 'max:255'],
            'location_office' => ['nullable', 'string', 'max:255'],
            'contact_person_name' => ['required', 'string', 'max:255'],
            'contact_person_number' => ['required', 'string', 'max:50'],
            'lot' => ['required', Rule::in([WasteCategories::LOT_SALE, WasteCategories::LOT_DISPOSAL])],
            'category' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'collection_date' => ['required', 'date'],
        ]);

        if (! WasteCategories::isValidCategory((int) $data['lot'], $data['category'])) {
            return back()->withInput()->withErrors(['category' => 'Choose a category that belongs to the selected lot.']);
        }

        $user = Auth::user();

        $collection = Collection::create([
            ...$data,
            'unit' => WasteCategories::unitFor((int) $data['lot'], $data['category']),
            'user_id' => $user->id,
            'relationship_manager' => $user->name,
            'collected_by' => $user->name,
        ]);

        AuditLog::record('collection.created', $collection, [
            'entity_name' => $collection->entity_name,
            'lot' => $collection->lotLabel(),
            'category' => $collection->categoryLabel(),
            'quantity' => $collection->quantity,
            'unit' => $collection->unit,
        ]);

        return redirect()->route('rm.dashboard')->with('status', 'Collection recorded successfully.');
    }
}
