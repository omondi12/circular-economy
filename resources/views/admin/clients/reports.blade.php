<x-layout title="{{ $client->name }} - Reports">
        <x-page-header
            :title="$client->name"
            :subtitle="'Daily engagement reports. '.number_format($reports->total()).' logged so far.'"
            :back="route('admin.assign-rms', ['view' => 'clients'])"
            back-label="Back to Clients"
        />

        {{-- New report form --}}
        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm mb-8">
            <div class="bg-gradient-to-r from-brand-700 to-brand-500 px-4 py-2.5">
                <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Log a Report</h2>
            </div>

            <form method="POST" action="{{ route('admin.clients.reports.store', $client) }}">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="rm_id" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        RM Name
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <select id="rm_id" name="rm_id" class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink">
                            <option value="">— Not sure / not listed —</option>
                            @foreach ($rms as $rm)
                                <option value="{{ $rm->id }}" @selected(old('rm_id', $client->assigned_rm_id) == $rm->id)>{{ $rm->name }}</option>
                            @endforeach
                        </select>
                        @error('rm_id')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="report_date" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Report Date <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <input
                            type="date" id="report_date" name="report_date" required
                            value="{{ old('report_date', now()->toDateString()) }}"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink"
                        >
                        @error('report_date')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="engagement_type" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Type of Engagement <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <select id="engagement_type" name="engagement_type" required class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink">
                            <option value="">Select…</option>
                            @foreach ($engagementTypes as $type)
                                <option value="{{ $type }}" @selected(old('engagement_type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('engagement_type')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="contact_person" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Contact Person
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <input
                            type="text" id="contact_person" name="contact_person" value="{{ old('contact_person') }}"
                            placeholder="e.g. Jane Doe, Procurement Manager"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink placeholder:text-ink-faint"
                        >
                        @error('contact_person')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="outcome" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Outcome / Feedback <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <textarea
                            id="outcome" name="outcome" required rows="3"
                            placeholder="What happened during the engagement?"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink placeholder:text-ink-faint"
                        >{{ old('outcome') }}</textarea>
                        @error('outcome')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="current_stage" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Current Stage <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <select id="current_stage" name="current_stage" required class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink">
                            <option value="">Select…</option>
                            @foreach ($stages as $stage)
                                <option value="{{ $stage }}" @selected(old('current_stage') === $stage)>{{ $stage }}</option>
                            @endforeach
                        </select>
                        @error('current_stage')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="next_action" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Next Action
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <input
                            type="text" id="next_action" name="next_action" value="{{ old('next_action') }}"
                            placeholder="e.g. Send programme information"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink placeholder:text-ink-faint"
                        >
                        @error('next_action')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="follow_up_date" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Follow-up Date
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <input
                            type="date" id="follow_up_date" name="follow_up_date" value="{{ old('follow_up_date') }}"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink"
                        >
                        @error('follow_up_date')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr]">
                    <label for="comments" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Comments
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <textarea
                            id="comments" name="comments" rows="2"
                            placeholder="Challenges, support needed, anything else"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink placeholder:text-ink-faint"
                        >{{ old('comments') }}</textarea>
                        @error('comments')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="px-4 py-4 flex justify-end border-t border-border">
                    <button type="submit" class="px-6 py-2.5 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm shadow-brand-900/20">
                        Log Report
                    </button>
                </div>
            </form>
        </div>

        {{-- Report history --}}
        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium">Date</th>
                        <th class="px-4 py-2 font-medium">RM</th>
                        <th class="px-4 py-2 font-medium">Type</th>
                        <th class="px-4 py-2 font-medium">Stage</th>
                        <th class="px-4 py-2 font-medium">Outcome</th>
                        <th class="px-4 py-2 font-medium">Follow-up</th>
                        <th class="px-4 py-2 font-medium">Logged By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($reports as $report)
                        <tr class="hover:bg-panel-muted transition-colors align-top">
                            <td class="px-4 py-3 whitespace-nowrap text-ink-muted">{{ $report->report_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $report->rm->name ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-ink-muted">{{ $report->engagement_type }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full bg-brand-50 text-brand-800 text-xs font-medium">{{ $report->current_stage }}</span>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <div class="line-clamp-3 text-ink-muted">{{ $report->outcome }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-ink-muted">
                                {{ $report->follow_up_date?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-ink-faint">{{ $report->createdBy->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-ink-faint">No reports logged for this client yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $reports->links() }}
        </div>
</x-layout>
