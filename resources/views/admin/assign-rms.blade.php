<x-layout title="Assign RMs">
        @if (session('error'))
            <div class="mb-6 rounded-lg bg-red-50 border border-danger/30 text-danger text-sm px-4 py-3">
                {{ session('error') }}
            </div>
        @endif

        <x-page-header
            title="Assign RMs"
            subtitle="Assign or reassign which RM covers each ministry and each client. Picking a new RM always shifts the assignment, even if one is already set."
            :back="route('admin.dashboard')"
            back-label="Back to admin"
        />

        <div class="inline-flex flex-wrap rounded-lg border border-border bg-white p-1 text-sm mb-6">
            @foreach (['ministries' => 'Ministries', 'clients' => 'Clients'] as $key => $label)
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
                <form
                    method="POST" action="{{ route('admin.assign-rms.ministries.distribute') }}"
                    onsubmit="return confirm('This recomputes every ministry\'s assignment from scratch (2 named exceptions + round-robin), overwriting any manual assignments made above. Continue?')"
                >
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-gold-600 hover:bg-gold-700 text-white text-sm font-semibold transition-colors shadow-sm">
                        Distribute Automatically
                    </button>
                </form>
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
        @else
            <form method="GET" class="mb-4">
                <input type="hidden" name="view" value="clients">
                <div class="relative max-w-md">
                    <input
                        type="text" name="q" value="{{ $search }}"
                        placeholder="Search clients by name…"
                        class="w-full rounded-lg border border-border bg-white pl-4 pr-24 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                    >
                    <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 px-3 py-1.5 rounded-md bg-brand-700 text-white text-xs font-medium hover:bg-brand-800 transition-colors">
                        Search
                    </button>
                </div>
                @if ($search)
                    <a href="{{ route('admin.assign-rms', ['view' => 'clients']) }}" class="inline-block mt-2 text-xs text-ink-faint hover:text-ink-muted">
                        Clear search ({{ $clients->total() }} match{{ $clients->total() === 1 ? '' : 'es' }})
                    </a>
                @endif
            </form>

            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-brand-50 text-left text-ink-faint">
                        <tr>
                            <th class="px-4 py-2 font-medium">Client</th>
                            <th class="px-4 py-2 font-medium">Currently Assigned</th>
                            <th class="px-4 py-2 font-medium">Assign To</th>
                            <th class="px-4 py-2 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($clients as $client)
                            <tr class="hover:bg-panel-muted transition-colors">
                                <td class="px-4 py-3 font-medium max-w-md">
                                    <div class="line-clamp-2">{{ $client->name }}</div>
                                </td>
                                <td class="px-4 py-3 text-ink-muted">
                                    {{ $client->assignedRm->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('admin.assign-rms.clients.update', $client) }}">
                                        @csrf
                                        <select
                                            name="assigned_rm_id" onchange="this.form.submit()"
                                            class="rounded-lg border border-border bg-white px-3 py-1.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                                        >
                                            <option value="">— Unassigned —</option>
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
                                <td colspan="4" class="px-4 py-8 text-center text-ink-faint">No clients match this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $clients->links() }}
            </div>
        @endif
</x-layout>
