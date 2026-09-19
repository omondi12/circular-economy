<x-layout title="Requisitions" wide>
        @if (session('status'))
            <div class="mb-6 rounded-lg bg-brand-50 border border-brand-600/30 text-brand-800 text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex items-start justify-between gap-4 mb-6">
            <x-page-header
                title="Requisitions"
                subtitle="Every RM/Supervisor transport and airtime request. Only admins can approve or decline."
                :back="route('admin.dashboard')"
                back-label="Back to admin"
            />
            <a
                href="{{ route('admin.requisitions.export', array_filter(['requester_id' => $filters['requester_id']])) }}"
                class="shrink-0 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg border border-border bg-white text-sm font-medium text-ink hover:bg-panel-muted transition-colors"
                title="Download every approved transport/airtime request as a spreadsheet, for the boss or finance"
            >
                <x-icon name="file-text" size="16" />
                Export Approved
            </a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4 mb-6">
            <x-stat-tile label="Total Requests" :value="number_format($stats['totalCount'])" icon="calendar" tone="teal" />
            <x-stat-tile label="Pending" :value="number_format($stats['pendingCount'])" icon="inbox" tone="rose" />
            <x-stat-tile label="Declined" :value="number_format($stats['declinedCount'])" icon="x" tone="violet" />
            <x-stat-tile label="Total Requested" :value="'KES '.number_format($stats['totalRequested'], 0)" hint="Transport + airtime, excludes declined" icon="scale" tone="gold" />
            <x-stat-tile label="Approved" :value="'KES '.number_format($stats['totalApproved'], 0)" hint="Authorized, whether paid yet or not" icon="circle-check" tone="violet" />
            <x-stat-tile label="Total Paid" :value="'KES '.number_format($stats['totalPaid'], 0)" hint="Actually disbursed" icon="circle-check" tone="green" />
            <x-stat-tile label="Outstanding" :value="'KES '.number_format($stats['totalBalance'], 0)" hint="Approved but not yet paid" icon="alert-triangle" tone="rose" />
        </div>

        <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-faint mb-3">Today ({{ now()->format('D, d M Y') }})</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4 mb-6">
            <x-stat-tile label="Requests Today" :value="number_format($todayStats['totalCount'])" icon="calendar" tone="teal" />
            <x-stat-tile label="Pending" :value="number_format($todayStats['pendingCount'])" icon="inbox" tone="rose" />
            <x-stat-tile label="Declined" :value="number_format($todayStats['declinedCount'])" icon="x" tone="violet" />
            <x-stat-tile label="Requested Today" :value="'KES '.number_format($todayStats['totalRequested'], 0)" hint="Transport + airtime, excludes declined" icon="scale" tone="gold" />
            <x-stat-tile label="Approved Today" :value="'KES '.number_format($todayStats['totalApproved'], 0)" hint="Authorized, whether paid yet or not" icon="circle-check" tone="violet" />
            <x-stat-tile label="Paid Today" :value="'KES '.number_format($todayStats['totalPaid'], 0)" hint="Actually disbursed" icon="circle-check" tone="green" />
            <x-stat-tile label="Outstanding Today" :value="'KES '.number_format($todayStats['totalBalance'], 0)" hint="Approved but not yet paid" icon="alert-triangle" tone="rose" />
        </div>

        <div class="bg-panel border border-border rounded-xl p-4 shadow-sm mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-xs text-ink-faint uppercase tracking-wide mb-1">Public page PIN</p>
                @if ($pin)
                    <p class="text-2xl font-display tabular-nums text-ink">{{ $pin }}</p>
                @else
                    <p class="text-sm text-ink-faint">No PIN generated yet.</p>
                @endif
            </div>
            @if (auth()->user()->isAdmin())
                <form method="POST" action="{{ route('admin.requisitions.pin.regenerate') }}" onsubmit="return confirm('This invalidates the current PIN - anyone using it will need the new one. Continue?')">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg border border-border text-sm font-medium hover:bg-panel-muted transition-colors">
                        {{ $pin ? 'Regenerate PIN' : 'Generate PIN' }}
                    </button>
                </form>
            @endif
        </div>

        <form method="GET" class="mb-4 flex flex-wrap gap-2 items-center">
            <select name="status" onchange="this.form.submit()" class="rounded-lg border border-border bg-white px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600">
                <option value="">All statuses</option>
                <option value="pending" @selected($filters['status'] === 'pending')>Pending only</option>
            </select>
            <select name="requester_id" onchange="this.form.submit()" class="rounded-lg border border-border bg-white px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600">
                <option value="">All requesters</option>
                @foreach ($requesters as $r)
                    <option value="{{ $r->id }}" @selected((string) $filters['requester_id'] === (string) $r->id)>{{ $r->name }}</option>
                @endforeach
            </select>
            @if ($filters['status'] || $filters['requester_id'])
                <a href="{{ route('admin.requisitions.index') }}" class="text-xs text-ink-faint hover:text-ink-muted">Clear filters</a>
            @endif
        </form>

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Name</th>
                        <th class="px-3 py-2 font-medium">Institution</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Working Day</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Transport Requested</th>
                        <th class="px-3 py-2 font-medium">Transport</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Airtime Requested</th>
                        <th class="px-3 py-2 font-medium">Airtime</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Total for Day</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Cumulative</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Days Facilitated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($requisitions as $req)
                        @php
                            $totals = $requesterTotals[$req->requester_id] ?? ['days' => 0, 'cumulative' => 0];
                            $canApprove = auth()->user()->canApproveRequisition($req);
                        @endphp
                        <tr class="hover:bg-panel-muted transition-colors align-top">
                            <td class="px-3 py-3 font-medium whitespace-nowrap">{{ $req->requester->name ?? '—' }}</td>
                            <td class="px-3 py-3 max-w-[200px]"><div class="line-clamp-2">{{ $req->institution_visiting }}</div></td>
                            <td class="px-3 py-3 whitespace-nowrap text-ink-muted">{{ $req->working_day->format('D, d M Y') }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-ink-muted">
                                KES {{ number_format($req->transport_amount_requested, 0) }}
                                <div class="text-xs text-ink-faint">{{ $req->transport_requested_at->format('d M, H:i') }}</div>
                            </td>
                            <td class="px-3 py-3 min-w-[220px]">
                                <x-requisition-status-badge :status="$req->transport_status" />
                                @if ($req->transport_status === 'pending')
                                    @if ($canApprove)
                                        <div class="flex items-center gap-1 mt-2">
                                            <form method="POST" action="{{ route('admin.requisitions.transport.approve', $req) }}">
                                                @csrf
                                                <button type="submit" class="px-2 py-1 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-xs font-medium">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.requisitions.transport.decline', $req) }}" onsubmit="return confirm('Decline this transport request?')">
                                                @csrf
                                                <button type="submit" class="px-2 py-1 rounded-md border border-border text-xs font-medium hover:bg-panel-muted">Decline</button>
                                            </form>
                                        </div>
                                    @else
                                        <p class="text-xs text-ink-faint mt-1">Awaiting approval</p>
                                    @endif
                                @else
                                    <div class="text-xs text-ink-faint mt-1">
                                        {{ $req->transportApprovedBy->name ?? '—' }}
                                        <div class="tabular-nums">Paid: {{ number_format($req->transport_paid_amount, 0) }} · Bal: {{ number_format($req->transportBalance(), 0) }}</div>
                                    </div>
                                    @if ($req->transport_status === 'approved' && $req->transportBalance() > 0 && $canApprove)
                                        <form method="POST" action="{{ route('admin.requisitions.transport.pay', $req) }}" class="flex items-center gap-1 mt-2">
                                            @csrf
                                            <input type="number" step="0.01" min="0" max="{{ $req->transport_amount_requested }}" name="paid_amount" value="{{ $req->transport_amount_requested }}" class="w-20 rounded-md border-border text-xs py-1 px-1.5">
                                            <button type="submit" class="px-2 py-1 rounded-md bg-gold-600 hover:bg-gold-700 text-white text-xs font-medium">Paid</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-ink-muted">
                                KES {{ number_format($req->airtime_amount_requested, 0) }}
                                <div class="text-xs text-ink-faint">{{ $req->airtime_requested_at->format('d M, H:i') }}</div>
                            </td>
                            <td class="px-3 py-3 min-w-[220px]">
                                <x-requisition-status-badge :status="$req->airtime_status" />
                                @if ($req->airtime_status === 'pending')
                                    @if ($canApprove)
                                        <div class="flex items-center gap-1 mt-2">
                                            <form method="POST" action="{{ route('admin.requisitions.airtime.approve', $req) }}">
                                                @csrf
                                                <button type="submit" class="px-2 py-1 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-xs font-medium">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.requisitions.airtime.decline', $req) }}" onsubmit="return confirm('Decline this airtime request?')">
                                                @csrf
                                                <button type="submit" class="px-2 py-1 rounded-md border border-border text-xs font-medium hover:bg-panel-muted">Decline</button>
                                            </form>
                                        </div>
                                    @else
                                        <p class="text-xs text-ink-faint mt-1">Awaiting approval</p>
                                    @endif
                                @else
                                    <div class="text-xs text-ink-faint mt-1">
                                        {{ $req->airtimeApprovedBy->name ?? '—' }}
                                        <div class="tabular-nums">Paid: {{ number_format($req->airtime_paid_amount, 0) }} · Bal: {{ number_format($req->airtimeBalance(), 0) }}</div>
                                    </div>
                                    @if ($req->airtime_status === 'approved' && $req->airtimeBalance() > 0 && $canApprove)
                                        <form method="POST" action="{{ route('admin.requisitions.airtime.pay', $req) }}" class="flex items-center gap-1 mt-2">
                                            @csrf
                                            <input type="number" step="0.01" min="0" max="{{ $req->airtime_amount_requested }}" name="paid_amount" value="{{ $req->airtime_amount_requested }}" class="w-20 rounded-md border-border text-xs py-1 px-1.5">
                                            <button type="submit" class="px-2 py-1 rounded-md bg-gold-600 hover:bg-gold-700 text-white text-xs font-medium">Paid</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium tabular-nums">KES {{ number_format($req->totalRequestedForDay(), 0) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap tabular-nums text-ink-muted">KES {{ number_format($totals['cumulative'], 0) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap tabular-nums text-ink-muted">{{ number_format($totals['days']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-ink-faint">No requisitions logged yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $requisitions->links() }}
        </div>
</x-layout>
