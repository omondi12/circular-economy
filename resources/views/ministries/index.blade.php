<x-layout title="Ministries">
        <x-page-header
            title="Ministries"
            :subtitle="$ministries->where('submissions', '>', 0)->count().' of '.$totalMinistries.' ministries (including the Presidency and Council of Governors) have submitted collections. Search matches ministry, state department and institution names.'"
        />

        <form method="GET" class="mb-4">
            <div class="relative max-w-md">
                <input
                    type="text" name="q" value="{{ $search }}"
                    placeholder="Search by ministry, department or institution…"
                    class="w-full rounded-lg border border-border bg-white pl-4 pr-24 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                >
                <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 px-3 py-1.5 rounded-md bg-brand-700 text-white text-xs font-medium hover:bg-brand-800 transition-colors">
                    Search
                </button>
            </div>
            @if ($search)
                <a href="{{ route('ministries.index') }}" class="inline-block mt-2 text-xs text-ink-faint hover:text-ink-muted">
                    Clear search ({{ $ministries->count() }} match{{ $ministries->count() === 1 ? '' : 'es' }})
                </a>
            @endif
        </form>

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gold-50 text-left text-ink-muted">
                    <tr>
                        <th class="px-4 py-2 font-medium">Ministry</th>
                        <th class="px-4 py-2 font-medium">Coordinator</th>
                        <th class="px-4 py-2 font-medium text-right">Submissions</th>
                        <th class="px-4 py-2 font-medium text-right">Recorded Quantities</th>
                        <th class="px-4 py-2 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($ministries as $row)
                        <tr class="hover:bg-panel-muted transition-colors {{ $row['submissions'] === 0 ? 'text-ink-faint' : '' }}">
                            <td class="px-4 py-3 font-medium {{ $row['submissions'] === 0 ? 'text-ink-faint font-normal' : '' }} max-w-md">
                                <div class="line-clamp-2">{{ $row['name'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-ink-muted">
                                @if ($row['coordinator'])
                                    {{ $row['coordinator'] }}
                                @else
                                    <span class="text-ink-faint">Unassigned</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-ink-faint">{{ number_format($row['submissions']) }}</td>
                            <td class="px-4 py-3 text-right font-medium">
                                @if ($row['submissions'] > 0)
                                    <x-entity-quantity :row="(object) $row" />
                                @else
                                    <span class="text-ink-faint">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('ministries.show', $row['id']) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-brand-50 text-brand-800 text-xs font-medium hover:bg-brand-100 transition-colors">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-ink-faint">No ministries match "{{ $search }}".</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</x-layout>
