<?php

namespace App\Console\Commands;

use App\Models\GovernmentEntity;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One ministry -> one RM, so the boss can see per-RM performance. Two RMs
 * had named exceptions (their own portfolio picked directly by the boss);
 * the rest of the 22 ministries are split round-robin, in ministry-id
 * order, across the remaining real (non-demo) RM accounts. Re-running this
 * command always recomputes the full assignment from scratch, so it stays
 * correct if the RM roster changes later.
 *
 * A named exception silently folds back into the normal round-robin pool
 * if that email no longer belongs to an active RM (2026-09-07: this broke
 * outright - "no active RM found" - the moment Josephine Wambui and Dennis
 * Thuo were promoted to Supervisor, since they'd hard-failed the whole
 * command instead of just no longer applying).
 */
class DistributeMinistries extends Command
{
    protected $signature = 'ministries:distribute {--dry-run : Show the assignment without saving it}';

    protected $description = 'Assign each ministry to one RM, distributing them as evenly as possible';

    /**
     * email => ministry name substrings the boss picked directly for that RM.
     */
    private const NAMED_ASSIGNMENTS = [
        'josephine.wambui@amacplc.com' => [
            'Roads and Transport',
            'Ministry of Education',
        ],
        'dennis.thuo@amacplc.com' => [
            'Energy and Petroleum',
            'Lands, Public Works, Housing and Urban Development',
        ],
    ];

    public function handle(): int
    {
        $ministries = GovernmentEntity::ministries()->orderBy('id')->get();
        $realRms = User::assignableRms()->orderBy('id')->get();

        $assignments = [];
        $namedMinistryIds = [];

        foreach (self::NAMED_ASSIGNMENTS as $email => $nameNeedles) {
            $rm = $realRms->firstWhere('email', $email);

            if ($rm === null) {
                // No longer an active RM (e.g. promoted to Supervisor) -
                // their ministries just join the normal round-robin pool
                // below instead of blocking the whole distribution.
                continue;
            }

            foreach ($nameNeedles as $needle) {
                $ministry = $ministries->first(fn (GovernmentEntity $m) => str_contains($m->name, $needle));

                if ($ministry === null) {
                    $this->error("No ministry found matching \"{$needle}\" for {$rm->name}.");

                    return self::FAILURE;
                }

                $assignments[$ministry->id] = $rm;
                $namedMinistryIds[] = $ministry->id;
            }
        }

        $remainingRms = $realRms->reject(fn (User $rm) => array_key_exists($rm->email, self::NAMED_ASSIGNMENTS))->values();

        if ($remainingRms->isEmpty()) {
            $this->error('No remaining RMs to distribute the rest of the ministries to.');

            return self::FAILURE;
        }

        $remainingMinistries = $ministries->reject(fn (GovernmentEntity $m) => in_array($m->id, $namedMinistryIds, true))->values();

        foreach ($remainingMinistries as $i => $ministry) {
            $assignments[$ministry->id] = $remainingRms[$i % $remainingRms->count()];
        }

        $rows = collect($assignments)->map(fn (User $rm, int $ministryId) => [
            'ministry' => $ministries->firstWhere('id', $ministryId)->name,
            'rm' => $rm->name,
            'email' => $rm->email,
        ])->sortBy('rm')->values();

        $this->table(['Ministry', 'Assigned RM', 'Email'], $rows->all());

        $perRmCounts = $rows->countBy('rm');
        $this->newLine();
        $this->info('Ministries per RM: '.$perRmCounts->map(fn ($count, $rm) => "{$rm}: {$count}")->implode(', '));

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->comment('Dry run - no changes saved.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($assignments): void {
            foreach ($assignments as $ministryId => $rm) {
                GovernmentEntity::where('id', $ministryId)->update(['assigned_rm_id' => $rm->id]);
            }
        });

        $this->newLine();
        $this->info('Saved: '.count($assignments).' ministries assigned across '.$rows->pluck('rm')->unique()->count().' RMs.');

        return self::SUCCESS;
    }
}
