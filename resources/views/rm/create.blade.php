<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Record a Collection - AMAC Circular Economy</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            title="Record a Collection"
            subtitle="Choose the Lot first, then pick the category it belongs to."
            :back="route('rm.dashboard')"
            back-label="Back to my dashboard"
        />

        <form method="POST" action="{{ route('rm.collections.store') }}" class="space-y-6">
            @csrf

            {{-- Entity & contact block --}}
            <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm">
                <div class="bg-gradient-to-r from-[#0f7a3d] to-[#1a9650] px-4 py-2.5">
                    <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Entity Details</h2>
                </div>
                <x-form-field label="Ministry / County / Commission" name="entity_name" required />
                <x-form-field label="State Department" name="state_department" />
                <x-form-field label="Department / Agency" name="department_agency" />
                <x-form-field label="Location / Office" name="location_office" />
                <x-form-field label="Contact Person Name" name="contact_person_name" required />
                <x-form-field label="Contact Person Number" name="contact_person_number" required />
            </div>

            {{-- Lot / Category / Quantity --}}
            <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm">
                <div class="bg-gradient-to-r from-[#0f7a3d] to-[#1a9650] px-4 py-2.5">
                    <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Lot &amp; Category</h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-neutral-200">
                    <label for="lot" class="bg-[#f7edd6] px-4 py-3 text-sm font-semibold text-neutral-700 flex items-center">
                        Lot <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <select id="lot" name="lot" required onchange="onLotChange()"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-neutral-900">
                            <option value="">Select a lot…</option>
                            @foreach ($lots as $lotKey => $lot)
                                <option value="{{ $lotKey }}" @selected(old('lot') == $lotKey)>{{ $lot['label'] }}</option>
                            @endforeach
                        </select>
                        @error('lot')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-neutral-200">
                    <label for="category" class="bg-[#f7edd6] px-4 py-3 text-sm font-semibold text-neutral-700 flex items-center">
                        Category <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <select id="category" name="category" required onchange="onCategoryChange()"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-neutral-900" disabled>
                            <option value="">Select a lot first…</option>
                        </select>
                        @error('category')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr]">
                    <label for="quantity" class="bg-[#f7edd6] px-4 py-3 text-sm font-semibold text-neutral-700 flex items-center">
                        Quantity <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex items-center gap-2">
                        <input
                            type="number" step="0.01" min="0.01" id="quantity" name="quantity" value="{{ old('quantity') }}" required
                            class="w-40 border-0 focus:ring-0 text-sm py-1.5 px-0 text-neutral-900 placeholder:text-neutral-300"
                        >
                        <span id="unit-label" class="text-sm text-neutral-400">—</span>
                        @error('quantity')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Date --}}
            <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm">
                <x-form-field label="Date" name="collection_date" type="date" required :value="now()->toDateString()" />
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 rounded-md bg-[#0f7a3d] hover:bg-[#0b5c2e] text-white text-sm font-semibold transition-colors shadow-sm shadow-[#0f7a3d]/30">
                    Submit Collection
                </button>
            </div>
        </form>
    </div>

    <script>
        const LOTS = @json($lots);
        const oldLot = @json(old('lot'));
        const oldCategory = @json(old('category'));

        function onLotChange() {
            const lotSelect = document.getElementById('lot');
            const categorySelect = document.getElementById('category');
            const lot = lotSelect.value;

            categorySelect.innerHTML = '';

            if (!lot || !LOTS[lot]) {
                categorySelect.disabled = true;
                categorySelect.innerHTML = '<option value="">Select a lot first…</option>';
                onCategoryChange();
                return;
            }

            categorySelect.disabled = false;
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Select a category…';
            categorySelect.appendChild(placeholder);

            Object.entries(LOTS[lot].categories).forEach(([key, meta]) => {
                const option = document.createElement('option');
                option.value = key;
                option.textContent = meta.label + ' (' + meta.unit + ')';
                option.dataset.unit = meta.unit;
                categorySelect.appendChild(option);
            });

            onCategoryChange();
        }

        function onCategoryChange() {
            const categorySelect = document.getElementById('category');
            const unitLabel = document.getElementById('unit-label');
            const selected = categorySelect.options[categorySelect.selectedIndex];
            unitLabel.textContent = selected && selected.dataset.unit ? selected.dataset.unit : '—';
        }

        // Re-select previous values on validation-error round trips.
        if (oldLot) {
            document.getElementById('lot').value = oldLot;
            onLotChange();
            if (oldCategory) {
                document.getElementById('category').value = oldCategory;
                onCategoryChange();
            }
        }
    </script>
</body>
</html>
