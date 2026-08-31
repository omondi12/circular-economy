<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feasibility Study - AMAC Circular Economy Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            title="Feasibility Study"
            :subtitle="number_format($totalSubmissions).' collections recorded by RMs. Browse by who submitted it, what it was, or which government body it was recorded against.'"
        />

        @php
            $tabs = [
                'agent' => 'By Agent (RM)',
                'materials' => 'By Materials',
                'ministries' => 'By Ministries',
                'state-corporation' => 'By State Corporation',
            ];
        @endphp

        <div class="inline-flex flex-wrap rounded-lg border border-neutral-200 bg-white p-1 text-sm mb-4">
            @foreach ($tabs as $key => $label)
                <a
                    href="{{ route('feasibility-study.index', ['view' => $key]) }}"
                    @class([
                        'px-3 py-1.5 rounded-md font-medium transition-colors whitespace-nowrap',
                        'bg-[#0f7a3d] text-white' => $view === $key,
                        'text-neutral-500 hover:text-neutral-900' => $view !== $key,
                    ])
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if ($view === 'state-corporation' && $rows->sum('submissions') === 0)
            <div class="mb-4 rounded-lg bg-[#c98500]/10 border border-[#c98500]/30 text-[#8a5c00] text-sm px-4 py-3">
                No submissions reference a State Corporation yet - that's recorded through the Account Manager
                availability survey (Level 4), which isn't wired up to the RM form yet.
            </div>
        @endif

        <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#f7edd6] text-left text-neutral-600">
                    <tr>
                        <th class="px-4 py-2 font-medium">
                            {{ $view === 'agent' ? 'Relationship Manager' : ($view === 'materials' ? 'Material' : ($view === 'ministries' ? 'Ministry' : 'State Corporation')) }}
                        </th>
                        <th class="px-4 py-2 font-medium text-right">Submissions</th>
                        <th class="px-4 py-2 font-medium text-right">Recorded Quantities</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-neutral-50 transition-colors">
                            <td class="px-4 py-3 font-medium max-w-md">
                                <div class="line-clamp-2">{{ $view === 'agent' ? $row->relationship_manager : $row['label'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-500">
                                {{ number_format($view === 'agent' ? $row->submissions : $row['submissions']) }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium">
                                @if ($view === 'materials')
                                    @forelse ($row['units'] as $u)
                                        <div class="tabular-nums">{{ number_format($u['quantity'], 1) }} {{ $u['label'] }}</div>
                                    @empty
                                        <span class="text-neutral-300">—</span>
                                    @endforelse
                                @else
                                    <x-entity-quantity :row="$view === 'agent' ? $row : (object) $row" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-neutral-400">No data yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
