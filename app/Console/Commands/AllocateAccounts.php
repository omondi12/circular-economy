<?php

namespace App\Console\Commands;

use App\Models\GovernmentEntity;
use App\Models\StateCorporation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Applies the boss's "Circular Economy – Account Allocation" WhatsApp
 * excel (2026-09-03) - 62 pilot accounts, each assigned to one of the 10
 * real RMs. Cross-referenced by hand against the existing 421-client
 * registry: 45 rows matched an existing client (some under a shorter/
 * differently-punctuated name than the excel uses - see `existing_name`
 * in the data file), the other 17 didn't exist yet and are created here
 * (mostly state departments the excel tracks as their own pilot account,
 * distinct from the ministry hierarchy, plus Safaricom, CRBC and the
 * National Lands Commission).
 *
 * "Ken Wambo" in the excel replaced Ann Temko, who has left - her
 * account was renamed to him separately (not by this command) before
 * this runs, so the plain by-name RM lookup below resolves correctly.
 *
 * Idempotent - re-running reapplies the same 62 assignments and upserts
 * the 17 new clients by name, safe if the data file is corrected later.
 */
class AllocateAccounts extends Command
{
    protected $signature = 'accounts:allocate {--dry-run : Show what would happen without saving}';

    protected $description = 'Assign RMs to clients per database/data/account_allocations.json, creating any client that doesn\'t exist yet';

    public function handle(): int
    {
        $path = database_path('data/account_allocations.json');

        if (! File::exists($path)) {
            $this->error("Data file not found: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode(File::get($path), true);

        if (! is_array($rows)) {
            $this->error('Data file is not valid JSON.');

            return self::FAILURE;
        }

        $rmsByName = User::where('role', User::ROLE_RM)->get()->keyBy(fn (User $u) => mb_strtolower($u->name));
        $ministriesByName = GovernmentEntity::ministries()->get()->keyBy('name');

        $created = 0;
        $updated = 0;
        $errors = [];
        $summary = [];
        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                $rm = $rmsByName->get(mb_strtolower($row['rm_name']));

                if ($rm === null) {
                    $errors[] = "No RM account found named \"{$row['rm_name']}\" (for \"{$row['excel_name']}\").";

                    continue;
                }

                if (! empty($row['new'])) {
                    $ministryId = null;
                    if (! empty($row['ministry'])) {
                        $ministry = $ministriesByName->get($row['ministry']);
                        if ($ministry === null) {
                            $errors[] = "Unknown ministry \"{$row['ministry']}\" for new client \"{$row['excel_name']}\" - leaving ministry blank.";
                        } else {
                            $ministryId = $ministry->id;
                        }
                    }

                    $client = StateCorporation::updateOrCreate(
                        ['name' => $row['excel_name']],
                        [
                            'cluster' => $row['cluster'] ?? null,
                            'class' => null,
                            'subclass' => null,
                            'classification' => $row['classification'] ?? 'State Corporation',
                            'ministry_id' => $ministryId,
                            'phase' => StateCorporation::PHASE_TWO,
                            'assigned_rm_id' => $rm->id,
                        ]
                    );

                    $client->wasRecentlyCreated ? $created++ : $updated++;
                } else {
                    $client = StateCorporation::where('name', $row['existing_name'])->first();

                    if ($client === null) {
                        $errors[] = "Existing client \"{$row['existing_name']}\" (excel: \"{$row['excel_name']}\") not found - skipped.";

                        continue;
                    }

                    $client->update(['assigned_rm_id' => $rm->id]);
                    $updated++;
                }

                $summary[] = [
                    'client' => $row['excel_name'],
                    'rm' => $rm->name,
                    'new' => ! empty($row['new']) ? 'yes' : '',
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

        $this->table(['Client', 'Assigned RM', 'New?'], $summary);

        if ($errors) {
            $this->newLine();
            foreach ($errors as $e) {
                $this->error($e);
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN - nothing saved] ' : '')."Done: {$created} client(s) created, {$updated} assignment(s) updated.");

        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
