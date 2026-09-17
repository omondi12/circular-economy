<x-layout title="Facilitation" wide>
        <x-page-header
            title="Facilitation"
            subtitle="Daily transport and airtime requests across every RM and Supervisor."
            :back="route('dashboard')"
            back-label="Back to dashboard"
        />

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <x-stat-tile label="Total Requests" :value="number_format($stats['totalCount'])" icon="calendar" tone="teal" />
            <x-stat-tile label="Pending" :value="number_format($stats['pendingCount'])" icon="inbox" tone="rose" />
            <x-stat-tile label="Total Requested" :value="'KES '.number_format($stats['totalRequested'], 0)" hint="Transport + airtime" icon="scale" tone="gold" />
            <x-stat-tile label="Total Paid" :value="'KES '.number_format($stats['totalPaid'], 0)" icon="circle-check" tone="green" />
            <x-stat-tile label="Outstanding" :value="'KES '.number_format($stats['totalBalance'], 0)" hint="Approved/requested but not yet paid" icon="alert-triangle" tone="violet" />
        </div>

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Name</th>
                        <th class="px-3 py-2 font-medium">Institution</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Working Day</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Transport Requested</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Transport Approved By</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Transport Paid</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Transport Balance</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Airtime Requested</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Airtime Approved By</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Airtime Paid</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Airtime Balance</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Total for Day</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Cumulative</th>
                        <th class="px-3 py-2 font-medium whitespace-nowrap">Days Facilitated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($requisitions as $req)
                        @php $totals = $requesterTotals[$req->requester_id] ?? ['days' => 0, 'cumulative' => 0]; @endphp
                        <tr class="hover:bg-panel-muted transition-colors align-top">
                            <td class="px-3 py-3 font-medium whitespace-nowrap">{{ $req->requester->name ?? '—' }}</td>
                            <td class="px-3 py-3 max-w-[180px]"><div class="line-clamp-2">{{ $req->institution_visiting }}</div></td>
                            <td class="px-3 py-3 whitespace-nowrap text-ink-muted">{{ $req->working_day->format('D, d M Y') }}</td>
                            <td class="px-3 py-3 whitespace-nowrap tabular-nums text-ink-muted">KES {{ number_format($req->transport_amount_requested, 0) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-ink-muted">{{ $req->transportApprovedBy->name ?? '—' }}</td>
                            <td class="px-3 py-3 whitespace-nowrap tabular-nums text-ink-muted">KES {{ number_format($req->transport_paid_amount, 0) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap tabular-nums text-ink-muted">KES {{ number_format($req->transportBalance(), 0) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap tabular-nums text-ink-muted">KES {{ number_format($req->airtime_amount_requested, 0) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-ink-muted">{{ $req->airtimeApprovedBy->name ?? '—' }}</td>
                            <td class="px-3 py-3 whitespace-nowrap tabular-nums text-ink-muted">KES {{ number_format($req->airtime_paid_amount, 0) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap tabular-nums text-ink-muted">KES {{ number_format($req->airtimeBalance(), 0) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium tabular-nums">KES {{ number_format($req->totalRequestedForDay(), 0) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap tabular-nums text-ink-muted">KES {{ number_format($totals['cumulative'], 0) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap tabular-nums text-ink-muted">{{ number_format($totals['days']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="px-4 py-8 text-center text-ink-faint">No requisitions logged yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $requisitions->links() }}
        </div>
</x-layout>
