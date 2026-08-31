<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $ministry->name }} - Westport Industrial City</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            :title="$ministry->name"
            :subtitle="number_format($overall->submissions).' '.\Illuminate\Support\Str::plural('submission', $overall->submissions).' across '.$departments->count().' state departments. Tap a card to see its institutions.'"
            :back="route('ministries.index')"
            back-label="Back to By Ministry"
        />

        <div class="mb-6 bg-white border border-neutral-200 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-neutral-400 mb-2">Recorded Quantities</p>
            <div class="text-2xl font-bold text-[#0f7a3d]">
                <x-entity-quantity :row="$overall" />
            </div>
        </div>

        @if ($departments->isEmpty())
            <div class="bg-white border border-neutral-200 rounded-xl p-8 text-center text-neutral-400">
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
    </div>
</body>
</html>
