<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RmAllocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * RM allocation (2026-11-03, per the boss): supervisor areas of 4-5 counties, the rules check (no RM above 7 accounts)
 * and balancing a supervisor's team. For whoever allocates RMs (User::canAllocateRms()).
 */
class RmAllocationController extends Controller
{
    public function index(RmAllocation $allocation): View
    {
        abort_unless(Auth::user()->canAllocateRms(), 403);

        $holdings = $allocation->holdings();
        $rmNames = User::query()->whereIn('id', $holdings->filter()->unique()->values())->with('supervisor:id,name')->get()->keyBy('id');

        return view('admin.allocation.index', [
            'check' => $allocation->check(),
            'counties' => $allocation->counties(),
            'areas' => $allocation->areas(),
            'holders' => $holdings->map(fn ($rmId) => $rmId ? $rmNames[$rmId] ?? null : null),
            'supervisors' => User::query()->where('role', User::ROLE_SUPERVISOR)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function saveAreas(Request $request, RmAllocation $allocation): RedirectResponse
    {
        abort_unless(Auth::user()->canAllocateRms(), 403);

        $data = $request->validate(['area' => ['array'], 'area.*' => ['nullable', 'integer']]);
        $allocation->saveAreas($data['area'] ?? [], Auth::user());

        return redirect()->route('admin.allocation.index')->with('status', 'Supervisor areas saved.');
    }

    public function balance(User $supervisor, RmAllocation $allocation): View
    {
        abort_unless(Auth::user()->canAllocateRms(), 403);
        abort_unless($supervisor->isSupervisor(), 404);

        return view('admin.allocation.balance', ['supervisor' => $supervisor, 'plan' => $allocation->plan($supervisor)]);
    }

    public function applyBalance(User $supervisor, RmAllocation $allocation): RedirectResponse
    {
        abort_unless(Auth::user()->canAllocateRms(), 403);
        abort_unless($supervisor->isSupervisor(), 404);

        $moved = $allocation->apply($supervisor, Auth::user());

        return redirect()->route('admin.allocation.index')->with('status', "{$supervisor->name}'s team balanced: {$moved} ".($moved === 1 ? 'client' : 'clients').' moved.');
    }
}
