<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
