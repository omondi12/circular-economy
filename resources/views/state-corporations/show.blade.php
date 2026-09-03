<x-layout title="{{ $corporation->name }}">
        <x-page-header
            :title="$corporation->name"
            :subtitle="number_format($overall->submissions).' '.\Illuminate\Support\Str::plural('submission', $overall->submissions).' recorded against this client.'"
            :back="route('state-corporations.index')"
            back-label="Back to Clients"
        />

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-panel border border-border rounded-xl p-5 shadow-sm">
                <p class="text-xs text-ink-faint mb-1.5">Classification</p>
                <p class="text-sm font-medium text-ink">{{ $corporation->classification ?? '—' }}</p>
            </div>
            <div class="bg-panel border border-border rounded-xl p-5 shadow-sm">
                <p class="text-xs text-ink-faint mb-1.5">Ministry</p>
                <p class="text-sm font-medium {{ ! $corporation->ministry && $corporation->ministryDisplay() === 'Independent' ? 'italic text-ink-faint' : 'text-ink' }}">
                    {{ $corporation->ministryDisplay() }}
                </p>
            </div>
            <div class="bg-panel border border-border rounded-xl p-5 shadow-sm">
                <p class="text-xs text-ink-faint mb-1.5">Assigned RM</p>
                <p class="text-sm font-medium text-ink">
                    @if ($corporation->assignedRm)
                        {{ $corporation->assignedRm->name }}
                    @else
                        <span class="text-ink-faint font-normal">Unassigned</span>
                    @endif
                </p>
            </div>
            <div class="bg-panel border border-border rounded-xl p-5 shadow-sm">
                <p class="text-xs text-ink-faint mb-1.5">Phase</p>
                <p class="text-sm font-medium text-ink">
                    @if ($corporation->phase === \App\Models\StateCorporation::PHASE_ONE)
                        <span class="inline-flex px-2 py-0.5 rounded-full bg-brand-50 text-brand-800 text-xs font-medium">Phase 1</span>
                    @else
                        <span class="inline-flex px-2 py-0.5 rounded-full bg-panel-high text-ink-faint text-xs font-medium">Phase 2</span>
                    @endif
                </p>
            </div>
        </div>

        @if ($corporation->cluster || $corporation->class || $corporation->subclass)
            <div class="mb-6 bg-panel border border-border rounded-xl p-5 shadow-sm">
                <p class="text-xs text-ink-faint mb-2">Official Classification (Annex I)</p>
                <div class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
                    <div><span class="text-ink-faint">Cluster:</span> <span class="font-medium text-ink">{{ $corporation->cluster ?? '—' }}</span></div>
                    <div><span class="text-ink-faint">Class:</span> <span class="font-medium text-ink">{{ $corporation->class ?? '—' }}</span></div>
                    <div><span class="text-ink-faint">Sub-Class:</span> <span class="font-medium text-ink">{{ $corporation->subclass ?? '—' }}</span></div>
                </div>
            </div>
        @endif

        <div class="mb-6 bg-panel border border-border rounded-xl p-5 shadow-sm">
            <p class="text-xs text-ink-faint mb-2">Recorded Quantities</p>
            <div class="text-2xl font-bold text-brand-700">
                <x-entity-quantity :row="$overall" />
            </div>
        </div>

        <x-submissions-table :collections="$collections" />
</x-layout>
