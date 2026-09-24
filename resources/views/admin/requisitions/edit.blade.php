<x-layout title="Edit Requisition">
        <x-page-header
            title="Edit Requisition"
            :subtitle="'Correcting '.($requisition->requester->name ?? 'this requester').'\'s submitted details. Approval/payment status isn\'t changed here - use Approve/Decline/Pay for that.'"
            :back="route('admin.requisitions.index')"
            back-label="Back to Requisitions"
        />

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm max-w-3xl">
            <div class="bg-gradient-to-r from-brand-700 to-brand-500 px-4 py-2.5">
                <h2 class="text-sm font-semibold text-white uppercase tracking-wide">{{ $requisition->requester->name ?? 'Requester' }}</h2>
            </div>

            <form method="POST" action="{{ route('admin.requisitions.update', $requisition) }}">
                @csrf
                @method('PUT')

                <div
                    class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border"
                    x-data="{ institutions: {{ old('institutions') ? json_encode(old('institutions')) : json_encode($institutions) }} }"
                >
                    <label class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-start sm:items-center">
                        Institution(s) Visiting <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center gap-2">
                        <template x-for="(institution, i) in institutions" :key="i">
                            <div class="flex items-center gap-2">
                                <input
                                    type="text" :name="'institutions[' + i + ']'" required
                                    x-model="institutions[i]"
                                    placeholder="e.g. Kenya Revenue Authority"
                                    class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink placeholder:text-ink-faint"
                                >
                                <button
                                    type="button" x-show="institutions.length > 1" x-cloak
                                    @click="institutions.splice(i, 1)"
                                    class="shrink-0 text-ink-faint hover:text-danger"
                                >
                                    <x-icon name="x" size="14" />
                                </button>
                            </div>
                        </template>
                        <button
                            type="button" @click="institutions.push('')"
                            class="self-start text-xs font-medium text-brand-700 hover:text-brand-800"
                        >
                            + Add another institution
                        </button>
                        @error('institutions')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                        @error('institutions.*')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="working_day" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Working Day <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <input
                            type="date" id="working_day" name="working_day" required
                            value="{{ old('working_day', $requisition->working_day->toDateString()) }}"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink"
                        >
                        @error('working_day')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="requested_date" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-start sm:items-center">
                        Requested Date
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <input
                            type="date" id="requested_date" name="requested_date" required
                            value="{{ old('requested_date', $requisition->transport_requested_at->toDateString()) }}"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink"
                        >
                        <p class="text-xs text-ink-faint">The date shown under "Transport Requested" / "Airtime Requested" in the table - the time of day is kept as-is.</p>
                        @error('requested_date')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div
                    class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border"
                    x-data="{ recipientPhones: {{ old('recipient_phone_numbers') ? json_encode(old('recipient_phone_numbers')) : json_encode($requisition->recipientPhoneNumbers() ?: ['']) }} }"
                >
                    <label class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-start sm:items-center">
                        Nawiri recipient number(s) <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center gap-2">
                        <template x-for="(phone, i) in recipientPhones" :key="i">
                            <div class="flex items-center gap-2">
                                <input
                                    type="tel" :name="'recipient_phone_numbers[' + i + ']'" required
                                    x-model="recipientPhones[i]"
                                    placeholder="e.g. 0712345678"
                                    class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink placeholder:text-ink-faint"
                                >
                                <button
                                    type="button" x-show="recipientPhones.length > 1" x-cloak
                                    @click="recipientPhones.splice(i, 1)"
                                    class="shrink-0 text-ink-faint hover:text-danger"
                                >
                                    <x-icon name="x" size="14" />
                                </button>
                            </div>
                        </template>
                        <button
                            type="button" @click="recipientPhones.push('')"
                            class="self-start text-xs font-medium text-brand-700 hover:text-brand-800"
                        >
                            + Add another recipient
                        </button>
                        @error('recipient_phone_numbers')
                            <p class="text-xs text-danger">{{ $message }}</p>
                        @enderror
                        @error('recipient_phone_numbers.*')
                            <p class="text-xs text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="transport_amount_requested" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Transport per recipient <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <input
                            type="number" step="1" min="0" id="transport_amount_requested" name="transport_amount_requested" required
                            value="{{ old('transport_amount_requested', (int) $requisition->transport_amount_requested) }}"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink"
                        >
                        @error('transport_amount_requested')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr]">
                    <label for="airtime_amount_requested" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Airtime per recipient <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <input
                            type="number" step="1" min="0" id="airtime_amount_requested" name="airtime_amount_requested" required
                            value="{{ old('airtime_amount_requested', (int) $requisition->airtime_amount_requested) }}"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink"
                        >
                        @error('airtime_amount_requested')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="px-4 py-4 flex justify-end gap-2 border-t border-border">
                    <a href="{{ route('admin.requisitions.index') }}" class="px-6 py-2.5 rounded-md border border-border text-sm font-medium hover:bg-panel-muted transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2.5 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm shadow-brand-900/20">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
</x-layout>
