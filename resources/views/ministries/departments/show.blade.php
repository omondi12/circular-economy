<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $department->name }} - AMAC Circular Economy Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            :title="$department->name"
            :subtitle="$ministry->name.' - '.number_format($overall->submissions).' '.\Illuminate\Support\Str::plural('submission', $overall->submissions).' across '.$institutions->count().' institutions.'"
            :back="route('ministries.show', $ministry)"
            :back-label="'Back to '.$ministry->name"
        />

        <div class="mb-6 bg-white border border-neutral-200 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-neutral-400 mb-2">Recorded Quantities</p>
            <div class="text-2xl font-bold text-[#0f7a3d]">
                <x-entity-quantity :row="$overall" />
            </div>
        </div>

        @if ($institutions->isEmpty())
            <div class="bg-white border border-neutral-200 rounded-xl p-8 text-center text-neutral-400 mb-6">
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
                <a href="{{ route('ministries.departments.show', [$ministry, $department]) }}" class="text-sm text-[#0f7a3d] hover:text-[#0b5c2e] font-medium">
                    &larr; Clear institution filter
                </a>
            </div>
        @endif

        <x-submissions-table :collections="$collections" />
    </div>
</body>
</html>
