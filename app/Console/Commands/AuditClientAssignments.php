<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\GovernmentEntity;
use App\Models\StateCorporation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Read-only report (2026-10-05) for the users' complaint that "every
 * ministry had been allocated, only County Governments were unallocated,
 * and some counties were allocated" - yet Assign RMs -> Clients ->
 * Unassigned shows far more than that.
 *
 * Since 2026-09-28 a client only counts as assigned through a direct
 * override or its state department's RM (StateCorporation::
 * effectiveAssignedRmId()); a ministry-level RM no longer reaches its
 * clients at all. This lists, side by side, what users would have seen
 * under the old ministry-wide rule vs what the page counts now, plus
 * the audit-log history of assignments that have since been lost.
 *
 * Saves nothing. --csv=path also writes every client-level discrepancy.
 */
class AuditClientAssignments extends Command
{
    protected $signature = 'clients:audit-assignments {--csv= : Also write client-level discrepancies to this CSV path}';

    protected $description = 'Report discrepancies between remembered (ministry-wide) client allocation and current effective assignment - read-only';

    public function handle(): int
    {
        $users = User::all()->keyBy('id');

        $clients = StateCorporation::with('ministry.parent.parent')->orderBy('name')->get();

        $rows = $clients->map(function (StateCorporation $c) use ($users) {
            $topMinistry = $this->topMinistry($c->ministry);
            $stateDept = $c->stateDepartmentEntity();
            $effectiveId = $c->effectiveAssignedRmId();

            return [
                'client' => $c,
                'is_county' => $this->isCounty($c),
                'ministry' => $topMinistry,
                'ministry_rm' => $topMinistry?->assigned_rm_id ? $users->get($topMinistry->assigned_rm_id) : null,
                'state_dept' => $stateDept,
                'override_rm' => $c->assigned_rm_id ? $users->get($c->assigned_rm_id) : null,
                'effective_rm' => $effectiveId ? $users->get($effectiveId) : null,
            ];
        });

        $counties = $rows->where('is_county', true);
        $others = $rows->where('is_county', false);

        $this->section('1. Summary');
        $this->table(['Group', 'Total', 'Assigned now', 'Unassigned now', 'Would be assigned under old ministry-wide rule'], [
            ['Non-county clients', $others->count(), $others->whereNotNull('effective_rm')->count(), $others->whereNull('effective_rm')->count(),
                $others->filter(fn ($r) => $r['effective_rm'] || $r['ministry_rm'])->count()],
            ['County Governments', $counties->count(), $counties->whereNotNull('effective_rm')->count(), $counties->whereNull('effective_rm')->count(),
                $counties->filter(fn ($r) => $r['effective_rm'] || $r['ministry_rm'])->count()],
        ]);

        // The core discrepancy: users allocated the ministry, the page
        // ignores ministry RMs, so these show as unassigned.
        $hidden = $others->filter(fn ($r) => ! $r['effective_rm'] && $r['ministry_rm']);
        $this->section("2. Non-county clients whose MINISTRY has an RM but show as UNASSIGNED ({$hidden->count()})");
        $this->line('   Reason "no state dept linked": client points straight at the ministry, so only a direct override can assign it.');
        $this->line('   Reason "state dept has no RM": assign that state department (Assign RMs -> State Departments) to fix the whole group.');
        $this->table(['Ministry', 'Ministry RM', 'State department', 'Reason', 'Clients'],
            $hidden->groupBy(fn ($r) => $r['ministry']->id.'|'.($r['state_dept']?->id ?? 0))
                ->map(fn (SupportCollection $g) => [
                    $g->first()['ministry']->name,
                    $this->rmLabel($g->first()['ministry_rm']),
                    $g->first()['state_dept']?->name ?? '—',
                    $g->first()['state_dept'] ? 'state dept has no RM' : 'no state dept linked',
                    $g->count(),
                ])->sortBy(0)->values()->all());

        $orphans = $others->filter(fn ($r) => ! $r['effective_rm'] && ! $r['ministry_rm']);
        $this->section("3. Non-county clients unassigned with NO ministry RM either ({$orphans->count()})");
        $this->table(['Ministry', 'Clients'], $orphans->groupBy(fn ($r) => $r['ministry']?->name ?? '(no ministry linked)')
            ->map(fn ($g, $name) => [$name, $g->count()])->sortBy(0)->values()->all());

        $this->section('4. Ministries with an RM, and how many of their state departments have one');
        $this->table(['Ministry', 'Ministry RM', 'State depts', 'With RM', 'Without RM'],
            GovernmentEntity::ministries()->whereNotNull('assigned_rm_id')->with('children')->orderBy('name')->get()
                ->map(function (GovernmentEntity $m) use ($users) {
                    $depts = $m->children->where('level', GovernmentEntity::LEVEL_STATE_DEPARTMENT);

                    return [$m->name, $this->rmLabel($users->get($m->assigned_rm_id)), $depts->count(),
                        $depts->whereNotNull('assigned_rm_id')->count(), $depts->whereNull('assigned_rm_id')->count()];
                })->all());

        $this->section("5. County Governments: {$counties->whereNotNull('effective_rm')->count()} assigned, {$counties->whereNull('effective_rm')->count()} unassigned");
        $this->table(['County', 'Assigned RM'], $counties->map(fn ($r) => [$r['client']->name, $this->rmLabel($r['effective_rm'])])->values()->all());

        // Assignments that still count but point at someone who can't
        // work them - inactive or no longer an RM.
        $dead = $rows->filter(fn ($r) => $r['effective_rm'] && (! $r['effective_rm']->isRm() || ! $r['effective_rm']->is_active));
        $this->section("6. Clients assigned to an inactive / non-RM account ({$dead->count()})");
        $this->table(['Client', 'Assigned to', 'Role', 'Active'], $dead->map(fn ($r) => [
            $r['client']->name, $r['effective_rm']->name, $r['effective_rm']->role, $r['effective_rm']->is_active ? 'yes' : 'no',
        ])->values()->all());

        $lost = $this->lostClientAssignments($rows);
        $this->section("7. Clients whose LAST manual assignment (audit log) no longer matches today ({$lost->count()})");
        $this->line('   Bulk commands (distribute/rebalance/allocate) and account deletions/role changes do not log per client, so this is a lower bound.');
        $this->table(['Client', 'Last assigned to', 'On', 'By', 'Now'], $lost->all());

        $this->section('8. Ministry / state department assignments that changed since they were last set');
        $this->table(['Entity', 'Last set to', 'On', 'Now'], $this->changedEntityAssignments($users)->all());

        $this->section('9. Events that clear assignments without a per-client log');
        $this->table(['When', 'Event', 'Detail'], AuditLog::whereIn('action', ['user.deleted', 'user.updated', 'clients.distributed', 'clients.rebalanced', 'ministries.distributed'])
            ->orderBy('created_at')->get()
            ->filter(fn (AuditLog $l) => $l->action !== 'user.updated'
                || (($l->meta['previous_role'] ?? null) === User::ROLE_RM && ($l->meta['role'] ?? null) !== User::ROLE_RM))
            ->map(fn (AuditLog $l) => [
                $l->created_at?->format('Y-m-d H:i'),
                $l->action === 'user.updated' ? 'RM role removed (their clients freed)' : $l->action,
                $l->action === 'user.deleted' && ($l->meta['role'] ?? null) === User::ROLE_RM
                    ? ($l->meta['name'] ?? '?').' (RM deleted - their clients freed)'
                    : ($l->meta['name'] ?? json_encode($l->meta)),
            ])->values()->all());

        if ($path = $this->option('csv')) {
            $this->writeCsv($path, $hidden, $orphans, $counties, $dead);
            $this->info("Client-level discrepancies written to {$path}");
        }

        return self::SUCCESS;
    }

    private function lostClientAssignments(SupportCollection $rows): SupportCollection
    {
        $byId = $rows->keyBy(fn ($r) => $r['client']->id);

        return AuditLog::with('user')
            ->where('action', 'client.rm_assigned')
            ->where('subject_type', (new StateCorporation)->getMorphClass())
            ->orderBy('created_at')->get()
            ->keyBy('subject_id')            // keeps each client's latest event
            ->filter(fn (AuditLog $l) => ($l->meta['new_rm'] ?? null) !== null)
            ->filter(fn (AuditLog $l) => $byId->has($l->subject_id)
                && $byId[$l->subject_id]['effective_rm']?->name !== $l->meta['new_rm'])
            ->map(fn (AuditLog $l) => [
                $l->meta['client'] ?? $byId[$l->subject_id]['client']->name,
                $l->meta['new_rm'],
                $l->created_at?->format('Y-m-d H:i'),
                $l->user?->name ?? 'system',
                $this->rmLabel($byId[$l->subject_id]['effective_rm']),
            ])->sortBy(0)->values();
    }

    private function changedEntityAssignments(SupportCollection $users): SupportCollection
    {
        $entities = GovernmentEntity::all()->keyBy('id');

        return AuditLog::whereIn('action', ['ministry.rm_assigned', 'state_department.rm_assigned'])
            ->where('subject_type', (new GovernmentEntity)->getMorphClass())
            ->orderBy('created_at')->get()
            ->keyBy('subject_id')
            ->filter(function (AuditLog $l) use ($entities, $users) {
                $now = $entities->get($l->subject_id)?->assigned_rm_id;

                return ($l->meta['new_rm'] ?? null) !== ($now ? $users->get($now)?->name : null);
            })
            ->map(fn (AuditLog $l) => [
                ($l->meta['ministry'] ?? $l->meta['state_department'] ?? '?').($l->action === 'ministry.rm_assigned' ? ' (ministry)' : ' (state dept)'),
                $l->meta['new_rm'] ?? '— (unassigned)',
                $l->created_at?->format('Y-m-d H:i'),
                $this->rmLabel(($id = $entities->get($l->subject_id)?->assigned_rm_id) ? $users->get($id) : null),
            ])->sortBy(0)->values();
    }

    private function writeCsv(string $path, SupportCollection ...$groups): void
    {
        [$hidden, $orphans, $counties, $dead] = $groups;
        $out = fopen($path, 'w');
        fputcsv($out, ['issue', 'client', 'classification', 'ministry', 'ministry_rm', 'state_department', 'state_department_rm', 'override_rm', 'effective_rm']);

        $emit = function (string $issue, SupportCollection $rows) use ($out) {
            foreach ($rows as $r) {
                fputcsv($out, [
                    $issue, $r['client']->name, $r['client']->classification, $r['ministry']?->name,
                    $r['ministry_rm']?->name, $r['state_dept']?->name, $r['state_dept']?->assignedRm?->name,
                    $r['override_rm']?->name, $r['effective_rm']?->name,
                ]);
            }
        };

        $emit('ministry has RM but client unassigned', $hidden);
        $emit('unassigned, no ministry RM either', $orphans);
        $emit('county government', $counties);
        $emit('assigned to inactive/non-RM account', $dead);
        fclose($out);
    }

    private function topMinistry(?GovernmentEntity $entity): ?GovernmentEntity
    {
        while ($entity && $entity->level !== GovernmentEntity::LEVEL_MINISTRY && $entity->parent) {
            $entity = $entity->parent;
        }

        return $entity;
    }

    private function isCounty(StateCorporation $c): bool
    {
        return $c->classification === 'County Government' || str_starts_with($c->name, 'County Government');
    }

    private function rmLabel(?User $rm): string
    {
        if (! $rm) {
            return '—';
        }

        return $rm->name.($rm->isRm() && $rm->is_active ? '' : ' (inactive/not RM)');
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->info($title);
    }
}
