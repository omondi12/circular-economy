<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClientReport;
use App\Models\Collection;
use App\Models\GovernmentEntity;
use App\Models\StateCorporation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'userCount' => User::where('role', User::ROLE_RM)->count(),
            'submissionCount' => Collection::count(),
            'reportCount' => ClientReport::count(),
            'recentAuditLog' => AuditLog::with('user')->latest()->limit(10)->get(),
        ]);
    }

    public function users(): View
    {
        return view('admin.users', [
            'users' => User::where('role', User::ROLE_RM)->orderBy('name')->get(),
        ]);
    }

    public function createUser(): View
    {
        return view('admin.create-user');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ]);

        $user = User::create([
            ...$data,
            'role' => User::ROLE_RM,
            'is_active' => true,
        ]);

        AuditLog::record('user.created', $user, ['name' => $user->name, 'email' => $user->email]);

        return redirect()->route('admin.users')->with('status', "RM account created for {$user->name}.");
    }

    public function toggleUser(User $user): RedirectResponse
    {
        $user->update(['is_active' => ! $user->is_active]);

        AuditLog::record($user->is_active ? 'user.activated' : 'user.deactivated', $user);

        return back()->with('status', $user->is_active ? "{$user->name} reactivated." : "{$user->name} deactivated.");
    }

    /**
     * Per-RM performance: their assigned ministry portfolio (see
     * DistributeMinistries) alongside their actual submission activity, so
     * the boss can see who's covering what and how active they are.
     * Scoped to real (@amacplc.com) accounts only, matching the
     * ministry-distribution scope - demo accounts don't carry a
     * meaningful "performance."
     */
    public function rmPerformance(): View
    {
        $rms = User::where('role', User::ROLE_RM)
            ->where('email', 'like', '%@amacplc.com')
            ->orderBy('name')
            ->get()
            ->map(function (User $rm) {
                $submissions = Collection::where('user_id', $rm->id);

                return [
                    'rm' => $rm,
                    'ministries' => $rm->assignedMinistries()->orderBy('name')->pluck('name'),
                    'totalSubmissions' => (clone $submissions)->count(),
                    'submissionsThisMonth' => (clone $submissions)
                        ->whereMonth('collection_date', now()->month)
                        ->whereYear('collection_date', now()->year)
                        ->count(),
                    'lastSubmissionAt' => (clone $submissions)->max('collection_date'),
                ];
            });

        return view('admin.rm-performance', ['rms' => $rms]);
    }

    /**
     * Manual RM assignment for both portfolios that carry an
     * `assigned_rm_id` - ministries (previously only settable via the
     * ministries:distribute CLI script) and clients (previously not
     * settable at all). One page, two tabs, since the boss asked for both
     * in the same place. The RM list is scoped to active @amacplc.com
     * accounts, matching ministries:distribute's own scope - demo
     * accounts aren't real assignees.
     */
    public function assignRms(Request $request): View
    {
        $view = $request->string('view')->toString();
        $view = in_array($view, ['ministries', 'clients'], true) ? $view : 'ministries';

        $rms = User::where('role', User::ROLE_RM)
            ->where('is_active', true)
            ->where('email', 'like', '%@amacplc.com')
            ->orderBy('name')
            ->get();

        if ($view === 'clients') {
            $search = $request->string('q')->toString() ?: null;

            $clients = StateCorporation::query()
                ->with('assignedRm')
                ->when($search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
                ->orderBy('name')
                ->paginate(50)
                ->withQueryString();

            return view('admin.assign-rms', [
                'view' => $view,
                'rms' => $rms,
                'clients' => $clients,
                'search' => $search,
            ]);
        }

        $ministries = GovernmentEntity::ministries()->orderBy('id')->with('assignedRm')->get();

        return view('admin.assign-rms', [
            'view' => $view,
            'rms' => $rms,
            'ministries' => $ministries,
        ]);
    }

    /**
     * Assigns (or, if the ministry already has one, reassigns/shifts) one
     * ministry to one RM - a plain overwrite, since a dropdown selection
     * naturally replaces whatever was there before.
     */
    public function assignMinistryRm(Request $request, GovernmentEntity $ministry): RedirectResponse
    {
        abort_unless($ministry->level === GovernmentEntity::LEVEL_MINISTRY, 404);

        $data = $request->validate([
            'assigned_rm_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $previousRm = $ministry->assignedRm?->name;
        $newRm = $data['assigned_rm_id'] ? User::find($data['assigned_rm_id']) : null;

        $ministry->update(['assigned_rm_id' => $newRm?->id]);

        AuditLog::record('ministry.rm_assigned', $ministry, [
            'ministry' => $ministry->name,
            'previous_rm' => $previousRm,
            'new_rm' => $newRm?->name,
        ]);

        return back()->with('status', $newRm
            ? "{$ministry->name} assigned to {$newRm->name}."
            : "{$ministry->name} unassigned.");
    }

    /**
     * Same shift/reassign behaviour as assignMinistryRm, for clients
     * (state corporations, counties, polytechnics, etc.) - the boss
     * specifically asked for clients to be assignable "directly" here,
     * distinct from the ministries' bulk auto-distribute below.
     */
    public function assignClientRm(Request $request, StateCorporation $stateCorporation): RedirectResponse
    {
        $data = $request->validate([
            'assigned_rm_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $previousRm = $stateCorporation->assignedRm?->name;
        $newRm = $data['assigned_rm_id'] ? User::find($data['assigned_rm_id']) : null;

        $stateCorporation->update(['assigned_rm_id' => $newRm?->id]);

        AuditLog::record('client.rm_assigned', $stateCorporation, [
            'client' => $stateCorporation->name,
            'previous_rm' => $previousRm,
            'new_rm' => $newRm?->name,
        ]);

        return back()->with('status', $newRm
            ? "{$stateCorporation->name} assigned to {$newRm->name}."
            : "{$stateCorporation->name} unassigned.");
    }

    /**
     * Brings the ministries:distribute script into the admin UI, per the
     * boss's request - re-runs the exact same round-robin logic (delegated
     * to the Artisan command, so there's one source of truth) rather than
     * duplicating it here. This recomputes every ministry's assignment
     * from scratch, overwriting any manual assignments made above.
     */
    public function distributeMinistries(): RedirectResponse
    {
        $exitCode = Artisan::call('ministries:distribute');

        if ($exitCode !== 0) {
            $lastLine = collect(explode("\n", trim(Artisan::output())))->filter()->last();

            return redirect()->route('admin.assign-rms')
                ->with('error', 'Distribution failed: '.($lastLine ?: 'see the server log for details.'));
        }

        AuditLog::record('ministries.distributed');

        return redirect()->route('admin.assign-rms')->with('status', 'Ministries re-distributed across RMs.');
    }

    public function auditLog(Request $request): View
    {
        $filters = [
            'action' => $request->string('action')->toString() ?: null,
            'user_id' => $request->string('user_id')->toString() ?: null,
        ];

        $entries = AuditLog::with('user')
            ->when($filters['action'], fn ($q, $v) => $q->where('action', $v))
            ->when($filters['user_id'], fn ($q, $v) => $q->where('user_id', $v))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-log', [
            'entries' => $entries,
            'filters' => $filters,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'rms' => User::where('role', User::ROLE_RM)->orderBy('name')->get(),
        ]);
    }
}
