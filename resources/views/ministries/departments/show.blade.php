<x-layout title="{{ $department->name }}">
        <x-page-header
            :title="$department->name"
            :subtitle="$ministry->name.' - '.number_format($overall->submissions).' '.\Illuminate\Support\Str::plural('submission', $overall->submissions).' across '.$institutions->count().' institutions.'"
            :back="route('ministries.show', $ministry)"
            :back-label="'Back to '.$ministry->name"
        />

        <div class="mb-6 bg-panel border border-border rounded-xl p-5 shadow-sm">
            <p class="text-xs text-ink-faint mb-2">Recorded Quantities</p>
            <div class="text-2xl font-bold text-brand-700">
                <x-entity-quantity :row="$overall" />
            </div>
        </div>

        @if ($institutions->isEmpty())
            <div class="bg-panel border border-border rounded-xl p-8 text-center text-ink-faint mb-6">
                No institutions listed for this state department yet.
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                @foreach ($institutions as $inst)
                    <x-drill-card
                        :label="$inst['name']"
                        :count="$inst['submissions']"
                        :href="route('ministries.departments.show', [$ministry, $department, 'institution' => $inst['id'] ?? 'none'])"
                    />
                @endforeach
            </div>
        @endif

        @if ($filters['institution'])
            <div class="mb-4">
                <a href="{{ route('ministries.departments.show', [$ministry, $department]) }}" class="text-sm text-brand-700 hover:text-brand-800 font-medium">
                    &larr; Clear institution filter
                </a>
            </div>
        @endif

        <x-submissions-table :collections="$collections" />
</x-layout>
