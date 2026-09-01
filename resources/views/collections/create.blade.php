<x-layout title="Recyclable Materials Collection Form">
        <x-page-header
            title="Recyclable Materials Collection Form"
            subtitle="Please complete all applicable fields."
        />

        <form method="POST" action="{{ route('collections.store') }}" class="space-y-6">
            @csrf

            {{-- Entity & contact block --}}
            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <div class="bg-gradient-to-r from-brand-700 to-brand-500 px-4 py-2.5">
                    <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Entity Details</h2>
                </div>
                <x-form-field label="Ministry / County / Commission" name="entity_name" required />
                <x-form-field label="Relationship Manager" name="relationship_manager" />
                <x-form-field label="State Department" name="state_department" />
                <x-form-field label="Department / Agency" name="department_agency" />
                <x-form-field label="Location / Office" name="location_office" />
                <x-form-field label="Contact Person Name" name="contact_person_name" required />
                <x-form-field label="Contact Person Number" name="contact_person_number" required />
            </div>

            {{-- Materials --}}
            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <div class="bg-gradient-to-r from-brand-700 to-brand-500 px-4 py-2.5">
                    <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Amount of Recyclable Material (Kg)</h2>
                </div>
                <table class="w-full">
                    <thead class="bg-gold-50 text-left">
                        <tr>
                            <th class="px-4 py-2 text-sm font-semibold text-ink-muted">Material</th>
                            <th class="px-4 py-2 text-sm font-semibold text-ink-muted">Quantity (Kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <x-material-field number="1" label="Paper" name="paper_kg" />
                        <x-material-field number="2" label="Metal" name="metal_kg" />
                        <x-material-field number="3" label="Plastic" name="plastic_kg" />
                        <x-material-field number="4" label="Furniture" name="furniture_kg" />
                        <x-material-field number="5" label="E-Waste" name="ewaste_kg" />
                        <tr>
                            <td class="px-4 py-2.5 align-top">
                                <p class="text-sm font-medium text-ink-muted mb-1">6. Any Other Materials</p>
                                <input
                                    type="text"
                                    name="other_material_name"
                                    value="{{ old('other_material_name') }}"
                                    placeholder="Please specify"
                                    class="w-full sm:w-48 border-border rounded-md text-xs focus:border-brand-600 focus:ring-brand-600"
                                >
                            </td>
                            <td class="px-4 py-2">
                                <input
                                    type="number" step="0.01" min="0"
                                    name="other_kg"
                                    value="{{ old('other_kg') }}"
                                    placeholder="0.00"
                                    class="w-full sm:w-40 border-border rounded-md text-sm focus:border-brand-600 focus:ring-brand-600"
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
                @error('paper_kg')
                    <p class="text-xs text-danger px-4 pb-3">{{ $message }}</p>
                @enderror
            </div>

            {{-- Date & signatory --}}
            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <x-form-field label="Date" name="collection_date" type="date" required :value="now()->toDateString()" />
                <x-form-field label="Collected By" name="collected_by" />
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm shadow-brand-900/20">
                    Submit Collection
                </button>
            </div>
        </form>
</x-layout>
