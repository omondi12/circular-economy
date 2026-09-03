<?php

namespace App\Console\Commands;

use App\Models\GovernmentEntity;
use App\Models\StateCorporation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Adds every client that was missing against a full Kenya public-sector
 * institutions register (2026-09-03, "kenya_public_institutions.xlsx" -
 * SAGAs/parastatals, public universities, constitutional commissions &
 * independent offices, judiciary, legislature). Cross-referenced by hand
 * against the existing ~438 clients: many entries in that register were
 * already present under a shorter/abbreviated/differently-punctuated
 * name (handled as a plain no-op, not listed in the data file here) -
 * this file is only the ~76 genuinely new ones.
 *
 * Institutions that were vague/generic (e.g. "Tribunals (various)"),
 * operational sub-arms of a body already tracked under its own name
 * (e.g. a commission's "Secretariat"), or ones this register's own
 * source material left too uncertain to name confidently, were left out
 * entirely rather than guessed - see the account allocation task's data
 * file for a similar precedent of flagging rather than fabricating.
 *
 * No RM assignment happens here (unlike accounts:allocate) - these are
 * added unassigned, ready for the boss/admin to assign via Assign RMs.
 */
class ImportKenyaInstitutions extends Command
{
    protected $signature = 'clients:import-missing {--dry-run : Show what would happen without saving}';

    protected $description = 'Add clients found in the Kenya public institutions register that are not yet in the system';

    public function handle(): int
    {
        $path = database_path('data/kenya_institutions_additions.json');

        if (! File::exists($path)) {
            $this->error("Data file not found: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode(File::get($path), true);

        if (! is_array($rows)) {
            $this->error('Data file is not valid JSON.');

            return self::FAILURE;
        }

        $ministriesByName = GovernmentEntity::ministries()->get()->keyBy('name');

        $created = 0;
        $updated = 0;
        $warnings = [];
        $summary = [];
        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                $ministryId = null;

                if (! empty($row['ministry'])) {
                    $ministry = $ministriesByName->get($row['ministry']);
                    if ($ministry === null) {
                        $warnings[] = "Unknown ministry \"{$row['ministry']}\" for \"{$row['name']}\" - leaving ministry blank.";
                    } else {
                        $ministryId = $ministry->id;
                    }
                }

                $client = StateCorporation::updateOrCreate(
                    ['name' => $row['name']],
                    [
                        'classification' => $row['classification'],
                        'ministry_id' => $ministryId,
                        'phase' => StateCorporation::PHASE_TWO,
                    ]
                );

                $client->wasRecentlyCreated ? $created++ : $updated++;

                $summary[] = [
                    'name' => $row['name'],
                    'classification' => $row['classification'],
                    'ministry' => $row['ministry'] ?? '—',
                    'new' => $client->wasRecentlyCreated ? 'yes' : 'already existed',
                ];
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->table(['Name', 'Classification', 'Ministry', 'New?'], $summary);

        if ($warnings) {
            $this->newLine();
            foreach ($warnings as $w) {
                $this->warn($w);
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN - nothing saved] ' : '')."Done: {$created} client(s) created, {$updated} already present (re-applied the same classification/ministry - safe no-op).");

        return self::SUCCESS;
    }
}
