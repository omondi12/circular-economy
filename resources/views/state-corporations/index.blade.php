<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>State Corporations - Westport Industrial City</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            title="State Corporations"
            :subtitle="'From the official 348-corporation list. Phase 1 ('.number_format($phase1Count).') are the pilot clients already engaged; Phase 2 ('.number_format($phase2Count).') is everyone else.'"
        />

        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
            <div class="inline-flex rounded-lg border border-neutral-200 bg-white p-1 text-sm">
                @foreach ([null => 'All', 1 => 'Phase 1', 2 => 'Phase 2'] as $value => $label)
                    <a
                        href="{{ route('state-corporations.index', array_filter(['phase' => $value, 'q' => $filters['q']])) }}"
                        @class([
                            'px-3 py-1.5 rounded-md font-medium transition-colors',
                            'bg-[#0f7a3d] text-white' => $filters['phase'] === $value,
                            'text-neutral-500 hover:text-neutral-900' => $filters['phase'] !== $value,
                        ])
                    >
                        {{ $label }}
                        @if ($value === 1) ({{ number_format($phase1Count) }}) @endif
                        @if ($value === 2) ({{ number_format($phase2Count) }}) @endif
                    </a>
                @endforeach
            </div>

            <form method="GET" class="flex-1">
                @if ($filters['phase'])
                    <input type="hidden" name="phase" value="{{ $filters['phase'] }}">
                @endif
                <div class="relative max-w-md">
                    <input
                        type="text" name="q" value="{{ $filters['q'] }}"
                        placeholder="Search by name…"
                        class="w-full rounded-lg border border-neutral-200 bg-white pl-4 pr-24 py-2.5 text-sm text-neutral-900 placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-[#0f7a3d]/30 focus:border-[#0f7a3d]"
                    >
                    <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 px-3 py-1.5 rounded-md bg-[#0f7a3d] text-white text-xs font-medium hover:bg-[#0b5c2e] transition-colors">
                        Search
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#f7edd6] text-left text-neutral-600">
                    <tr>
                        <th class="px-4 py-2 font-medium">Name</th>
                        <th class="px-4 py-2 font-medium">Cluster</th>
                        <th class="px-4 py-2 font-medium">Class</th>
                        <th class="px-4 py-2 font-medium">Sub-Class</th>
                        <th class="px-4 py-2 font-medium">Phase</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($corporations as $corp)
                        <tr class="hover:bg-neutral-50 transition-colors">
                            <td class="px-4 py-3 font-medium max-w-md">
                                <div class="line-clamp-2">{{ $corp->name }}</div>
                            </td>
                            <td class="px-4 py-3 text-neutral-500">{{ $corp->cluster ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $corp->class ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $corp->subclass ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($corp->phase === 1)
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-[#0f7a3d]/10 text-[#0b5c2e] text-xs font-medium">Phase 1</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-500 text-xs font-medium">Phase 2</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-neutral-400">No state corporations match this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
