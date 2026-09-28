<x-layout title="Assign RMs">
        @if (session('error'))
            <div class="mb-6 rounded-lg bg-red-50 border border-danger/30 text-danger text-sm px-4 py-3">
                {{ session('error') }}
            </div>
        @endif

        <x-page-header
            title="Assign RMs"
            subtitle="Clients follow their state department now - assign one there and every client under it shows that RM immediately. Direct client assignment is still available as a one-off exception. Ministry assignment only scopes an RM's own collection-entry form."
            :back="route('admin.dashboard')"
            back-label="Back to admin"
        />

        <div class="inline-flex flex-wrap rounded-lg border border-border bg-white p-1 text-sm mb-6">
            @foreach ((auth()->user()->isAdmin() ? ['ministries' => 'Ministries', 'state-departments' => 'State Departments', 'clients' => 'Clients', 'supervisors' => 'Supervisors'] : ['ministries' => 'Ministries', 'state-departments' => 'State Departments', 'clients' => 'Clients']) as $key => $label)
                <a
                    href="{{ route('admin.assign-rms', ['view' => $key]) }}"
                    @class([
                        'px-3 py-1.5 rounded-md font-medium transition-colors whitespace-nowrap',
                        'bg-brand-700 text-white' => $view === $key,
                        'text-ink-faint hover:text-ink' => $view !== $key,
                    ])
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if ($view === 'ministries')
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm text-ink-faint">{{ $ministries->count() }} ministries.</p>
                @if (auth()->user()->isAdmin())
                    <form
                        method="POST" action="{{ route('admin.assign-rms.ministries.distribute') }}"
                        onsubmit="return confirm('This recomputes every ministry\'s assignment from scratch (2 named exceptions + round-robin), overwriting any manual assignments made above. Continue?')"
                    >
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-gold-600 hover:bg-gold-700 text-white text-sm font-semibold transition-colors shadow-sm">
                            Distribute Automatically
                        </button>
                    </form>
                @endif
            </div>

            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-brand-50 text-left text-ink-faint">
                        <tr>
                            <th class="px-4 py-2 font-medium">Ministry</th>
                            <th class="px-4 py-2 font-medium">Currently Assigned</th>
                            <th class="px-4 py-2 font-medium">Assign To</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($ministries as $ministry)
                            <tr class="hover:bg-panel-muted transition-colors">
                                <td class="px-4 py-3 font-medium max-w-md">
                                    <div class="line-clamp-2">{{ $ministry->name }}</div>
                                </td>
                                <td class="px-4 py-3 text-ink-muted">
                                    {{ $ministry->assignedRm->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('admin.assign-rms.ministries.update', $ministry) }}">
                                        @csrf
                                        <select
                                            name="assigned_rm_id" onchange="this.form.submit()"
                                            class="rounded-lg border border-border bg-white px-3 py-1.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                                        >
                                            <option value="">— Unassigned —</option>
                                            @foreach ($rms as $rm)
                                                <option value="{{ $rm->id }}" @selected($ministry->assigned_rm_id === $rm->id)>{{ $rm->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif ($view === 'state-departments')
            <p class="text-sm text-ink-faint mb-4">
                {{ $stateDepartments->count() }} state department(s). Assign each one to the RM covering it directly, finer than a ministry-wide assignment.
                @if (auth()->user()->canManageStateDepartmentContacts())
                    The contact person is who that RM reaches out to there.
                @endif
            </p>

            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-brand-50 text-left text-ink-faint">
                        <tr>
                            <th class="px-4 py-2 font-medium">State Department</th>
                            <th class="px-4 py-2 font-medium">Ministry</th>
                            <th class="px-4 py-2 font-medium">Contact Person</th>
                            <th class="px-4 py-2 font-medium">Currently Assigned</th>
                            <th class="px-4 py-2 font-medium">Assign To</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($stateDepartments as $department)
                            <tr class="hover:bg-panel-muted transition-colors align-top">
                                <td class="px-4 py-3 font-medium max-w-md">
                                    <div class="line-clamp-2">{{ $department->name }}</div>
                                </td>
                                <td class="px-4 py-3 text-ink-faint max-w-xs">
                                    <div class="line-clamp-2">{{ $department->parent->name ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-3 min-w-[220px]">
                                    @if (auth()->user()->canManageStateDepartmentContacts())
                                        <form method="POST" action="{{ route('admin.assign-rms.state-departments.contact', $department) }}" class="flex flex-col gap-1.5">
                                            @csrf
                                            <input
                                                type="text" name="contact_person_name" value="{{ $department->contact_person_name }}"
                                                placeholder="Contact person name"
                                                class="w-full rounded-md border border-border bg-white px-2 py-1 text-xs text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                                            >
                                            <input
                                                type="text" name="contact_person_phone" value="{{ $department->contact_person_phone }}"
                                                placeholder="Phone number"
                                                class="w-full rounded-md border border-border bg-white px-2 py-1 text-xs text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                                            >
                                            <button type="submit" class="self-start text-xs font-medium text-brand-700 hover:text-brand-800">Save</button>
                                        </form>
                                    @else
                                        <div class="text-ink-muted">{{ $department->contact_person_name ?: '—' }}</div>
                                        <div class="text-xs text-ink-faint tabular-nums">{{ $department->contact_person_phone }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-ink-muted">
                                    {{ $department->assignedRm->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('admin.assign-rms.state-departments.update', $department) }}">
                                        @csrf
                                        <select
                                            name="assigned_rm_id" onchange="this.form.submit()"
                                            class="rounded-lg border border-border bg-white px-3 py-1.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                                        >
                                            <option value="">— Unassigned —</option>
                                            @foreach ($rms as $rm)
                                                <option value="{{ $rm->id }}" @selected($department->assigned_rm_id === $rm->id)>{{ $rm->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-ink-faint">No state departments visible to you.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif ($view === 'clients')
            <p class="text-sm text-ink-faint mb-4">
                Clients get their RM from their state department now - assign one there instead of here for a whole portfolio at once. Use the dropdown below only for a one-off exception.
            </p>

            <form method="GET" class="mb-4">
                <input type="hidden" name="view" value="clients">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative max-w-md flex-1 min-w-[220px]">
                        <input
                            type="text" name="q" value="{{ $search }}"
                            placeholder="Search clients by name…"
                            class="w-full rounded-lg border border-border bg-white pl-4 pr-24 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                        >
                        <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 px-3 py-1.5 rounded-md bg-brand-700 text-white text-xs font-medium hover:bg-brand-800 transition-colors">
                            Search
                        </button>
                    </div>

                    <select
                        name="status" onchange="this.form.submit()"
                        class="rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                    >
                        <option value="" @selected(! $status)>All clients</option>
                        <option value="assigned" @selected($status === 'assigned')>Assigned only</option>
                        <option value="unassigned" @selected($status === 'unassigned')>Unassigned only</option>
                    </select>
                </div>
                @if ($search || $status)
                    <a href="{{ route('admin.assign-rms', ['view' => 'clients']) }}" class="inline-block mt-2 text-xs text-ink-faint hover:text-ink-muted">
                        Clear filters ({{ $clients->count() }} match{{ $clients->count() === 1 ? '' : 'es' }})
                    </a>
                @else
                    <p class="mt-2 text-xs text-ink-faint">Showing all {{ $clients->count() }} client(s) visible to you - no pagination, so nothing is hidden on another page.</p>
                @endif
            </form>

            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-brand-50 text-left text-ink-faint">
                        <tr>
                            <th class="px-4 py-2 font-medium">Client</th>
                            <th class="px-4 py-2 font-medium">CEO</th>
                            <th class="px-4 py-2 font-medium">Ministry</th>
                            <th class="px-4 py-2 font-medium">State Department</th>
                            <th class="px-4 py-2 font-medium">Currently Assigned</th>
                            <th class="px-4 py-2 font-medium">Assign To</th>
                            <th class="px-4 py-2 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($clients as $client)
                            <tr class="hover:bg-panel-muted transition-colors align-top">
                                <td class="px-4 py-3 font-medium max-w-md">
                                    <div class="line-clamp-2">{{ $client->name }}</div>
                                </td>
                                <td class="px-4 py-3 min-w-[180px]">
                                    @if (auth()->user()->isAdmin())
                                        <form method="POST" action="{{ route('admin.assign-rms.clients.ceo', $client) }}" class="flex items-center gap-1.5">
                                            @csrf
                                            <input
                                                type="text" name="ceo_name" value="{{ $client->ceo_name }}"
                                                placeholder="CEO name"
                                                class="w-full rounded-md border border-border bg-white px-2 py-1 text-xs text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                                            >
                                            <button type="submit" class="shrink-0 text-xs font-medium text-brand-700 hover:text-brand-800">Save</button>
                                        </form>
                                    @else
                                        <div class="text-ink-muted">{{ $client->ceo_name ?: '—' }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-ink-faint whitespace-nowrap max-w-xs">
                                    <div class="line-clamp-2">{{ $client->ministryDisplay() }}</div>
                                </td>
                                <td class="px-4 py-3 text-ink-faint whitespace-nowrap max-w-xs">
                                    <div class="line-clamp-2">{{ $client->stateDepartmentDisplay() }}</div>
                                </td>
                                <td class="px-4 py-3 text-ink-muted">
                                    {{ $client->effectiveAssignedRm()->name ?? '—' }}
                                    @if (! $client->assigned_rm_id && $client->effectiveAssignedRm())
                                        <div class="text-xs text-ink-faint">via {{ $client->stateDepartmentDisplay() }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('admin.assign-rms.clients.update', $client) }}">
                                        @csrf
                                        <select
                                            name="assigned_rm_id" onchange="this.form.submit()"
                                            class="rounded-lg border border-border bg-white px-3 py-1.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                                        >
                                            <option value="">— No override (follow department) —</option>
                                            @foreach ($rms as $rm)
                                                <option value="{{ $rm->id }}" @selected($client->assigned_rm_id === $rm->id)>{{ $rm->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <a href="{{ route('admin.clients.reports.index', $client) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-gold-50 text-gold-700 text-xs font-medium hover:bg-gold-100 transition-colors">
                                        Report
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-ink-faint">No clients match this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @else
            <p class="text-sm text-ink-faint mb-4">{{ $allRms->count() }} RM(s). Moves an RM to a different supervisor's team - use this to sort out RMs that existed before their supervisor did.</p>

            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-brand-50 text-left text-ink-faint">
                        <tr>
                            <th class="px-4 py-2 font-medium">RM</th>
                            <th class="px-4 py-2 font-medium">Currently Reports To</th>
                            <th class="px-4 py-2 font-medium">Assign To Supervisor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($allRms as $rm)
                            <tr class="hover:bg-panel-muted transition-colors">
                                <td class="px-4 py-3 font-medium">{{ $rm->name }}</td>
                                <td class="px-4 py-3 text-ink-muted">{{ $rm->supervisor->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('admin.assign-rms.supervisor.update', $rm) }}">
                                        @csrf
                                        <select
                                            name="supervisor_id" onchange="this.form.submit()"
                                            class="rounded-lg border border-border bg-white px-3 py-1.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                                        >
                                            <option value="">— Unassigned —</option>
                                            @foreach ($supervisors as $supervisor)
                                                <option value="{{ $supervisor->id }}" @selected($rm->supervisor_id === $supervisor->id)>{{ $supervisor->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-ink-faint">No RMs yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
</x-layout>
