<x-layout title="Record an LSO">
    <x-page-header
        title="Record an LSO"
        subtitle="Enter the details from the Local Service Order document and upload it as evidence."
        :back="route('rm.lsos.index')"
        back-label="Back to my LSOs"
    />

    <form method="POST" action="{{ route('rm.lsos.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
            <div class="bg-gradient-to-r from-brand-700 to-brand-500 px-4 py-2.5">
                <h2 class="text-sm font-semibold text-white uppercase tracking-wide">LSO Details</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                <label for="reference_number" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                    LSO Reference <span class="text-danger ml-1">*</span>
                </label>
                <div class="px-4 py-2 flex flex-col justify-center">
                    <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number') }}"
                        class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink" placeholder="e.g. LSO/2026/00123">
                    @error('reference_number')
                        <p class="text-xs text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                <label for="customer_name" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                    Customer / Company <span class="text-danger ml-1">*</span>
                </label>
                <div class="px-4 py-2 flex flex-col justify-center">
                    <input type="text" id="customer_name" name="customer_name" value="{{ old('customer_name') }}"
                        class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink" placeholder="Who was awarded the tender/service?">
                    @error('customer_name')
                        <p class="text-xs text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                <label for="customer_contact" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                    Customer Contact
                </label>
                <div class="px-4 py-2 flex flex-col justify-center">
                    <input type="text" id="customer_contact" name="customer_contact" value="{{ old('customer_contact') }}"
                        class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink" placeholder="Phone or email">
                    @error('customer_contact')
                        <p class="text-xs text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                <label for="ministry_id" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                    Related Ministry
                </label>
                <div class="px-4 py-2 flex flex-col justify-center">
                    <select id="ministry_id" name="ministry_id" class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink">
                        <option value="">Not applicable</option>
                        @foreach ($ministries as $ministry)
                            <option value="{{ $ministry->id }}" @selected(old('ministry_id') == $ministry->id)>{{ $ministry->name }}</option>
                        @endforeach
                    </select>
                    @error('ministry_id')
                        <p class="text-xs text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                <label for="original_amount" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                    Original LSO Value (KES) <span class="text-danger ml-1">*</span>
                </label>
                <div class="px-4 py-2 flex flex-col justify-center">
                    <input type="number" min="1" step="1" id="original_amount" name="original_amount" value="{{ old('original_amount') }}"
                        class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink" placeholder="Value shown on the LSO document">
                    @error('original_amount')
                        <p class="text-xs text-danger mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-ink-faint mt-1">This is the LSO's stated value - not money collected yet.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                <label for="issue_date" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                    LSO Issue Date <span class="text-danger ml-1">*</span>
                </label>
                <div class="px-4 py-2 flex flex-col justify-center">
                    <input type="date" id="issue_date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}"
                        class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink">
                    @error('issue_date')
                        <p class="text-xs text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                <label for="document" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                    LSO Document <span class="text-danger ml-1">*</span>
                </label>
                <div class="px-4 py-2.5 flex flex-col justify-center">
                    <input type="file" id="document" name="document" accept=".jpg,.jpeg,.png,.webp,.pdf"
                        class="w-full text-sm text-ink file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-800 file:text-sm file:font-medium">
                    @error('document')
                        <p class="text-xs text-danger mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-ink-faint mt-1">Photo or scan of the LSO. JPG, PNG, WEBP or PDF, up to 5MB. Kept private - only you and Finance/Admin can view it.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr]">
                <label for="notes" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                    Notes
                </label>
                <div class="px-4 py-2 flex flex-col justify-center">
                    <textarea id="notes" name="notes" rows="2" class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink" placeholder="Anything else worth recording">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="text-xs text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-700 text-white text-sm font-semibold hover:bg-brand-800 transition-colors shadow-sm">
                Record LSO
            </button>
        </div>
    </form>
</x-layout>
