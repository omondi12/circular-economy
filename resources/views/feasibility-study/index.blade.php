<x-layout title="Feasibility Study">
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

        <div class="inline-flex flex-wrap rounded-lg border border-border bg-white p-1 text-sm mb-4">
            @foreach ($tabs as $key => $label)
                <a
                    href="{{ route('feasibility-study.index', ['view' => $key]) }}"
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

        @if ($view === 'state-corporation' && $rows->sum('submissions') === 0)
            <div class="mb-4 rounded-lg bg-gold-100 border border-gold-500/30 text-gold-700 text-sm px-4 py-3">
                No submissions reference a State Corporation yet - that's recorded through the Account Manager
                availability survey (Level 4), which isn't wired up to the RM form yet.
            </div>
        @endif

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gold-50 text-left text-ink-muted">
                    <tr>
                        <th class="px-4 py-2 font-medium">
                            {{ $view === 'agent' ? 'Relationship Manager' : ($view === 'materials' ? 'Material' : ($view === 'ministries' ? 'Ministry' : 'State Corporation')) }}
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
                                <div class="line-clamp-2">{{ $view === 'agent' ? $row->relationship_manager : $row['label'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-ink-faint">
                                {{ number_format($view === 'agent' ? $row->submissions : $row['submissions']) }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium">
                                @if ($view === 'materials')
                                    @forelse ($row['units'] as $u)
                                        <div class="tabular-nums">{{ number_format($u['quantity'], 1) }} {{ $u['label'] }}</div>
                                    @empty
                                        <span class="text-ink-faint">—</span>
                                    @endforelse
                                @else
                                    <x-entity-quantity :row="$view === 'agent' ? $row : (object) $row" />
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $viewHref = match ($view) {
                                        'agent' => route('collections.index', ['agent' => $row->relationship_manager]),
                                        'materials' => route('collections.index', ['lot' => $row['lot'], 'category' => $row['category']]),
                                        'ministries' => route('ministries.show', $row['id']),
                                        'state-corporation' => route('collections.index', ['state_corporation' => $row['id']]),
                                    };
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
