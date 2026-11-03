<x-layout title="Report Analysis">
    @php
        $h = $headline;
        $bandTone = [
            'critical' => 'bg-danger-bg text-danger',
            'high' => 'bg-gold-100 text-gold-700',
            'medium' => 'bg-brand-100 text-brand-800',
            'low' => 'bg-panel-muted text-ink-muted',
            'closed' => 'bg-panel-muted text-ink-faint',
        ];
        $statusTone = [
            'working' => 'bg-success-bg text-success',
            'reporting' => 'bg-brand-100 text-brand-800',
            'new_reporting' => 'bg-[#efe7f6] text-[#4f3268]',
            'new_waiting' => 'bg-[#efe7f6] text-[#4f3268]',
            'quiet' => 'bg-danger-bg text-danger',
            'never' => 'bg-danger-bg text-danger',
        ];
        $ago = fn (?int $days) => $days === null ? 'never' : ($days === 0 ? 'today' : ($days === 1 ? 'yesterday' : $days.' days ago'));
        $maxWeek = max(1, collect($weeks)->max('count'));
        $maxStage = max(1, $stages->max('count'));
        $maxTop = max(1, $top_clients->max('reports') ?? 1);
        $bandCounts = $clients->countBy('band');
        $statusCounts = $rms->countBy('status');
    @endphp

    <x-page-header
        title="Report Analysis"
        :subtitle="'What the RMs\' '.number_format($h['reports']).' client reports say: which clients to prioritise, which RMs are working well, who is new and who is not active.'"
        :back="route('reports.index')"
        back-label="Back to client reports"
    />

    {{-- Headline --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        <x-stat-tile label="Critical clients" icon="alert-triangle" tone="rose" :value="number_format($h['clients_critical'])"
            :hint="number_format($h['clients_hot']).' interested or meeting · '.number_format($h['follow_up_overdue']).' follow-ups overdue · '.number_format($h['going_cold']).' going cold'" />
        <x-stat-tile label="Clients reported on" icon="building" tone="green" :value="number_format($h['clients'])"
            :hint="number_format($h['reports']).' reports in all · '.number_format($h['reports_30']).' in the last 30 days'" />
        <x-stat-tile label="RMs working well" icon="circle-check" tone="teal" :value="number_format($h['rms_working']).' of '.number_format($h['rms'])"
            :hint="number_format($h['rms_reporting_30']).' RMs reported in the last 30 days · '.number_format($h['rms_new']).' new'" />
        <x-stat-tile label="RMs not active" icon="clock" tone="gold" :value="number_format($h['rms_inactive'])"
            :hint="'No report in '.\App\Services\ReportAnalysis::INACTIVE_AFTER_DAYS.' days, or never (new RMs not counted)'" />
    </div>

    {{-- Clients to prioritise --}}
    <section x-data="{ band: '', all: false }" class="mb-8 bg-panel border border-border rounded-xl shadow-sm">
        <div class="px-5 pt-5">
            <h2 class="font-medium text-ink">Clients to prioritise, most critical first</h2>
            <p class="mt-1 text-xs text-ink-faint">
                Each client is ranked on its latest report: how close the stage is to business (a proposal requested or the institution interested comes first),
                whether the follow-up date has passed, how many reports it has had, and whether it is going cold (no report for {{ \App\Services\ReportAnalysis::COLD_AFTER_DAYS }} days while still live).
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" x-on:click="band = ''" :class="band === '' ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-border'" class="rounded-full border px-3 py-1 text-xs font-medium">All {{ $clients->count() }}</button>
                @foreach (\App\Services\ReportAnalysis::BANDS as $key => $label)
                    <button type="button" x-on:click="band = '{{ $key }}'" :class="band === '{{ $key }}' ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-border'" class="rounded-full border px-3 py-1 text-xs font-medium">
                        {{ $label }} <span class="tabular-nums text-ink-faint">{{ $bandCounts[$key] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
        </div>
        <div class="mt-4 border-t border-border overflow-x-auto">
            <table data-stack class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium">#</th>
                        <th class="px-4 py-2 font-medium">Client</th>
                        <th class="px-4 py-2 font-medium">Priority</th>
                        <th class="px-4 py-2 font-medium">Latest stage</th>
                        <th class="px-4 py-2 font-medium text-right">Reports</th>
                        <th class="px-4 py-2 font-medium">Last report</th>
                        <th class="px-4 py-2 font-medium">Next action</th>
                        <th class="px-4 py-2 font-medium">RM</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clients as $i => $c)
                        <tr class="border-t border-border align-top" x-show="(band === '' || band === '{{ $c['band'] }}') && (all || band !== '' || {{ $i }} < 25)">
                            <td class="px-4 py-2 tabular-nums text-ink-faint" data-label="#">{{ $i + 1 }}</td>
                            <td class="px-4 py-2 min-w-[200px]" data-label="Client">
                                @if ($c['client'])
                                    <a href="{{ route('state-corporations.show', $c['client']) }}" class="font-medium text-brand-700 hover:underline">{{ $c['client']->name }}</a>
                                @else
                                    <span class="text-ink-faint">Client removed</span>
                                @endif
                                <span class="block text-xs text-ink-faint">{{ implode(' · ', array_slice($c['reasons'], 1)) }}</span>
                            </td>
                            <td class="px-4 py-2" data-label="Priority">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $bandTone[$c['band']] }}">{{ \App\Services\ReportAnalysis::BANDS[$c['band']] }}</span>
                            </td>
                            <td class="px-4 py-2" data-label="Latest stage">{{ $c['stage'] ?: '—' }}</td>
                            <td class="px-4 py-2 text-right tabular-nums" data-label="Reports">{{ $c['reports'] }}</td>
                            <td class="px-4 py-2 whitespace-nowrap" data-label="Last report">
                                {{ $c['last']?->format('j M Y') ?? '—' }}
                                <span class="block text-xs {{ $c['cold'] ? 'text-danger' : 'text-ink-faint' }}">{{ $ago($c['days']) }}</span>
                            </td>
                            <td class="px-4 py-2 min-w-[220px]" data-label="Next action">
                                {{ $c['next_action'] ?: '—' }}
                                @if ($c['follow_up'])
                                    <span class="block text-xs {{ $c['overdue_days'] > 0 && $c['band'] !== 'closed' ? 'text-danger' : 'text-ink-faint' }}">
                                        Follow up {{ $c['follow_up']->format('j M') }}{{ $c['overdue_days'] > 0 && $c['band'] !== 'closed' ? ' · '.$c['overdue_days'].' days overdue' : '' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap" data-label="RM">
                                {{ $c['rm']?->name ?? '—' }}
                                @if ($c['rms'] > 1)<span class="block text-xs text-ink-faint">{{ $c['rms'] }} RMs reported</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($clients->count() > 25)
            <div class="border-t border-border px-5 py-3" x-show="band === ''">
                <button type="button" x-on:click="all = ! all" class="text-sm font-medium text-brand-700 hover:underline" x-text="all ? 'Show the top 25' : 'Show all {{ $clients->count() }} clients'"></button>
            </div>
        @endif
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
        {{-- Most reported clients --}}
        <section class="bg-panel border border-border rounded-xl p-5 shadow-sm">
            <h2 class="font-medium text-ink">Clients with the most reports</h2>
            <p class="mt-1 text-xs text-ink-faint">Where the RMs are putting in the most work, and the stage it has reached.</p>
            <ul class="mt-4 flex flex-col gap-3">
                @foreach ($top_clients as $c)
                    <li class="text-sm">
                        <span class="flex justify-between gap-3">
                            <span class="truncate font-medium text-ink">{{ $c['client']?->name ?? 'Client removed' }}</span>
                            <span class="whitespace-nowrap tabular-nums text-ink-faint">{{ $c['reports'] }} reports</span>
                        </span>
                        <span class="mt-1 block h-2 overflow-hidden rounded-full bg-panel-muted"><span class="block h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400" style="width: {{ round($c['reports'] / $maxTop * 100, 1) }}%"></span></span>
                        <span class="mt-0.5 block text-xs text-ink-faint">{{ $c['stage'] }} · {{ $c['rm']?->name }} · last {{ $ago($c['days']) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Pipeline --}}
        <section class="bg-panel border border-border rounded-xl p-5 shadow-sm">
            <h2 class="font-medium text-ink">Where the clients stand</h2>
            <p class="mt-1 text-xs text-ink-faint">Clients by the stage of their latest report, from first contact to closed.</p>
            <ul class="mt-4 flex flex-col gap-2">
                @foreach ($stages as $s)
                    <li class="text-sm">
                        <span class="flex justify-between gap-3">
                            <span class="{{ $s['hot'] ? 'font-medium text-ink' : 'text-ink-muted' }}">{{ $s['stage'] }}</span>
                            <span class="tabular-nums text-ink-faint">{{ $s['count'] }}</span>
                        </span>
                        <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-panel-muted"><span class="block h-full rounded-full {{ $s['hot'] ? 'bg-gold-500' : ($s['closed'] ? 'bg-ink-faint' : 'bg-brand-500') }}" style="width: {{ round($s['count'] / $maxStage * 100, 1) }}%"></span></span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-ink-faint"><span class="inline-block size-2 rounded-full bg-gold-500"></span> interested or meeting, the closest to business</p>
        </section>
    </div>

    {{-- RMs --}}
    <section x-data="{ status: '' }" class="mb-8 bg-panel border border-border rounded-xl shadow-sm">
        <div class="px-5 pt-5">
            <h2 class="font-medium text-ink">How each RM is reporting</h2>
            <p class="mt-1 text-xs text-ink-faint">
                Working well: a report in the last 7 days and at least {{ \App\Services\ReportAnalysis::WORKING_WELL_MONTH }} in the last 30.
                New: joined in the last {{ \App\Services\ReportAnalysis::NEW_RM_DAYS }} days, so not yet judged.
                Not active: no report in {{ \App\Services\ReportAnalysis::INACTIVE_AFTER_DAYS }} days.
                Coverage: the share of the RM's assigned clients they have reported on.
                @if ($inactive_rm_accounts) {{ $inactive_rm_accounts }} deactivated RM accounts are not listed. @endif
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" x-on:click="status = ''" :class="status === '' ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-border'" class="rounded-full border px-3 py-1 text-xs font-medium">All {{ $rms->count() }}</button>
                @foreach (\App\Services\ReportAnalysis::RM_STATUSES as $key => $label)
                    <button type="button" x-on:click="status = '{{ $key }}'" :class="status === '{{ $key }}' ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-border'" class="rounded-full border px-3 py-1 text-xs font-medium">
                        {{ $label }} <span class="tabular-nums text-ink-faint">{{ $statusCounts[$key] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
        </div>
        <div class="mt-4 border-t border-border overflow-x-auto">
            <table data-stack class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium">RM</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 font-medium text-right">Last 7 days</th>
                        <th class="px-4 py-2 font-medium text-right">Last 30 days</th>
                        <th class="px-4 py-2 font-medium text-right">All</th>
                        <th class="px-4 py-2 font-medium text-right">Clients reported</th>
                        <th class="px-4 py-2 font-medium text-right">Interested / meeting</th>
                        <th class="px-4 py-2 font-medium">Last report</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rms as $r)
                        <tr class="border-t border-border" x-show="status === '' || status === '{{ $r['status'] }}'">
                            <td class="px-4 py-2" data-label="RM">
                                <a href="{{ route('reports.index', ['rm_id' => $r['rm']->id]) }}" class="font-medium text-brand-700 hover:underline">{{ $r['rm']->name }}</a>
                                <span class="block text-xs text-ink-faint">Joined {{ $r['joined']?->format('j M Y') ?? '—' }} · {{ $r['per_week'] }} reports a week</span>
                            </td>
                            <td class="px-4 py-2" data-label="Status"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap {{ $statusTone[$r['status']] }}">{{ \App\Services\ReportAnalysis::RM_STATUSES[$r['status']] }}</span></td>
                            <td class="px-4 py-2 text-right tabular-nums" data-label="Last 7 days">{{ $r['reports_7'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums" data-label="Last 30 days">{{ $r['reports_30'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums" data-label="All">{{ $r['reports'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums" data-label="Clients reported">
                                {{ $r['clients'] }}{{ $r['assigned'] ? ' of '.$r['assigned'] : '' }}
                                @if ($r['coverage'] !== null)<span class="block text-xs text-ink-faint">{{ $r['coverage'] }}% coverage</span>@endif
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums" data-label="Interested / meeting">{{ $r['hot'] }}</td>
                            <td class="px-4 py-2 whitespace-nowrap" data-label="Last report">
                                {{ $r['last']?->format('j M Y') ?? '—' }}
                                <span class="block text-xs {{ in_array($r['status'], ['quiet', 'never'], true) ? 'text-danger' : 'text-ink-faint' }}">{{ $ago($r['days']) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
        {{-- Weekly trend --}}
        <section class="lg:col-span-2 bg-panel border border-border rounded-xl p-5 shadow-sm">
            <h2 class="font-medium text-ink">Reports each week</h2>
            <div class="mt-4 flex h-40 items-end gap-1.5" role="img" aria-label="Reports logged in each of the last 12 weeks">
                @foreach ($weeks as $w)
                    <div class="group relative flex h-full flex-1 flex-col justify-end">
                        <span class="pointer-events-none absolute -top-6 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded bg-ink px-2 py-0.5 text-xs text-white opacity-0 group-hover:opacity-100">{{ $w['count'] }} reports</span>
                        <div class="rounded-t bg-gradient-to-t from-brand-700 to-brand-400" style="height: {{ max(2, $w['count'] / $maxWeek * 100) }}%"></div>
                        <span class="mt-1 text-center text-[10px] text-ink-faint">{{ $w['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- How RMs engage --}}
        <section class="bg-panel border border-border rounded-xl p-5 shadow-sm">
            <h2 class="font-medium text-ink">How the RMs engage</h2>
            @php $engTotal = max(1, $engagement->sum()); @endphp
            <ul class="mt-4 flex flex-col gap-2 text-sm">
                @foreach ($engagement as $type => $count)
                    <li class="flex justify-between gap-3"><span class="text-ink-muted">{{ $type }}</span><span class="tabular-nums text-ink-faint">{{ $count }} · {{ round($count / $engTotal * 100) }}%</span></li>
                @endforeach
            </ul>
        </section>
    </div>
</x-layout>
