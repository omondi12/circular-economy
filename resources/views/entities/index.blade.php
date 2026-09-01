<x-layout title="Participating Entities">
    <x-page-header
        title="Participating Entities"
        :subtitle="number_format($entities->count()).' ministries, counties and commissions have submitted collections. Tap a row to see its submissions.'"
    />

    <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gold-50 text-left text-ink-muted text-[11px] font-mono uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-2.5 font-medium">{{ __('Ministry / County / Commission') }}</th>
                    <th class="px-4 py-2.5 font-medium text-right">{{ __('Submissions') }}</th>
                    <th class="px-4 py-2.5 font-medium text-right">{{ __('Recorded Quantities') }}</th>
                    <th class="px-4 py-2.5 font-medium">{{ __('Last Collection') }}</th>
                    <th class="px-4 py-2.5 font-medium">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($entities as $row)
                    <tr class="hover:bg-panel-muted transition-colors">
                        <td class="px-4 py-3 font-medium max-w-sm">
                            <div class="line-clamp-2">{{ $row->entity_name }}</div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-ink-faint">{{ number_format($row->submissions) }}</td>
                        <td class="px-4 py-3 text-right font-medium"><x-entity-quantity :row="$row" /></td>
                        <td class="px-4 py-3 whitespace-nowrap text-ink-muted">{{ \Illuminate\Support\Carbon::parse($row->last_collection_date)->format('d M Y') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('collections.index', ['entity' => $row->entity_name]) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-brand-50 text-brand-800 text-xs font-medium hover:bg-brand-100 transition-colors">
                                {{ __('View') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-ink-faint">{{ __('No entities recorded yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
