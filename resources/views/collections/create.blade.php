<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recyclable Materials Collection Form - Westport Industrial City</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            title="Recyclable Materials Collection Form"
            subtitle="Please complete all applicable fields."
        />

        <form method="POST" action="{{ route('collections.store') }}" class="space-y-6">
            @csrf

            {{-- Entity & contact block --}}
            <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm">
                <div class="bg-gradient-to-r from-[#0f7a3d] to-[#1a9650] px-4 py-2.5">
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
            <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm">
                <div class="bg-gradient-to-r from-[#0f7a3d] to-[#1a9650] px-4 py-2.5">
                    <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Amount of Recyclable Material (Kg)</h2>
                </div>
                <table class="w-full">
                    <thead class="bg-[#f7edd6] text-left">
                        <tr>
                            <th class="px-4 py-2 text-sm font-semibold text-neutral-700">Material</th>
                            <th class="px-4 py-2 text-sm font-semibold text-neutral-700">Quantity (Kg)</th>
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
                                <p class="text-sm font-medium text-neutral-700 mb-1">6. Any Other Materials</p>
                                <input
                                    type="text"
                                    name="other_material_name"
                                    value="{{ old('other_material_name') }}"
                                    placeholder="Please specify"
                                    class="w-full sm:w-48 border-neutral-300 rounded-md text-xs focus:border-[#0f7a3d] focus:ring-[#0f7a3d]"
                                >
                            </td>
                            <td class="px-4 py-2">
                                <input
                                    type="number" step="0.01" min="0"
                                    name="other_kg"
                                    value="{{ old('other_kg') }}"
                                    placeholder="0.00"
                                    class="w-full sm:w-40 border-neutral-300 rounded-md text-sm focus:border-[#0f7a3d] focus:ring-[#0f7a3d]"
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
                @error('paper_kg')
                    <p class="text-xs text-red-600 px-4 pb-3">{{ $message }}</p>
                @enderror
            </div>

            {{-- Date & signatory --}}
            <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm">
                <x-form-field label="Date" name="collection_date" type="date" required :value="now()->toDateString()" />
                <x-form-field label="Collected By" name="collected_by" />
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 rounded-md bg-[#0f7a3d] hover:bg-[#0b5c2e] text-white text-sm font-semibold transition-colors shadow-sm shadow-[#0f7a3d]/30">
                    Submit Collection
                </button>
            </div>
        </form>
    </div>
</body>
</html>
