<x-layout title="Clients">
        <x-page-header
            title="Clients"
            :subtitle="'State corporations, all 47 counties, National Polytechnics, IEBC and the Government Printer. Phase 1 ('.number_format($phase1Count).') are the pilot clients already engaged; Phase 2 ('.number_format($phase2Count).') is everyone else.'"
        />

        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
            <div class="inline-flex rounded-lg border border-border bg-white p-1 text-sm">
                @foreach ([null => 'All', 1 => 'Phase 1', 2 => 'Phase 2'] as $value => $label)
                    <a
                        href="{{ route('state-corporations.index', array_filter(['phase' => $value, 'q' => $filters['q'], 'classification' => $filters['classification'], 'rm' => $filters['rm']])) }}"
                        @class([
                            'px-3 py-1.5 rounded-md font-medium transition-colors',
                            'bg-brand-700 text-white' => $filters['phase'] === $value,
                            'text-ink-faint hover:text-ink' => $filters['phase'] !== $value,
                        ])
                    >
                        {{ $label }}
                        @if ($value === 1) ({{ number_format($phase1Count) }}) @endif
                        @if ($value === 2) ({{ number_format($phase2Count) }}) @endif
                    </a>
                @endforeach
            </div>

            <form method="GET" class="flex flex-1 gap-2">
                @if ($filters['phase'])
                    <input type="hidden" name="phase" value="{{ $filters['phase'] }}">
                @endif
                <select
                    name="classification" onchange="this.form.submit()"
                    class="rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                >
                    <option value="">All classifications</option>
                    @foreach ($classifications as $c)
                        <option value="{{ $c }}" @selected($filters['classification'] === $c)>{{ $c }}</option>
                    @endforeach
                </select>
                <select
                    name="rm" onchange="this.form.submit()"
                    class="rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                >
                    <option value="">All RMs</option>
                    <option value="assigned" @selected($filters['rm'] === 'assigned')>Assigned (has an RM)</option>
                    <option value="unassigned" @selected($filters['rm'] === 'unassigned')>Unassigned</option>
                    @foreach ($rms as $rm)
                        <option value="{{ $rm->id }}" @selected((string) $filters['rm'] === (string) $rm->id)>{{ $rm->name }}</option>
                    @endforeach
                </select>
                <div class="relative flex-1 max-w-md">
                    <input
                        type="text" name="q" value="{{ $filters['q'] }}"
                        placeholder="Search by name…"
                        class="w-full rounded-lg border border-border bg-white pl-4 pr-24 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                    >
                    <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 px-3 py-1.5 rounded-md bg-brand-700 text-white text-xs font-medium hover:bg-brand-800 transition-colors">
                        Search
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gold-50 text-left text-ink-muted">
                    <tr>
                        <th class="px-4 py-2 font-medium">Name</th>
                        <th class="px-4 py-2 font-medium">Classification</th>
                        <th class="px-4 py-2 font-medium">Ministry</th>
                        <th class="px-4 py-2 font-medium">RM</th>
                        <th class="px-4 py-2 font-medium">Cluster</th>
                        <th class="px-4 py-2 font-medium">Class</th>
                        <th class="px-4 py-2 font-medium">Sub-Class</th>
                        <th class="px-4 py-2 font-medium">Phase</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($corporations as $corp)
                        <tr class="hover:bg-panel-muted transition-colors">
                            <td class="px-4 py-3 font-medium max-w-md">
                                <div class="line-clamp-2">{{ $corp->name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($corp->classification === 'State Corporation')
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-panel-high text-ink-faint text-xs font-medium whitespace-nowrap">{{ $corp->classification }}</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-gold-50 text-gold-800 text-xs font-medium whitespace-nowrap">{{ $corp->classification ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($corp->ministry)
                                    <span class="text-ink-faint">{{ $corp->ministry->name }}</span>
                                @elseif ($corp->ministryDisplay() === 'Independent')
                                    <span class="italic text-ink-faint">Independent</span>
                                @else
                                    <span class="text-ink-faint">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($corp->assignedRm)
                                    {{ $corp->assignedRm->name }}
                                @else
                                    <span class="text-ink-faint">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-ink-faint">{{ $corp->cluster ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-faint">{{ $corp->class ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-faint">{{ $corp->subclass ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($corp->phase === 1)
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-brand-50 text-brand-800 text-xs font-medium">Phase 1</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-panel-high text-ink-faint text-xs font-medium">Phase 2</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-ink-faint">No clients match this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</x-layout>
