<x-layout title="Admin">
        @if (session('status'))
            <div class="mb-6 rounded-lg bg-brand-50 border border-brand-600/30 text-brand-800 text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        <header class="relative mb-8 rounded-2xl bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 shadow-xl shadow-brand-900/10 px-6 py-7 sm:px-8 sm:py-8 overflow-hidden">
            <div class="absolute -top-16 -right-10 w-64 h-64 rounded-full bg-white/10 blur-2xl"></div>

            <div class="relative flex flex-col sm:flex-row items-center gap-6">
                <div class="flex-1 text-center sm:text-left">
                    <p class="text-white/70 text-xs uppercase tracking-wide">Admin</p>
                    <h1 class="text-xl sm:text-2xl font-semibold text-white">{{ auth()->user()->name }}</h1>
                    <p class="text-sm text-white/80 mt-1">Manage RM accounts and review activity.</p>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="px-4 py-3 rounded-lg bg-white/15 ring-1 ring-white/25 text-white text-sm font-medium hover:bg-white/25 transition-colors">
                        Log Out
                    </button>
                </form>
            </div>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
            <x-stat-tile label="Relationship Managers" :value="number_format($userCount)" hint="Tap to manage accounts" icon="building" tone="green" :href="route('admin.users')" />
            <x-stat-tile label="Total Submissions" :value="number_format($submissionCount)" hint="Across all RMs and legacy data" icon="document" tone="gold" :href="route('dashboard')" />
            <x-stat-tile label="RM Performance" value="View" hint="Ministries and activity per RM" icon="landmark" tone="violet" :href="route('admin.rm-performance')" />
            <x-stat-tile label="Audit Log" value="View" hint="Every account and submission action" icon="scale" tone="teal" :href="route('admin.audit-log')" />
        </div>

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Recent Activity</h2>
                <a href="{{ route('admin.audit-log') }}" class="text-sm text-brand-700 hover:text-brand-800 font-medium">View full log →</a>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium">Who</th>
                        <th class="px-4 py-2 font-medium">Action</th>
                        <th class="px-4 py-2 font-medium">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($recentAuditLog as $entry)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $entry->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3 text-ink-muted">{{ $entry->action }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-ink-faint">{{ $entry->created_at->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-ink-faint">No activity recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</x-layout>
