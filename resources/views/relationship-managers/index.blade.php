<x-layout title="Relationship Managers">
        <x-page-header
            title="Relationship Managers"
            :subtitle="$rms->count().' active RM account(s), and who they report to.'"
            :back="route('dashboard')"
            back-label="Back to dashboard"
        />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <x-stat-tile label="RMs" :value="number_format($rms->count())" icon="user" tone="teal" />
            <x-stat-tile label="Clients Assigned" :value="number_format($totalClients)" icon="circle-check" tone="green" />
            <x-stat-tile label="Collections Recorded" :value="number_format($totalCollections)" icon="scale" tone="gold" />
            <x-stat-tile label="Facilitations" :value="number_format($totalFacilitations)" :hint="number_format($pendingFacilitations).' pending'" icon="calendar" tone="violet" />
        </div>

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium"></th>
                        <th class="px-4 py-2 font-medium">Name</th>
                        <th class="px-4 py-2 font-medium">Supervisor</th>
                        <th class="px-4 py-2 font-medium">Assigned Clients</th>
                        <th class="px-4 py-2 font-medium">Collections</th>
                        <th class="px-4 py-2 font-medium">Facilitations</th>
                        <th class="px-4 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($rms as $rm)
                        <tr class="hover:bg-panel-muted transition-colors">
                            <td class="px-4 py-3">
                                @if ($rm->profilePhotoUrl())
                                    <img src="{{ $rm->profilePhotoUrl() }}" alt="{{ $rm->name }}" class="w-9 h-9 rounded-full object-cover ring-1 ring-border">
                                @else
                                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-gold-100 text-gold-700 text-xs font-bold">
                                        {{ $rm->initials() }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-medium">{{ $rm->name }}</td>
                            <td class="px-4 py-3 text-ink-faint">{{ $rm->supervisor->name ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ number_format($rm->client_count) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ number_format($rm->collection_count) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ number_format($rm->requisition_count) }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('relationship-managers.show', $rm) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-gold-50 text-gold-700 text-xs font-medium hover:bg-gold-100 transition-colors">
                                    View Profile
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-ink-faint">No RM accounts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</x-layout>
