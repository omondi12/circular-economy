<x-layout title="Client Reports">
        <x-page-header
            title="Client Reports"
            :subtitle="number_format($totalReports).' daily engagement report(s) logged across all clients.'"
            :back="route('dashboard')"
            back-label="Back to dashboard"
        />

        {{-- Filters --}}
        <form method="GET" action="{{ route('reports.index') }}" class="mb-6 bg-panel border border-border rounded-xl p-4 shadow-sm flex flex-wrap gap-3 items-end">
            <div class="flex flex-col gap-1 flex-1 min-w-[200px]">
                <label for="q" class="text-xs text-ink-faint">Client</label>
                <input
                    type="text" id="q" name="q" value="{{ $filters['q'] }}"
                    placeholder="Search by client name…"
                    class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600"
                >
            </div>

            <div class="flex flex-col gap-1">
                <label for="rm_id" class="text-xs text-ink-faint">RM</label>
                <select id="rm_id" name="rm_id" class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">All RMs</option>
                    @foreach ($rms as $rm)
                        <option value="{{ $rm->id }}" @selected((string) $filters['rm_id'] === (string) $rm->id)>{{ $rm->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="current_stage" class="text-xs text-ink-faint">Stage</label>
                <select id="current_stage" name="current_stage" class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">All stages</option>
                    @foreach ($stages as $stage)
                        <option value="{{ $stage }}" @selected($filters['current_stage'] === $stage)>{{ $stage }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-medium transition-colors shadow-sm shadow-brand-900/20">
                    Filter
                </button>
                <a href="{{ route('reports.index') }}" class="px-4 py-2 rounded-md border border-border text-sm hover:bg-panel-muted transition-colors">
                    Reset
                </a>
            </div>
        </form>

        <div x-data="{ activeReport: null }">
            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-brand-50 text-left text-ink-faint">
                        <tr>
                            <th class="px-4 py-2 font-medium">Date</th>
                            <th class="px-4 py-2 font-medium">Client</th>
                            <th class="px-4 py-2 font-medium">RM</th>
                            <th class="px-4 py-2 font-medium">Type</th>
                            <th class="px-4 py-2 font-medium">Stage</th>
                            <th class="px-4 py-2 font-medium">Outcome</th>
                            <th class="px-4 py-2 font-medium">Follow-up</th>
                            <th class="px-4 py-2 font-medium">Logged By</th>
                            <th class="px-4 py-2 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($reports as $report)
                            <tr class="hover:bg-panel-muted transition-colors align-top">
                                <td class="px-4 py-3 whitespace-nowrap text-ink-muted">{{ $report->report_date->format('d M Y') }}</td>
                                <td class="px-4 py-3 font-medium max-w-xs">
                                    <div class="line-clamp-2">{{ $report->client->name ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $report->rm->name ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-ink-muted">{{ $report->engagement_type }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-brand-50 text-brand-800 text-xs font-medium whitespace-nowrap">{{ $report->current_stage }}</span>
                                </td>
                                <td class="px-4 py-3 max-w-xs">
                                    <div class="line-clamp-3 text-ink-muted">{{ $report->outcome }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-ink-muted">
                                    {{ $report->follow_up_date?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-ink-faint">{{ $report->createdBy->name ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="activeReport = {{ $report->id }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-brand-50 text-brand-800 text-xs font-medium hover:bg-brand-100 transition-colors">
                                            View
                                        </button>
                                        @if (auth()->check() && (auth()->user()->isAdmin() || $report->created_by === auth()->id()))
                                            <a href="{{ route('admin.reports.edit', $report) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-gold-50 text-gold-700 text-xs font-medium hover:bg-gold-100 transition-colors">
                                                Edit
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-ink-faint">No reports match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $reports->links() }}
            </div>

            {{-- Full-report view modals - one per row, shown by matching report id --}}
            @foreach ($reports as $report)
                <div
                    x-show="activeReport === {{ $report->id }}" x-cloak
                    @click.self="activeReport = null" @keydown.escape.window="activeReport = null"
                    class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
                >
                    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[85vh] overflow-y-auto">
                        <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-border">
                            <div>
                                <h3 class="font-semibold text-ink">{{ $report->client->name ?? 'Report' }}</h3>
                                <p class="text-xs text-ink-faint mt-0.5">{{ $report->report_date->format('d M Y') }} · {{ $report->engagement_type }}</p>
                            </div>
                            <button type="button" @click="activeReport = null" class="shrink-0 p-1.5 rounded-md text-ink-faint hover:text-ink hover:bg-panel-muted transition-colors">
                                <x-icon name="x" size="18" />
                            </button>
                        </div>
                        <div class="px-5 py-4 space-y-4 text-sm">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-xs text-ink-faint uppercase tracking-wide">RM</p>
                                    <p class="text-ink mt-0.5">{{ $report->rm->name ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-ink-faint uppercase tracking-wide">Stage</p>
                                    <p class="mt-0.5">
                                        <span class="inline-flex px-2 py-0.5 rounded-full bg-brand-50 text-brand-800 text-xs font-medium">{{ $report->current_stage }}</span>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-ink-faint uppercase tracking-wide">Contact Person</p>
                                    <p class="text-ink mt-0.5">{{ $report->contact_person ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-ink-faint uppercase tracking-wide">Contact Phone</p>
                                    <p class="text-ink mt-0.5">{{ $report->contact_person_phone ?? '—' }}</p>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs text-ink-faint uppercase tracking-wide mb-1">Outcome / Feedback</p>
                                <p class="text-ink whitespace-pre-line">{{ $report->outcome }}</p>
                            </div>

                            @if ($report->next_action)
                                <div>
                                    <p class="text-xs text-ink-faint uppercase tracking-wide mb-1">Next Action</p>
                                    <p class="text-ink">{{ $report->next_action }}</p>
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-xs text-ink-faint uppercase tracking-wide">Follow-up Date</p>
                                    <p class="text-ink mt-0.5">{{ $report->follow_up_date?->format('d M Y') ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-ink-faint uppercase tracking-wide">Logged By</p>
                                    <p class="text-ink mt-0.5">{{ $report->createdBy->name ?? '—' }}</p>
                                </div>
                            </div>

                            @if ($report->comments)
                                <div>
                                    <p class="text-xs text-ink-faint uppercase tracking-wide mb-1">Comments</p>
                                    <p class="text-ink whitespace-pre-line">{{ $report->comments }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
</x-layout>
