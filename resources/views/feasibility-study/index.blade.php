<x-layout title="Feasibility Study">
        <x-page-header
            title="Feasibility Study"
            :subtitle="number_format($totalSubmissions).' collections recorded by RMs. Browse by client or by ministry.'"
        />

        @php
            $tabs = [
                'state-corporation' => 'Clients',
                'ministries' => 'Ministries',
            ];
        @endphp

        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
            <div class="inline-flex flex-wrap rounded-lg border border-border bg-white p-1 text-sm">
                @foreach ($tabs as $key => $label)
                    <a
                        href="{{ route('feasibility-study.index', ['view' => $key, 'q' => $search]) }}"
                        @class([
                            'px-3 py-1.5 rounded-md font-medium transition-colors whitespace-nowrap',
                            'bg-brand-700 text-white' => $view === $key,
                            'text-ink-faint hover:text-ink' => $view !== $key,
                        ])
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <form method="GET" class="flex-1">
                <input type="hidden" name="view" value="{{ $view }}">
                <div class="relative max-w-md">
                    <input
                        type="text" name="q" value="{{ $search }}"
                        placeholder="Search by name…"
                        class="w-full rounded-lg border border-border bg-white pl-4 pr-24 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                    >
                    <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 px-3 py-1.5 rounded-md bg-brand-700 text-white text-xs font-medium hover:bg-brand-800 transition-colors">
                        Search
                    </button>
                </div>
            </form>
        </div>

        @if ($search)
            <a href="{{ route('feasibility-study.index', ['view' => $view]) }}" class="inline-block mb-4 text-xs text-ink-faint hover:text-ink-muted">
                Clear search ({{ $rows->count() }} match{{ $rows->count() === 1 ? '' : 'es' }})
            </a>
        @endif

        @if ($view === 'state-corporation' && $rows->sum('submissions') === 0)
            <div class="mb-4 rounded-lg bg-gold-100 border border-gold-500/30 text-gold-700 text-sm px-4 py-3">
                No submissions reference a client yet - that's recorded through the Account Manager
                availability survey (Level 4), which isn't wired up to the RM form yet.
            </div>
        @endif

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gold-50 text-left text-ink-muted">
                    <tr>
                        <th class="px-4 py-2 font-medium">
                            {{ $view === 'ministries' ? 'Ministry' : 'Client' }}
                        </th>
                        <th class="px-4 py-2 font-medium text-right">Submissions</th>
                        <th class="px-4 py-2 font-medium text-right">Recorded Quantities</th>
                        <th class="px-4 py-2 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-panel-muted transition-colors">
                            <td class="px-4 py-3 font-medium max-w-md">
                                <div class="line-clamp-2">{{ $row['label'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-ink-faint">
                                {{ number_format($row['submissions']) }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium">
                                <x-entity-quantity :row="(object) $row" />
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $viewHref = $view === 'ministries'
                                        ? route('ministries.show', $row['id'])
                                        : route('state-corporations.show', $row['id']);
                                @endphp
                                <a href="{{ $viewHref }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-brand-50 text-brand-800 text-xs font-medium hover:bg-brand-100 transition-colors">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-ink-faint">No data yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</x-layout>
