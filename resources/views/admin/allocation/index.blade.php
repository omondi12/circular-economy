<x-layout title="Areas & Rules">
    @php
        $max = \App\Services\RmAllocation::MAX_ACCOUNTS;
        $min = \App\Services\RmAllocation::MIN_COUNTIES;
        $maxC = \App\Services\RmAllocation::MAX_COUNTIES;
        $breaks = $check['over']->count() + $check['supervisors']->reject(fn ($s) => $s['area_ok'])->count() + $check['counties_without_area']->count() + $check['unassigned']->count();
        // After a refused save, the choices just made, not the saved ones
        $chosen = collect(old('area', $areas->all()))->map(fn ($id) => $id ? (int) $id : null);
        $areaCounts = $chosen->filter()->countBy();
    @endphp

    <x-page-header
        title="Assign RMs"
        :subtitle="'The boss\'s rules: no RM has more than '.$max.' accounts (a County Government counts the same as a parastatal), and every supervisor\'s area has '.$min.' or '.$maxC.' counties.'"
        :back="route('admin.dashboard')"
        back-label="Back to admin"
    />

    <div class="inline-flex flex-wrap rounded-lg border border-border bg-white p-1 text-sm mb-6">
        @foreach (['ministries' => 'Ministries', 'state-departments' => 'State Departments', 'clients' => 'Clients', 'supervisors' => 'Supervisors', 'allocation' => 'Areas & rules'] as $key => $label)
            <a href="{{ $key === 'allocation' ? route('admin.allocation.index') : route('admin.assign-rms', ['view' => $key]) }}"
                @class(['px-3 py-1.5 rounded-md font-medium transition-colors whitespace-nowrap', 'bg-brand-700 text-white' => $key === 'allocation', 'text-ink-faint hover:text-ink' => $key !== 'allocation'])>{{ $label }}</a>
        @endforeach
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-danger bg-danger-bg px-4 py-3 text-sm text-danger" role="alert">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    {{-- 1. Rules check --}}
    <h2 class="section-title">Rules check</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-tile label="RMs over {{ $max }} accounts" icon="alert-triangle" tone="rose" :value="number_format($check['over']->count())" :hint="'Of '.$check['rms']->count().' active RMs · '.$check['empty']->count().' have no accounts yet'" />
        <x-stat-tile label="Supervisors outside {{ $min }}-{{ $maxC }} counties" icon="building" tone="gold" :value="number_format($check['supervisors']->reject(fn ($s) => $s['area_ok'])->count())" :hint="'Of '.$check['supervisors']->count().' active supervisors'" />
        <x-stat-tile label="Counties without a supervisor" icon="building-community" tone="teal" :value="number_format($check['counties_without_area']->count())" :hint="'Of '.$counties->count().' counties'" />
        <x-stat-tile label="Clients nobody covers" icon="inbox" tone="violet" :value="number_format($check['unassigned']->count())" :hint="$check['no_supervisor']->count().' RMs are in no supervisor\'s team'" />
    </div>
    @if ($breaks === 0)
        <p class="mb-8 rounded-lg bg-success-bg px-4 py-3 text-sm text-success">Every rule is met.</p>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
        <section class="bg-panel border border-border rounded-xl p-5 shadow-sm">
            <h3 class="font-medium text-ink">RMs over {{ $max }} accounts</h3>
            <p class="mt-1 text-xs text-ink-faint">Balance their supervisor's team below, or move clients on the Clients tab.</p>
            <ul class="mt-3 divide-y divide-border text-sm">
                @forelse ($check['over'] as $r)
                    <li class="flex justify-between gap-3 py-2">
                        <span>{{ $r['rm']->name }} <span class="block text-xs text-ink-faint">{{ $r['rm']->supervisor?->name ? 'Team of '.$r['rm']->supervisor->name : 'In no team' }}</span></span>
                        <span class="whitespace-nowrap tabular-nums text-danger">{{ $r['accounts'] }} accounts{{ $r['counties'] ? ' · '.$r['counties'].' counties' : '' }}</span>
                    </li>
                @empty
                    <li class="py-2 text-ink-faint">None.</li>
                @endforelse
            </ul>
        </section>
        <section class="bg-panel border border-border rounded-xl p-5 shadow-sm">
            <h3 class="font-medium text-ink">Still to sort out</h3>
            <dl class="mt-3 flex flex-col gap-3 text-sm">
                <div><dt class="text-ink-faint">RMs with no accounts ({{ $check['empty']->count() }})</dt><dd>{{ $check['empty']->map(fn ($r) => $r['rm']->name)->implode(', ') ?: 'None' }}</dd></div>
                <div><dt class="text-ink-faint">RMs in no supervisor's team ({{ $check['no_supervisor']->count() }})</dt><dd>{{ $check['no_supervisor']->map(fn ($r) => $r['rm']->name)->implode(', ') ?: 'None' }} @if ($check['no_supervisor']->isNotEmpty())<a href="{{ route('admin.assign-rms', ['view' => 'supervisors']) }}" class="text-brand-700 hover:underline">Put them in a team</a>@endif</dd></div>
                <div><dt class="text-ink-faint">Counties without a supervisor ({{ $check['counties_without_area']->count() }})</dt><dd>{{ $check['counties_without_area']->implode(', ') ?: 'None' }}</dd></div>
                <div><dt class="text-ink-faint">Clients nobody covers ({{ $check['unassigned']->count() }})</dt><dd>{{ $check['unassigned']->implode(', ') ?: 'None' }}</dd></div>
            </dl>
        </section>
    </div>

    {{-- 2. Supervisors: their area, team and a balance --}}
    <h2 class="section-title">Supervisors</h2>
    <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto mb-8">
        <table data-stack class="w-full text-sm">
            <thead class="bg-brand-50 text-left text-ink-faint">
                <tr>
                    <th class="px-4 py-2 font-medium">Supervisor</th>
                    <th class="px-4 py-2 font-medium">Area</th>
                    <th class="px-4 py-2 font-medium text-right">RMs</th>
                    <th class="px-4 py-2 font-medium text-right">Accounts</th>
                    <th class="px-4 py-2 font-medium">To fix</th>
                    <th class="px-4 py-2 font-medium"><span class="sr-only">Balance</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($check['supervisors'] as $s)
                    <tr class="align-top">
                        <td class="px-4 py-3 font-medium" data-label="Supervisor">{{ $s['supervisor']->name }}</td>
                        <td class="px-4 py-3 min-w-[220px]" data-label="Area">
                            <span @class(['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', 'bg-success-bg text-success' => $s['area_ok'], 'bg-danger-bg text-danger' => ! $s['area_ok']])>{{ $s['area']->count() }} {{ $s['area']->count() === 1 ? 'county' : 'counties' }}</span>
                            <span class="block mt-1 text-xs text-ink-muted">{{ $s['area']->implode(', ') ?: 'No area yet: choose counties below' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums" data-label="RMs">{{ $s['rms'] }}</td>
                        <td class="px-4 py-3 text-right tabular-nums" data-label="Accounts">{{ $s['accounts'] }}</td>
                        <td class="px-4 py-3 min-w-[240px] text-xs" data-label="To fix">
                            @if ($s['over'])<p class="text-danger">{{ $s['over'] }} RMs over {{ $max }} accounts</p>@endif
                            @if ($s['area_elsewhere']->isNotEmpty())<p>Area counties held outside the team: {{ $s['area_elsewhere']->implode(', ') }}</p>@endif
                            @if ($s['outside_area']->isNotEmpty())<p class="text-ink-faint">Team holds counties outside its area: {{ $s['outside_area']->implode(', ') }}</p>@endif
                            @if (! $s['over'] && $s['area_elsewhere']->isEmpty() && $s['outside_area']->isEmpty() && $s['area_ok'])<p class="text-success">Nothing</p>@endif
                        </td>
                        <td class="px-4 py-3 text-right" data-label="">
                            @if ($s['rms'])
                                <a href="{{ route('admin.allocation.balance', $s['supervisor']) }}" class="btn btn-secondary">Balance team</a>
                            @else
                                <a href="{{ route('admin.assign-rms', ['view' => 'supervisors']) }}" class="text-xs text-brand-700 hover:underline">Add RMs to the team</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state title="No active supervisors yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 3. Supervisor areas --}}
    <h2 class="section-title">Supervisor areas</h2>
    <p class="mb-3 text-sm text-ink-faint">Choose the supervisor whose area each county is in, then save. Each supervisor must end with {{ $min }} or {{ $maxC }} counties; a county can wait without one while you set the areas up.</p>
    <form method="POST" action="{{ route('admin.allocation.areas') }}" x-data="{ counts: @js($areaCounts) }" class="mb-8">
        @csrf
        <div class="mb-3 flex flex-wrap gap-2 text-xs">
            @foreach ($supervisors as $sup)
                <span class="inline-flex items-center gap-1 rounded-full border border-border bg-panel px-3 py-1">
                    {{ $sup->name }}
                    <span class="tabular-nums font-medium" :class="(counts[{{ $sup->id }}] || 0) >= {{ $min }} && (counts[{{ $sup->id }}] || 0) <= {{ $maxC }} ? 'text-success' : 'text-danger'" x-text="counts[{{ $sup->id }}] || 0">{{ $areaCounts[$sup->id] ?? 0 }}</span>
                </span>
            @endforeach
        </div>
        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table data-stack class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium">County</th>
                        <th class="px-4 py-2 font-medium">Held by RM</th>
                        <th class="px-4 py-2 font-medium">Supervisor's area</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($counties as $county)
                        @php $holder = $holders[$county->id] ?? null; @endphp
                        <tr>
                            <td class="px-4 py-2 font-medium" data-label="County">{{ \Illuminate\Support\Str::after($county->name, 'County Government of ') }}</td>
                            <td class="px-4 py-2 text-ink-muted" data-label="Held by RM">{{ $holder?->name ?? '—' }}@if ($holder?->supervisor)<span class="block text-xs text-ink-faint">Team of {{ $holder->supervisor->name }}</span>@endif</td>
                            <td class="px-4 py-2" data-label="Supervisor's area">
                                <select name="area[{{ $county->id }}]" class="field-control w-auto"
                                    x-on:change="const was = $event.target.dataset.was; if (was) counts[was] = (counts[was] || 1) - 1; if ($event.target.value) counts[$event.target.value] = (counts[$event.target.value] || 0) + 1; $event.target.dataset.was = $event.target.value"
                                    data-was="{{ $chosen[$county->id] ?? '' }}">
                                    <option value="">— No supervisor —</option>
                                    @foreach ($supervisors as $sup)
                                        <option value="{{ $sup->id }}" @selected(($chosen[$county->id] ?? null) === $sup->id)>{{ $sup->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4 flex justify-end"><button type="submit" class="btn btn-primary">Save areas</button></div>
    </form>
</x-layout>
