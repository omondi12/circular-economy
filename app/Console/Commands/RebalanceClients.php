<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\StateCorporation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Rebalances clients across every active, real RM - the tool for
 * onboarding a new RM after everyone else is already fully assigned
 * (clients:distribute only fills genuinely-unassigned clients, so it
 * does nothing once every client already has someone; this command
 * reshuffles the poolable set instead).
 *
 * Leaves the 62 clients from the boss's own named WhatsApp-excel mapping
 * (database/data/account_allocations.json) untouched no matter how
 * unbalanced that makes their specific RMs - "poolable" is everything
 * else. For the poolable set, computes one even target per RM (poolable
 * total / RM count), unassigns whichever RMs are over that target down
 * to it, then hands every freed-up (or previously null) client to
 * whichever RM currently has the fewest - the same convergence approach
 * as clients:distribute, just run over a set that includes clients
 * pulled back from already-assigned RMs, not only null ones.
 */
class RebalanceClients extends Command
{
    protected $signature = 'clients:rebalance {--dry-run : Show what would happen without saving}';

    protected $description = 'Rebalance non-pilot clients evenly across every active RM, including newly-onboarded ones with zero clients';

    public function handle(): int
    {
        $protectedPath = database_path('data/account_allocations.json');
        $protectedNames = [];

        if (File::exists($protectedPath)) {
            $rows = json_decode(File::get($protectedPath), true) ?? [];
            $protectedNames = collect($rows)->map(fn ($row) => $row['existing_name'] ?? $row['excel_name'])->all();
        }

        $rms = User::assignableRms()->orderBy('name')->get();

        if ($rms->isEmpty()) {
            $this->error('No active, real (non-demo) RM accounts found.');

            return self::FAILURE;
        }

        $poolable = StateCorporation::whereNotIn('name', $protectedNames)->count();
        $target = intdiv($poolable, $rms->count());
        $remainder = $poolable % $rms->count();

        // A handful of RMs (in name order) absorb the remainder so the
        // total accounted for is exact, not short by a few clients.
        $targetPerRm = [];
        foreach ($rms as $i => $rm) {
            $targetPerRm[$rm->id] = $target + ($i < $remainder ? 1 : 0);
        }

        $dryRun = (bool) $this->option('dry-run');
        $released = 0;

        DB::beginTransaction();

        try {
            // Pull each over-target RM down to its target, oldest-id-first
            // for a deterministic, reviewable result.
            foreach ($rms as $rm) {
                $current = StateCorporation::whereNotIn('name', $protectedNames)
                    ->where('assigned_rm_id', $rm->id)
                    ->orderBy('id')
                    ->get();

                $excess = $current->count() - $targetPerRm[$rm->id];

                if ($excess > 0) {
                    $ids = $current->take($excess)->pluck('id');
                    StateCorporation::whereIn('id', $ids)->update(['assigned_rm_id' => null]);
                    $released += $excess;
                }
            }

            $counts = [];
            $namesById = [];
            foreach ($rms as $rm) {
                $counts[$rm->id] = StateCorporation::whereNotIn('name', $protectedNames)
                    ->where('assigned_rm_id', $rm->id)->count();
                $namesById[$rm->id] = $rm->name;
            }

            $toPlace = StateCorporation::whereNotIn('name', $protectedNames)
                ->whereNull('assigned_rm_id')
                ->orderBy('name')
                ->get();

            $newlyAssigned = [];
            foreach ($toPlace as $client) {
                asort($counts);
                $rmId = array_key_first($counts);

                $client->update(['assigned_rm_id' => $rmId]);
                $counts[$rmId]++;
                $newlyAssigned[$rmId] = ($newlyAssigned[$rmId] ?? 0) + 1;
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
                AuditLog::record('clients.rebalanced', null, [
                    'released' => $released,
                    'reassigned' => $toPlace->count(),
                    'final_totals' => collect($counts)->mapWithKeys(fn ($c, $id) => [$namesById[$id] => $c])->all(),
                ]);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $rows = collect($counts)->map(fn ($total, $id) => [
            'rm' => $namesById[$id],
            'newly_assigned' => $newlyAssigned[$id] ?? 0,
            'final_total' => $total,
        ])->sortBy('rm')->values();

        $this->table(['RM', 'Newly Assigned', 'Final Total (excl. pilot clients)'], $rows->all());

        $this->newLine();
        $protectedCount = count($protectedNames);
        $this->info(($dryRun ? '[DRY RUN - nothing saved] ' : '')."Done: {$released} released from over-target RMs, {$toPlace->count()} (re)assigned across {$rms->count()} RMs. {$protectedCount} pilot clients left untouched.");

        return self::SUCCESS;
    }
}
