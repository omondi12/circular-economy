<x-layout :title="$department->name">
        <x-page-header
            :title="$department->name"
            :subtitle="($department->parent->name ?? 'Ministry not set').' - '.number_format($clients->count()).' client(s) connected, '.number_format($totalSubmissions).' LSO(s) recorded across them.'"
            :back="route('state-departments.index')"
            back-label="Back to State Departments"
        />

        {{-- Department info panel --}}
        <div class="bg-panel border border-border rounded-2xl p-6 shadow-sm mb-8 grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div>
                <p class="text-xs text-ink-faint uppercase tracking-wide mb-1.5">Principal Secretary</p>
                @if ($department->contact_person_name)
                    <p class="text-sm font-medium text-ink">{{ $department->contact_person_name }}</p>
                    @if ($department->contact_person_phone)
                        <p class="text-xs text-ink-faint tabular-nums mt-0.5">{{ $department->contact_person_phone }}</p>
                    @endif
                @else
                    <p class="text-sm text-ink-faint italic">Not on file yet</p>
                @endif
            </div>

            <div>
                <p class="text-xs text-ink-faint uppercase tracking-wide mb-1.5">Handled By</p>
                @if ($department->assignedRm)
                    <div class="flex items-center gap-2.5">
                        @if ($department->assignedRm->profilePhotoUrl())
                            <img src="{{ $department->assignedRm->profilePhotoUrl() }}" alt="{{ $department->assignedRm->name }}" class="w-9 h-9 rounded-full object-cover ring-1 ring-border shrink-0">
                        @else
                            <span class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-full bg-gold-100 text-gold-700 text-xs font-bold">
                                {{ $department->assignedRm->initials() }}
                            </span>
                        @endif
                        <a href="{{ route('relationship-managers.show', $department->assignedRm) }}" class="text-sm font-medium text-brand-700 hover:text-brand-800">
                            {{ $department->assignedRm->name }}
                        </a>
                    </div>
                @else
                    <p class="text-sm text-ink-faint italic">Unassigned</p>
                @endif
            </div>

            <div>
                <p class="text-xs text-ink-faint uppercase tracking-wide mb-1.5">Ministry</p>
                <p class="text-sm font-medium text-ink">{{ $department->parent->name ?? '—' }}</p>
            </div>
        </div>

        {{-- Clients --}}
        <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-faint mb-3">Clients ({{ number_format($clients->count()) }})</h2>

        @forelse ($clients as $client)
            <div class="bg-panel border border-border rounded-2xl p-5 shadow-sm mb-4">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div class="min-w-0">
                        <a href="{{ route('state-corporations.show', $client) }}" class="font-display italic text-lg text-ink hover:text-brand-700 transition-colors">
                            {{ $client->name }}
                        </a>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-ink-faint">
                            <span>{{ $client->classification ?? '—' }}</span>
                            @if ($client->ceo_name)
                                <span>&middot; CEO: {{ $client->ceo_name }}</span>
                            @endif
                            @if ($client->effectiveAssignedRm())
                                <span>&middot; RM: {{ $client->effectiveAssignedRm()->name }}</span>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('state-corporations.show', $client) }}" class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-gold-50 text-gold-700 text-xs font-medium hover:bg-gold-100 transition-colors">
                        Full history
                        <x-icon name="arrow-right" size="12" />
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                    <div>
                        <p class="text-xs text-ink-faint uppercase tracking-wide mb-1">LSOs Recorded</p>
                        <p class="font-display text-xl text-ink tabular-nums">{{ number_format($client->overall->submissions) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-ink-faint uppercase tracking-wide mb-1">Quantity</p>
                        <x-entity-quantity :row="$client->overall" />
                    </div>
                    <div>
                        <p class="text-xs text-ink-faint uppercase tracking-wide mb-1">Last LSO</p>
                        <p class="text-sm text-ink-muted">
                            {{ $client->overall->last_collection_date ? \Illuminate\Support\Carbon::parse($client->overall->last_collection_date)->format('d M Y') : '—' }}
                        </p>
                    </div>
                </div>

                @if ($client->latest_collections->isNotEmpty())
                    <div class="border-t border-border pt-3">
                        <p class="text-xs text-ink-faint uppercase tracking-wide mb-2">Recent LSOs</p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="text-left text-ink-faint">
                                    <tr>
                                        <th class="pr-4 py-1 font-medium">Date</th>
                                        <th class="pr-4 py-1 font-medium">Lot</th>
                                        <th class="pr-4 py-1 font-medium">Category</th>
                                        <th class="pr-4 py-1 font-medium">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach ($client->latest_collections as $collection)
                                        <tr>
                                            <td class="pr-4 py-1.5 whitespace-nowrap text-ink-muted">{{ $collection->collection_date->format('d M Y') }}</td>
                                            <td class="pr-4 py-1.5 whitespace-nowrap text-ink-muted">{{ $collection->lotLabel() }}</td>
                                            <td class="pr-4 py-1.5 text-ink-muted">{{ $collection->categoryLabel() }}{{ $collection->subcategoryLabel() ? ' - '.$collection->subcategoryLabel() : '' }}</td>
                                            <td class="pr-4 py-1.5 whitespace-nowrap tabular-nums text-ink-muted">{{ number_format($collection->quantity, 1) }} {{ $collection->unitLabel() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <p class="text-xs text-ink-faint italic border-t border-border pt-3">No LSOs recorded for this client yet.</p>
                @endif
            </div>
        @empty
            <div class="bg-panel border border-border rounded-2xl p-8 text-center shadow-sm">
                <p class="text-sm text-ink-faint italic">No clients linked to this department yet.</p>
            </div>
        @endforelse
</x-layout>
