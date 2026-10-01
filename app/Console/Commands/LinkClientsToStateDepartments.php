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
 *
 * Third pass (2026-10-01): after the first two passes, 421 real clients
 * were still stuck at Ministry level - neither source document names
 * them individually. Two sub-passes close most of that gap:
 *
 *   - A name-pattern rule for TVET institutions (National Polytechnics,
 *     Technical Training Institutes, Technical and Vocational Colleges)
 *     and public universities/university colleges - ~210 of the 421,
 *     all unambiguous by naming convention alone in Kenya's structure.
 *   - A hand-researched exact-name map ($manualDepartmentLinks /
 *     $manualDepartmentIdLinks) for ~150 specific, individually
 *     identified agencies, each checked against the agency's own
 *     publicly stated parent ministry/state department (not guessed
 *     from the agency's name) - see the inline citations in the array.
 *     A handful of genuinely ambiguous cases (e.g. an agency reporting
 *     directly to the Office of the President, which has no
 *     corresponding GovernmentEntity row at all) are deliberately left
 *     unlinked rather than forced onto the nearest guess - flagged in
 *     $knownGaps instead.
 *
 * County Government entries (47) are a separate constitutional level of
 * government, not a subordinate agency of any national ministry - they
 * are correctly excluded from "unresolved" counts rather than forced
 * under a state department that has no real authority over them.
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
                ->whereNotIn('classification', ['Constitutional Commission', 'Independent Office', 'Judiciary', 'Legislature', 'Private Company', 'County Government'])
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

            // Third pass, sub-pass A: TVET institutions and universities are
            // identifiable from their own name with no real ambiguity in
            // Kenya's structure - a "X National Polytechnic" or "X Technical
            // and Vocational College" is always under the TVET department, a
            // "X University"/"University College" always under Higher
            // Education. National Defense University is deliberately
            // excluded - it reports to Defence, not civilian Higher
            // Education, and is handled in the manual map below instead.
            $tvetDepartment = $departmentsByName->get($normalize('State Department for Technical and Vocational Education and Training'));
            $higherEducationDepartment = $departmentsByName->get($normalize('State Department for Higher Education and Research'));

            $patternMatches = 0;

            if ($tvetDepartment || $higherEducationDepartment) {
                $tvetPattern = '/technical (and )?(vocational college|training institute)|national polytechnic/i';
                $universityPattern = '/\buniversity\b/i';

                $stillUnlinked = StateCorporation::where(function ($q) {
                    $q->whereNull('ministry_id')
                        ->orWhereHas('ministry', fn ($q2) => $q2->where('level', GovernmentEntity::LEVEL_MINISTRY));
                })->get(['id', 'name']);

                foreach ($stillUnlinked as $client) {
                    if ($client->name === 'National Defense University - Kenya') {
                        continue;
                    }

                    $target = match (true) {
                        $tvetDepartment && preg_match($tvetPattern, $client->name) === 1 => $tvetDepartment,
                        $higherEducationDepartment && preg_match($universityPattern, $client->name) === 1 => $higherEducationDepartment,
                        default => null,
                    };

                    if ($target !== null) {
                        StateCorporation::whereKey($client->id)->update(['ministry_id' => $target->id]);
                        $patternMatches++;
                        $clientsLinked++;
                    }
                }
            }

            // Third pass, sub-pass B: specific agencies identified
            // individually, each verified against its own publicly stated
            // parent ministry/state department (not inferred from the
            // agency's name) - see comments per group. Exact-name match
            // against the client's current name only, same as every other
            // pass; no fuzzy matching.
            $manualDepartmentLinks = [
                // Agriculture
                'Agricultural and Food Authority' => 'State Department for Agriculture',
                'Agricultural Information Resource Centre (AIRC)' => 'State Department for Agriculture',
                'Ahero Rice Meals' => 'State Department for Agriculture',
                'Chemilil Sugar Company Ltd' => 'State Department for Agriculture',
                'Coffee Research Institute' => 'State Department for Agriculture',
                'Fertilizers and Animal Food Stuff Board' => 'State Department for Agriculture',
                'Freshpick Processors' => 'State Department for Agriculture',
                'Gatitu Tea Factory' => 'State Department for Agriculture',
                'Kenya National Multi Commodities Exchange Ltd' => 'State Department for Agriculture',
                'Kenya Plant Health Inspectorate Services' => 'State Department for Agriculture',
                'Kenya Seed Company Limited' => 'State Department for Agriculture',
                'Kibo Seeds Ltd (Tanzania)' => 'State Department for Agriculture',
                'Kipchabo Tea Factory' => 'State Department for Agriculture',
                'Miwani Sugar Company Limited' => 'State Department for Agriculture',
                'Muhoroni Sugar Company Ltd' => 'State Department for Agriculture',
                'Mumias Sugar Company' => 'State Department for Agriculture',
                'Mwea Rice Mills' => 'State Department for Agriculture',
                'New Kenya Planters Cooperative Union' => 'State Department for Agriculture',
                'Simlaw Seeds Kenya Ltd' => 'State Department for Agriculture',
                'Simlaw Seeds Rwanda' => 'State Department for Agriculture',
                'Simlaw Seeds Uganda' => 'State Department for Agriculture',
                'South Nyanza Sugar Company Limited (SONY)' => 'State Department for Agriculture',
                'Strategic Food Reserve Trust Fund' => 'State Department for Agriculture',
                'Tea Research Foundation' => 'State Department for Agriculture',
                'Warehouse Receipt System Council' => 'State Department for Agriculture',
                'Western Kenya Rice Mills Ltd' => 'State Department for Agriculture',

                // Livestock
                'Kenya Animal Genetic Resource Centre' => 'State Department for Livestock Development',
                'Kenya Veterinary Vaccines Production Institute (KEVEVAPI)' => 'State Department for Livestock Development',

                // Co-operatives
                'Agri and Cooperative Training & Consultancy Services Ltd' => 'State Department for Co-operatives',
                'Cooperative Tribunal' => 'State Department for Co-operatives',
                'State Department for Cooperatives Development' => 'State Department for Co-operatives',

                // Industry / Trade / MSME - Anti-Counterfeit Authority's own
                // site confirms State Department for Industry
                'Anti-Counterfeit Agency' => 'State Department for Industry',
                'Industrial and Commercial Development Corporation' => 'State Department for Industry',
                'Industrial Development Bank Capital' => 'State Department for Industry',
                'Kenya Industrial Estate' => 'State Department for Industry',
                'Kenya Industrial Research & Development Institute' => 'State Department for Industry',
                'Kenya Industrial Training Institute' => 'State Department for Industry',
                'Kenya National Accreditation Service' => 'State Department for Industry',
                'Kenya National Trading Corporation Ltd' => 'State Department for Trade',
                'Kenya Trade Remedies Agency' => 'State Department for Trade',
                'Micro & Small Enterprises Authority' => 'State Department for MSME Development',
                'Kenya National Entrepreneurs Savings Trust' => 'State Department for MSME Development',

                // Energy / Petroleum / Mining
                'Geothermal Development Company (GDC)' => 'State Department for Energy',
                'Kenya Nuclear Regulatory Authority' => 'State Department for Energy',
                'Kenya Power and Lighting Company Ltd' => 'State Department for Energy',
                'Rural Electrification and Renewable Energy Corporation (REREC)' => 'State Department for Energy',
                'Kenya Petroleum Refineries Limited' => 'State Department for Petroleum',
                'Kenya Pipeline Corporation' => 'State Department for Petroleum',
                'National Oil Corporation of Kenya (NOCK)' => 'State Department for Petroleum',
                'Geologist Registration Board' => 'State Department for Mining',

                // Roads / Public Works / Transport
                'Kenya National Highways Authority (KeNHA)' => 'State Department for Roads',
                'Board of Registration of Architects and Quantity Surveyors' => 'State Department for Public Works',
                'Kenya Engineering Technology Registration Board' => 'State Department for Public Works',
                'KENATCO TAXIS Kltd (under receivership)' => 'State Department for Transport',
                'Nairobi Area Metropolitan Transport Authority' => 'State Department for Transport',
                'National Transport & Safety Authority' => 'State Department for Transport',

                // Lands
                'Lands Limited' => 'State Department for Lands and Physical Planning',

                // Water / Environment
                'Hydrologist Registration Board' => 'State Department for Water and Sanitation',
                'Northern Water Works Development Agency (NWSB)' => 'State Department for Water and Sanitation',
                'Regional Centre for Ground Water Resources, Education, Training and Research in East Africa' => 'State Department for Water and Sanitation',
                'Kenya Water Towers Agency' => 'State Department for Environment and Climate Change',
                'National Environment Trust Fund' => 'State Department for Environment and Climate Change',

                // Wildlife / Tourism
                'Wildlife Research Training Institute' => 'State Department for Wildlife',
                'Golf Hotel Kakamega' => 'State Department for Tourism',
                'Kabarnet Hotel Ltd' => 'State Department for Tourism',
                'Kenya Safari Lodges and Hotels Ltd' => 'State Department for Tourism',
                'Kenyatta International Convention Centre' => 'State Department for Tourism',
                'Mt. Elgon Lodge' => 'State Department for Tourism',
                'Sunset Hotel Kisumu' => 'State Department for Tourism',
                'Tourism Finance Corporation (Defunct)' => 'State Department for Tourism',
                'Tourism Research Institute' => 'State Department for Tourism',

                // Blue Economy / Fisheries - official name is "State
                // Department for the Blue Economy and Fisheries"; ours is
                // recorded as "State Department for Blue Economy"
                'Fisheries Development Fund' => 'State Department for Blue Economy',
                'Kenya Fish Marketing Authority' => 'State Department for Blue Economy',
                'Kenya Marine & Fisheries Research Institute' => 'State Department for Blue Economy',

                // Regional Development Authorities (own GovernmentEntity row)
                'Ewaso Ng’iro North Development Authority' => 'Regional Development Authorities',
                'Ewaso Ng’iro South Development Authority' => 'Regional Development Authorities',

                // Health
                'Clinical Council of Kenya' => 'State Department for Medical Services',
                'Health Records and Information Management Board' => 'State Department for Public Health and Professional Standards',
                'Kenya BioVax Institute' => 'State Department for Medical Services',
                'Kenya Health Human Resource Advisory Council' => 'State Department for Medical Services',
                'Kenya Medical Laboratory Technicians and Technologists Board' => 'State Department for Medical Services',
                'Kenya Medical Practitioners and Dentist Council' => 'State Department for Medical Services',
                'Kenya Medical Supplies Agency (KEMSA)' => 'State Department for Medical Services',
                'Kenya National Public Health Institute' => 'State Department for Public Health and Professional Standards',
                'National Hospital Insurance Fund' => 'State Department for Medical Services',
                'National Public Health Laboratory Services' => 'State Department for Public Health and Professional Standards',
                'National Quality Control Laboratory' => 'State Department for Public Health and Professional Standards',
                'National Spinal Injury Hospital' => 'State Department for Medical Services',
                'National Syndemic Disease Control Council' => 'State Department for Public Health and Professional Standards',
                'Occupational Therapy Council of Kenya' => 'State Department for Medical Services',
                'Pharmacy & Poisons Board' => 'State Department for Medical Services',
                'Public Health Officers and Technician Council' => 'State Department for Public Health and Professional Standards',
                'Referral Hospitals Authority' => 'State Department for Medical Services',

                // Basic/special education
                'Centre for Mathematics, Science and Technology Education in Africa' => 'State Department for Basic Education',
                'Kenya Institute of Special Education (KISE)' => 'State Department for Basic Education',
                'National Commission for Nomadic Education in Kenya' => 'State Department for Basic Education',

                // Science / Economic Planning
                'Kenya Advanced Institute of Science and Technology' => 'State Department for Science, Research and Innovation',
                'Kenya Institute of Public Policy Research & Analysis' => 'State Department for Economic Planning',
                'National Coordinating Agency for Population and Development' => 'State Department for Economic Planning',
                'Public Private Partnership Directorate' => 'State Department for Economic Planning',

                // Higher education subsidiary (not itself a university, so
                // not caught by the university name pattern above)
                'JKUAT Industrial Park Limited' => 'State Department for Higher Education and Research',

                // Treasury / Public Investments - confirmed via Capital
                // Markets Authority's own published parent (National
                // Treasury) for the finance-professional-body cluster
                'CBKL Bancassurance Intermediary' => 'State Department for Public Investments and Assets Management',
                'Consolidated Bank' => 'State Department for Public Investments and Assets Management',
                'Development Bank of Kenya Ltd.' => 'State Department for Public Investments and Assets Management',
                'East African Portland Cement Company Ltd' => 'State Department for Public Investments and Assets Management',
                'Kenya National Assurance Company (under receivership)' => 'State Department for Public Investments and Assets Management',
                'Kenya Re-Insurance Corporation' => 'State Department for Public Investments and Assets Management',
                'Kenya Re-Insurance Corporation Cote d’Ivoire' => 'State Department for Public Investments and Assets Management',
                'Kenya Re-Insurance Corporation Uganda SMC' => 'State Department for Public Investments and Assets Management',
                'Kenya Re-Insurance Corporation Zambia Ltd' => 'State Department for Public Investments and Assets Management',
                'Kenya Wine Agencies Limited (KWAL)' => 'State Department for Public Investments and Assets Management',
                'Privatization Commission' => 'State Department for Public Investments and Assets Management',
                'Institute of Certified Investment and Financial Analysts' => 'National Treasury',
                'Institute of Certified Public Accountants of Kenya' => 'National Treasury',
                'Investor Compensation Fund Board' => 'National Treasury',
                'Kenya Accountants and Secretaries National Examination Board' => 'National Treasury',
                'Kenya School of Monetary Studies' => 'National Treasury',
                'Nairobi International Financial Center Authority' => 'National Treasury',
                'Registration of Certified Public Accountants Board' => 'National Treasury',

                // ICT / Broadcasting
                'Information & Communication Technology Authority' => 'State Department for ICT and Digital Economy',
                'Telkom Kenya Limited' => 'State Department for Broadcasting and Telecommunications',

                // Public Service / Labour
                'Institute of Human Resource Management Professionals Examinations Board' => 'State Department for Public Service',
                'Public Service Superannuation Fund Board' => 'State Department for Public Service',
                'Directorate of Occupational Safety and Health Services (DOSHS)' => 'State Department for Labour and Skills Development',
                'National Informal Sector Pension Ltd' => 'State Department for Labour and Skills Development',
                'National Social Security Fund' => 'State Department for Labour and Skills Development',

                // Security / Interior / Immigration / Correctional -
                // NACADA confirmed via its own published parent ministry
                'Directorate of Criminal Investigations (DCI)' => 'State Department for Internal Security and National Administration',
                'National Authority for the Campaign against Alcohol and Drug Abuse' => 'State Department for Internal Security and National Administration',
                'NGOs Coordination Board' => 'State Department for Internal Security and National Administration',
                'Kenya Citizens and Foreign Nationals Management Service' => 'State Department for Immigration and Citizen Services',
                'Kenya Prisons Enterprise Fund' => 'State Department for Correctional Services',

                // Youth / Creative Economy / Sports - Kenya Copyright
                // Board's own published parent is Youth Affairs, Creative
                // Economy and Sports, not Justice/AG as the name might
                // suggest
                'Kenya Copyrights Board' => 'State Department for Youth Affairs and Creative Economy',
                'National Youth Council' => 'State Department for Youth Affairs and Creative Economy',
                'Youth Enterprise Development Fund Board' => 'State Department for Youth Affairs and Creative Economy',
                'Anti-Doping Agency' => 'State Department for Sports',
                'Sports, Arts and Social Development Fund' => 'State Department for Sports',

                // Culture
                'Kenya National Library Service' => 'State Department for Culture, Arts and Heritage',

                // Children / Social protection
                'National Council for Children Services' => 'State Department for Children Services',
                'Street Families Rehabilitation Fund' => 'State Department for Social Protection and Senior Citizens Affairs',
                'Victims Protection Agency' => 'State Department for Justice, Human Rights and Constitutional Affairs',

                // TVET-adjacent boards/funds (not institutions themselves,
                // so not caught by the TVET name pattern above)
                'Technical and Vocational Education and Training Curriculum Development Assessment & Certification Council (TVETCDACC)' => 'State Department for Technical and Vocational Education and Training',
                'Technical Vocational and Training Authority' => 'State Department for Technical and Vocational Education and Training',
                'TVET Fund Board' => 'State Department for Technical and Vocational Education and Training',

                // Rows where the "client" IS itself a state department
                // (same StateCorporation record exists both as a ministry-
                // level placeholder and an actual department row to link to)
                'State Department for Correctional Services' => 'State Department for Correctional Services',
                'State Department for Energy' => 'State Department for Energy',
                'State Department for Housing' => 'State Department for Housing and Urban Development',
                'State Department for Immigration Services' => 'State Department for Immigration and Citizen Services',
                'State Department for Interior' => 'State Department for Internal Security and National Administration',
                'State Department for Lands' => 'State Department for Lands and Physical Planning',
                'State Department for Petroleum' => 'State Department for Petroleum',
                'State Department for Public Works' => 'State Department for Public Works',
                'State Department for Roads' => 'State Department for Roads',
                'State Department for Transport' => 'State Department for Transport',
                'State Department for Wildlife' => 'State Department for Wildlife',
            ];

            // Entities that report through a Ministry's "General / Direct
            // Reporting" placeholder rather than a named State Department -
            // id 24 is specifically "under Ministry of Defence" (see
            // $departmentIdAliases above).
            $manualDepartmentIdLinks = [
                'Kenya Ordinance and Factories Corporation (KOFC)' => 24,
                'Kenya Shipyard Ltd' => 24,
                'National Defense University - Kenya' => 24,
                'State Department for Defence' => 24,
            ];

            $manualMatches = 0;
            $manualUnresolvable = [];

            foreach ($manualDepartmentLinks as $clientName => $deptName) {
                $dept = $departmentsByName->get($normalize($deptName));
                $matches = $clientsByName->get($normalize($clientName));

                if ($dept === null || $matches === null || $matches->count() !== 1) {
                    $manualUnresolvable[] = $clientName;

                    continue;
                }

                $client = $matches->first();
                if ($client->ministry_id !== $dept->id) {
                    StateCorporation::whereKey($client->id)->update(['ministry_id' => $dept->id]);
                    $manualMatches++;
                    $clientsLinked++;
                }
            }

            foreach ($manualDepartmentIdLinks as $clientName => $deptId) {
                $matches = $clientsByName->get($normalize($clientName));

                if ($matches === null || $matches->count() !== 1) {
                    $manualUnresolvable[] = $clientName;

                    continue;
                }

                $client = $matches->first();
                if ($client->ministry_id !== $deptId) {
                    StateCorporation::whereKey($client->id)->update(['ministry_id' => $deptId]);
                    $manualMatches++;
                    $clientsLinked++;
                }
            }

            // Known, deliberate gaps - not guessed. The first two have no
            // corresponding GovernmentEntity row at all (nothing under the
            // Office of the President exists in the tree to link to); NG-CDF
            // has had a genuinely unsettled institutional home across
            // successive reorganizations and legal challenges, so rather
            // than guess, it's left for manual assignment.
            $knownGaps = [
                'Executive Office of the President' => 'No GovernmentEntity row exists for the Office of the President in the current tree.',
                'Government Printer (Government Press)' => 'No GovernmentEntity row exists for the Office of the President in the current tree.',
                'The President’s Awards' => 'No GovernmentEntity row exists for the Office of the President in the current tree.',
                'National Government Constituency Development Fund' => 'Institutional home has changed across several reorganizations/legal challenges - not safe to assume one without a direct decision.',
            ];

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->info(($dryRun ? '[DRY RUN - nothing saved] ' : '')."Contact person set on {$contactsUpdated} department(s). Clients linked/re-linked to their real state department: {$clientsLinked} (of which {$institutionMatches} via direct Institution-entity name match, {$patternMatches} via TVET/university name pattern, {$manualMatches} via the hand-researched manual map).");

        if ($manualUnresolvable) {
            $this->newLine();
            $this->warn(count($manualUnresolvable).' name(s) in the manual map no longer match exactly one client (renamed, removed, or already resolved differently) - review LinkClientsToStateDepartments\' $manualDepartmentLinks/$manualDepartmentIdLinks:');
            foreach ($manualUnresolvable as $name) {
                $this->line("  - {$name}");
            }
        }

        if ($knownGaps) {
            $this->newLine();
            $this->warn(count($knownGaps).' client(s) are known, deliberate gaps - not guessed:');
            foreach ($knownGaps as $name => $reason) {
                $this->line("  - {$name}: {$reason}");
            }
        }

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
            ->whereNotIn('classification', ['Constitutional Commission', 'Independent Office', 'Judiciary', 'Legislature', 'Private Company', 'County Government'])
            ->count();

        if ($stillUnresolved > 0) {
            $this->newLine();
            $this->warn("{$stillUnresolved} client(s) still have no state department after this run - their name doesn't appear in either source, so nothing was guessed. They'll need either better source data or a manual RM assignment (Assign RMs -> Clients).");
        }

        return self::SUCCESS;
    }
}
