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
    /**
     * A supervisor only ever sees figures about their own team here - their
     * own RM headcount, their own team's assigned clients, their own
     * team's reports, and a Recent Activity feed scoped the same way as
     * the Audit Log (see AuditLog::scopeVisibleTo). Org-wide peer data
     * (other supervisors, the total RM headcount) isn't "theirs" so it's
     * left off entirely rather than shown as a bare number (2026-09-07 -
     * a supervisor account was seeing every figure exactly like an admin,
     * which this closes). Admins are unrestricted.
     */
    public function dashboard(): View
    {
        $viewer = auth()->user();
        $isSupervisor = $viewer->isSupervisor();
        $rmIds = $isSupervisor ? $viewer->rms()->pluck('id') : null;

        return view('admin.dashboard', [
            'isSupervisor' => $isSupervisor,
            'userCount' => $isSupervisor
                ? User::where('supervisor_id', $viewer->id)->count()
                : User::where('role', User::ROLE_RM)->count(),
            'supervisorCount' => User::where('role', User::ROLE_SUPERVISOR)->count(),
            'assignedClientCount' => $isSupervisor
                ? StateCorporation::whereIn('assigned_rm_id', $rmIds)->count()
                : StateCorporation::whereNotNull('assigned_rm_id')->count(),
            'unassignedClientCount' => StateCorporation::whereNull('assigned_rm_id')->count(),
            'submissionCount' => Collection::count(),
            // Reports are no longer team-scoped (2026-09-08 - any
            // supervisor can view/log a report for any client) - "My
            // Client Reports" now means what they've personally logged.
            'reportCount' => $isSupervisor
                ? ClientReport::where('created_by', $viewer->id)->count()
                : ClientReport::count(),
            'recentAuditLog' => AuditLog::visibleTo($viewer)->with('user')->latest()->limit(10)->get(),
        ]);
    }

    /**
     * Team Accounts: an admin sees every RM and Supervisor (with who each
     * RM reports to); a supervisor sees only their own RMs - "they can
     * only see the ones that are theirs" (2026-09-06).
     */
    public function users(): View
    {
        $viewer = auth()->user();

        $users = $viewer->isSupervisor()
            ? User::visibleRmsFor($viewer)->orderBy('name')->get()
            : User::whereIn('role', [User::ROLE_RM, User::ROLE_SUPERVISOR])
                ->with('supervisor')->orderBy('role')->orderBy('name')->get();

        return view('admin.users', ['users' => $users]);
    }

    public function createUser(): View
    {
        return view('admin.create-user', [
            'supervisors' => auth()->user()->isAdmin()
                ? User::where('role', User::ROLE_SUPERVISOR)->orderBy('name')->get()
                : collect(),
        ]);
    }

    /**
     * A supervisor creating an account can only ever create their own RM
     * (role and supervisor are forced, not taken from the request); an
     * admin can create either an RM (optionally assigning it straight to a
     * supervisor) or a Supervisor. This is the one function that lets a
     * supervisor build their own team (2026-09-06).
     */
    public function storeUser(Request $request): RedirectResponse
    {
        $viewer = auth()->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ];

        if ($viewer->isAdmin()) {
            $rules['role'] = ['required', Rule::in([User::ROLE_RM, User::ROLE_SUPERVISOR])];
            $rules['supervisor_id'] = ['nullable', 'integer', 'exists:users,id'];
        }

        $data = $request->validate($rules);

        if ($viewer->isSupervisor()) {
            $data['role'] = User::ROLE_RM;
            $data['supervisor_id'] = $viewer->id;
        } elseif (($data['role'] ?? null) !== User::ROLE_RM) {
            $data['supervisor_id'] = null;
        }

        $user = User::create([...$data, 'is_active' => true]);

        AuditLog::record('user.created', $user, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'supervisor' => $user->supervisor?->name,
        ]);

        $roleLabel = $user->isSupervisor() ? 'Supervisor' : 'RM';

        return redirect()->route('admin.users')->with('status', "{$roleLabel} account created for {$user->name}.");
    }

    public function toggleUser(User $user): RedirectResponse
    {
        $viewer = auth()->user();
        abort_unless($viewer->isAdmin() || ($user->isRm() && $user->supervisor_id === $viewer->id), 403);

        $user->update(['is_active' => ! $user->is_active]);

        AuditLog::record($user->is_active ? 'user.activated' : 'user.deactivated', $user);

        return back()->with('status', $user->is_active ? "{$user->name} reactivated." : "{$user->name} deactivated.");
    }

    /**
     * Edit an existing account - the one function the boss asked for to
     * switch an existing account's role (e.g. an RM promoted to
     * Supervisor) without recreating it. Admins can edit anyone's role,
     * supervisor and details; a supervisor can edit their own RM's name/
     * email/password only - role and team assignment stay admin-only,
     * same boundary as everywhere else (2026-09-06).
     */
    public function editUser(User $user): View
    {
        $viewer = auth()->user();
        $isOwnRm = $user->isRm() && $user->supervisor_id === $viewer->id;
        abort_unless($viewer->isAdmin() || $isOwnRm, 403);

        return view('admin.edit-user', [
            'editedUser' => $user,
            'supervisors' => $viewer->isAdmin()
                ? User::where('role', User::ROLE_SUPERVISOR)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $viewer = auth()->user();
        $isOwnRm = $user->isRm() && $user->supervisor_id === $viewer->id;
        abort_unless($viewer->isAdmin() || $isOwnRm, 403);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::min(8)],
        ];

        if ($viewer->isAdmin()) {
            $rules['role'] = ['required', Rule::in([User::ROLE_RM, User::ROLE_SUPERVISOR])];
            $rules['supervisor_id'] = ['nullable', 'integer', 'exists:users,id'];
        }

        $data = $request->validate($rules);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        if ($viewer->isAdmin() && ($data['role'] ?? null) === User::ROLE_SUPERVISOR) {
            // A supervisor doesn't have a supervisor of their own.
            $data['supervisor_id'] = null;
        }

        $previousRole = $user->role;
        $user->update($data);

        // Switched away from RM - anything they held as an RM is now a
        // dangling reference to a non-RM account, so free it back up.
        if ($previousRole === User::ROLE_RM && $user->role !== User::ROLE_RM) {
            StateCorporation::where('assigned_rm_id', $user->id)->update(['assigned_rm_id' => null]);
            GovernmentEntity::where('assigned_rm_id', $user->id)->update(['assigned_rm_id' => null]);
        }

        // Switched away from Supervisor - their RMs need reassigning, not
        // left silently reporting to an account that's no longer one.
        if ($previousRole === User::ROLE_SUPERVISOR && $user->role !== User::ROLE_SUPERVISOR) {
            User::where('supervisor_id', $user->id)->update(['supervisor_id' => null]);
        }

        AuditLog::record('user.updated', $user, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'previous_role' => $previousRole,
            'supervisor' => $user->supervisor?->name,
        ]);

        return redirect()->route('admin.users')->with('status', "{$user->name} updated.");
    }

    /**
     * Per-RM performance: their assigned ministry portfolio (see
     * DistributeMinistries) alongside their actual submission activity, so
     * the boss can see who's covering what and how active they are.
     * Scoped to real (non-demo) accounts only - demo accounts don't
     * carry a meaningful "performance."
     */
    public function rmPerformance(): View
    {
        $rms = User::visibleRmsFor(auth()->user())->orderBy('name')->get()
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
     * settable at all), plus (admin-only) which supervisor each RM
     * reports to. A supervisor only ever sees/assigns their own team's
     * ministries and clients - "they can only see the ones that are
     * theirs" (2026-09-06); the Supervisors tab is admin-only since it
     * moves RMs between teams.
     */
    public function assignRms(Request $request): View
    {
        $viewer = auth()->user();

        $validViews = $viewer->isAdmin() ? ['ministries', 'clients', 'supervisors'] : ['ministries', 'clients'];
        $view = $request->string('view')->toString();
        $view = in_array($view, $validViews, true) ? $view : 'ministries';

        if ($view === 'supervisors') {
            $allRms = User::where('role', User::ROLE_RM)->with('supervisor')->orderBy('name')->get();
            $supervisors = User::where('role', User::ROLE_SUPERVISOR)->orderBy('name')->get();

            return view('admin.assign-rms', [
                'view' => $view,
                'allRms' => $allRms,
                'supervisors' => $supervisors,
            ]);
        }

        $rms = User::visibleRmsFor($viewer)->orderBy('name')->get();

        if ($view === 'clients') {
            $search = $request->string('q')->toString() ?: null;

            $clients = StateCorporation::query()
                ->visibleTo($viewer)
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

        $ministries = GovernmentEntity::ministries()->visibleTo($viewer)->orderBy('id')->with('assignedRm')->get();

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

        $viewer = auth()->user();
        abort_unless(GovernmentEntity::whereKey($ministry->id)->visibleTo($viewer)->exists(), 403);

        $data = $request->validate([
            'assigned_rm_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $previousRm = $ministry->assignedRm?->name;
        $newRm = $data['assigned_rm_id'] ? User::visibleRmsFor($viewer)->find($data['assigned_rm_id']) : null;
        abort_if($data['assigned_rm_id'] && ! $newRm, 403);

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
        $viewer = auth()->user();
        abort_unless(StateCorporation::whereKey($stateCorporation->id)->visibleTo($viewer)->exists(), 403);

        $data = $request->validate([
            'assigned_rm_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $previousRm = $stateCorporation->assignedRm?->name;
        $newRm = $data['assigned_rm_id'] ? User::visibleRmsFor($viewer)->find($data['assigned_rm_id']) : null;
        abort_if($data['assigned_rm_id'] && ! $newRm, 403);

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
        abort_unless(auth()->user()->isAdmin(), 403);

        $exitCode = Artisan::call('ministries:distribute');

        if ($exitCode !== 0) {
            $lastLine = collect(explode("\n", trim(Artisan::output())))->filter()->last();

            return redirect()->route('admin.assign-rms')
                ->with('error', 'Distribution failed: '.($lastLine ?: 'see the server log for details.'));
        }

        AuditLog::record('ministries.distributed');

        return redirect()->route('admin.assign-rms')->with('status', 'Ministries re-distributed across RMs.');
    }

    /**
     * Brings clients:rebalance into the admin UI - the Clients tab's own
     * "Distribute Automatically", matching the Ministries tab (2026-09-07,
     * the boss asked for this to be visible there too). Admin-only, same
     * as the ministries version: it recomputes across every RM system-wide,
     * crossing supervisor team boundaries, not just one supervisor's team.
     */
    public function distributeClients(): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $exitCode = Artisan::call('clients:rebalance');

        if ($exitCode !== 0) {
            $lastLine = collect(explode("\n", trim(Artisan::output())))->filter()->last();

            return redirect()->route('admin.assign-rms', ['view' => 'clients'])
                ->with('error', 'Distribution failed: '.($lastLine ?: 'see the server log for details.'));
        }

        AuditLog::record('clients.distributed');

        return redirect()->route('admin.assign-rms', ['view' => 'clients'])->with('status', 'Clients re-distributed across RMs.');
    }

    /**
     * Admin-only: moves an RM to a different supervisor's team (or back to
     * unassigned). This is the function the boss asked for to sort out the
     * RMs that already existed before supervisors did (2026-09-06).
     */
    public function assignRmSupervisor(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless($user->isRm(), 404);

        $data = $request->validate([
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $newSupervisor = $data['supervisor_id'] ? User::where('role', User::ROLE_SUPERVISOR)->find($data['supervisor_id']) : null;
        abort_if($data['supervisor_id'] && ! $newSupervisor, 422);

        $previousSupervisor = $user->supervisor?->name;

        $user->update(['supervisor_id' => $newSupervisor?->id]);

        AuditLog::record('user.supervisor_assigned', $user, [
            'rm' => $user->name,
            'previous_supervisor' => $previousSupervisor,
            'new_supervisor' => $newSupervisor?->name,
        ]);

        return back()->with('status', $newSupervisor
            ? "{$user->name} now reports to {$newSupervisor->name}."
            : "{$user->name} is now unassigned to a supervisor.");
    }

    /**
     * Scoped the same way as the dashboard's Recent Activity - a
     * supervisor only sees entries about their own team, never other
     * supervisors' (2026-09-07).
     */
    public function auditLog(Request $request): View
    {
        $viewer = auth()->user();

        $filters = [
            'action' => $request->string('action')->toString() ?: null,
            'user_id' => $request->string('user_id')->toString() ?: null,
        ];

        $entries = AuditLog::visibleTo($viewer)
            ->with('user')
            ->when($filters['action'], fn ($q, $v) => $q->where('action', $v))
            ->when($filters['user_id'], fn ($q, $v) => $q->where('user_id', $v))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $who = $viewer->isSupervisor()
            ? User::where('supervisor_id', $viewer->id)->orWhere('id', $viewer->id)
            : User::whereIn('role', [User::ROLE_RM, User::ROLE_SUPERVISOR, User::ROLE_ADMIN]);

        return view('admin.audit-log', [
            'entries' => $entries,
            'filters' => $filters,
            'actions' => AuditLog::visibleTo($viewer)->distinct()->orderBy('action')->pluck('action'),
            'rms' => $who->orderBy('name')->get(),
        ]);
    }
}
