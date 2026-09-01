<x-layout title="Record a Collection">
        <x-page-header
            title="Record a Collection"
            subtitle="Choose the Lot first, then pick the category it belongs to."
            :back="route('rm.dashboard')"
            back-label="Back to my dashboard"
        />

        <form method="POST" action="{{ route('rm.collections.store') }}" class="space-y-6">
            @csrf

            {{-- Entity & contact block --}}
            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <div class="bg-gradient-to-r from-brand-700 to-brand-500 px-4 py-2.5">
                    <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Entity Details</h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <span class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Entity Type <span class="text-danger ml-1">*</span>
                    </span>
                    <div class="px-4 py-2.5 flex flex-wrap items-center gap-x-5 gap-y-1.5">
                        <label class="flex items-center gap-1.5 text-sm text-ink-muted">
                            <input type="radio" name="entity_type" value="ministry" onchange="onEntityTypeChange()"
                                {{ old('entity_type', 'ministry') === 'ministry' ? 'checked' : '' }}
                                class="text-brand-700 focus:ring-brand-600">
                            National Government Ministry
                        </label>
                        <label class="flex items-center gap-1.5 text-sm text-ink-muted">
                            <input type="radio" name="entity_type" value="county" onchange="onEntityTypeChange()"
                                {{ old('entity_type') === 'county' ? 'checked' : '' }}
                                class="text-brand-700 focus:ring-brand-600">
                            County
                        </label>
                        <label class="flex items-center gap-1.5 text-sm text-ink-muted">
                            <input type="radio" name="entity_type" value="commission" onchange="onEntityTypeChange()"
                                {{ old('entity_type') === 'commission' ? 'checked' : '' }}
                                class="text-brand-700 focus:ring-brand-600">
                            Commission / Other
                        </label>
                    </div>
                </div>

                {{-- Ministry path: cascading Ministry -> State Department -> Institution --}}
                <div id="ministry-fields">
                    <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                        <label for="ministry_id" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                            Ministry <span class="text-danger ml-1">*</span>
                        </label>
                        <div class="px-4 py-2 flex flex-col justify-center">
                            <select id="ministry_id" name="ministry_id" onchange="onMinistryChange()"
                                class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink">
                                <option value="">Select a ministry…</option>
                                @foreach ($ministries as $ministry)
                                    <option value="{{ $ministry['id'] }}" @selected(old('ministry_id') == $ministry['id'])>{{ $ministry['name'] }}</option>
                                @endforeach
                            </select>
                            @error('ministry_id')
                                <p class="text-xs text-danger mt-1">{{ $message }}</p>
                            @enderror
                            @if ($restrictedToOwnMinistries)
                                <p class="text-xs text-ink-faint mt-1">Showing only the ministries assigned to you.</p>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                        <label for="state_department_id" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                            State Department
                        </label>
                        <div class="px-4 py-2 flex flex-col justify-center">
                            <select id="state_department_id" name="state_department_id" onchange="onDepartmentChange()" disabled
                                class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink">
                                <option value="">Select a ministry first…</option>
                            </select>
                            @error('state_department_id')
                                <p class="text-xs text-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                        <label for="institution_id" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                            Institution
                        </label>
                        <div class="px-4 py-2 flex flex-col justify-center">
                            <select id="institution_id" name="institution_id" disabled
                                class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink">
                                <option value="">Select a state department first…</option>
                            </select>
                            @error('institution_id')
                                <p class="text-xs text-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                        <label for="department_agency_ministry" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                            Department / Agency
                        </label>
                        <div class="px-4 py-2 flex flex-col justify-center">
                            <input type="text" id="department_agency_ministry" name="department_agency" value="{{ old('department_agency') }}"
                                class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink placeholder:text-ink-faint">
                        </div>
                    </div>
                </div>

                {{-- County path: County -> Department (same generic department list for every county) --}}
                <div id="county-fields">
                    <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                        <label for="county" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                            County <span class="text-danger ml-1">*</span>
                        </label>
                        <div class="px-4 py-2 flex flex-col justify-center">
                            <select id="county" name="county"
                                class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink">
                                <option value="">Select a county…</option>
                                @foreach ($counties as $countyName)
                                    <option value="{{ $countyName }}" @selected(old('county') === $countyName)>{{ $countyName }}</option>
                                @endforeach
                            </select>
                            @error('county')
                                <p class="text-xs text-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                        <label for="department_agency_county" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                            Department / Agency <span class="text-danger ml-1">*</span>
                        </label>
                        <div class="px-4 py-2 flex flex-col justify-center">
                            <select id="department_agency_county" name="department_agency"
                                class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink">
                                <option value="">Select a department…</option>
                                @foreach ($countyDepartments as $dept)
                                    <option value="{{ $dept }}" @selected(old('entity_type') === 'county' && old('department_agency') === $dept)>{{ $dept }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Commission path: Commission -> Department (cascades - each body has its own directorates) --}}
                <div id="commission-fields">
                    <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                        <label for="commission" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                            Commission / Body <span class="text-danger ml-1">*</span>
                        </label>
                        <div class="px-4 py-2 flex flex-col justify-center">
                            <select id="commission" name="commission" onchange="onCommissionChange()"
                                class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink">
                                <option value="">Select a commission or body…</option>
                                @foreach ($commissions as $name => $meta)
                                    <option value="{{ $name }}" @selected(old('commission') === $name)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('commission')
                                <p class="text-xs text-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                        <label for="department_agency_commission" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                            Department / Directorate <span class="text-danger ml-1">*</span>
                        </label>
                        <div class="px-4 py-2 flex flex-col justify-center">
                            <select id="department_agency_commission" name="department_agency" disabled
                                class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink">
                                <option value="">Select a commission first…</option>
                            </select>
                        </div>
                    </div>
                </div>

                <x-form-field label="Location / Office" name="location_office" />
                <x-form-field label="Contact Person Name" name="contact_person_name" required />
                <x-form-field label="Contact Person Number" name="contact_person_number" required />
            </div>

            {{-- Lot / Category / Subcategory / Unit / Quantity --}}
            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <div class="bg-gradient-to-r from-brand-700 to-brand-500 px-4 py-2.5">
                    <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Lot &amp; Category</h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="lot" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Lot <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <select id="lot" name="lot" required onchange="onLotChange()"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink">
                            <option value="">Select a lot…</option>
                            @foreach ($lots as $lotKey => $lot)
                                <option value="{{ $lotKey }}" @selected(old('lot') == $lotKey)>{{ $lot['label'] }}</option>
                            @endforeach
                        </select>
                        @error('lot')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="category" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Main Category <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <select id="category" name="category" required onchange="onCategoryChange()"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink" disabled>
                            <option value="">Select a lot first…</option>
                        </select>
                        @error('category')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div id="subcategory-row" class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="subcategory" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Subcategory <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <select id="subcategory" name="subcategory" onchange="onSubcategoryChange()"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink" disabled>
                            <option value="">Select a category first…</option>
                        </select>
                        @error('subcategory')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="unit" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Unit of Measure <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <select id="unit" name="unit" required
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink" disabled>
                            <option value="">Select a category first…</option>
                        </select>
                        @error('unit')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="quantity" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Quantity <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <input
                            type="number" step="0.01" min="0.01" id="quantity" name="quantity" value="{{ old('quantity') }}" required
                            class="w-40 border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink placeholder:text-ink-faint"
                        >
                        @error('quantity')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <x-form-field label="Description" name="description" :value="old('description')" />
            </div>

            {{-- Date --}}
            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <x-form-field label="Date" name="collection_date" type="date" required :value="now()->toDateString()" />
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm shadow-brand-900/20">
                    Submit Collection
                </button>
            </div>
        </form>

    <script>
        const LOTS = @json($lots);
        const UNIT_LABELS = @json(\App\Support\WasteCategories::UNIT_LABELS);
        const oldLot = @json(old('lot'));
        const oldCategory = @json(old('category'));
        const oldSubcategory = @json(old('subcategory'));
        const oldUnit = @json(old('unit'));

        const MINISTRIES = @json($ministries->keyBy('id'));
        const COMMISSIONS = @json($commissions);
        const oldEntityType = @json(old('entity_type', 'ministry'));
        const oldMinistryId = @json(old('ministry_id'));
        const oldDepartmentId = @json(old('state_department_id'));
        const oldInstitutionId = @json(old('institution_id'));
        const oldCommission = @json(old('commission'));
        const oldDepartmentAgency = @json(old('department_agency'));

        // Only the fields belonging to the active Entity Type should be
        // enabled - a disabled <select>/<input> never submits, which is how
        // 3 same-named "department_agency" controls (free text for Ministry,
        // a select for County, a select for Commission) coexist in one form.
        function onEntityTypeChange() {
            const type = document.querySelector('input[name="entity_type"]:checked')?.value ?? 'ministry';
            const blocks = {
                ministry: document.getElementById('ministry-fields'),
                county: document.getElementById('county-fields'),
                commission: document.getElementById('commission-fields'),
            };
            const requiredFieldByType = { ministry: 'ministry_id', county: 'county', commission: 'commission' };
            const deptFieldByType = {
                ministry: 'department_agency_ministry',
                county: 'department_agency_county',
                commission: 'department_agency_commission',
            };

            Object.entries(blocks).forEach(([key, el]) => {
                const active = key === type;
                el.classList.toggle('hidden', !active);
                document.getElementById(requiredFieldByType[key]).disabled = !active;
                document.getElementById(requiredFieldByType[key]).required = active;
                const deptEl = document.getElementById(deptFieldByType[key]);
                deptEl.disabled = !active;
                deptEl.required = active && key !== 'ministry';
            });
        }

        function onCommissionChange() {
            const name = document.getElementById('commission').value;
            const deptSelect = document.getElementById('department_agency_commission');
            const meta = COMMISSIONS[name];

            if (!meta) {
                fillSelect(deptSelect, [], 'Select a commission first…');
                return;
            }

            fillSelect(deptSelect, meta.departments.map((d) => [d, d]), 'Select a department…');
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
            if (oldDepartmentAgency) document.getElementById('department_agency_ministry').value = oldDepartmentAgency;
        } else if (oldEntityType === 'county' && oldDepartmentAgency) {
            document.getElementById('department_agency_county').value = oldDepartmentAgency;
        } else if (oldEntityType === 'commission' && oldCommission) {
            document.getElementById('commission').value = oldCommission;
            onCommissionChange();
            if (oldDepartmentAgency) document.getElementById('department_agency_commission').value = oldDepartmentAgency;
        }
    </script>
</x-layout>
