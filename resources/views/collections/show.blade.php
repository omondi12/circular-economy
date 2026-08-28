<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $collection->entity_name }} - AMAC Circular Economy Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            :title="$collection->entity_name"
            :subtitle="'Collected '.$collection->collection_date->format('d M Y')"
        />

        {{-- Total weight banner --}}
        <div class="mb-6 rounded-2xl bg-gradient-to-br from-[#0f7a3d] via-[#177a44] to-[#c98500] shadow-xl shadow-[#0f7a3d]/20 px-6 py-6 flex flex-wrap items-center gap-6">
            @if ($collection->isLegacyMaterialEntry())
                <div class="text-white">
                    <p class="text-white/70 text-xs uppercase tracking-wide">Total Collected</p>
                    <p class="text-3xl font-bold mt-1">{{ number_format($collection->totalKg(), 1) }} kg</p>
                </div>
            @else
                <div class="text-white">
                    <p class="text-white/70 text-xs uppercase tracking-wide">{{ \App\Support\WasteCategories::shortLotLabel($collection->lot) }}</p>
                    <p class="text-lg font-semibold mt-1">
                        {{ $collection->categoryLabel() }}
                        @if ($collection->subcategoryLabel())
                            <span class="text-white/70 font-normal">– {{ $collection->subcategoryLabel() }}</span>
                        @endif
                    </p>
                </div>
                <div class="text-white">
                    <p class="text-white/70 text-xs uppercase tracking-wide">Quantity</p>
                    <p class="text-3xl font-bold mt-1">{{ number_format($collection->quantity, 1) }} {{ $collection->unitLabel() }}</p>
                </div>
            @endif
        </div>

        @if ($collection->description)
            <div class="mb-4 bg-white border border-neutral-200 rounded-xl p-4 shadow-sm text-sm text-neutral-600">
                <span class="text-xs uppercase tracking-wide text-neutral-400 block mb-1">Description</span>
                {{ $collection->description }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            {{-- Entity details --}}
            <div class="bg-white border border-neutral-200 rounded-xl p-5 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide mb-4 border-l-4 border-[#0f7a3d] pl-3 text-neutral-600">
                    Entity Details
                </h2>
                <div class="grid grid-cols-1 gap-4">
                    <x-detail-item label="Ministry / County / Commission" :value="$collection->entity_name" />
                    <x-detail-item label="Relationship Manager" :value="$collection->relationship_manager" />
                    <x-detail-item label="State Department" :value="$collection->state_department" />
                    <x-detail-item label="Department / Agency" :value="$collection->department_agency" />
                    <x-detail-item label="Location / Office" :value="$collection->location_office" />
                </div>
            </div>

            {{-- Contact & meta --}}
            <div class="bg-white border border-neutral-200 rounded-xl p-5 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide mb-4 border-l-4 border-[#c98500] pl-3 text-neutral-600">
                    Contact &amp; Record
                </h2>
                <div class="grid grid-cols-1 gap-4">
                    <x-detail-item label="Contact Person" :value="$collection->contact_person_name" />
                    <x-detail-item label="Contact Number" :value="$collection->contact_person_number" />
                    <x-detail-item label="Collection Date" :value="$collection->collection_date->format('d M Y')" />
                    <x-detail-item label="Collected By" :value="$collection->collected_by" />
                </div>
            </div>
        </div>

        @if ($collection->isLegacyMaterialEntry())
            {{-- Materials --}}
            <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide mt-5 mb-4 border-l-4 border-[#0f7a3d] pl-3 text-neutral-600 mx-5">
                    Materials Collected
                </h2>
                <table class="w-full text-sm">
                    <thead class="bg-[#f7edd6] text-left text-neutral-600">
                        <tr>
                            <th class="px-5 py-2 font-medium">Material</th>
                            <th class="px-5 py-2 font-medium text-right">Quantity (Kg)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr>
                            <td class="px-5 py-2.5">Paper</td>
                            <td class="px-5 py-2.5 text-right tabular-nums">{{ number_format($collection->paper_kg, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="px-5 py-2.5">Metal</td>
                            <td class="px-5 py-2.5 text-right tabular-nums">{{ number_format($collection->metal_kg, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="px-5 py-2.5">Plastic</td>
                            <td class="px-5 py-2.5 text-right tabular-nums">{{ number_format($collection->plastic_kg, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="px-5 py-2.5">Furniture</td>
                            <td class="px-5 py-2.5 text-right tabular-nums">{{ number_format($collection->furniture_kg, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="px-5 py-2.5">E-Waste</td>
                            <td class="px-5 py-2.5 text-right tabular-nums">{{ number_format($collection->ewaste_kg, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="px-5 py-2.5">{{ $collection->other_material_name ?: 'Other' }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums">{{ number_format($collection->other_kg, 2) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-[#0f7a3d]/5 font-semibold">
                            <td class="px-5 py-2.5">Total</td>
                            <td class="px-5 py-2.5 text-right tabular-nums">{{ number_format($collection->totalKg(), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        @if ($collection->user)
            <div class="mt-4 bg-white border border-neutral-200 rounded-xl p-5 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide mb-4 border-l-4 border-[#0f7a3d] pl-3 text-neutral-600">
                    Submitted By
                </h2>
                <x-detail-item label="Relationship Manager" :value="$collection->user->name" />
            </div>
        @endif
    </div>
</body>
</html>
