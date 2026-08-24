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

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-neutral-200">
                    <span class="bg-[#f7edd6] px-4 py-3 text-sm font-semibold text-neutral-700 flex items-center">
                        Entity Type <span class="text-red-500 ml-1">*</span>
                    </span>
                    <div class="px-4 py-2.5 flex items-center gap-5">
                        <label class="flex items-center gap-1.5 text-sm text-neutral-700">
                            <input type="radio" name="entity_type" value="ministry" onchange="onEntityTypeChange()"
                                {{ old('entity_type', 'ministry') === 'ministry' ? 'checked' : '' }}
                                class="text-[#0f7a3d] focus:ring-[#0f7a3d]">
                            National Government Ministry
                        </label>
                        <label class="flex items-center gap-1.5 text-sm text-neutral-700">
                            <input type="radio" name="entity_type" value="other" onchange="onEntityTypeChange()"
                                {{ old('entity_type') === 'other' ? 'checked' : '' }}
                                class="text-[#0f7a3d] focus:ring-[#0f7a3d]">
                            County / Commission / Other
                        </label>
                    </div>
                </div>

                {{-- Ministry path: cascading Ministry -> State Department -> Institution --}}
                <div id="ministry-fields">
                    <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-neutral-200">
                        <label for="ministry_id" class="bg-[#f7edd6] px-4 py-3 text-sm font-semibold text-neutral-700 flex items-center">
                            Ministry <span class="text-red-500 ml-1">*</span>
                        </label>
                        <div class="px-4 py-2 flex flex-col justify-center">
                            <select id="ministry_id" name="ministry_id" onchange="onMinistryChange()"
                                class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-neutral-900">
                                <option value="">Select a ministry…</option>
                                @foreach ($ministries as $ministry)
                                    <option value="{{ $ministry['id'] }}" @selected(old('ministry_id') == $ministry['id'])>{{ $ministry['name'] }}</option>
                                @endforeach
                            </select>
                            @error('ministry_id')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-neutral-200">
                        <label for="state_department_id" class="bg-[#f7edd6] px-4 py-3 text-sm font-semibold text-neutral-700 flex items-center">
                            State Department
                        </label>
                        <div class="px-4 py-2 flex flex-col justify-center">
                            <select id="state_department_id" name="state_department_id" onchange="onDepartmentChange()" disabled
                                class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-neutral-900">
                                <option value="">Select a ministry first…</option>
                            </select>
                            @error('state_department_id')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-neutral-200">
                        <label for="institution_id" class="bg-[#f7edd6] px-4 py-3 text-sm font-semibold text-neutral-700 flex items-center">
                            Institution
                        </label>
                        <div class="px-4 py-2 flex flex-col justify-center">
                            <select id="institution_id" name="institution_id" disabled
                                class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-neutral-900">
                                <option value="">Select a state department first…</option>
                            </select>
                            @error('institution_id')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Non-ministry path: free text, as before --}}
                <div id="other-fields">
                    <x-form-field label="Ministry / County / Commission" name="entity_name" :value="old('entity_name')" />
                </div>

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

        const MINISTRIES = @json($ministries->keyBy('id'));
        const oldEntityType = @json(old('entity_type', 'ministry'));
        const oldMinistryId = @json(old('ministry_id'));
        const oldDepartmentId = @json(old('state_department_id'));
        const oldInstitutionId = @json(old('institution_id'));

        function onEntityTypeChange() {
            const type = document.querySelector('input[name="entity_type"]:checked')?.value ?? 'ministry';
            const ministryFields = document.getElementById('ministry-fields');
            const otherFields = document.getElementById('other-fields');
            const ministrySelect = document.getElementById('ministry_id');
            const entityNameInput = document.getElementById('entity_name');

            if (type === 'ministry') {
                ministryFields.classList.remove('hidden');
                otherFields.classList.add('hidden');
                ministrySelect.required = true;
                entityNameInput.required = false;
            } else {
                ministryFields.classList.add('hidden');
                otherFields.classList.remove('hidden');
                ministrySelect.required = false;
                entityNameInput.required = true;
            }
        }

        function onMinistryChange() {
            const ministryId = document.getElementById('ministry_id').value;
            const deptSelect = document.getElementById('state_department_id');
            const instSelect = document.getElementById('institution_id');

            deptSelect.innerHTML = '';
            const ministry = MINISTRIES[ministryId];

            if (!ministry) {
                deptSelect.disabled = true;
                deptSelect.innerHTML = '<option value="">Select a ministry first…</option>';
                onDepartmentChange();
                return;
            }

            deptSelect.disabled = false;
            deptSelect.appendChild(new Option('Select a state department…', ''));
            ministry.departments.forEach((dept) => {
                deptSelect.appendChild(new Option(dept.name, dept.id));
            });

            onDepartmentChange();
        }

        function onDepartmentChange() {
            const ministryId = document.getElementById('ministry_id').value;
            const deptId = document.getElementById('state_department_id').value;
            const instSelect = document.getElementById('institution_id');

            instSelect.innerHTML = '';
            const ministry = MINISTRIES[ministryId];
            const dept = ministry ? ministry.departments.find((d) => String(d.id) === String(deptId)) : null;

            if (!dept || dept.institutions.length === 0) {
                instSelect.disabled = true;
                instSelect.innerHTML = '<option value="">' + (dept ? 'No institutions listed' : 'Select a state department first…') + '</option>';
                return;
            }

            instSelect.disabled = false;
            instSelect.appendChild(new Option('Select an institution (optional)…', ''));
            dept.institutions.forEach((inst) => {
                instSelect.appendChild(new Option(inst.name, inst.id));
            });
        }

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

        onEntityTypeChange();

        if (oldEntityType === 'ministry' && oldMinistryId) {
            document.getElementById('ministry_id').value = oldMinistryId;
            onMinistryChange();
            if (oldDepartmentId) {
                document.getElementById('state_department_id').value = oldDepartmentId;
                onDepartmentChange();
                if (oldInstitutionId) {
                    document.getElementById('institution_id').value = oldInstitutionId;
                }
            }
        }
    </script>
</body>
</html>
