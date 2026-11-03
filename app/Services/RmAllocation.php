<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClientReport;
use App\Models\StateCorporation;
use App\Models\SupervisorCounty;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Allocating clients to RMs and RMs to supervisors by the boss's rules (2026-11-03):
 * - no RM has more than 7 accounts, a County Government counting the same as a parastatal;
 * - no supervisor's area has fewer than 4 or more than 5 counties.
 *
 * Supervisor areas say which counties each supervisor's team covers. The rules check shows every break of the rules.
 * Balancing a team brings its area's counties into the team, spreads them over its RMs and moves accounts off any
 * RM above 7 to the team's RMs with room - shown first as a plan, applied only on confirmation. A moved client gets
 * a direct assignment to its new RM (StateCorporation::assigned_rm_id).
 */
class RmAllocation
{
    public const MAX_ACCOUNTS = 7;

    public const MIN_COUNTIES = 4;

    public const MAX_COUNTIES = 5;

    public const COUNTY_CLASSIFICATION = 'County Government';

    /**
     * @return Collection<int, StateCorporation>
     */
    public function counties(): Collection
    {
        return StateCorporation::query()->where('classification', self::COUNTY_CLASSIFICATION)->orderBy('name')->get();
    }

    /**
     * The supervisor each county's area belongs to.
     *
     * @return Collection<int, int> county id => supervisor id
     */
    public function areas(): Collection
    {
        return SupervisorCounty::query()->pluck('supervisor_id', 'state_corporation_id');
    }

    /**
     * Saves every county's supervisor at once. A supervisor given counties must end with 4 or 5; a county may be left
     * without one while the areas are being set up (the rules check lists it).
     *
     * @param  array<int|string, int|string|null>  $countySupervisor  county id => supervisor id (empty: none)
     *
     * @throws ValidationException
     */
    public function saveAreas(array $countySupervisor, User $actor): void
    {
        $counties = $this->counties()->keyBy('id');
        $supervisors = User::query()->where('role', User::ROLE_SUPERVISOR)->where('is_active', true)->pluck('name', 'id');

        $chosen = collect($countySupervisor)
            ->mapWithKeys(fn ($supervisorId, $countyId) => [(int) $countyId => $supervisorId ? (int) $supervisorId : null])
            ->filter(fn ($supervisorId, $countyId) => $counties->has($countyId));

        if ($chosen->filter()->reject(fn ($id) => $supervisors->has($id))->isNotEmpty()) {
            throw ValidationException::withMessages(['areas' => 'Choose an active supervisor for every county.']);
        }

        $errors = $chosen->filter()->countBy()
            ->reject(fn (int $n) => $n >= self::MIN_COUNTIES && $n <= self::MAX_COUNTIES)
            ->map(fn (int $n, int $id) => $supervisors[$id].' would have '.$n.' '.($n === 1 ? 'county' : 'counties').': give each supervisor '.self::MIN_COUNTIES.' or '.self::MAX_COUNTIES.'.')
            ->values()->all();
        if ($errors) {
            throw ValidationException::withMessages(['areas' => $errors]);
        }

        $before = $this->areas();

        DB::transaction(function () use ($chosen) {
            SupervisorCounty::query()->whereIn('state_corporation_id', $chosen->keys())->delete();
            foreach ($chosen->filter() as $countyId => $supervisorId) {
                SupervisorCounty::create(['state_corporation_id' => $countyId, 'supervisor_id' => $supervisorId]);
            }
        });

        $changed = $chosen->filter(fn ($supervisorId, $countyId) => ($before[$countyId] ?? null) !== $supervisorId);
        if ($changed->isNotEmpty()) {
            AuditLog::record('supervisor_areas.saved', null, [
                'changes' => $changed->map(fn ($supervisorId, $countyId) => $counties[$countyId]->name.': '.($supervisorId ? $supervisors[$supervisorId] : 'no supervisor'))->values()->all(),
            ]);
        }
    }

    /**
     * Every break of the allocation rules.
     *
     * @return array{rms: Collection, over: Collection, empty: Collection, supervisors: Collection, unassigned: Collection, counties_without_area: Collection, no_supervisor: Collection}
     */
    public function check(): array
    {
        $holdings = $this->holdings();
        $rms = User::assignableRms()->with('supervisor:id,name')->orderBy('name')->get();
        $areas = $this->areas();
        $countyIds = $this->counties()->pluck('id')->flip();
        $names = StateCorporation::query()->pluck('name', 'id');

        $rmRows = $rms->map(function (User $rm) use ($holdings, $countyIds) {
            $mine = $holdings->filter(fn ($rmId) => $rmId === $rm->id)->keys();

            return ['rm' => $rm, 'accounts' => $mine->count(), 'counties' => $mine->filter(fn ($id) => $countyIds->has($id))->count()];
        });

        $supervisors = User::query()->where('role', User::ROLE_SUPERVISOR)->where('is_active', true)->orderBy('name')->get()
            ->map(function (User $supervisor) use ($rmRows, $holdings, $areas, $countyIds, $names) {
                $team = $rmRows->filter(fn ($r) => $r['rm']->supervisor_id === $supervisor->id);
                $teamIds = $team->map(fn ($r) => $r['rm']->id)->values();
                $area = $areas->filter(fn ($id) => $id === $supervisor->id)->keys();
                $teamCounties = $holdings->filter(fn ($rmId, $clientId) => $countyIds->has($clientId) && $teamIds->contains($rmId))->keys();

                return [
                    'supervisor' => $supervisor,
                    'rms' => $team->count(),
                    'accounts' => $team->sum('accounts'),
                    'area' => $area->map(fn ($id) => $names[$id])->sort()->values(),
                    'area_ok' => $area->count() >= self::MIN_COUNTIES && $area->count() <= self::MAX_COUNTIES,
                    // Area counties the team does not hold yet, and counties the team holds outside its area
                    'area_elsewhere' => $area->reject(fn ($id) => $teamCounties->contains($id))->map(fn ($id) => $names[$id])->sort()->values(),
                    'outside_area' => $teamCounties->reject(fn ($id) => $area->contains($id))->map(fn ($id) => $names[$id])->sort()->values(),
                    'over' => $team->where('accounts', '>', self::MAX_ACCOUNTS)->count(),
                ];
            });

        return [
            'rms' => $rmRows,
            'over' => $rmRows->where('accounts', '>', self::MAX_ACCOUNTS)->sortByDesc('accounts')->values(),
            'empty' => $rmRows->where('accounts', 0)->values(),
            'supervisors' => $supervisors,
            'unassigned' => $holdings->filter(fn ($rmId) => $rmId === null)->keys()->map(fn ($id) => $names[$id])->sort()->values(),
            'counties_without_area' => $countyIds->keys()->reject(fn ($id) => $areas->has($id))->map(fn ($id) => $names[$id])->sort()->values(),
            'no_supervisor' => $rmRows->filter(fn ($r) => $r['rm']->supervisor_id === null)->values(),
        ];
    }

    /**
     * The moves that would balance one supervisor's team, without making them.
     *
     * @return array{team: Collection<int, User>, moves: list<array{client: StateCorporation, from: ?User, to: User, reason: string}>, still_over: Collection, after: Collection}
     */
    public function plan(User $supervisor): array
    {
        $team = User::assignableRms()->where('supervisor_id', $supervisor->id)->orderBy('name')->get()->keyBy('id');
        $holdings = $this->holdings();
        $clients = StateCorporation::query()->get()->keyBy('id');
        $countyIds = $this->counties()->pluck('id')->flip();
        $area = $this->areas()->filter(fn ($id) => $id === $supervisor->id)->keys();
        $users = User::query()->whereIn('id', $holdings->filter()->unique()->values())->get()->keyBy('id');
        $moves = [];

        if ($team->isEmpty()) {
            return ['team' => $team, 'moves' => [], 'still_over' => collect(), 'after' => collect()];
        }

        // What each team RM holds as the plan goes on
        $load = $team->map(fn (User $rm) => $holdings->filter(fn ($rmId) => $rmId === $rm->id)->keys()->values());
        $reported = ClientReport::query()->whereIn('rm_id', $team->keys())->get(['rm_id', 'state_corporation_id'])
            ->groupBy('rm_id')->map(fn ($g) => $g->pluck('state_corporation_id')->countBy());
        $reports = fn (int $rmId, int $clientId) => (int) ($reported->get($rmId)?->get($clientId) ?? 0);
        $countiesOf = fn (int $rmId) => $load[$rmId]->filter(fn ($id) => $countyIds->has($id))->count();
        $move = function (int $clientId, ?int $fromId, int $toId, string $reason) use (&$load, &$moves, $clients, $team, $users) {
            if ($fromId !== null && $load->has($fromId)) {
                $load[$fromId] = $load[$fromId]->reject(fn ($id) => $id === $clientId)->values();
            }
            $load[$toId] = $load[$toId]->push($clientId);
            $moves[] = ['client' => $clients[$clientId], 'from' => $fromId ? ($team[$fromId] ?? $users[$fromId] ?? null) : null, 'to' => $team[$toId], 'reason' => $reason];
        };
        // The team RM with the fewest counties, then the fewest accounts
        $roomiest = fn (?int $except = null, bool $byCounties = true) => $team->keys()->reject(fn ($id) => $id === $except)
            ->sortBy(fn ($id) => [$byCounties ? $countiesOf($id) : 0, $load[$id]->count(), $team[$id]->name])->first();

        // 1. The area's counties come into the team
        foreach ($area as $countyId) {
            $holder = $holdings[$countyId] ?? null;
            if ($holder === null || ! $team->has($holder)) {
                $move($countyId, $holder, $roomiest(), $holder ? 'County in this supervisor\'s area, held outside the team' : 'County in this supervisor\'s area, not assigned');
            }
        }

        // 2. Counties spread over the team: an RM with two or more gives one to an RM with none
        while (($heavy = $team->keys()->first(fn ($id) => $countiesOf($id) > 1)) !== null
            && ($light = $team->keys()->sortBy(fn ($id) => [$load[$id]->count(), $team[$id]->name])->first(fn ($id) => $countiesOf($id) === 0)) !== null) {
            $county = $load[$heavy]->filter(fn ($id) => $countyIds->has($id))
                ->sortBy(fn ($id) => $reports($heavy, $id))->first();
            $move($county, $heavy, $light, 'Spreading the counties: one per RM');
        }

        // 3. No RM above 7: the accounts they have not reported on go first, counties stay, to the RMs with room
        foreach ($team->keys() as $rmId) {
            while ($load[$rmId]->count() > self::MAX_ACCOUNTS) {
                $target = $team->keys()->reject(fn ($id) => $id === $rmId)->filter(fn ($id) => $load[$id]->count() < self::MAX_ACCOUNTS)
                    ->sortBy(fn ($id) => [$load[$id]->count(), $team[$id]->name])->first();
                $client = $load[$rmId]->reject(fn ($id) => $countyIds->has($id))->sortBy(fn ($id) => [$reports($rmId, $id), $clients[$id]->name])->first();
                if ($target === null || $client === null) {
                    break;
                }
                $move($client, $rmId, $target, 'Over '.self::MAX_ACCOUNTS.' accounts');
            }
        }

        $after = $team->map(fn (User $rm) => ['rm' => $rm, 'before' => $holdings->filter(fn ($id) => $id === $rm->id)->count(), 'after' => $load[$rm->id]->count(), 'counties' => $countiesOf($rm->id)])->values();

        return [
            'team' => $team,
            'moves' => $moves,
            'still_over' => $after->where('after', '>', self::MAX_ACCOUNTS)->values(),
            'after' => $after,
        ];
    }

    /**
     * Makes the plan's moves: each client is assigned directly to its new RM, and every move is in the audit log.
     *
     * @return int the clients moved
     */
    public function apply(User $supervisor, User $actor): int
    {
        return DB::transaction(function () use ($supervisor) {
            $moves = $this->plan($supervisor)['moves'];
            $locked = StateCorporation::query()->whereKey(array_map(fn ($m) => $m['client']->id, $moves))->lockForUpdate()->get()->keyBy('id');

            foreach ($moves as $m) {
                $client = $locked[$m['client']->id];
                $client->update(['assigned_rm_id' => $m['to']->id]);
                AuditLog::record('client.rm_assigned', $client, [
                    'client' => $client->name,
                    'previous_rm' => $m['from']?->name,
                    'new_rm' => $m['to']->name,
                    'reason' => 'Team balance for '.$supervisor->name.': '.$m['reason'],
                ]);
            }

            return count($moves);
        });
    }

    /**
     * Who covers each client now (the direct assignment, else its state department's RM).
     *
     * @return Collection<int, ?int> client id => RM id
     */
    public function holdings(): Collection
    {
        return StateCorporation::query()->with('ministry.parent')->get()
            ->mapWithKeys(fn (StateCorporation $c) => [$c->id => $c->effectiveAssignedRmId()]);
    }
}
