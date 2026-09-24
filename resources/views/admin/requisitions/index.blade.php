<x-layout title="Requisitions" wide>
    @if ($errors->has('payment'))
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ $errors->first('payment') }}
        </div>
    @endif
        <div class="flex items-start justify-between gap-4 mb-6">
            <x-page-header
                title="Requisitions"
                subtitle="Every RM/Supervisor transport and airtime request. Only admins can approve or decline."
                :back="route('admin.dashboard')"
                back-label="Back to admin"
            />
            <div class="flex shrink-0 flex-wrap justify-end gap-2">
                @if (auth()->user()->isAdmin())
                    <a
                        href="{{ route('admin.nawiri-treasury.edit') }}"
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-border bg-white px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-panel-muted"
                    >
                        <x-icon name="building-bank" size="16" />
                        Nawiri Treasury
                    </a>
                @endif
                <a
                    href="{{ route('admin.requisitions.export', array_filter(['requester_id' => $filters['requester_id']])) }}"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-border bg-white px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-panel-muted"
                    title="Download every approved transport/airtime request as a spreadsheet, for the boss or finance"
                >
                    <x-icon name="file-text" size="16" />
                    Export Approved
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
            <x-stat-tile label="Total Requests" :value="number_format($stats['totalCount'])" icon="calendar" tone="teal" />
            <x-requisition-summary-tile
                label="Total Requested" :amount="$stats['totalRequested']"
                :pendingCount="$stats['pendingCount']" :declinedCount="$stats['declinedCount']"
            />
            <x-stat-tile label="Approved" :value="'KES '.number_format($stats['totalApproved'], 0)" hint="Authorized, not necessarily paid" icon="circle-check" tone="violet" />
            <x-stat-tile label="Total Paid" :value="'KES '.number_format($stats['totalPaid'], 0)" hint="Actually disbursed" icon="circle-check" tone="green" />
            <x-stat-tile label="Outstanding" :value="'KES '.number_format($stats['totalBalance'], 0)" hint="Approved but not yet paid" icon="alert-triangle" tone="rose" />
        </div>

        <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-faint mb-3">Today ({{ now()->format('D, d M Y') }})</h2>
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
            <x-stat-tile label="Requests Today" :value="number_format($todayStats['totalCount'])" icon="calendar" tone="teal" />
            <x-requisition-summary-tile
                label="Requested Today" :amount="$todayStats['totalRequested']"
                :pendingCount="$todayStats['pendingCount']" :declinedCount="$todayStats['declinedCount']"
            />
            <x-stat-tile label="Approved Today" :value="'KES '.number_format($todayStats['totalApproved'], 0)" hint="Authorized, not necessarily paid" icon="circle-check" tone="violet" />
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
                            $canPay = auth()->user()->canPayRequisitions();
                            $transportActivePayment = $req->activePayment('transport');
                            $airtimeActivePayment = $req->activePayment('airtime');
							$recipientPhones = $req->recipientPhoneNumbers();
                            $transportPayments = $req->latestPaymentsByRecipient('transport');
                            $airtimePayments = $req->latestPaymentsByRecipient('airtime');
                            $transportPayable = $req->payableRecipientBalances('transport');
                            $airtimePayable = $req->payableRecipientBalances('airtime');
                            $recipientCount = $req->recipientCount();
                        @endphp
                        <tr class="hover:bg-panel-muted transition-colors align-top">
                            <td class="px-3 py-3 font-medium whitespace-nowrap">
                                {{ $req->requester->name ?? '—' }}
								@if ($recipientPhones !== [])
									<div class="text-xs font-normal text-ink-faint">{{ implode(', ', $recipientPhones) }} · Nawiri recipient(s)</div>
                                @endif
                            </td>
                            <td class="px-3 py-3 max-w-[200px]"><div class="line-clamp-2">{{ $req->institution_visiting }}</div></td>
                            <td class="px-3 py-3 whitespace-nowrap text-ink-muted">{{ $req->working_day->format('D, d M Y') }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-ink-muted">
                                KES {{ number_format($req->transport_amount_requested, 0) }}{{ $recipientCount > 1 ? ' each' : '' }}
                                @if ($recipientCount > 1)
                                    <div class="text-xs text-ink-faint">KES {{ number_format($req->categoryTotalRequested('transport'), 0) }} total</div>
                                @endif
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
                                    @foreach ($transportPayments as $transportPayment)
                                        <div class="text-xs mt-1 {{ $transportPayment->status === 'completed' ? 'text-emerald-700' : ($transportPayment->status === 'failed' ? 'text-red-700' : 'text-amber-700') }}">
                                            {{ $transportPayment->phone_number }}:
                                            {{ $transportPayment->status === 'submitted' ? 'JamboPay processing' : str_replace('_', ' ', ucfirst($transportPayment->status)) }}
                                            @if ($transportPayment->failure_reason)<div>{{ $transportPayment->failure_reason }}</div>@endif
                                        </div>
                                    @endforeach
                                    @if ($transportActivePayment && $canPay && $transportPayable === [])
                                        <p class="mt-1 text-xs text-amber-700">Checking automatically. Do not pay again.</p>
                                    @elseif ($req->transport_status === 'approved' && $transportPayable !== [] && $canPay)
										<form method="POST" action="{{ route('admin.requisitions.transport.pay', $req) }}" class="flex flex-wrap items-center gap-1 mt-2" onsubmit="return confirm('Send KES {{ number_format(array_sum($transportPayable), 0) }} in transport to {{ count($transportPayable) }} unpaid recipient{{ count($transportPayable) === 1 ? '' : 's' }}?')">
                                            @csrf
                                            <button type="submit" class="px-2 py-1 rounded-md border border-gold-700 bg-gold-600 hover:bg-gold-700 text-white text-xs font-medium">
                                                Pay {{ count($transportPayable) }} recipient{{ count($transportPayable) === 1 ? '' : 's' }} · KES {{ number_format(array_sum($transportPayable), 0) }}
                                            </button>
                                        </form>
                                    @elseif ($req->transport_status === 'approved' && $req->transportBalance() > 0 && $canPay)
										<p class="mt-2 text-xs font-medium text-red-700">No Nawiri recipient number supplied</p>
                                    @endif
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-ink-muted">
                                KES {{ number_format($req->airtime_amount_requested, 0) }}{{ $recipientCount > 1 ? ' each' : '' }}
                                @if ($recipientCount > 1)
                                    <div class="text-xs text-ink-faint">KES {{ number_format($req->categoryTotalRequested('airtime'), 0) }} total</div>
                                @endif
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
                                    @foreach ($airtimePayments as $airtimePayment)
                                        <div class="text-xs mt-1 {{ $airtimePayment->status === 'completed' ? 'text-emerald-700' : ($airtimePayment->status === 'failed' ? 'text-red-700' : 'text-amber-700') }}">
                                            {{ $airtimePayment->phone_number }}:
                                            {{ $airtimePayment->status === 'submitted' ? 'JamboPay processing' : str_replace('_', ' ', ucfirst($airtimePayment->status)) }}
                                            @if ($airtimePayment->failure_reason)<div>{{ $airtimePayment->failure_reason }}</div>@endif
                                        </div>
                                    @endforeach
                                    @if ($airtimeActivePayment && $canPay && $airtimePayable === [])
                                        <p class="mt-1 text-xs text-amber-700">Checking automatically. Do not pay again.</p>
                                    @elseif ($req->airtime_status === 'approved' && $airtimePayable !== [] && $canPay)
										<form method="POST" action="{{ route('admin.requisitions.airtime.pay', $req) }}" class="flex flex-wrap items-center gap-1 mt-2" onsubmit="return confirm('Send KES {{ number_format(array_sum($airtimePayable), 0) }} in airtime to {{ count($airtimePayable) }} unpaid recipient{{ count($airtimePayable) === 1 ? '' : 's' }}?')">
                                            @csrf
                                            <button type="submit" class="px-2 py-1 rounded-md border border-gold-700 bg-gold-600 hover:bg-gold-700 text-white text-xs font-medium">
                                                Pay {{ count($airtimePayable) }} recipient{{ count($airtimePayable) === 1 ? '' : 's' }} · KES {{ number_format(array_sum($airtimePayable), 0) }}
                                            </button>
                                        </form>
                                    @elseif ($req->airtime_status === 'approved' && $req->airtimeBalance() > 0 && $canPay)
										<p class="mt-2 text-xs font-medium text-red-700">No Nawiri recipient number supplied</p>
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

        @if ($requisitions->getCollection()->contains(fn ($requisition) => $requisition->activePayment('transport') || $requisition->activePayment('airtime')))
            <script>
                window.setTimeout(() => window.location.reload(), 10000);
            </script>
        @endif
</x-layout>
