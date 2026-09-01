@props(['collections'])

<div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-panel-muted text-left text-ink-faint text-[11px] font-mono uppercase tracking-wider">
            <tr>
                <th class="px-4 py-2.5 font-medium">{{ __('Entity') }}</th>
                <th class="px-4 py-2.5 font-medium">{{ __('Lot / Category') }}</th>
                <th class="px-4 py-2.5 font-medium">{{ __('Contact') }}</th>
                <th class="px-4 py-2.5 font-medium text-right">{{ __('Quantity') }}</th>
                <th class="px-4 py-2.5 font-medium">{{ __('Date') }}</th>
                <th class="px-4 py-2.5 font-medium">{{ __('Action') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border">
            @forelse ($collections as $collection)
                <tr class="hover:bg-panel-muted transition-colors">
                    <td class="px-4 py-3 font-medium max-w-xs">
                        <div class="line-clamp-2">{{ $collection->entity_name }}</div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if ($collection->isLegacyMaterialEntry())
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-panel-high text-ink-faint text-xs">{{ __('Legacy') }}</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full {{ $collection->lot === 1 ? 'bg-brand-50 text-brand-800' : 'bg-gold-50 text-gold-700' }} text-xs font-medium">
                                {{ \App\Support\WasteCategories::shortLotLabel($collection->lot) }}
                            </span>
                            <span class="block text-xs text-ink-faint mt-0.5">
                                {{ $collection->categoryLabel() }}{{ $collection->subcategoryLabel() ? ' – '.$collection->subcategoryLabel() : '' }}
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-ink-muted">{{ $collection->contact_person_name }}</td>
                    <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap font-medium text-ink">
                        @if ($collection->isLegacyMaterialEntry())
                            {{ number_format($collection->totalKg(), 1) }} kg
                        @else
                            {{ number_format($collection->quantity, 1) }} {{ $collection->unitLabel() }}
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-ink-muted">{{ $collection->collection_date->format('d M Y') }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="{{ route('collections.show', $collection) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-brand-50 text-brand-800 text-xs font-medium hover:bg-brand-100 transition-colors">
                            {{ __('View') }}
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-ink-faint">
                        <x-icon name="inbox" class="text-xl mb-1.5 block" />
                        {{ __('No submissions match these filters.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $collections->links() }}
</div>
