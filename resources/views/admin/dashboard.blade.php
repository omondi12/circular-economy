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

                <div class="shrink-0 flex items-center gap-2">
                    <a href="{{ route('account.profile.edit') }}" class="inline-flex items-center gap-2 px-4 py-3 rounded-lg bg-white/15 ring-1 ring-white/25 text-white text-sm font-medium hover:bg-white/25 transition-colors">
                        <x-icon name="user" size="16" />
                        My Profile
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="px-4 py-3 rounded-lg bg-white/15 ring-1 ring-white/25 text-white text-sm font-medium hover:bg-white/25 transition-colors">
                            Log Out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        {{-- People & RM Operations: who's operating, and how much ground is covered. --}}
        <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted mb-3">People &amp; RM Operations</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
            <x-stat-tile
                :label="$isSupervisor ? 'My Relationship Managers' : 'Relationship Managers'"
                :value="number_format($userCount)" hint="Tap to manage accounts" icon="building" tone="teal"
                :href="route('admin.users')"
            />
            @if ($isSupervisor)
                <x-stat-tile label="My Client Reports" :value="number_format($reportCount)" hint="Reports you've personally logged" icon="calendar" tone="violet" :href="route('reports.index')" />
            @else
                <x-stat-tile label="Supervisors" :value="number_format($supervisorCount)" hint="Tap to manage accounts" icon="user" tone="violet" :href="route('admin.users')" />
            @endif
            <x-stat-tile
                :label="$isSupervisor ? 'My Client Coverage' : 'Client Coverage'"
                :value="number_format($assignedClientCount)"
                :hint="$unassignedClientCount > 0 ? number_format($unassignedClientCount).' still need an RM' : 'Every client has an RM assigned'"
                icon="building" tone="green"
                :href="route('admin.assign-rms', ['view' => 'clients'])"
            />
            <x-stat-tile
                label="Ministries"
                :value="number_format($ministryTotal)"
                :hint="number_format($ministriesWithRm).' with an RM assigned'"
                icon="building-community" tone="gold"
                :href="route('admin.assign-rms')"
            />
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
            <x-stat-tile label="Total Submissions" :value="number_format($submissionCount)" hint="Across all RMs and legacy data" icon="document" tone="gold" :href="route('dashboard')" />
            @unless ($isSupervisor)
                <x-stat-tile label="Client Reports" :value="number_format($reportCount)" hint="Daily engagement reports logged" icon="calendar" tone="violet" :href="route('reports.index')" />
            @endunless
        </div>

        {{-- LSO Activity & Revenue: the core business activity - what's happening and what it's worth. --}}
        <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted mb-3">LSO Activity &amp; Revenue</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
            <x-stat-tile
                label="LSOs" :value="number_format($lsoCount)"
                :hint="$lsoCount > 0
                    ? number_format($lsoFullyPaidCount).' fully paid · '.number_format($lsoInProgressCount).' in progress'.($lsoCancelledCount > 0 ? ' · '.number_format($lsoCancelledCount).' cancelled' : '')
                    : 'No LSOs recorded yet'"
                icon="document" tone="gold" :href="route('admin.lsos.index')"
            />
            <x-stat-tile
                label="Confirmed Collected" :value="'KES '.number_format($lsoTotalConfirmedMinor / 100)"
                :hint="'KES '.number_format($lsoTotalOutstandingMinor / 100).' outstanding · KES '.number_format($lsoTotalOriginalMinor / 100).' total LSO value'"
                icon="stamp" tone="teal" :href="route('admin.lsos.index')"
            />
        </div>

        {{-- Performance & Reporting: how the operation is doing against expectations. --}}
        <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted mb-3">Performance &amp; Reporting</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
            <x-stat-tile
                label="Target Achievement"
                :value="$targetAchievementAverage !== null ? number_format($targetAchievementAverage, 1).'%' : 'No active targets'"
                :hint="$targetAchievementAverage !== null ? 'Across '.number_format($activeTargetCount).' target(s) for '.number_format($activeTargetRmCount).' RM(s) this period' : 'Set a target for the current period'"
                icon="scale" tone="violet" :href="route('admin.rm-targets.index')"
            />
            <x-stat-tile label="RM Performance" value="View" :hint="$isSupervisor ? 'Ministries and activity per RM on your team' : 'Ministries and activity per RM'" icon="landmark" tone="rose" :href="route('admin.rm-performance')" />
        </div>

        {{-- Requisitions: a separate operational workflow (transport/airtime), not LSO/client business. --}}
        <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted mb-3">Requisitions</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
            <x-stat-tile
                label="Requisitions" :value="number_format($requisitionPendingCount)"
                :hint="($isSupervisor || $isOperations) ? 'My pending transport/airtime requests' : 'Pending transport/airtime requests to approve'"
                icon="calendar" tone="rose"
                :href="($isSupervisor || $isOperations) ? route('requisitions.mine') : route('admin.requisitions.index')"
            />
        </div>

        {{-- Plain navigation/actions - deliberately not stat cards, since they're not statistics. --}}
        <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted mb-3">Quick Actions</h2>
        <div class="flex flex-wrap gap-2 mb-8">
            <a href="{{ route('admin.assign-rms') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-border text-sm font-medium text-ink-muted hover:bg-panel-muted hover:text-ink transition-colors">
                <x-icon name="user" size="15" /> Assign RMs
            </a>
            <a href="{{ route('admin.assign-rms', ['view' => 'clients']) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-border text-sm font-medium text-ink-muted hover:bg-panel-muted hover:text-ink transition-colors">
                <x-icon name="building-community" size="15" /> Clients &amp; Reports
            </a>
            @unless ($isSupervisor)
                <a href="{{ route('state-corporations.export') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-border text-sm font-medium text-ink-muted hover:bg-panel-muted hover:text-ink transition-colors">
                    <x-icon name="file-text" size="15" /> Download Clients
                </a>
            @endunless
            <a href="{{ route('admin.audit-log') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-border text-sm font-medium text-ink-muted hover:bg-panel-muted hover:text-ink transition-colors">
                <x-icon name="scale" size="15" /> Audit Log
            </a>
            @unless ($isSupervisor || $isOperations)
                <a href="{{ route('admin.nawiri-treasury.edit') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-border text-sm font-medium text-ink-muted hover:bg-panel-muted hover:text-ink transition-colors">
                    <x-icon name="building-bank" size="15" /> Nawiri Treasury
                </a>
            @endunless
        </div>

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Recent Activity{{ $isSupervisor ? ' - Your Team' : '' }}</h2>
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
