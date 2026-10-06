<x-layout title="Team Accounts">

        <x-page-header
            title="Team Accounts"
            :subtitle="auth()->user()->isAdmin() ? $users->count().' account(s) - Relationship Managers, Supervisors and Office Admins.' : $users->count().' RM(s) on your team.'"
            :back="route('admin.dashboard')"
            back-label="Back to admin"
        >
            <x-slot:actions>
                <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                    <x-icon name="plus" size="16" />
                    New Account
                </a>
            </x-slot:actions>
        </x-page-header>

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
            <table data-stack class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium">Name</th>
                        <th class="px-4 py-2 font-medium">Email</th>
                        <th class="px-4 py-2 font-medium">Role</th>
                        @if (auth()->user()->isAdmin())
                            <th class="px-4 py-2 font-medium">Supervisor</th>
                        @endif
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($users as $user)
                        <tr class="hover:bg-panel-muted transition-colors">
                            <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-ink-faint">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                @if ($user->isSupervisor())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-violet-100 text-violet-800 text-xs font-medium">Supervisor</span>
                                @elseif ($user->isOfficeAdmin())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gold-100 text-gold-700 text-xs font-medium">Office Admin</span>
                                @elseif ($user->isOperations())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-teal-100 text-teal-800 text-xs font-medium">Operations</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-panel-muted text-ink-muted text-xs font-medium">RM</span>
                                @endif
                            </td>
                            @if (auth()->user()->isAdmin())
                                <td class="px-4 py-3 text-ink-faint">
                                    {{ $user->isRm() ? ($user->supervisor->name ?? '—') : '—' }}
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                @if ($user->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-brand-50 text-brand-800 text-xs font-medium">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-red-100 text-danger text-xs font-medium">Deactivated</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="text-xs font-medium text-brand-700 hover:text-brand-800">
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium {{ $user->is_active ? 'text-danger hover:text-danger' : 'text-brand-700 hover:text-brand-800' }}">
                                            {{ $user->is_active ? 'Deactivate' : 'Reactivate' }}
                                        </button>
                                    </form>
                                    @if (auth()->user()->isAdmin())
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Permanently delete {{ $user->name }}? This removes their account entirely - any clients/ministries assigned to them are freed, and any requests they submitted are deleted too. This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-medium text-danger hover:text-danger">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->isAdmin() ? 6 : 5 }}"><x-empty-state title="No accounts yet." /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</x-layout>
