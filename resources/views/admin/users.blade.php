<x-layout title="Relationship Managers">
        @if (session('status'))
            <div class="mb-6 rounded-lg bg-brand-50 border border-brand-600/30 text-brand-800 text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex items-center justify-between mb-6">
            <x-page-header
                title="Relationship Managers"
                :subtitle="$users->count().' RM account(s).'"
                :back="route('admin.dashboard')"
                back-label="Back to admin"
            />
            <a href="{{ route('admin.users.create') }}" class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm shadow-brand-900/20 -mt-10">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                New RM Account
            </a>
        </div>

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium">Name</th>
                        <th class="px-4 py-2 font-medium">Email</th>
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
                                @if ($user->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-brand-50 text-brand-800 text-xs font-medium">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-red-100 text-danger text-xs font-medium">Deactivated</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium {{ $user->is_active ? 'text-danger hover:text-danger' : 'text-brand-700 hover:text-brand-800' }}">
                                        {{ $user->is_active ? 'Deactivate' : 'Reactivate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-ink-faint">No RM accounts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</x-layout>
