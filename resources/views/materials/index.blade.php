<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Weight Breakdown - AMAC Circular Economy Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            title="Weight Breakdown"
            :subtitle="number_format($totalKg, 1).' kg collected across '.count($byWeight).' categories. Anything recorded in litres, tonnes, m³, pieces, units, cartons or sets is tracked separately, not shown here.'"
        />

        <div class="bg-white border border-neutral-200 rounded-xl p-5 shadow-sm mb-6">
            @if ($byWeight->sum('kg') == 0)
                <p class="text-sm text-neutral-400">No kg-denominated collections recorded yet.</p>
            @else
                <div class="space-y-3">
                    @php $maxKg = $byWeight->max('kg'); @endphp
                    @foreach ($byWeight as $row)
                        <x-category-weight-bar :label="$row['label']" :kg="$row['kg']" :max-kg="$maxKg" :color="$row['color']" />
                    @endforeach
                </div>
            @endif
        </div>

        <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#f7edd6] text-left text-neutral-600">
                    <tr>
                        <th class="px-4 py-2 font-medium">Category</th>
                        <th class="px-4 py-2 font-medium text-right">Total Kg</th>
                        <th class="px-4 py-2 font-medium text-right">Share of Total</th>
                        <th class="px-4 py-2 font-medium text-right">Entities Reporting</th>
                        <th class="px-4 py-2 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($byWeight as $row)
                        <tr class="hover:bg-neutral-50 transition-colors">
                            <td class="px-4 py-3 font-medium">
                                <span class="w-2 h-2 rounded-full inline-block mr-1.5" style="background-color: {{ $row['color'] }}"></span>
                                {{ $row['label'] }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['kg'], 1) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-500">{{ number_format($row['share'], 1) }}%</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-500">{{ number_format($row['entities']) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($row['key'] !== 'legacy')
                                    <a href="{{ route('collections.index', ['lot' => $row['lot'], 'category' => $row['category'], 'subcategory' => $row['subcategory']]) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-[#0f7a3d]/10 text-[#0b5c2e] text-xs font-medium hover:bg-[#0f7a3d]/20 transition-colors">
                                        View
                                    </a>
                                @else
                                    <span class="text-neutral-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
