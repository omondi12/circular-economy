<x-layout title="State Departments">
        <x-page-header
            title="State Departments"
            :subtitle="number_format($totalDepartments).' state department(s) across every ministry - who is handling each one, their contact person, and the clients connected under it.'"
        />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <x-stat-tile label="State Departments" :value="number_format($totalDepartments)" icon="stamp" tone="rose" />
            <x-stat-tile label="Handled by an RM" :value="number_format($handledCount)" icon="circle-check" tone="green" />
            <x-stat-tile label="Awaiting an RM" :value="number_format($totalDepartments - $handledCount)" icon="inbox" tone="gold" />
            <x-stat-tile label="Clients Connected" :value="number_format($totalClients)" icon="building-community" tone="violet" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach ($departments as $department)
                <div class="bg-panel border border-border rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <p class="text-xs text-ink-faint uppercase tracking-wide font-mono mb-1 truncate">{{ $department->parent->name ?? 'Ministry not set' }}</p>
                            <h3 class="font-display italic text-lg text-ink leading-snug">{{ $department->name }}</h3>
                        </div>
                        <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-full bg-brand-50 text-brand-800 text-xs font-semibold tabular-nums">
                            {{ number_format($department->client_total) }} client{{ $department->client_total === 1 ? '' : 's' }}
                        </span>
                    </div>

                    <div class="flex items-center gap-3 mb-4 pb-4 border-b border-border">
                        @if ($department->assignedRm)
                            @if ($department->assignedRm->profilePhotoUrl())
                                <img src="{{ $department->assignedRm->profilePhotoUrl() }}" alt="{{ $department->assignedRm->name }}" class="w-11 h-11 rounded-full object-cover ring-1 ring-border shrink-0">
                            @else
                                <span class="shrink-0 inline-flex items-center justify-center w-11 h-11 rounded-full bg-gold-100 text-gold-700 text-sm font-bold">
                                    {{ $department->assignedRm->initials() }}
                                </span>
                            @endif
                            <div class="min-w-0">
                                <p class="text-xs text-ink-faint">Handled by</p>
                                <p class="text-sm font-medium text-ink truncate">{{ $department->assignedRm->name }}</p>
                            </div>
                        @else
                            <span class="shrink-0 inline-flex items-center justify-center w-11 h-11 rounded-full bg-panel-muted text-ink-faint">
                                <x-icon name="user" size="18" />
                            </span>
                            <div>
                                <p class="text-xs text-ink-faint">Handled by</p>
                                <p class="text-sm text-ink-faint italic">Unassigned</p>
                            </div>
                        @endif
                    </div>

                    @if ($department->contact_person_name || $department->contact_person_phone)
                        <div class="mb-4">
                            <p class="text-xs text-ink-faint uppercase tracking-wide mb-1">Contact Person</p>
                            <p class="text-sm text-ink-muted">{{ $department->contact_person_name ?: '—' }}</p>
                            @if ($department->contact_person_phone)
                                <p class="text-xs text-ink-faint tabular-nums">{{ $department->contact_person_phone }}</p>
                            @endif
                        </div>
                    @endif

                    <div>
                        <p class="text-xs text-ink-faint uppercase tracking-wide mb-1.5">Clients</p>
                        @if ($department->clients->isEmpty())
                            <p class="text-sm text-ink-faint italic">None linked yet</p>
                        @else
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($department->clients->take(5) as $client)
                                    <span class="inline-flex items-center px-2 py-1 rounded-md bg-panel-muted text-ink-muted text-xs">{{ $client->name }}</span>
                                @endforeach
                                @if ($department->clients->count() > 5)
                                    <span class="inline-flex items-center px-2 py-1 rounded-md bg-panel-muted text-ink-faint text-xs">+{{ $department->clients->count() - 5 }} more</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
</x-layout>
