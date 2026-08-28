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

            {{-- Lot / Category / Subcategory / Unit / Quantity --}}
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
                        Main Category <span class="text-red-500 ml-1">*</span>
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

                <div id="subcategory-row" class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-neutral-200">
                    <label for="subcategory" class="bg-[#f7edd6] px-4 py-3 text-sm font-semibold text-neutral-700 flex items-center">
                        Subcategory <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <select id="subcategory" name="subcategory" onchange="onSubcategoryChange()"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-neutral-900" disabled>
                            <option value="">Select a category first…</option>
                        </select>
                        @error('subcategory')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-neutral-200">
                    <label for="unit" class="bg-[#f7edd6] px-4 py-3 text-sm font-semibold text-neutral-700 flex items-center">
                        Unit of Measure <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <select id="unit" name="unit" required
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-neutral-900" disabled>
                            <option value="">Select a category first…</option>
                        </select>
                        @error('unit')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-neutral-200">
                    <label for="quantity" class="bg-[#f7edd6] px-4 py-3 text-sm font-semibold text-neutral-700 flex items-center">
                        Quantity <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <input
                            type="number" step="0.01" min="0.01" id="quantity" name="quantity" value="{{ old('quantity') }}" required
                            class="w-40 border-0 focus:ring-0 text-sm py-1.5 px-0 text-neutral-900 placeholder:text-neutral-300"
                        >
                        @error('quantity')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <x-form-field label="Description" name="description" :value="old('description')" />
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
        const UNIT_LABELS = @json(\App\Support\WasteCategories::UNIT_LABELS);
        const oldLot = @json(old('lot'));
        const oldCategory = @json(old('category'));
        const oldSubcategory = @json(old('subcategory'));
        const oldUnit = @json(old('unit'));

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

        function currentLot() {
            return LOTS[document.getElementById('lot').value];
        }

        function currentCategory() {
            const lot = currentLot();
            const catKey = document.getElementById('category').value;
            return lot ? lot.categories[catKey] : null;
        }

        function fillSelect(select, options, placeholder) {
            select.innerHTML = '';
            select.appendChild(new Option(placeholder, ''));
            options.forEach(([value, label]) => select.appendChild(new Option(label, value)));
            select.disabled = options.length === 0;
        }

        function onLotChange() {
            const lot = currentLot();
            const categorySelect = document.getElementById('category');
            const subcatRow = document.getElementById('subcategory-row');
            const subcatSelect = document.getElementById('subcategory');

            if (!lot) {
                fillSelect(categorySelect, [], 'Select a lot first…');
                categorySelect.disabled = true;
                onCategoryChange();
                return;
            }

            fillSelect(categorySelect, Object.entries(lot.categories).map(([k, c]) => [k, c.label]), 'Select a category…');

            // Lot 2 has no subcategory level - hide the row entirely rather
            // than asking the RM to click through a pointless single option.
            if (lot.has_subcategories) {
                subcatRow.classList.remove('hidden');
                subcatSelect.required = true;
            } else {
                subcatRow.classList.add('hidden');
                subcatSelect.required = false;
                subcatSelect.value = '';
            }

            onCategoryChange();
        }

        function onCategoryChange() {
            const lot = currentLot();
            const category = currentCategory();
            const subcatSelect = document.getElementById('subcategory');

            if (!lot || !category) {
                fillSelect(subcatSelect, [], 'Select a category first…');
                populateUnits([]);
                return;
            }

            if (lot.has_subcategories) {
                fillSelect(subcatSelect, Object.entries(category.subcategories).map(([k, s]) => [k, s.label]), 'Select a subcategory…');
                populateUnits([]);
            } else {
                // No subcategory level - units come straight from the category.
                populateUnits(category.units);
            }
        }

        function onSubcategoryChange() {
            const category = currentCategory();
            const subKey = document.getElementById('subcategory').value;
            const sub = category && category.subcategories ? category.subcategories[subKey] : null;
            populateUnits(sub ? sub.units : []);
        }

        function populateUnits(unitKeys) {
            const unitSelect = document.getElementById('unit');
            fillSelect(unitSelect, unitKeys.map((u) => [u, UNIT_LABELS[u] || u]), unitKeys.length ? 'Select a unit…' : 'Select a subcategory first…');
            // Exactly one valid unit is the common case (e.g. Cars -> Units) -
            // auto-select it rather than making the RM click through a
            // dropdown with only one real option.
            if (unitKeys.length === 1) unitSelect.value = unitKeys[0];
        }

        // Re-select previous values on validation-error round trips.
        if (oldLot) {
            document.getElementById('lot').value = oldLot;
            onLotChange();
            if (oldCategory) {
                document.getElementById('category').value = oldCategory;
                onCategoryChange();
                if (oldSubcategory) {
                    document.getElementById('subcategory').value = oldSubcategory;
                    onSubcategoryChange();
                }
                if (oldUnit) document.getElementById('unit').value = oldUnit;
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
