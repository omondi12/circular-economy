<x-layout title="Attach a service order">
    <x-page-header
        title="Attach a service order"
        subtitle="A client's order for Lot 2 disposal work (e.g. a hospital's Local Service Order). Upload the scanned order and tick the collections it covers."
        :back="route('admin.lso-lots.index')"
        back-label="Back to Lot 2 Revenue"
    />

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('admin.service-orders.create') }}" class="card mb-6 flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-64 flex-1">
            <label for="rm_id" class="field-label">RM handling the client</label>
            <select id="rm_id" name="rm_id" class="field-control" onchange="this.form.submit()">
                <option value="">Choose the RM</option>
                @foreach ($rms as $option)
                    <option value="{{ $option->id }}" @selected($rm?->id === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
        <noscript><button class="btn btn-secondary">Show collections</button></noscript>
    </form>

    @if ($rm)
        <form method="POST" action="{{ route('admin.service-orders.store') }}" enctype="multipart/form-data" class="flex flex-col gap-6"
              x-data="{ picked: {{ json_encode(old('collection_ids', [])) }}.map(Number), rates: {{ json_encode($collections->mapWithKeys(fn ($c) => [$c->id => (int) $c->lsoLot?->expected_revenue_minor])) }}, get total() { return this.picked.reduce((s, id) => s + (this.rates[id] || 0), 0) / 100 } }">
            @csrf
            <input type="hidden" name="rm_id" value="{{ $rm->id }}">

            <div class="card overflow-hidden">
                <x-form-field label="Order number" name="reference_number" placeholder="e.g. 0044" required />
                <x-form-field label="Client (as on the order)" name="customer_name" placeholder="e.g. Mwai Kibaki Referral Hospital" required />
                <x-form-field label="Order date" name="order_date" type="date" />
                <x-form-field label="Order total (KES)" name="amount" type="number" step="1" hint="As written on the order; checked against the collections at the contract rates." />
                <div class="grid grid-cols-1 border-b border-border sm:grid-cols-[220px_1fr]">
                    <label for="document" class="flex items-center bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted">Scanned order <span class="ml-1 text-danger" aria-hidden="true">*</span></label>
                    <div class="px-4 py-3">
                        <input type="file" id="document" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" required class="text-sm">
                        <p class="field-hint">PDF or photo, up to 10 MB. Kept private: only people who can see this RM can open it.</p>
                    </div>
                </div>
                <x-form-field label="Notes" name="notes" placeholder="e.g. ticket numbers, vote/A/C number" />
            </div>

            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-border px-4 py-3">
                    <h2 class="font-semibold">{{ $rm->name }}'s Lot 2 collections without an order</h2>
                    <p class="text-sm text-ink-muted">Ticked: <span class="font-semibold tabular-nums" x-text="'KES ' + total.toLocaleString()">KES 0</span> at contract rates</p>
                </div>
                @forelse ($collections as $c)
                    <label class="flex cursor-pointer items-start gap-3 border-b border-border px-4 py-3 last:border-b-0 hover:bg-panel-muted">
                        <input type="checkbox" name="collection_ids[]" value="{{ $c->id }}" x-model.number="picked" class="mt-1">
                        <span class="min-w-0 flex-1 text-sm">
                            <span class="font-medium">{{ $c->collection_date?->format('d M Y') }} · {{ number_format($c->quantity, 1) }} {{ $c->unitLabel() }} {{ $c->categoryLabel() }}</span>
                            <span class="block text-ink-muted">{{ $c->entity_name }}</span>
                            <span class="block line-clamp-1 text-xs text-ink-faint">{{ $c->description }}</span>
                        </span>
                        <span class="whitespace-nowrap text-sm tabular-nums">{{ $c->lsoLot?->expected_revenue_minor !== null ? 'KES '.number_format($c->lsoLot->expected_revenue_minor / 100) : 'Unpriced' }}</span>
                    </label>
                @empty
                    <p class="px-4 py-6 text-sm text-ink-muted">No Lot 2 collections without a service order for {{ $rm->name }}.</p>
                @endforelse
            </div>

            <div class="flex justify-end"><button type="submit" class="btn btn-primary">Attach service order</button></div>
        </form>
    @endif
</x-layout>
