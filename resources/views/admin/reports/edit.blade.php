<x-layout title="Edit Report">
        <x-page-header
            title="Edit Report"
            :subtitle="'For '.($report->client->name ?? 'an unknown client').' - logged '.$report->report_date->format('d M Y').'.'"
            :back="route('reports.index')"
            back-label="Back to Reports"
        />

        <form method="POST" action="{{ route('admin.reports.update', $report) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="rm_id" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        RM Name
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <select id="rm_id" name="rm_id" class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink">
                            <option value="">— Not sure / not listed —</option>
                            @foreach ($rms as $rm)
                                <option value="{{ $rm->id }}" @selected(old('rm_id', $report->rm_id) == $rm->id)>{{ $rm->name }}</option>
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
                            value="{{ old('report_date', $report->report_date->toDateString()) }}"
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
                                <option value="{{ $type }}" @selected(old('engagement_type', $report->engagement_type) === $type)>{{ $type }}</option>
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
                            type="text" id="contact_person" name="contact_person" value="{{ old('contact_person', $report->contact_person) }}"
                            placeholder="e.g. Jane Doe, Procurement Manager"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink placeholder:text-ink-faint"
                        >
                        @error('contact_person')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="contact_person_phone" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Contact Person Phone
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <input
                            type="text" id="contact_person_phone" name="contact_person_phone" value="{{ old('contact_person_phone', $report->contact_person_phone) }}"
                            placeholder="e.g. 0712 345 678"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink placeholder:text-ink-faint"
                        >
                        @error('contact_person_phone')
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
                        >{{ old('outcome', $report->outcome) }}</textarea>
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
                                <option value="{{ $stage }}" @selected(old('current_stage', $report->current_stage) === $stage)>{{ $stage }}</option>
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
                            type="text" id="next_action" name="next_action" value="{{ old('next_action', $report->next_action) }}"
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
                            type="date" id="follow_up_date" name="follow_up_date" value="{{ old('follow_up_date', $report->follow_up_date?->toDateString()) }}"
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
                        >{{ old('comments', $report->comments) }}</textarea>
                        @error('comments')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm shadow-brand-900/20">
                    Save Changes
                </button>
            </div>
        </form>
</x-layout>
