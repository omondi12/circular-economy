<x-layout title="Weight Breakdown">
    <x-page-header
        title="Weight Breakdown"
        :subtitle="number_format($totalKg, 1).' kg collected across '.count($byWeight).' categories. Anything recorded in litres, tonnes, m³, pieces, units, cartons or sets is tracked separately, not shown here.'"
    />

    <div class="bg-panel border border-border rounded-xl p-5 shadow-sm mb-6">
        @if ($byWeight->sum('kg') == 0)
            <p class="text-sm text-ink-faint">{{ __('No kg-denominated collections recorded yet.') }}</p>
        @else
            <div class="space-y-3">
                @php $maxKg = $byWeight->max('kg'); @endphp
                @foreach ($byWeight as $row)
                    <x-category-weight-bar :label="$row['label']" :kg="$row['kg']" :max-kg="$maxKg" :color="$row['color']" />
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gold-50 text-left text-ink-muted text-[11px] font-mono uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-2.5 font-medium">{{ __('Category') }}</th>
                    <th class="px-4 py-2.5 font-medium text-right">{{ __('Total Kg') }}</th>
                    <th class="px-4 py-2.5 font-medium text-right">{{ __('Share of Total') }}</th>
                    <th class="px-4 py-2.5 font-medium text-right">{{ __('Entities Reporting') }}</th>
                    <th class="px-4 py-2.5 font-medium">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @foreach ($byWeight as $row)
                    <tr class="hover:bg-panel-muted transition-colors">
                        <td class="px-4 py-3 font-medium text-ink">
                            <span class="w-2 h-2 rounded-full inline-block mr-1.5" style="background-color: {{ $row['color'] }}"></span>
                            {{ $row['label'] }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['kg'], 1) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-ink-faint">{{ number_format($row['share'], 1) }}%</td>
                        <td class="px-4 py-3 text-right tabular-nums text-ink-faint">{{ number_format($row['entities']) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($row['key'] !== 'legacy')
                                <a href="{{ route('collections.index', ['lot' => $row['lot'], 'category' => $row['category'], 'subcategory' => $row['subcategory']]) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-brand-50 text-brand-800 text-xs font-medium hover:bg-brand-100 transition-colors">
                                    {{ __('View') }}
                                </a>
                            @else
                                <span class="text-ink-faint">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layout>
