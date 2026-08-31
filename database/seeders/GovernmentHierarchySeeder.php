<?php

namespace Database\Seeders;

use App\Models\GovernmentEntity;
use Illuminate\Database\Seeder;

/**
 * Transcribed from "Kenya National Government Master Hierarchy" (the
 * boss's brief). Kept to 3 levels - Ministry -> State Department ->
 * Institution - matching what the RM form actually needs, even though a
 * few branches in the source document go a level deeper.
 *
 * Judgment calls made while transcribing, flagged here rather than left
 * silent:
 *  - A few institutions have their own listed sub-bodies (e.g. National
 *    Police Service -> Kenya Police Service / Administration Police
 *    Service; Kenya Defence Forces -> Army/Air Force/Navy). Both the
 *    parent and its children are flattened into level 3 as separate,
 *    independently selectable institutions.
 *  - Where a ministry lists institutions directly (no named state
 *    department above them - e.g. Defence, most of Agriculture's
 *    Fisheries line), they're bucketed under a synthetic "General / Direct
 *    Reporting" state department for that ministry.
 *  - The Interior ministry's "National Government Administration
 *    Structures" lists office-holder ranks (Regional Commissioners,
 *    Chiefs, etc.), not institutions - those are skipped; the structure
 *    itself is kept as one selectable entry.
 *  - "State Law Office and Department of Justice" isn't literally named a
 *    "Ministry" in the brief, but the brief's own master top-level tree
 *    (section 23) lists it as a peer of every ministry, so it's imported
 *    at level 1 alongside them.
 *
 * Every record is seeded with status = 'pending_verification' (the Data
 * Quality Rule's default) since none of this has been confirmed against a
 * current Gazette notice yet - that's a follow-up task, not this one.
 */
class GovernmentHierarchySeeder extends Seeder
{
    private const DIRECT = 'General / Direct Reporting';

    private const HIERARCHY = [
        'Ministry of Interior and National Administration' => [
            'State Department for Internal Security and National Administration' => [
                'National Police Service', 'Kenya Police Service', 'Administration Police Service',
                'National Government Administration', 'National Police Service Commission',
                'National Disaster Operations Centre', 'National Counter Terrorism Centre',
                'Kenya Focal Point on Small Arms and Light Weapons',
                'National Government Administration Structures',
            ],
            'State Department for Correctional Services' => [
                'Kenya Prisons Service', 'Probation and Aftercare Service', 'Borstal and Rehabilitation Institutions',
            ],
            'State Department for Immigration and Citizen Services' => [
                'Directorate of Immigration Services', 'National Registration Bureau', 'Civil Registration Services',
                'Refugee Affairs Secretariat', 'National Registration Services',
                'Huduma-related citizen service structures where applicable',
            ],
        ],
        'Ministry of Defence' => [
            self::DIRECT => [
                'Kenya Defence Forces', 'Kenya Army', 'Kenya Air Force', 'Kenya Navy',
                'Defence Forces Canteen Organization', 'Defence Forces Medical Insurance Scheme',
                'Defence Forces Old Comrades Association', 'Kenya Ordnance Factories Corporation',
                'Kenya Meat Commission', 'Kenya Shipyards Limited', 'Kenya Space Agency',
                'National Defence University–Kenya',
            ],
        ],
        'The National Treasury and Economic Planning' => [
            'National Treasury' => [
                'Central Bank of Kenya', 'Kenya Revenue Authority', 'Kenya Development Corporation',
                'Public Procurement Regulatory Authority', 'Public Sector Accounting Standards Board',
                'Capital Markets Authority', 'Insurance Regulatory Authority', 'Retirement Benefits Authority',
                'Competition Authority of Kenya', 'Competition Tribunal', 'Financial Reporting Centre',
                'Kenya Reinsurance Corporation', 'Kenya Post Office Savings Bank', 'Consolidated Bank of Kenya',
                'Nairobi International Financial Centre Authority', 'Kenya Investment Authority',
                'Kenya Trade Network Agency', 'Kenya Accountants and Secretaries National Examinations Board',
                'Unclaimed Financial Assets Authority', 'Public Private Partnerships Directorate',
                'Government Clearing Agency', 'Public Service Superannuation Scheme',
                'Other Treasury-linked institutions',
            ],
            'State Department for Economic Planning' => [
                'Kenya National Bureau of Statistics', 'Kenya Institute for Public Policy Research and Analysis',
                'National economic planning structures',
            ],
            'State Department for Public Investments and Assets Management' => [
                'Public investment oversight structures', 'Government asset management structures',
                'Public investments and portfolio management structures',
            ],
        ],
        'Ministry of Foreign and Diaspora Affairs' => [
            'State Department for Foreign Affairs' => [
                'Kenya Foreign Service Institute', 'Kenyan Embassies', 'High Commissions', 'Consulates',
                'Permanent Missions', 'Diplomatic Missions',
            ],
            'State Department for Diaspora Affairs' => [
                'Diaspora Services', 'Diaspora Investment Support', 'Diaspora Welfare Services',
                'Diaspora Engagement Structures',
            ],
        ],
        'Ministry of Public Service, Gender and Affirmative Action' => [
            'State Department for Public Service' => [
                'Kenya School of Government', 'Government Human Resource Management Structures',
                'Public Service Transformation Structures',
            ],
            'State Department for Gender and Affirmative Action' => [
                'National Gender and Equality Commission', 'National Gender and Equality structures',
                'Women Empowerment Programmes', 'Youth and Affirmative Action support structures',
                'Gender Mainstreaming structures',
            ],
        ],
        'Ministry of Roads and Transport' => [
            'State Department for Roads' => [
                'Kenya National Highways Authority', 'Kenya Urban Roads Authority', 'Kenya Rural Roads Authority',
                'Kenya Roads Board', 'Kenya Institute of Highways and Building Technology',
                'Kenya Institute of Technology', 'Engineers Registration Board of Kenya',
            ],
            'State Department for Transport' => [
                'Kenya Airports Authority', 'Kenya Civil Aviation Authority', 'Kenya Ports Authority',
                'Kenya Ferry Services', 'Kenya National Shipping Line', 'Kenya Railways Corporation',
                'National Transport and Safety Authority',
                'Northern Corridor Transit and Transport Coordination Authority',
                'LAPSSET Corridor Development Authority',
            ],
            'State Department for Shipping and Maritime Affairs' => [
                'Kenya Maritime Authority', 'Maritime training and regulatory structures',
                'Shipping and maritime administration structures',
            ],
        ],
        'Ministry of Lands, Public Works, Housing and Urban Development' => [
            'State Department for Lands and Physical Planning' => [
                'National Land Commission', 'Kenya Institute of Surveying and Mapping', 'Directorate of Surveys',
                'Land Registration Offices', 'Land Adjudication Offices', 'Physical Planning Offices',
            ],
            'State Department for Housing and Urban Development' => [
                'National Housing Corporation', 'Affordable Housing Programme structures',
                'Urban Development institutions', 'Housing development structures',
            ],
            'State Department for Public Works' => [
                'Government Buildings Department', 'Mechanical and Transport Services structures',
                'Public Works technical offices',
            ],
        ],
        'Ministry of Information, Communications and the Digital Economy' => [
            'State Department for ICT and Digital Economy' => [
                'Communications Authority of Kenya', 'Kenya ICT Authority',
                'Konza Technopolis Development Authority', 'Office of the Data Protection Commissioner',
                'Digital Government structures',
            ],
            'State Department for Broadcasting and Telecommunications' => [
                'Kenya Broadcasting Corporation', 'Postal Corporation of Kenya',
                'Kenya Institute of Mass Communication', 'National Communications Secretariat',
                'Media Council of Kenya', 'Kenya Yearbook Editorial Board',
            ],
            self::DIRECT => [
                'Digital Government and Communication Support Structures',
            ],
        ],
        'Ministry of Health' => [
            'State Department for Medical Services' => [
                'Kenya Medical Supplies Authority', 'Kenya Medical Research Institute',
                'National Hospital Insurance / Social Health structures', 'Kenyatta National Hospital',
                'Moi Teaching and Referral Hospital', 'Mathari National Teaching and Referral Hospital',
                'Kenya National Blood Transfusion Service', 'National Referral and Specialized Health Institutions',
            ],
            'State Department for Public Health and Professional Standards' => [
                'Kenya Medical Practitioners and Dentists Council', 'Nursing Council of Kenya',
                'Pharmacy and Poisons Board', 'Clinical Officers Council',
                'Kenya Nutritionists and Dieticians Institute', 'Other Health Professional Regulatory Bodies',
            ],
            'State Department for Digital Health and related health information structures' => [],
        ],
        'Ministry of Education' => [
            'State Department for Basic Education' => [
                'Kenya National Examinations Council', 'Kenya Institute of Curriculum Development',
                'Kenya Institute of Special Education', 'Kenya Education Management Institute',
                'Kenya Literature Bureau', 'Jomo Kenyatta Foundation', 'School Equipment Production Unit',
                'National Commission for UNESCO', 'Basic Education administration structures',
            ],
            'State Department for Higher Education and Research' => [
                'Commission for University Education', 'Higher Education Loans Board', 'Universities Fund',
                'Kenya Universities and Colleges Central Placement Service',
                'National Commission for Science, Technology and Innovation', 'National Research Fund',
                'Kenya National Innovation Agency', 'Public Universities', 'University Constituent Colleges',
                'Research Institutions',
            ],
            'State Department for Technical and Vocational Education and Training' => [
                'Technical and Vocational Education and Training Authority',
                'TVET Curriculum Development, Assessment and Certification Council', 'Kenya School of TVET',
                'National Polytechnics', 'Technical Training Institutes', 'Vocational and Technical Institutions',
            ],
            'State Department for Science, Research and Innovation' => [
                'National Biosafety Authority', 'Biosafety Appeals Board', 'Research and innovation structures',
                'Science and technology institutions',
            ],
        ],
        'Ministry of Agriculture and Livestock Development' => [
            'State Department for Agriculture' => [
                'Agriculture and Food Authority', 'Kenya Agricultural and Livestock Research Organization',
                'Kenya Plant Health Inspectorate Service', 'Kenya Agricultural and Livestock Extension structures',
                'Kenya Agricultural Insurance structures', 'Commodity and crop development institutions',
            ],
            'State Department for Livestock Development' => [
                'Kenya Meat Commission', 'Kenya Veterinary Vaccines Production Institute', 'Kenya Veterinary Board',
                'Veterinary Medicines Directorate', 'Livestock Marketing structures', 'Animal Production institutions',
            ],
            self::DIRECT => [
                'Fisheries and Aquaculture institutions where assigned under the current portfolio',
            ],
        ],
        'Ministry of Trade, Investments and Industry' => [
            'State Department for Trade' => [
                'Kenya Export Promotion and Branding Agency', 'Anti-Counterfeit Authority',
                'Kenya National Trading Corporation', 'Kenya Trade Portal', 'Trade development structures',
            ],
            'State Department for Industry' => [
                'Kenya Industrial Research and Development Institute', 'Kenya Industrial Estates',
                'Numerical Machining Complex', 'Kenya Industrial Property Institute',
                'Industrial development institutions',
            ],
            'State Department / Investment Promotion Function' => [
                'Kenya Investment Authority', 'Special Economic Zones Authority',
                'Investment promotion structures',
            ],
        ],
        'Ministry of Co-operatives and MSME Development' => [
            'State Department for Co-operatives' => [
                'Sacco Societies Regulatory Authority', 'Co-operative University and Training structures',
                'Co-operative development offices',
            ],
            'State Department for MSME Development' => [
                'Micro and Small Enterprises Authority', 'Micro and Small Enterprises development structures',
                'MSME financing and enterprise development structures', 'Enterprise development institutions',
            ],
        ],
        'Ministry of Youth Affairs, Sports and the Arts' => [
            'State Department for Youth Affairs and Creative Economy' => [
                'National Youth Service', 'Youth Enterprise Development Fund', 'Youth Development institutions',
                'Creative Economy structures',
            ],
            'State Department for Sports' => [
                'Sports Kenya', 'Anti-Doping Agency of Kenya', 'Kenya Academy of Sports',
                'National Sports Institutions',
            ],
            'State Department for Culture, Arts and Heritage' => [
                'National Museums of Kenya', 'Kenya Cultural Centre', 'Kenya Film Classification Board',
                'Kenya Film Commission', 'National Archives', 'Cultural and Heritage institutions',
            ],
        ],
        'Ministry of Environment and Forestry' => [
            'State Department for Environment and Climate Change' => [
                'National Environment Management Authority', 'Climate Change Directorate',
                'National Climate Change Council structures', 'Environmental management structures',
            ],
            'State Department for Forestry' => [
                'Kenya Forest Service', 'Kenya Forestry Research Institute',
                'Forestry and forest conservation institutions',
            ],
        ],
        'Ministry of Tourism, Wildlife and Heritage' => [
            'State Department for Tourism' => [
                'Kenya Tourism Board', 'Tourism Regulatory Authority', 'Tourism Fund', 'Kenya Utalii College',
                'Tourism promotion institutions',
            ],
            'State Department for Wildlife' => [
                'Kenya Wildlife Service', 'Wildlife conservation structures',
            ],
            self::DIRECT => [
                'Heritage institutions where assigned under the current portfolio',
            ],
        ],
        'Ministry of Water and Sanitation' => [
            'State Department for Water and Sanitation' => [
                'Water Services Regulatory Board', 'Water Sector Trust Fund',
                'National Water Harvesting and Storage Authority', 'Water Works Development Agencies',
                'Regional Water Development Agencies',
            ],
            'State Department for Irrigation' => [
                'National Irrigation Authority', 'Irrigation development structures',
                'National irrigation schemes',
            ],
        ],
        'Ministry of Energy and Petroleum' => [
            'State Department for Energy' => [
                'Energy and Petroleum Regulatory Authority', 'Kenya Power and Lighting Company',
                'Kenya Electricity Generating Company', 'Kenya Electricity Transmission Company',
                'Geothermal Development Company', 'Rural Electrification and Renewable Energy Corporation',
                'Nuclear Power and Energy Agency', 'Energy development institutions',
            ],
            'State Department for Petroleum' => [
                'Petroleum regulatory structures', 'National Oil Corporation of Kenya',
                'Petroleum development institutions',
            ],
        ],
        'Ministry of Labour and Social Protection' => [
            'State Department for Labour and Skills Development' => [
                'National Employment Authority', 'National Industrial Training Authority',
                'Directorate of Occupational Safety and Health Services', 'Wages Councils and Labour institutions',
                'Labour offices',
            ],
            'State Department for Social Protection and Senior Citizens Affairs' => [
                'National Social Protection structures', 'Older Persons Cash Transfer structures',
                'Social Assistance structures', 'Social Development offices',
            ],
            'State Department for Children Services' => [
                'Directorate / Department of Children Services', "Children's Remand Homes",
                "Children's Rehabilitation Centres", 'Child protection structures',
            ],
        ],
        'Ministry of East African Community, ASALs and Regional Development' => [
            'State Department for East African Community Affairs' => [
                'East African Community coordination structures', 'Regional integration offices',
            ],
            'State Department for ASALs and Regional Development' => [
                'National Drought Management Authority', 'ASAL development structures',
                'Regional development programmes',
            ],
            'Regional Development Authorities' => [
                "Ewaso Ng'iro North Development Authority", "Ewaso Ng'iro South Development Authority",
                'Lake Basin Development Authority', 'Kerio Valley Development Authority',
                'Coast Development Authority', 'Tana and Athi Rivers Development Authority',
                'Northern Kenya Development Authority / successor structures where applicable',
            ],
            self::DIRECT => [
                'Devolution-related functions where assigned',
            ],
        ],
        'Ministry of Mining, Blue Economy and Maritime Affairs' => [
            'State Department for Mining' => [
                'Geological Survey of Kenya', 'Mineral Rights Board', 'Kenya Mining Cadastre Administration',
                'Mining regulatory structures',
            ],
            'State Department for Blue Economy' => [
                'Kenya Fisheries Service', 'Kenya Fisheries and Marine Research Institute',
                'Kenya Marine and Fisheries Research structures', 'Blue Economy development institutions',
            ],
            'State Department for Maritime Affairs' => [
                'Kenya Maritime Authority', 'Maritime training structures',
                'Shipping and maritime development institutions',
            ],
        ],
        'State Law Office and Department of Justice' => [
            self::DIRECT => [
                'Office of the Attorney-General', 'Department of Justice', 'Kenya Law Reform Commission',
                'Business Registration Service', 'Office of the Registrar-General', 'Kenya School of Law',
                'Council of Legal Education', 'National Legal Aid Service',
            ],
            'State Department for Justice, Human Rights and Constitutional Affairs' => [
                'Justice sector reform structures', 'Human rights coordination structures',
                'Constitutional affairs structures',
            ],
        ],
        // Added per the boss's Level 1 spec ("22 Ministries, Council of
        // Governors and the Presidency") - brings the top level to 24.
        // No state departments listed yet for either; the Presidency's own
        // departments ("Presidency Departments" per that same spec) are a
        // follow-up once that list is provided, same placeholder pattern
        // used for Rural Housing/Climate Works in the sibling project.
        'The Presidency' => [],
        'Council of Governors' => [],
    ];

    public function run(): void
    {
        $ministries = 0;
        $departments = 0;
        $institutions = 0;

        foreach (self::HIERARCHY as $ministryName => $stateDepartments) {
            $ministry = GovernmentEntity::firstOrCreate(
                ['name' => $ministryName, 'level' => GovernmentEntity::LEVEL_MINISTRY],
                ['type' => 'ministry']
            );
            $ministries++;

            foreach ($stateDepartments as $deptName => $institutionNames) {
                $department = GovernmentEntity::firstOrCreate(
                    ['name' => $deptName, 'parent_id' => $ministry->id, 'level' => GovernmentEntity::LEVEL_STATE_DEPARTMENT],
                    ['type' => 'state_department']
                );
                $departments++;

                foreach ($institutionNames as $institutionName) {
                    GovernmentEntity::firstOrCreate(
                        ['name' => $institutionName, 'parent_id' => $department->id, 'level' => GovernmentEntity::LEVEL_INSTITUTION],
                        ['type' => 'institution']
                    );
                    $institutions++;
                }
            }
        }

        $this->command?->info("Government hierarchy seeded: {$ministries} ministries, {$departments} state departments, {$institutions} institutions.");
    }
}
