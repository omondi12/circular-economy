<x-layout title="Orders">
    @php
        $kes = fn (int $m) => $m >= 100_000_000 ? 'KES '.number_format($m / 100 / 1_000_000, 2).'M' : 'KES '.number_format($m / 100);
        $full = fn (?int $m) => $m === null ? '—' : 'KES '.number_format($m / 100);
        $s = $summary;
        $maxMonth = max(1, collect($s['months'])->max('value'));
        $link = fn (array $change) => route('orders.index', array_filter(array_merge($filters, $change), fn ($v) => $v !== null && $v !== ''));
        $filtered = collect($filters)->filter()->isNotEmpty();
        $tone = fn (string $status) => match ($status) {
            'confirmed', 'fully_paid' => 'bg-brand-100 text-brand-800',
            'partly_confirmed', 'active' => 'bg-gold-100 text-gold-700',
            'rejected', 'cancelled' => 'bg-red-50 text-red-700',
            default => 'bg-panel-muted text-ink-muted',
        };
    @endphp

    <x-page-header
        title="Orders"
        :subtitle="number_format($s['count']).' order(s): sale LSOs (Lot 1) and disposal service orders (Lot 2). Agent 360 reads these from here.'"
        :back="route('dashboard')"
        back-label="Back to dashboard"
    >
        <x-slot:actions>
            <a href="{{ route('orders.export', array_filter($filters)) }}" class="btn btn-secondary">Download</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Headline: one row per lot --}}
    @foreach ([
        \App\Services\OrderBook::SALE => ['Lot 1', 'Sale orders (LSOs)', 'Collected', 'collected on the sale orders', 'Not yet collected', 'of the order value'],
        \App\Services\OrderBook::DISPOSAL => ['Lot 2', 'Disposal orders (service orders)', 'Confirmed', 'confirmed by Finance at the contract rates', 'Not yet confirmed', 'of the contract-rate revenue'],
    ] as $kind => [$lot, $title, $receivedLabel, $receivedHint, $outstandingLabel, $outstandingHint])
        @php $l = $lots[$kind]; @endphp
        <section class="mb-6" aria-label="{{ $lot }}: {{ $title }}">
            <h2 class="mb-3 flex items-baseline gap-2">
                <span class="rounded-md bg-brand-700 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-white">{{ $lot }}</span>
                <span class="font-medium text-ink">{{ $title }}</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <x-stat-tile label="Orders" icon="document" tone="violet" :value="number_format($l['count'])" :hint="$l['count'] ? $lot.' orders recorded in WIC' : 'None recorded yet'" :href="$link(['kind' => $kind, 'page' => null])" />
                <x-stat-tile label="Order value" icon="scale" tone="green" :value="$kes($l['value'])" :hint="$kind === 'sale' ? 'Stated on the LSOs (KES 0 until an auction sets it)' : 'Stated on the service orders'" />
                <x-stat-tile :label="$receivedLabel" icon="calendar" tone="teal" :value="$kes($l['received'])" :hint="ucfirst($receivedHint).'. Westport earned '.$kes($l['earned'])" />
                <x-stat-tile :label="$outstandingLabel" icon="building" tone="gold" :value="$kes($l['outstanding'])" :hint="ucfirst($outstandingHint)" />
            </div>
        </section>
    @endforeach

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        {{-- By month --}}
        <section class="lg:col-span-2 bg-panel border border-border rounded-xl p-5 shadow-sm">
            <h2 class="font-medium text-ink">Order value by month</h2>
            <div class="mt-4 flex h-40 items-end gap-1.5" role="img" aria-label="Order value for each of the last 12 months">
                @foreach ($s['months'] as $m)
                    <div class="group relative flex h-full flex-1 flex-col justify-end">
                        <span class="pointer-events-none absolute -top-6 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded bg-ink px-2 py-0.5 text-xs text-white opacity-0 group-hover:opacity-100">{{ $m['count'] }} · {{ $kes($m['value']) }}</span>
                        <div class="rounded-t bg-gradient-to-t from-brand-700 to-brand-400" style="height: {{ max(2, $m['value'] / $maxMonth * 100) }}%"></div>
                        <span class="mt-1 text-center text-xs text-ink-faint">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- By type --}}
        <section class="bg-panel border border-border rounded-xl p-5 shadow-sm">
            <h2 class="font-medium text-ink">By type of order</h2>
            <ul class="mt-3 flex flex-col gap-3">
                @foreach (\App\Services\OrderBook::KINDS as $key => $label)
                    @php $k = $s['kinds']->firstWhere('key', $key) ?? ['count' => 0, 'value' => 0, 'received' => 0]; @endphp
                    <li>
                        <a href="{{ $link(['kind' => $filters['kind'] === $key ? null : $key, 'page' => null]) }}" @class(['block rounded-lg p-3 border transition-colors', 'border-brand-500 bg-brand-50' => $filters['kind'] === $key, 'border-border hover:border-brand-300' => $filters['kind'] !== $key])>
                            <span class="flex justify-between text-sm"><span class="font-medium">{{ $label }}</span><span class="tabular-nums text-ink-muted">{{ $k['count'] }}</span></span>
                            <span class="mt-1 block text-lg font-display text-brand-700 tabular-nums">{{ $kes($k['value']) }}</span>
                            <span class="text-xs text-ink-faint">{{ $kes($k['received']) }} received / confirmed</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <section class="bg-panel border border-border rounded-xl p-5 shadow-sm">
            <h2 class="font-medium text-ink">By RM</h2>
            <ul class="mt-3 divide-y divide-border text-sm">
                @forelse ($s['rms'] as $r)
                    <li class="flex justify-between gap-3 py-2">
                        <a href="{{ $link(['rm_id' => $filters['rm_id'] === $r['key'] ? null : $r['key'], 'page' => null]) }}" class="text-brand-700 hover:underline">{{ $r['label'] }}</a>
                        <span class="tabular-nums text-ink-muted">{{ $r['count'] }} · {{ $kes($r['value']) }}</span>
                    </li>
                @empty
                    <li class="py-2 text-ink-faint">No orders yet.</li>
                @endforelse
            </ul>
        </section>
        <section class="bg-panel border border-border rounded-xl p-5 shadow-sm">
            <h2 class="font-medium text-ink">Top customers</h2>
            <ul class="mt-3 divide-y divide-border text-sm">
                @forelse ($s['clients'] as $c)
                    <li class="flex justify-between gap-3 py-2"><span class="truncate">{{ $c['label'] }}</span><span class="tabular-nums whitespace-nowrap text-ink-muted">{{ $c['count'] }} · {{ $kes($c['value']) }}</span></li>
                @empty
                    <li class="py-2 text-ink-faint">No orders yet.</li>
                @endforelse
            </ul>
            @if ($s['statuses']->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($s['statuses'] as $st)
                        <a href="{{ $link(['status' => $filters['status'] === $st['key'] ? null : $st['key'], 'page' => null]) }}" class="rounded-full px-3 py-1 text-xs font-medium {{ $tone($st['key']) }} {{ $filters['status'] === $st['key'] ? 'ring-2 ring-brand-500' : '' }}">{{ $st['label'] }} · {{ $st['count'] }}</a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('orders.index') }}" class="mb-6 bg-panel border border-border rounded-xl p-4 shadow-sm flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-56"><label for="q" class="field-label">Search</label><input id="q" name="q" value="{{ $filters['q'] }}" placeholder="Order number, customer, client, RM" class="field-control"></div>
        <div><label for="kind" class="field-label">Type</label><select id="kind" name="kind" class="field-control"><option value="">All</option>@foreach (\App\Services\OrderBook::KINDS as $k => $l)<option value="{{ $k }}" @selected($filters['kind'] === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="status" class="field-label">Status</label><select id="status" name="status" class="field-control"><option value="">All</option>@foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="rm_id" class="field-label">RM</label><select id="rm_id" name="rm_id" class="field-control"><option value="">All</option>@foreach ($rms as $id => $name)<option value="{{ $id }}" @selected($filters['rm_id'] === (string) $id)>{{ $name }}</option>@endforeach</select></div>
        <div><label for="from" class="field-label">From</label><input type="date" id="from" name="from" value="{{ $filters['from'] }}" class="field-control"></div>
        <div><label for="to" class="field-label">To</label><input type="date" id="to" name="to" value="{{ $filters['to'] }}" class="field-control"></div>
        <button class="btn btn-primary">Filter</button>
        @if ($filtered)<a href="{{ route('orders.index') }}" class="btn btn-secondary">Clear</a>@endif
    </form>

    {{-- The orders --}}
    <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
        <table data-stack class="w-full text-sm">
            <thead class="bg-brand-50 text-left text-ink-faint">
                <tr>
                    <th class="px-4 py-2 font-medium">Date</th>
                    <th class="px-4 py-2 font-medium">Order</th>
                    <th class="px-4 py-2 font-medium">Customer</th>
                    <th class="px-4 py-2 font-medium">RM</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium text-right">Value</th>
                    <th class="px-4 py-2 font-medium text-right">Received / confirmed</th>
                    <th class="px-4 py-2 font-medium text-right">Not yet received / confirmed</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($orders as $o)
                    <tr class="align-top hover:bg-panel-muted transition-colors">
                        <td class="px-4 py-3 whitespace-nowrap text-ink-faint">{{ $o['date']?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $o['reference'] }}</span>
                            <span class="block text-xs text-ink-faint">{{ $o['kind_label'] }}@if ($o['quantity']) · {{ $o['quantity'] }}@endif</span>
                            @if ($canOpenDocuments)
                                <a href="{{ $o['kind'] === 'sale' ? route('lso.document.show', $o['id']) : route('service-orders.document', $o['id']) }}" target="_blank" class="text-xs text-brand-700 hover:underline">Open document</a>
                            @endif
                        </td>
                        <td class="px-4 py-3 max-w-xs"><span class="line-clamp-2">{{ $o['customer'] }}</span><span class="block text-xs text-ink-faint line-clamp-1">{{ $o['client'] }}</span></td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $o['rm']?->name ?? '—' }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $tone($o['status']) }}">{{ \App\Services\OrderBook::statusLabel($o['status']) }}</span></td>
                        <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap">{{ $full($o['value']) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap">{{ $full($o['received']) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap">{{ $full($o['outstanding']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty-state :title="$filtered ? 'No orders match these filters.' : 'No orders yet.'" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
</x-layout>
