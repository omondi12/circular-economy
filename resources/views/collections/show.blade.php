<x-layout title="{{ $collection->entity_name }}">
        <x-page-header
            :title="$collection->entity_name"
            :subtitle="'Collected '.$collection->collection_date->format('d M Y')"
        />

        {{-- Total weight banner --}}
        <div class="mb-6 rounded-2xl bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 shadow-xl shadow-brand-900/10 px-6 py-6 flex flex-wrap items-center gap-6">
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
            <div class="mb-4 bg-panel border border-border rounded-xl p-4 shadow-sm text-sm text-ink-muted">
                <span class="text-xs uppercase tracking-wide text-ink-faint block mb-1">Description</span>
                {{ $collection->description }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            {{-- Entity details --}}
            <div class="bg-panel border border-border rounded-xl p-5 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide mb-4 border-l-4 border-brand-600 pl-3 text-ink-muted">
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
            <div class="bg-panel border border-border rounded-xl p-5 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide mb-4 border-l-4 border-gold-500 pl-3 text-ink-muted">
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
            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide mt-5 mb-4 border-l-4 border-brand-600 pl-3 text-ink-muted mx-5">
                    Materials Collected
                </h2>
                <table class="w-full text-sm">
                    <thead class="bg-gold-50 text-left text-ink-muted">
                        <tr>
                            <th class="px-5 py-2 font-medium">Material</th>
                            <th class="px-5 py-2 font-medium text-right">Quantity (Kg)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
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
                        <tr class="bg-brand-50 font-semibold">
                            <td class="px-5 py-2.5">Total</td>
                            <td class="px-5 py-2.5 text-right tabular-nums">{{ number_format($collection->totalKg(), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        @if ($collection->user)
            <div class="mt-4 bg-panel border border-border rounded-xl p-5 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide mb-4 border-l-4 border-brand-600 pl-3 text-ink-muted">
                    Submitted By
                </h2>
                <x-detail-item label="Relationship Manager" :value="$collection->user->name" />
            </div>
        @endif
</x-layout>
