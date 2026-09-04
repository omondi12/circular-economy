<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\StateCorporation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills in every still-unassigned client so every one of them has an RM,
 * per the boss's request (2026-09-04) - "distribute all the clients to
 * RMs equally, make sure all are assigned". Deliberately does NOT touch
 * the ~62 clients already assigned via accounts:allocate (the boss's own
 * named WhatsApp-excel mapping) - those stay exactly as he set them.
 *
 * "Equally" is read against the FINAL per-RM total, not just an even
 * split of the leftover pool: each unassigned client goes to whichever
 * RM currently has the fewest clients overall (existing + newly
 * assigned so far), so RMs who already have more pilot clients get
 * fewer of the new ones - the end state converges to as-even-as-possible
 * totals rather than compounding the existing pilot imbalance.
 */
class DistributeClients extends Command
{
    protected $signature = 'clients:distribute {--dry-run : Show what would happen without saving}';

    protected $description = 'Assign every still-unassigned client to whichever RM currently has the fewest, so all clients end up assigned and totals stay as even as possible';

    public function handle(): int
    {
        $rms = User::where('role', User::ROLE_RM)
            ->where('is_active', true)
            ->where('email', 'like', '%@amacplc.com')
            ->orderBy('name')
            ->get();

        if ($rms->isEmpty()) {
            $this->error('No active @amacplc.com RM accounts found.');

            return self::FAILURE;
        }

        $counts = [];
        $namesById = [];
        foreach ($rms as $rm) {
            $counts[$rm->id] = StateCorporation::where('assigned_rm_id', $rm->id)->count();
            $namesById[$rm->id] = $rm->name;
        }

        $unassigned = StateCorporation::whereNull('assigned_rm_id')->orderBy('name')->get();

        if ($unassigned->isEmpty()) {
            $this->info('Every client already has an RM assigned - nothing to do.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $assignments = []; // rm_id => count of newly-assigned clients this run

        DB::beginTransaction();

        try {
            foreach ($unassigned as $client) {
                asort($counts);
                $rmId = array_key_first($counts);

                $client->update(['assigned_rm_id' => $rmId]);
                $counts[$rmId]++;
                $assignments[$rmId] = ($assignments[$rmId] ?? 0) + 1;
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
                AuditLog::record('clients.distributed', null, [
                    'newly_assigned' => $unassigned->count(),
                    'final_totals' => collect($counts)->mapWithKeys(fn ($c, $id) => [$namesById[$id] => $c])->all(),
                ]);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $rows = collect($counts)->map(fn ($total, $id) => [
            'rm' => $namesById[$id],
            'newly_assigned' => $assignments[$id] ?? 0,
            'final_total' => $total,
        ])->sortBy('rm')->values();

        $this->table(['RM', 'Newly Assigned', 'Final Total'], $rows->all());

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN - nothing saved] ' : '')."Done: {$unassigned->count()} previously-unassigned client(s) distributed across {$rms->count()} RMs.");

        return self::SUCCESS;
    }
}
