<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Materials Breakdown - AMAC Circular Economy Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            title="Materials Breakdown"
            :subtitle="number_format($totalKg, 1).' kg collected across '.count($byMaterial).' material types.'"
        />

        <div class="bg-white border border-neutral-200 rounded-xl p-5 shadow-sm mb-6">
            @if ($byMaterial->sum('kg') == 0)
                <p class="text-sm text-neutral-400">No collections recorded yet.</p>
            @else
                <div class="space-y-3">
                    @php $maxKg = $byMaterial->max('kg'); @endphp
                    @foreach ($byMaterial as $row)
                        <x-material-bar :label="$row['label']" :kg="$row['kg']" :max-kg="$maxKg" />
                    @endforeach
                </div>
            @endif
        </div>

        <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#f7edd6] text-left text-neutral-600">
                    <tr>
                        <th class="px-4 py-2 font-medium">Material</th>
                        <th class="px-4 py-2 font-medium text-right">Total Kg</th>
                        <th class="px-4 py-2 font-medium text-right">Share of Total</th>
                        <th class="px-4 py-2 font-medium text-right">Entities Reporting</th>
                        <th class="px-4 py-2 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($byMaterial as $row)
                        <tr class="hover:bg-neutral-50 transition-colors">
                            <td class="px-4 py-3 font-medium">{{ $row['label'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['kg'], 1) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-500">{{ number_format($row['share'], 1) }}%</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-500">{{ number_format($row['entities']) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('collections.index', ['material' => $row['key']]) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-[#0f7a3d]/10 text-[#0b5c2e] text-xs font-medium hover:bg-[#0f7a3d]/20 transition-colors">
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
