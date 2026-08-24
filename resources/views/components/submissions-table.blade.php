@props(['collections'])

<div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-[#0f7a3d]/5 text-left text-neutral-500">
            <tr>
                <th class="px-4 py-2 font-medium">Entity</th>
                <th class="px-4 py-2 font-medium">Lot / Category</th>
                <th class="px-4 py-2 font-medium">Contact</th>
                <th class="px-4 py-2 font-medium text-right">Quantity</th>
                <th class="px-4 py-2 font-medium">Date</th>
                <th class="px-4 py-2 font-medium">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
            @forelse ($collections as $collection)
                <tr class="hover:bg-neutral-50 transition-colors">
                    <td class="px-4 py-3 font-medium max-w-xs">
                        <div class="line-clamp-2">{{ $collection->entity_name }}</div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if ($collection->isLegacyMaterialEntry())
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-500 text-xs">Legacy</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full {{ $collection->lot === 1 ? 'bg-[#0f7a3d]/10 text-[#0b5c2e]' : 'bg-[#c98500]/10 text-[#8a5c00]' }} text-xs font-medium">
                                {{ \App\Support\WasteCategories::shortLotLabel($collection->lot) }}
                            </span>
                            <span class="block text-xs text-neutral-500 mt-0.5">{{ $collection->categoryLabel() }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-neutral-500">{{ $collection->contact_person_name }}</td>
                    <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap font-medium">
                        @if ($collection->isLegacyMaterialEntry())
                            {{ number_format($collection->totalKg(), 1) }} kg
                        @else
                            {{ number_format($collection->quantity, 1) }} {{ $collection->unit }}
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-neutral-500">{{ $collection->collection_date->format('d M Y') }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="{{ route('collections.show', $collection) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-[#0f7a3d]/10 text-[#0b5c2e] text-xs font-medium hover:bg-[#0f7a3d]/20 transition-colors">
                            View
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-neutral-400">No submissions match these filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $collections->links() }}
</div>
