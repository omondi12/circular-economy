<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RmTarget;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * RM collection targets - monetary or count, per RM per explicit period.
 * Viewable by anyone with admin-area access; only admin/office admin can
 * create one (see User::canManageRmTargets()), same tier as Nawiri
 * Treasury.
 */
class RmTargetController extends Controller
{
    public function index(): View
    {
        $targets = RmTarget::with('user')
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->paginate(30);

        return view('admin.rm-targets.index', [
            'targets' => $targets,
            'rms' => User::assignableRms()->orderBy('name')->get(),
            'canManage' => Auth::user()->canManageRmTargets(),
        ]);
    }

    /**
     * Targets are never edited in place - a correction is a new row, so
     * past achievement figures stay explainable against the target that
     * was actually in force at the time.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->canManageRmTargets(), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in([RmTarget::TYPE_MONETARY, RmTarget::TYPE_COUNT])],
            'target_amount' => ['required_if:type,'.RmTarget::TYPE_MONETARY, 'nullable', 'integer', 'min:1'],
            'target_count' => ['required_if:type,'.RmTarget::TYPE_COUNT, 'nullable', 'integer', 'min:1'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $rm = User::assignableRms()->findOrFail($data['user_id']);

        $target = RmTarget::create([
            'user_id' => $rm->id,
            'type' => $data['type'],
            'target_amount_minor' => $data['type'] === RmTarget::TYPE_MONETARY ? $data['target_amount'] * 100 : null,
            'target_count' => $data['type'] === RmTarget::TYPE_COUNT ? $data['target_count'] : null,
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'created_by' => Auth::id(),
        ]);

        AuditLog::record('rm_target.created', $target, [
            'rm' => $rm->name,
            'type' => $target->type,
            'target_value' => $target->targetValue(),
            'period_start' => $target->period_start->toDateString(),
            'period_end' => $target->period_end->toDateString(),
        ]);

        return back()->with('status', "Target set for {$rm->name}.");
    }
}
