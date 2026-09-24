<x-layout title="{{ $rm->name }} — Profile" wide>
        <x-page-header
            :title="$rm->name"
            subtitle="Full profile: clients, collections and facilitations."
            :back="route('relationship-managers.index')"
            back-label="Back to Relationship Managers"
        />

        {{-- Header card --}}
        <div class="bg-panel border border-border rounded-xl shadow-sm p-6 mb-6 flex flex-col sm:flex-row items-center sm:items-start gap-5">
            @if ($rm->profilePhotoUrl())
                <img src="{{ $rm->profilePhotoUrl() }}" alt="{{ $rm->name }}" class="w-24 h-24 rounded-full object-cover ring-1 ring-border shrink-0">
            @else
                <span class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gold-100 text-gold-700 text-2xl font-bold shrink-0">
                    {{ $rm->initials() }}
                </span>
            @endif

            <div class="flex-1 text-center sm:text-left">
                <h2 class="text-lg font-semibold text-ink">{{ $rm->name }}</h2>
                <p class="text-sm text-ink-faint">{{ $rm->email }}</p>
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mt-3">
                    <span class="inline-flex px-2.5 py-1 rounded-full bg-teal-50 text-teal-800 text-xs font-medium">Relationship Manager</span>
                    <span @class(['inline-flex px-2.5 py-1 rounded-full text-xs font-medium', 'bg-brand-50 text-brand-800' => $rm->is_active, 'bg-panel-high text-ink-faint' => ! $rm->is_active])>
                        {{ $rm->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <span class="inline-flex px-2.5 py-1 rounded-full bg-panel-high text-ink-faint text-xs font-medium">
                        Supervisor: {{ $rm->supervisor->name ?? '—' }}
                    </span>
                    <span class="inline-flex px-2.5 py-1 rounded-full bg-panel-high text-ink-faint text-xs font-medium">
                        On the team since {{ $rm->created_at->format('d M Y') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Summary stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <x-stat-tile label="Assigned Clients" :value="number_format($clients->count())" icon="circle-check" tone="green" />
            <x-stat-tile label="Assigned Ministries" :value="number_format($ministries->count())" icon="building" tone="teal" />
            <x-stat-tile label="Collections Recorded" :value="number_format($totalCollections)" :hint="number_format($totalQuantity, 1).' total quantity (mixed units)'" icon="scale" tone="gold" />
            <x-stat-tile label="Facilitations Made" :value="number_format($requisitions->count())" :hint="number_format($pendingCount).' pending'" icon="calendar" tone="violet" />
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
            {{-- Assigned clients --}}
            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Assigned Clients</h3>
                    <span class="text-xs text-ink-faint">{{ number_format($clients->count()) }} total</span>
                </div>
                <div class="max-h-96 overflow-y-auto divide-y divide-border">
                    @forelse ($clients as $client)
                        <a href="{{ route('state-corporations.show', $client) }}" class="block px-5 py-3 hover:bg-panel-muted transition-colors">
                            <p class="text-sm font-medium text-ink">{{ $client->name }}</p>
                            <p class="text-xs text-ink-faint mt-0.5">{{ $client->ministryDisplay() }}</p>
                        </a>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-ink-faint">No clients assigned yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Assigned ministries --}}
            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Assigned Ministries</h3>
                    <span class="text-xs text-ink-faint">{{ number_format($ministries->count()) }} total</span>
                </div>
                <div class="max-h-96 overflow-y-auto divide-y divide-border">
                    @forelse ($ministries as $ministry)
                        <a href="{{ route('ministries.show', $ministry) }}" class="block px-5 py-3 hover:bg-panel-muted transition-colors text-sm font-medium text-ink">
                            {{ $ministry->name }}
                        </a>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-ink-faint">No ministries assigned yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Collections recorded --}}
        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm mb-8">
            <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Collections Recorded</h3>
                <a href="{{ route('collections.index', ['rm' => $rm->id]) }}" class="text-xs font-medium text-brand-700 hover:text-brand-800">
                    View all {{ number_format($totalCollections) }} →
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-brand-50 text-left text-ink-faint">
                        <tr>
                            <th class="px-4 py-2 font-medium">Entity</th>
                            <th class="px-4 py-2 font-medium">Lot / Category</th>
                            <th class="px-4 py-2 font-medium text-right">Quantity</th>
                            <th class="px-4 py-2 font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($collections as $collection)
                            <tr class="hover:bg-panel-muted transition-colors">
                                <td class="px-4 py-3 font-medium max-w-xs">
                                    <div class="line-clamp-2">{{ $collection->entity_name }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full {{ $collection->lot === 1 ? 'bg-brand-50 text-brand-800' : 'bg-gold-100 text-gold-700' }} text-xs font-medium">
                                        {{ \App\Support\WasteCategories::shortLotLabel($collection->lot) }}
                                    </span>
                                    <span class="block text-xs text-ink-faint mt-0.5">{{ $collection->categoryLabel() }}</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap font-medium">{{ number_format($collection->quantity, 1) }} {{ $collection->unit }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-ink-faint">{{ $collection->collection_date->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-ink-faint">No collections recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Facilitations --}}
        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Facilitations (Transport &amp; Airtime)</h3>
                @unless ($facilitationUnlocked)
                    <a href="{{ route('requisitions.public') }}" class="text-xs font-medium text-brand-700 hover:text-brand-800">
                        Unlock with PIN to view amounts →
                    </a>
                @endunless
            </div>

            @if (! $facilitationUnlocked)
                <p class="px-5 py-8 text-center text-sm text-ink-faint">
                    Facilitation amounts are PIN-protected, same as the Facilitation page. Unlock it once to see them here too.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-brand-50 text-left text-ink-faint">
                            <tr>
                                <th class="px-4 py-2 font-medium">Working Day</th>
                                <th class="px-4 py-2 font-medium">Institution</th>
                                <th class="px-4 py-2 font-medium">Transport</th>
                                <th class="px-4 py-2 font-medium">Airtime</th>
                                <th class="px-4 py-2 font-medium text-right">Total for Day</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse ($requisitions as $req)
                                <tr class="hover:bg-panel-muted transition-colors align-top">
                                    <td class="px-4 py-3 whitespace-nowrap text-ink-muted">{{ $req->working_day->format('d M Y') }}</td>
                                    <td class="px-4 py-3 max-w-[220px]"><div class="line-clamp-2">{{ $req->institution_visiting }}</div></td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <x-requisition-status-badge :status="$req->transport_status" />
                                        <div class="text-xs text-ink-faint mt-1 tabular-nums">KES {{ number_format($req->transport_amount_requested, 0) }} · Paid {{ number_format($req->transport_paid_amount, 0) }}</div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <x-requisition-status-badge :status="$req->airtime_status" />
                                        <div class="text-xs text-ink-faint mt-1 tabular-nums">KES {{ number_format($req->airtime_amount_requested, 0) }} · Paid {{ number_format($req->airtime_paid_amount, 0) }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap font-medium tabular-nums">KES {{ number_format($req->totalRequestedForDay(), 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-ink-faint">No facilitation requests yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
</x-layout>
