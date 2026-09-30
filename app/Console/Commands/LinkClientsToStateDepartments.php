<?php

namespace App\Console\Commands;

use App\Models\GovernmentEntity;
use App\Models\StateCorporation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Every client's ministry_id currently points at a Ministry (level 1),
 * never at the state department or institution underneath it - so
 * StateCorporation::stateDepartmentDisplay() shows "-" for practically
 * everyone (2026-09-28, per the boss). This re-points ministry_id at
 * the correct state department (level 2) by matching each client's name
 * against the Institution/Agency column of Executive Order No. 1 of
 * 2025 (database/data/state_department_institutions.json), so the
 * public State Departments directory can show a department's real
 * client list even before an RM is assigned to it. Also fills in each
 * department's contact_person_name from the Order's Principal Secretary
 * list (database/data/state_departments_ps.json) - phone numbers aren't
 * in the source document, so that field is untouched.
 *
 * Matching is exact (normalized: lowercased, trimmed, collapsed
 * whitespace, a leading "The " stripped) on both sides - no fuzzy
 * matching, since a wrong department link would be worse than a
 * missing one. Anything that doesn't match exactly is left alone and
 * counted, never guessed.
 *
 * Second pass (2026-09-30): the Order's institution list only names a
 * subset of agencies per department, so most clients were still stuck
 * at Ministry level after the first pass - not because their
 * department is unknown, but because the Order simply doesn't
 * enumerate them. Many already exist as their own Institution-level
 * GovernmentEntity row elsewhere in the tree (from the separate
 * clients:import-missing registers), complete with the correct parent
 * State Department - so any client still at Ministry level gets a
 * second, equally exact-match-only attempt against that existing
 * Institution list before being left alone.
 */
class LinkClientsToStateDepartments extends Command
{
    protected $signature = 'clients:link-state-departments
        {--dry-run : Show what would happen without saving}';

    protected $description = 'Link clients to their real state department and fill in Principal Secretary contact names, from Executive Order No. 1 of 2025';

    public function handle(): int
    {
        $deptsPath = database_path('data/state_departments_ps.json');
        $institutionsPath = database_path('data/state_department_institutions.json');

        if (! File::exists($deptsPath) || ! File::exists($institutionsPath)) {
            $this->error('Data files not found under database/data/ - expected state_departments_ps.json and state_department_institutions.json.');

            return self::FAILURE;
        }

        $deptRows = json_decode(File::get($deptsPath), true);
        $institutionRows = json_decode(File::get($institutionsPath), true);

        $normalize = function (string $s): string {
            $s = strtolower(trim($s));
            $s = preg_replace('/\s+/', ' ', $s);

            return preg_replace('/^the\s+/', '', $s);
        };

        $departmentsById = GovernmentEntity::where('level', GovernmentEntity::LEVEL_STATE_DEPARTMENT)->get()->keyBy('id');
        $departmentsByName = $departmentsById->keyBy(fn (GovernmentEntity $d) => $normalize($d->name));

        $clientsByName = StateCorporation::all()->groupBy(fn (StateCorporation $c) => $normalize($c->name));

        // Hand-verified against the existing government-entity tree, which
        // reflects an earlier cabinet arrangement than this 2025 Order (a
        // few ministries were split/merged/renamed since) - each of these
        // is confirmed to be the SAME real department under different
        // wording, not a guess. Two ("General / Direct Reporting") are
        // ambiguous by name alone since six ministries share that
        // placeholder row, so those resolve by id instead of name.
        $departmentIdAliases = [
            'Ministry of Defence (single Principal Secretary; no named State Departments)' => 24, // General / Direct Reporting, under Ministry of Defence
            'The State Law Office (headed by the Attorney-General and Solicitor-General)' => 362, // General / Direct Reporting, under State Law Office and Department of Justice
        ];

        $departmentNameAliases = [
            'State Department for Science, Research & Innovation' => 'State Department for Science, Research and Innovation',
            'State Department for Public Service and Human Capital Development' => 'State Department for Public Service',
            'State Department for Information Communication Technology (ICT) and Digital Economy' => 'State Department for ICT and Digital Economy',
            'State Department for Technical, Vocational Education and Training (TVET)' => 'State Department for Technical and Vocational Education and Training',
            'State Department for Higher Education' => 'State Department for Higher Education and Research',
            'State Department for Investments Promotion' => 'State Department / Investment Promotion Function',
            'State Department for Micro, Small, and Medium Enterprises (MSMEs) Development' => 'State Department for MSME Development',
            'State Department for Gender Affairs and Affirmative Action' => 'State Department for Gender and Affirmative Action',
            'State Department for Culture, the Arts & Heritage' => 'State Department for Culture, Arts and Heritage',
            'State Department for Social Protection and Senior Citizen Affairs' => 'State Department for Social Protection and Senior Citizens Affairs',
            'State Department for East African Community (EAC) Affairs' => 'State Department for East African Community Affairs',
            'State Department for the ASALs and Regional Development' => 'State Department for ASALs and Regional Development',
            'State Department for the Blue Economy and Fisheries' => 'State Department for Blue Economy',
            'State Department for Justice, Human Rights & Constitutional Affairs' => 'State Department for Justice, Human Rights and Constitutional Affairs',
        ];

        $resolveDepartment = function (string $excelName) use ($departmentIdAliases, $departmentNameAliases, $departmentsById, $departmentsByName, $normalize): ?GovernmentEntity {
            if (isset($departmentIdAliases[$excelName])) {
                return $departmentsById->get($departmentIdAliases[$excelName]);
            }

            $lookupName = $departmentNameAliases[$excelName] ?? $excelName;

            return $departmentsByName->get($normalize($lookupName));
        };

        $dryRun = (bool) $this->option('dry-run');
        $contactsUpdated = 0;
        $clientsLinked = 0;
        $unmatchedDepartments = [];
        $unmatchedInstitutions = [];
        $ambiguousInstitutions = [];

        DB::beginTransaction();

        try {
            foreach ($deptRows as $row) {
                $department = $resolveDepartment($row['state_department']);

                if ($department === null) {
                    $unmatchedDepartments[] = $row['state_department'];

                    continue;
                }

                $ps = trim((string) $row['principal_secretary']);
                if ($ps !== '' && ! str_starts_with($ps, 'N/A') && ! $department->contact_person_name) {
                    $department->update(['contact_person_name' => $ps]);
                    $contactsUpdated++;
                }
            }

            foreach ($institutionRows as $row) {
                $department = $resolveDepartment($row['state_department']);
                if ($department === null) {
                    continue; // Already counted above.
                }

                $matches = $clientsByName->get($normalize($row['institution']));
                if ($matches === null) {
                    $unmatchedInstitutions[] = $row['institution'];

                    continue;
                }

                if ($matches->count() > 1) {
                    $ambiguousInstitutions[] = $row['institution'];

                    continue;
                }

                $client = $matches->first();
                if ($client->ministry_id !== $department->id) {
                    $client->update(['ministry_id' => $department->id]);
                    $clientsLinked++;
                }
            }

            // Second pass (2026-09-30): the Order's institution list above
            // only names a subset of institutions per department, so most
            // clients whose ministry_id still points at a plain Ministry
            // aren't in that file at all - not because the department is
            // unknown, but because the Order doesn't enumerate every
            // agency. Many of those same clients already exist as their
            // own Institution-level GovernmentEntity row elsewhere in the
            // tree (built from the separate institution-register imports -
            // see clients:import-missing), complete with the correct
            // parent State Department. Same exact-match-only rule as
            // above: a client still sitting at Ministry level is only
            // re-pointed when its normalized name matches exactly one
            // existing Institution row, pointed at that Institution
            // directly (consistent with how ministry_id already points at
            // Institution level for earlier matches - stateDepartmentEntity()
            // walks up from there).
            $institutionEntitiesByName = GovernmentEntity::where('level', GovernmentEntity::LEVEL_INSTITUTION)
                ->get()
                ->groupBy(fn (GovernmentEntity $e) => $normalize($e->name));

            $stillStuck = StateCorporation::where(function ($q) {
                $q->whereNull('ministry_id')
                    ->orWhereHas('ministry', fn ($q2) => $q2->where('level', GovernmentEntity::LEVEL_MINISTRY));
            })
                ->whereNotIn('classification', ['Constitutional Commission', 'Independent Office', 'Judiciary', 'Legislature', 'Private Company'])
                ->get();

            $institutionMatches = 0;
            $institutionAmbiguous = [];

            foreach ($stillStuck as $client) {
                $candidates = $institutionEntitiesByName->get($normalize($client->name));

                if ($candidates === null) {
                    continue; // No matching source of truth for this one - left alone, not guessed.
                }

                if ($candidates->count() > 1) {
                    $institutionAmbiguous[] = $client->name;

                    continue;
                }

                $institution = $candidates->first();
                if ($client->ministry_id !== $institution->id) {
                    $client->update(['ministry_id' => $institution->id]);
                    $institutionMatches++;
                    $clientsLinked++;
                }
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

        $this->info(($dryRun ? '[DRY RUN - nothing saved] ' : '')."Contact person set on {$contactsUpdated} department(s). Clients linked/re-linked to their real state department: {$clientsLinked} (of which {$institutionMatches} via direct Institution-entity name match).");

        if ($unmatchedDepartments) {
            $this->newLine();
            $this->warn(count($unmatchedDepartments).' department name(s) in the source file did not match an existing state department:');
            foreach (array_unique($unmatchedDepartments) as $name) {
                $this->line("  - {$name}");
            }
        }

        if ($unmatchedInstitutions) {
            $this->newLine();
            $this->warn(count($unmatchedInstitutions).' institution name(s) did not match an existing client (not in the system, or under a different name) - sample:');
            foreach (array_slice($unmatchedInstitutions, 0, 25) as $name) {
                $this->line("  - {$name}");
            }
        }

        if ($ambiguousInstitutions) {
            $this->newLine();
            $this->warn(count($ambiguousInstitutions).' institution name(s) matched more than one client with the same name - skipped:');
            foreach ($ambiguousInstitutions as $name) {
                $this->line("  - {$name}");
            }
        }

        if ($institutionAmbiguous) {
            $this->newLine();
            $this->warn(count($institutionAmbiguous).' client name(s) matched more than one Institution-level entity - skipped:');
            foreach ($institutionAmbiguous as $name) {
                $this->line("  - {$name}");
            }
        }

        $stillUnresolved = StateCorporation::where(function ($q) {
            $q->whereNull('ministry_id')
                ->orWhereHas('ministry', fn ($q2) => $q2->where('level', GovernmentEntity::LEVEL_MINISTRY));
        })
            ->whereNotIn('classification', ['Constitutional Commission', 'Independent Office', 'Judiciary', 'Legislature', 'Private Company'])
            ->count();

        if ($stillUnresolved > 0) {
            $this->newLine();
            $this->warn("{$stillUnresolved} client(s) still have no state department after this run - their name doesn't appear in either source, so nothing was guessed. They'll need either better source data or a manual RM assignment (Assign RMs -> Clients).");
        }

        return self::SUCCESS;
    }
}
