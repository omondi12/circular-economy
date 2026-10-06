<x-layout title="Requisitions" wide :show-flash="false">
    <div data-requisition-page>
        @php
            $actionMessage = $errors->any() ? implode(' ', $errors->all()) : (session('warning') ?? session('status'));
        @endphp
        <div data-requisition-feedback role="status" aria-live="polite" tabindex="-1"
             @class(['toast-enter fixed bottom-4 sm:bottom-6 inset-x-4 sm:inset-x-auto sm:right-6 z-50 sm:w-[28rem] overflow-hidden rounded-xl border border-border-strong bg-white pl-5 pr-4 py-3.5 text-sm text-ink shadow-lg shadow-ink/10 focus:outline-none', 'hidden' => ! $actionMessage])>
            <span class="absolute inset-y-0 left-0 w-1 bg-brand-600" aria-hidden="true"></span>
            <p data-action-message class="break-words">{{ $actionMessage }}</p>
            <div class="mt-2.5 flex gap-2">
                <button type="button" data-requisition-refresh class="btn btn-sm btn-secondary"><x-icon name="refresh" size="13" />Refresh status</button>
                <button type="button" data-dismiss-feedback class="btn btn-sm btn-ghost">Dismiss</button>
            </div>
        </div>
        <div data-requisition-content>
        <x-page-header
            title="Requisitions"
            subtitle="Admins, supervisors and office admins can review approvals. Only office admins can make payments."
            :back="route('admin.dashboard')"
            back-label="Back to admin"
        >
            <x-slot:actions>
                @if (auth()->user()->isAdmin() || auth()->user()->isOfficeAdmin())
                    <a href="{{ route('admin.nawiri-treasury.edit') }}" class="btn btn-secondary">
                        <x-icon name="building-bank" size="16" />
                        Nawiri Treasury
                    </a>
                @endif
                <a
                    href="{{ route('admin.requisitions.export', array_filter(['requester_id' => $filters['requester_id']])) }}"
                    class="btn btn-secondary"
                    title="Download every approved transport/airtime request as a spreadsheet, for the boss or finance"
                >
                    <x-icon name="download" size="16" />
                    Export Approved
                </a>
            </x-slot:actions>
        </x-page-header>

        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4 mb-6 [&>*:last-child]:col-span-2 md:[&>*:last-child]:col-span-1">
            <x-stat-tile label="Total Requests" :value="number_format($stats['totalCount'])" icon="calendar" tone="teal" />
            <x-requisition-summary-tile
                label="Total Requested" :amount="$stats['totalRequested']"
                :pendingCount="$stats['pendingCount']" :declinedCount="$stats['declinedCount']"
            />
            <x-stat-tile label="Approved" :value="'KES '.number_format($stats['totalApproved'], 0)" hint="Authorized, not necessarily paid" icon="circle-check" tone="violet" />
            <x-stat-tile label="Total Paid" :value="'KES '.number_format($stats['totalPaid'], 0)" hint="Actually disbursed" icon="circle-check" tone="green" />
            <x-stat-tile label="Outstanding" :value="'KES '.number_format($stats['totalBalance'], 0)" hint="Approved but not yet paid" icon="alert-triangle" tone="rose" />
        </div>

        @php
            // Figures below must never read as "today's activity" when the
            // admin has actually selected a different working day (2026-10-02
            // finding) - every tile label and the heading share this same
            // isToday() check, so they can never say different things.
            $daySuffix = $workingDate->isToday() ? 'Today' : $workingDate->format('d M');
        @endphp
        <h2 class="section-title">
            {{ $workingDate->isToday() ? 'Today' : 'Selected Day' }} ({{ $workingDate->format('D, d M Y') }})
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4 mb-6 [&>*:last-child]:col-span-2 md:[&>*:last-child]:col-span-1">
            <x-stat-tile :label="'Requests '.$daySuffix" :value="number_format($todayStats['totalCount'])" icon="calendar" tone="teal" />
            <x-requisition-summary-tile
                :label="'Requested '.$daySuffix" :amount="$todayStats['totalRequested']"
                :pendingCount="$todayStats['pendingCount']" :declinedCount="$todayStats['declinedCount']"
            />
            <x-stat-tile :label="'Approved '.$daySuffix" :value="'KES '.number_format($todayStats['totalApproved'], 0)" hint="Authorized, not necessarily paid" icon="circle-check" tone="violet" />
            <x-stat-tile :label="'Paid '.$daySuffix" :value="'KES '.number_format($todayStats['totalPaid'], 0)" hint="Actually disbursed" icon="circle-check" tone="green" />
            <x-stat-tile :label="'Outstanding '.$daySuffix" :value="'KES '.number_format($todayStats['totalBalance'], 0)" hint="Approved but not yet paid" icon="alert-triangle" tone="rose" />
        </div>

        <div class="card p-4 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
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
                    <button type="submit" class="btn btn-secondary">
                        <x-icon name="refresh" size="15" />
                        {{ $pin ? 'Regenerate PIN' : 'Generate PIN' }}
                    </button>
                </form>
            @endif
        </div>

        @php
            $activeFilterCount = collect([$filters['status'], $filters['requester_id'], $filters['date']])->filter()->count();
        @endphp
        <form method="GET" class="card p-4 mb-4" aria-label="Filter requisitions">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[repeat(3,minmax(0,14rem))_auto] gap-3 lg:items-end">
                <div>
                    <label for="filter-status" class="field-label">Status</label>
                    <select id="filter-status" name="status" onchange="this.form.submit()" class="field-control">
                        <option value="">All statuses</option>
                        <option value="pending" @selected($filters['status'] === 'pending')>Pending only</option>
                    </select>
                </div>
                <div>
                    <label for="filter-requester" class="field-label">Requester</label>
                    <select id="filter-requester" name="requester_id" onchange="this.form.submit()" class="field-control">
                        <option value="">All requesters</option>
                        @foreach ($requesters as $r)
                            <option value="{{ $r->id }}" @selected((string) $filters['requester_id'] === (string) $r->id)>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-date" class="field-label">Working date</label>
                    <input id="filter-date" type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="field-control">
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="filter" size="15" />
                        Apply
                    </button>
                    @if ($activeFilterCount > 0)
                        <a href="{{ route('admin.requisitions.index') }}" class="btn btn-ghost">Clear filters</a>
                    @endif
                </div>
            </div>
            @if ($activeFilterCount > 0)
                <div class="mt-3 pt-3 border-t border-border flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-ink-faint">Showing:</span>
                    @if ($filters['status'])
                        <span class="badge badge-info">Pending only</span>
                    @endif
                    @if ($filters['requester_id'])
                        <span class="badge badge-info">{{ $requesters->firstWhere('id', (int) $filters['requester_id'])?->name ?? 'Selected requester' }}</span>
                    @endif
                    @if ($filters['date'])
                        <a href="{{ route('admin.requisitions.index', array_filter(['status' => $filters['status'], 'requester_id' => $filters['requester_id']])) }}" class="badge badge-info hover:bg-info/15" title="Clear date">
                            {{ $workingDate->format('D, d M Y') }}
                            <x-icon name="x" size="11" />
                            <span class="sr-only">Clear date</span>
                        </a>
                    @endif
                </div>
            @endif
        </form>

        <div data-requisition-table class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table data-stack class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-muted text-xs">
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
                            $canPay = auth()->user()->canPayRequisition($req);
                            $canEdit = auth()->user()->canEditRequisitions();
                            $transportActivePayment = $req->activePayment('transport');
                            $airtimeActivePayment = $req->activePayment('airtime');
							$recipientPhones = $req->recipientPhoneNumbers();
                            $transportPayments = $req->latestPaymentsByRecipient('transport');
                            $airtimePayments = $req->latestPaymentsByRecipient('airtime');
                            $transportPayable = $req->payableRecipientBalances('transport');
                            $airtimePayable = $req->payableRecipientBalances('airtime');
                            $recipientCount = $req->recipientCount();
                        @endphp
                        <tr id="requisition-{{ $req->id }}" data-requisition-row class="hover:bg-panel-muted transition-colors align-top">
                            <td class="px-3 py-3 font-medium whitespace-nowrap">
                                {{ $req->requester->name ?? '—' }}
								@if ($recipientPhones !== [])
									<div class="text-xs font-normal text-ink-faint">{{ implode(', ', $recipientPhones) }} · Nawiri recipient(s)</div>
                                @endif
                                @if ($canEdit)
                                    <a href="{{ route('admin.requisitions.edit', $req) }}" class="inline-flex items-center gap-1 mt-1 text-xs font-medium text-brand-700 hover:text-brand-800">
                                        <x-icon name="file-text" size="11" />
                                        Edit
                                    </a>
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
                                        <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                            <form method="POST" action="{{ route('admin.requisitions.transport.approve', $req) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary"><x-icon name="check" size="13" />Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.requisitions.transport.decline', $req) }}" onsubmit="return confirm('Decline this transport request?')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-danger">Decline</button>
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
                                            {{ $transportPayment->requiresOtp() ? 'JamboPay OTP required' : ($transportPayment->status === 'submitted' ? 'JamboPay processing' : str_replace('_', ' ', ucfirst($transportPayment->status))) }}
                                            @if ($transportPayment->failure_reason)<div>{{ $transportPayment->failure_reason }}</div>@endif
                                        </div>
                                        <x-payment-authorization :payment="$transportPayment" />
                                    @endforeach
                                    @if ($transportActivePayment && $canPay && $transportPayable === [])
                                        <p class="mt-1 text-xs text-amber-700">Checking automatically. Do not pay again.</p>
                                    @elseif ($req->transport_status === 'approved' && $transportPayable !== [] && $canPay)
										<form method="POST" action="{{ route('admin.requisitions.transport.pay', $req) }}" class="flex flex-wrap items-center gap-1 mt-2">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-pay whitespace-normal text-left">
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
                                        <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                            <form method="POST" action="{{ route('admin.requisitions.airtime.approve', $req) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary"><x-icon name="check" size="13" />Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.requisitions.airtime.decline', $req) }}" onsubmit="return confirm('Decline this airtime request?')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-danger">Decline</button>
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
                                            {{ $airtimePayment->requiresOtp() ? 'JamboPay OTP required' : ($airtimePayment->status === 'submitted' ? 'JamboPay processing' : str_replace('_', ' ', ucfirst($airtimePayment->status))) }}
                                            @if ($airtimePayment->failure_reason)<div>{{ $airtimePayment->failure_reason }}</div>@endif
                                        </div>
                                        <x-payment-authorization :payment="$airtimePayment" />
                                    @endforeach
                                    @if ($airtimeActivePayment && $canPay && $airtimePayable === [])
                                        <p class="mt-1 text-xs text-amber-700">Checking automatically. Do not pay again.</p>
                                    @elseif ($req->airtime_status === 'approved' && $airtimePayable !== [] && $canPay)
										<form method="POST" action="{{ route('admin.requisitions.airtime.pay', $req) }}" class="flex flex-wrap items-center gap-1 mt-2">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-pay whitespace-normal text-left">
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
                            <td colspan="10">
                                @if ($activeFilterCount > 0)
                                    <x-empty-state title="No requisitions match these filters." message="Try another date or requester, or clear the filters.">
                                        <a href="{{ route('admin.requisitions.index') }}" class="btn btn-secondary btn-sm">Clear filters</a>
                                    </x-empty-state>
                                @else
                                    <x-empty-state title="No requisitions logged yet." message="Requests submitted by RMs and staff appear here for approval." />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $requisitions->links() }}
        </div>

        @if ($requisitions->getCollection()->contains(fn ($requisition) => $requisition->activePayment('transport') || $requisition->activePayment('airtime')))
            <div
                id="payment-update-banner"
                class="fixed top-20 left-1/2 -translate-x-1/2 z-50 hidden items-center gap-3 rounded-full bg-brand-800 text-white text-sm px-5 py-3 shadow-lg shadow-brand-900/30"
            >
                <span>A payment status changed.</span>
                <button type="button" data-requisition-refresh class="px-3 py-1 rounded-full bg-white/15 hover:bg-white/25 font-medium transition-colors">
                    Refresh
                </button>
                <button type="button" onclick="document.getElementById('payment-update-banner').classList.add('hidden')" class="text-white/60 hover:text-white">
                    <x-icon name="x" size="14" />
                </button>
            </div>
        @endif
        </div>
    </div>
</x-layout>
