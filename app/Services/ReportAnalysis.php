<?php

namespace App\Services;

use App\Models\ClientReport;
use App\Models\StateCorporation;
use App\Models\User;
use App\Support\ClientReportOptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The analysis of the RMs' client reports (2026-11-03, per the boss): which clients to prioritise, from the most
 * critical to the least; which clients get the most reports; where the clients stand in the pipeline; which RMs are
 * working and reporting well, which are new, and which are not active.
 *
 * A client's standing is its latest report (by report date). Its priority comes from how close that stage is to
 * business, whether the agreed follow-up date has passed, how much work has gone into it (its reports) and whether
 * it is going cold (a live client with no report for a while).
 */
class ReportAnalysis
{
    /** How close each stage is to business: the base of a client's priority */
    public const STAGE_WEIGHT = [
        'Proposal/information requested' => 100,
        'Institution interested' => 90,
        'Meeting held' => 80,
        'Meeting scheduled' => 75,
        'Meeting requested' => 60,
        'Follow-up in progress' => 55,
        'Relevant contact person identified' => 45,
        'Awaiting response' => 40,
        'Initial contact successful' => 35,
        'Initial contact attempted' => 20,
        'Not yet contacted' => 10,
    ];

    /** Stages where there is nothing left to chase: listed last, not prioritised */
    public const CLOSED_STAGES = ['Not interested', 'No current opportunity', 'Opportunity completed/closed'];

    /** Stages at or past real interest: the clients closest to an order */
    public const HOT_STAGES = ['Proposal/information requested', 'Institution interested', 'Meeting held', 'Meeting scheduled'];

    /** A live client with no report for longer than this is going cold */
    public const COLD_AFTER_DAYS = 14;

    /** An RM who joined within this many days is new */
    public const NEW_RM_DAYS = 14;

    /** An RM with no report for longer than this is not active */
    public const INACTIVE_AFTER_DAYS = 14;

    /** Reporting well: a report in the last 7 days and at least this many in the last 30 */
    public const WORKING_WELL_MONTH = 4;

    public const BANDS = [
        'critical' => 'Critical',
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
        'closed' => 'Closed',
    ];

    public const RM_STATUSES = [
        'working' => 'Working well',
        'reporting' => 'Reporting',
        'new_reporting' => 'New, reporting',
        'new_waiting' => 'New, no report yet',
        'quiet' => 'Not active',
        'never' => 'Never reported',
    ];

    /**
     * @return array{headline: array<string, int>, clients: Collection, top_clients: Collection, stages: Collection, rms: Collection, engagement: Collection, weeks: list<array{label: string, count: int}>, inactive_rm_accounts: int}
     */
    public function build(): array
    {
        $reports = ClientReport::query()->with(['client:id,name,assigned_rm_id', 'rm:id,name'])
            ->orderByDesc('report_date')->orderByDesc('id')->get();
        $today = today();

        $clients = $this->clients($reports, $today);
        $rms = $this->rms($reports, $clients, $today);
        $recent = $reports->filter(fn (ClientReport $r) => $r->report_date?->gte($today->copy()->subDays(29)));

        return [
            'headline' => [
                'reports' => $reports->count(),
                'reports_30' => $recent->count(),
                'clients' => $clients->count(),
                'clients_critical' => $clients->where('band', 'critical')->count(),
                'clients_hot' => $clients->where('hot', true)->count(),
                'follow_up_overdue' => $clients->where('overdue_days', '>', 0)->where('band', '!=', 'closed')->count(),
                'going_cold' => $clients->where('cold', true)->count(),
                'rms' => $rms->count(),
                'rms_reporting_30' => $recent->pluck('rm_id')->unique()->intersect($rms->pluck('rm.id'))->count(),
                'rms_working' => $rms->where('status', 'working')->count(),
                'rms_new' => $rms->whereIn('status', ['new_reporting', 'new_waiting'])->count(),
                'rms_inactive' => $rms->whereIn('status', ['quiet', 'never'])->count(),
            ],
            'clients' => $clients,
            'top_clients' => $clients->sortByDesc(fn ($c) => [$c['reports'], $c['last']?->timestamp ?? 0])->take(10)->values(),
            'stages' => $this->stages($clients),
            'rms' => $rms,
            'engagement' => $reports->groupBy(fn ($r) => $r->engagement_type ?: 'Not set')->map->count()->sortDesc(),
            'weeks' => collect(range(11, 0))->map(function (int $back) use ($reports, $today) {
                $start = $today->copy()->startOfWeek()->subWeeks($back);
                $end = $start->copy()->endOfWeek();

                return ['label' => $start->format('j M'), 'count' => $reports->filter(fn ($r) => $r->report_date?->between($start, $end))->count()];
            })->all(),
            'inactive_rm_accounts' => User::query()->where('role', User::ROLE_RM)->where('is_active', false)->count(),
        ];
    }

    /**
     * Every reported client with its standing and priority, most critical first.
     *
     * @param  Collection<int, ClientReport>  $reports  newest first
     * @return Collection<int, array{client: ?StateCorporation, rm: ?User, rms: int, reports: int, reports_30: int, first: ?Carbon, last: ?Carbon, days: int, stage: string, engagement: ?string, outcome: ?string, next_action: ?string, follow_up: ?Carbon, overdue_days: int, hot: bool, cold: bool, score: int, band: string, reasons: list<string>}>
     */
    private function clients(Collection $reports, Carbon $today): Collection
    {
        return $reports->groupBy('state_corporation_id')->map(function (Collection $group) use ($today) {
            /** @var ClientReport $latest */
            $latest = $group->first();
            $stage = (string) $latest->current_stage;
            $last = $latest->report_date;
            $days = $last ? (int) $last->copy()->startOfDay()->diffInDays($today) : 0;
            $followUp = $latest->follow_up_date;
            $overdue = $followUp && $followUp->lt($today) ? (int) $followUp->diffInDays($today) : 0;
            $closed = in_array($stage, self::CLOSED_STAGES, true);
            $weight = self::STAGE_WEIGHT[$stage] ?? 30;
            $cold = ! $closed && $weight >= 40 && $days > self::COLD_AFTER_DAYS;

            $score = $closed ? 0 : $weight + ($overdue > 0 ? 15 : 0) + min($group->count(), 10) * 2 + ($cold ? 10 : 0);
            $band = match (true) {
                $closed => 'closed',
                $score >= 95 => 'critical',
                $score >= 70 => 'high',
                $score >= 45 => 'medium',
                default => 'low',
            };

            $reasons = array_values(array_filter([
                $stage ?: 'No stage',
                $overdue > 0 ? "follow-up {$overdue} ".($overdue === 1 ? 'day' : 'days').' overdue' : null,
                $group->count().' '.($group->count() === 1 ? 'report' : 'reports'),
                $cold ? "no report for {$days} days" : null,
            ]));

            return [
                'client' => $latest->client,
                'rm' => $latest->rm,
                'rms' => $group->pluck('rm_id')->unique()->count(),
                'reports' => $group->count(),
                'reports_30' => $group->filter(fn ($r) => $r->report_date?->gte($today->copy()->subDays(29)))->count(),
                'first' => $group->last()->report_date,
                'last' => $last,
                'days' => $days,
                'stage' => $stage,
                'engagement' => $latest->engagement_type,
                'outcome' => $latest->outcome,
                'next_action' => $latest->next_action,
                'follow_up' => $followUp,
                'overdue_days' => $overdue,
                'hot' => in_array($stage, self::HOT_STAGES, true),
                'cold' => $cold,
                'score' => $score,
                'band' => $band,
                'reasons' => $reasons,
            ];
        })->sortByDesc(fn (array $c) => [$c['score'], $c['last']?->timestamp ?? 0])->values();
    }

    /**
     * Clients by the stage of their latest report, in pipeline order.
     *
     * @return Collection<int, array{stage: string, count: int, closed: bool, hot: bool}>
     */
    private function stages(Collection $clients): Collection
    {
        $counts = $clients->countBy('stage');

        return collect(ClientReportOptions::STAGES)->map(fn (string $stage) => [
            'stage' => $stage,
            'count' => (int) ($counts[$stage] ?? 0),
            'closed' => in_array($stage, self::CLOSED_STAGES, true),
            'hot' => in_array($stage, self::HOT_STAGES, true),
        ]);
    }

    /**
     * Every active RM with how they report, best first; new RMs are told apart from RMs who are not active.
     *
     * @param  Collection<int, ClientReport>  $reports
     * @param  Collection<int, array<string, mixed>>  $clients
     * @return Collection<int, array{rm: User, joined: ?Carbon, joined_days: int, reports: int, reports_7: int, reports_30: int, clients: int, assigned: int, coverage: ?int, hot: int, last: ?Carbon, days: ?int, per_week: float, status: string}>
     */
    private function rms(Collection $reports, Collection $clients, Carbon $today): Collection
    {
        $byRm = $reports->groupBy('rm_id');
        $hotByRm = $clients->where('hot', true)->groupBy(fn ($c) => $c['rm']?->id)->map->count();

        $order = array_flip(array_keys(self::RM_STATUSES));

        return User::assignableRms()->orderBy('name')->get()->map(function (User $rm) use ($byRm, $hotByRm, $today) {
            $mine = $byRm->get($rm->id, collect());
            $last = $mine->first()?->report_date;
            $days = $last ? (int) $last->copy()->startOfDay()->diffInDays($today) : null;
            $joinedDays = $rm->created_at ? (int) $rm->created_at->copy()->startOfDay()->diffInDays($today) : 999;
            $reports7 = $mine->filter(fn ($r) => $r->report_date?->gte($today->copy()->subDays(6)))->count();
            $reports30 = $mine->filter(fn ($r) => $r->report_date?->gte($today->copy()->subDays(29)))->count();
            $assigned = StateCorporation::query()->assignedToRm($rm->id)->count();
            $reportedClients = $mine->pluck('state_corporation_id')->unique()->count();
            $isNew = $joinedDays <= self::NEW_RM_DAYS;

            $status = match (true) {
                $isNew && $mine->isEmpty() => 'new_waiting',
                $reports7 > 0 && $reports30 >= self::WORKING_WELL_MONTH => 'working',
                $isNew => 'new_reporting',
                $mine->isEmpty() => 'never',
                $days > self::INACTIVE_AFTER_DAYS => 'quiet',
                default => 'reporting',
            };

            return [
                'rm' => $rm,
                'joined' => $rm->created_at,
                'joined_days' => $joinedDays,
                'reports' => $mine->count(),
                'reports_7' => $reports7,
                'reports_30' => $reports30,
                'clients' => $reportedClients,
                'assigned' => $assigned,
                'coverage' => $assigned > 0 ? (int) round(min($reportedClients, $assigned) / $assigned * 100) : null,
                'hot' => (int) ($hotByRm[$rm->id] ?? 0),
                'last' => $last,
                'days' => $days,
                'per_week' => round($mine->count() / max(1, min($joinedDays, 120) / 7), 1),
                'status' => $status,
            ];
        })->sortBy([
            fn ($a, $b) => $order[$a['status']] <=> $order[$b['status']],
            fn ($a, $b) => $b['reports_30'] <=> $a['reports_30'],
            fn ($a, $b) => $b['reports'] <=> $a['reports'],
            fn ($a, $b) => strcmp($a['rm']->name, $b['rm']->name),
        ])->values();
    }
}
