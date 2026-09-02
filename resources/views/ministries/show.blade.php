<x-layout title="{{ $ministry->name }}">
        <x-page-header
            :title="$ministry->name"
            :subtitle="number_format($overall->submissions).' '.\Illuminate\Support\Str::plural('submission', $overall->submissions).' across '.$departments->count().' state departments. Tap a card to see its institutions.'"
            :back="route('ministries.index')"
            back-label="Back to By Ministry"
        />

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div class="bg-panel border border-border rounded-xl p-5 shadow-sm">
                <p class="text-xs text-ink-faint mb-1.5">Assigned RM</p>
                <p class="text-sm font-medium text-ink">
                    @if ($ministry->assignedRm)
                        {{ $ministry->assignedRm->name }}
                    @else
                        <span class="text-ink-faint font-normal">Unassigned</span>
                    @endif
                </p>
            </div>
            <div class="bg-panel border border-border rounded-xl p-5 shadow-sm">
                <p class="text-xs text-ink-faint mb-2">Recorded Quantities</p>
                <div class="text-2xl font-bold text-brand-700">
                    <x-entity-quantity :row="$overall" />
                </div>
            </div>
        </div>

        @if ($departments->isEmpty())
            <div class="bg-panel border border-border rounded-xl p-8 text-center text-ink-faint">
                No state departments listed for this ministry yet.
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($departments as $dept)
                    <x-drill-card
                        :label="$dept['name']"
                        :count="$dept['submissions']"
                        :href="route('ministries.departments.show', [$ministry, $dept['id']])"
                    />
                @endforeach
            </div>
        @endif
</x-layout>
