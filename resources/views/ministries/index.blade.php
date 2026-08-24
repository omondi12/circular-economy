<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>By Ministry - AMAC Circular Economy Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            title="By Ministry"
            :subtitle="$ministries->where('submissions', '>', 0)->count().' of '.$ministries->count().' national government ministries have submitted collections. Tap a row to see its submissions.'"
        />

        <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#f7edd6] text-left text-neutral-600">
                    <tr>
                        <th class="px-4 py-2 font-medium">Ministry</th>
                        <th class="px-4 py-2 font-medium text-right">Submissions</th>
                        <th class="px-4 py-2 font-medium text-right">Recorded Quantities</th>
                        <th class="px-4 py-2 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($ministries as $row)
                        <tr class="hover:bg-neutral-50 transition-colors {{ $row['submissions'] === 0 ? 'text-neutral-400' : '' }}">
                            <td class="px-4 py-3 font-medium {{ $row['submissions'] === 0 ? 'text-neutral-400 font-normal' : '' }} max-w-md">
                                <div class="line-clamp-2">{{ $row['name'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-500">{{ number_format($row['submissions']) }}</td>
                            <td class="px-4 py-3 text-right font-medium">
                                @if ($row['submissions'] > 0)
                                    <x-entity-quantity :row="(object) $row" />
                                @else
                                    <span class="text-neutral-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('ministries.show', $row['id']) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-[#0f7a3d]/10 text-[#0b5c2e] text-xs font-medium hover:bg-[#0f7a3d]/20 transition-colors">
                                    View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
