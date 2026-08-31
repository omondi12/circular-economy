<?php

namespace App\Console\Commands;

use App\Models\StateCorporation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Seeds the "State Corporations" registry from
 * database/data/state_corporations.json - 348 entries transcribed from the
 * official "LIST OF STATE CORPORATIONS (348 No.) AS AT 5.2.2024" (Annex I),
 * plus 2 real pilot-client bodies (Kenya National Archives, Kenya News
 * Agency) that the boss named directly but don't appear anywhere on that
 * official list - kept rather than dropped, with no cluster/class data
 * since the source PDF has none for them.
 *
 * Phase 1 (42 rows) = the corporations named in the boss's "Circular
 * Economy Pilot Clients" screenshot, cross-matched against the official
 * list; everything else is Phase 2. Idempotent - upserts by name, so
 * re-running after correcting the data file is safe.
 */
class ImportStateCorporations extends Command
{
    protected $signature = 'state-corporations:import';

    protected $description = 'Import/refresh the State Corporations registry (348 official + 2 pilot-only) with phase tags';

    public function handle(): int
    {
        $path = database_path('data/state_corporations.json');

        if (! File::exists($path)) {
            $this->error("Data file not found: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode(File::get($path), true);

        if (! is_array($rows)) {
            $this->error('Data file is not valid JSON.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $row) {
                $corp = StateCorporation::updateOrCreate(
                    ['name' => $row['name']],
                    [
                        'cluster' => $row['cluster'],
                        'class' => $row['class'],
                        'subclass' => $row['subclass'],
                        'phase' => $row['phase'],
                    ]
                );

                $corp->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        $phase1 = StateCorporation::phaseOne()->count();
        $phase2 = StateCorporation::phaseTwo()->count();

        $this->info("State corporations imported: {$created} created, {$updated} updated.");
        $this->info("Phase 1: {$phase1}, Phase 2: {$phase2}, Total: ".($phase1 + $phase2));

        return self::SUCCESS;
    }
}
