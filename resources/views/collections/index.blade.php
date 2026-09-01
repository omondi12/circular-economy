<x-layout title="Submissions">
        <x-page-header
            title="Submissions"
            :subtitle="number_format($collections->total()).' collections recorded. Search or filter to find one directly.'"
        />

        {{-- Filters --}}
        <form method="GET" action="{{ route('collections.index') }}" class="mb-6 bg-panel border border-border rounded-xl p-4 shadow-sm flex flex-wrap gap-3 items-end">
            <div class="flex flex-col gap-1 flex-1 min-w-[180px]">
                <label for="entity" class="text-xs text-ink-faint">Ministry / County / Commission</label>
                <input type="text" id="entity" name="entity" value="{{ $filters['entity'] }}" placeholder="e.g. Ministry of..."
                    class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600">
            </div>

            <div class="flex flex-col gap-1">
                <label for="lot" class="text-xs text-ink-faint">Lot</label>
                <select id="lot" name="lot" class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">All lots</option>
                    @foreach ($lots as $key => $lot)
                        <option value="{{ $key }}" @selected((string) $filters['lot'] === (string) $key)>{{ $lot['short_label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="material" class="text-xs text-ink-faint">Legacy material</label>
                <select id="material" name="material" class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">All materials</option>
                    @foreach ($materials as $key => $label)
                        <option value="{{ $key }}" @selected($filters['material'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="from" class="text-xs text-ink-faint">From</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] }}"
                    class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600">
            </div>

            <div class="flex flex-col gap-1">
                <label for="to" class="text-xs text-ink-faint">To</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] }}"
                    class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600">
            </div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-medium transition-colors shadow-sm shadow-brand-900/20">
                    Filter
                </button>
                <a href="{{ route('collections.index') }}" class="px-4 py-2 rounded-md border border-border text-sm hover:bg-panel-muted transition-colors">
                    Reset
                </a>
            </div>
        </form>

        <x-submissions-table :collections="$collections" />
</x-layout>
