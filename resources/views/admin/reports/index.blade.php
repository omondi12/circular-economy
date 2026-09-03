<x-layout title="Client Reports">
        <x-page-header
            title="Client Reports"
            :subtitle="number_format($totalReports).' daily engagement report(s) logged across all clients.'"
            :back="route('admin.dashboard')"
            back-label="Back to admin"
        />

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.reports.index') }}" class="mb-6 bg-panel border border-border rounded-xl p-4 shadow-sm flex flex-wrap gap-3 items-end">
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
                <a href="{{ route('admin.reports.index') }}" class="px-4 py-2 rounded-md border border-border text-sm hover:bg-panel-muted transition-colors">
                    Reset
                </a>
            </div>
        </form>

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
                                @if ($report->client)
                                    <a href="{{ route('admin.clients.reports.index', $report->client) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-brand-50 text-brand-800 text-xs font-medium hover:bg-brand-100 transition-colors">
                                        View
                                    </a>
                                @endif
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
</x-layout>
