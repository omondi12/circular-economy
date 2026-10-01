<x-layout title="My Dashboard">
        @if (session('status'))
            <div class="mb-6 rounded-lg bg-brand-50 border border-brand-600/30 text-brand-800 text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        <header class="relative mb-8 rounded-2xl bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 shadow-xl shadow-brand-900/10 px-6 py-7 sm:px-8 sm:py-8 overflow-hidden">
            <div class="absolute -top-16 -right-10 w-64 h-64 rounded-full bg-white/10 blur-2xl"></div>

            <div class="relative flex flex-col sm:flex-row items-center gap-6">
                <div class="flex-1 text-center sm:text-left">
                    <p class="text-white/70 text-xs uppercase tracking-wide">Relationship Manager</p>
                    <h1 class="text-xl sm:text-2xl font-semibold text-white">{{ auth()->user()->name }}</h1>
                    <p class="text-sm text-white/80 mt-1">Your submissions, at a glance.</p>
                </div>

                <div class="shrink-0 flex items-center gap-2">
                    <a href="{{ route('rm.collections.create') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-white text-brand-800 text-sm font-semibold hover:bg-white/90 transition-colors shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Record a Collection
                    </a>
                    <a href="{{ route('rm.clients.index') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-white/15 ring-1 ring-white/25 text-white text-sm font-semibold hover:bg-white/25 transition-colors">
                        <x-icon name="document" size="16" />
                        Log a Report
                    </a>
                    <a href="{{ route('requisitions.mine') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-white/15 ring-1 ring-white/25 text-white text-sm font-semibold hover:bg-white/25 transition-colors">
                        <x-icon name="calendar" size="16" />
                        My Requisitions
                    </a>
                    <a href="{{ route('account.profile.edit') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-white/15 ring-1 ring-white/25 text-white text-sm font-semibold hover:bg-white/25 transition-colors">
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

        @if ($assignedStateDepartments->isNotEmpty())
            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm mb-6">
                <div class="px-5 py-4 border-b border-border">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">My State Department(s)</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-5">
                    @foreach ($assignedStateDepartments as $department)
                        <div class="rounded-lg border border-border p-4">
                            <p class="text-sm font-semibold text-ink">{{ $department->name }}</p>
                            <p class="text-xs text-ink-faint mb-2">{{ $department->parent->name ?? '' }}</p>
                            @if ($department->contact_person_name || $department->contact_person_phone)
                                <p class="text-xs text-ink-faint uppercase tracking-wide mt-2">Contact Person</p>
                                <p class="text-sm text-ink-muted">{{ $department->contact_person_name ?: '—' }}</p>
                                <p class="text-sm text-ink-muted tabular-nums">{{ $department->contact_person_phone }}</p>
                            @else
                                <p class="text-xs text-ink-faint italic">No contact person on file yet.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <x-stat-tile label="My Submissions" :value="number_format($totalSubmissions)" icon="document" tone="green" />
            <x-stat-tile label="My Commission Earned" :value="'KES '.number_format($myCommissionMinor / 100)" hint="10% of Westport's confirmed Lot 1 commission" icon="stamp" tone="violet" />
            <x-stat-tile label="Total Quantity Recorded" :value="number_format($totalQuantity, 1)" hint="Mixed units - see submissions below" icon="scale" tone="gold" />
            @foreach ($byLot as $lot)
                <x-stat-tile :label="$lot['label']" :value="number_format($lot['count'])" hint="submissions" icon="building" tone="teal" />
            @endforeach
        </div>

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-border">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">My Submissions</h2>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium">Entity</th>
                        <th class="px-4 py-2 font-medium">Lot / Category</th>
                        <th class="px-4 py-2 font-medium text-right">Quantity</th>
                        <th class="px-4 py-2 font-medium">Date</th>
                        <th class="px-4 py-2 font-medium">LSO / Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($submissions as $submission)
                        <tr class="hover:bg-panel-muted transition-colors">
                            <td class="px-4 py-3 font-medium max-w-xs">
                                <div class="line-clamp-2">{{ $submission->entity_name }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full {{ $submission->lot === 1 ? 'bg-brand-50 text-brand-800' : 'bg-gold-100 text-gold-700' }} text-xs font-medium">
                                    {{ \App\Support\WasteCategories::shortLotLabel($submission->lot) }}
                                </span>
                                <span class="block text-xs text-ink-faint mt-0.5">{{ $submission->categoryLabel() }}</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap font-medium">{{ number_format($submission->quantity, 1) }} {{ $submission->unit }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-ink-faint">{{ $submission->collection_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($submission->lso_id)
                                    <a href="{{ route('rm.lsos.show', $submission->lso_id) }}" class="text-brand-700 hover:text-brand-800 font-medium text-xs">
                                        View / Record Payment
                                    </a>
                                @elseif ($submission->lsoLot?->expected_revenue_minor !== null)
                                    <span class="text-ink font-medium text-xs">KES {{ number_format($submission->lsoLot->expected_revenue_minor / 100) }}</span>
                                @elseif ($submission->lsoLot)
                                    <span class="text-ink-faint text-xs italic">Pricing pending</span>
                                @else
                                    <span class="text-ink-faint text-xs">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-ink-faint">You haven't recorded any collections yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $submissions->links() }}
        </div>
</x-layout>
