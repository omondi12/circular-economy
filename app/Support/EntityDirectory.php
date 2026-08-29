<?php

namespace App\Support;

/**
 * Counties and Commissions/Other Bodies, transcribed from the boss's
 * "Kenya_County_Commission_Department_Tree.xlsx" (Flat Dropdown Feed
 * sheet). Entity Type is now 3-way, not 2: National Government Ministry
 * (unchanged, handled separately via GovernmentEntity), County, and
 * Commission / Other - each of the latter two get their own cascading
 * Entity -> Department/Agency dropdown here.
 *
 * Per the workbook's own "Read Me" sheet: county department/docket NAMES
 * differ from county to county in reality and change with every new
 * administration (each Governor issues an Executive Order naming their own
 * County Executive Committee dockets) - the list here is a GENERIC first
 * draft shared across all 47 counties, not an official per-county list.
 * Commission/directorate names are similarly indicative, based on typical
 * corporate-services structures, and should be confirmed against each
 * body's current organogram before this is treated as authoritative.
 */
class EntityDirectory
{
    private const COUNTIES = [
        'Nairobi',
        'Kiambu',
        'Kirinyaga',
        "Murang'a",
        'Nyandarua',
        'Nyeri',
        'Kilifi',
        'Kwale',
        'Lamu',
        'Mombasa',
        'Taita-Taveta',
        'Tana River',
        'Embu',
        'Isiolo',
        'Kitui',
        'Machakos',
        'Makueni',
        'Marsabit',
        'Meru',
        'Tharaka-Nithi',
        'Garissa',
        'Mandera',
        'Wajir',
        'Homa Bay',
        'Kisii',
        'Kisumu',
        'Migori',
        'Nyamira',
        'Siaya',
        'Baringo',
        'Bomet',
        'Elgeyo-Marakwet',
        'Kajiado',
        'Kericho',
        'Laikipia',
        'Nakuru',
        'Nandi',
        'Narok',
        'Samburu',
        'Trans Nzoia',
        'Turkana',
        'Uasin Gishu',
        'West Pokot',
        'Bungoma',
        'Busia',
        'Kakamega',
        'Vihiga',
    ];

    /**
     * Shared across all 47 counties (a generic first draft, per the
     * workbook - see class docblock).
     */
    private const COUNTY_DEPARTMENTS = [
        'Finance and Economic Planning',
        'Health Services',
        'Agriculture, Livestock and Fisheries',
        'Education, ICT, Youth Affairs and Sports',
        'Trade, Industrialization, Tourism and Cooperative Development',
        'Water, Environment, Energy and Natural Resources',
        'Lands, Physical Planning, Housing and Urban Development',
        'Roads, Transport, Public Works and Infrastructure',
        'Public Service, County Administration and Devolution',
        'Gender, Culture, Social Services and Special Programmes',
    ];

    private const COMMISSIONS = [
        'Independent Electoral and Boundaries Commission (IEBC)' => [
            'category' => 'Constitutional Commission',
            'departments' => [
                'Electoral Operations',
                'Voter Registration',
                'Boundaries Delimitation',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'Kenya National Commission on Human Rights (KNCHR)' => [
            'category' => 'Constitutional Commission',
            'departments' => [
                'Human Rights Monitoring & Investigations',
                'Complaints & Redress',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'National Land Commission (NLC)' => [
            'category' => 'Constitutional Commission',
            'departments' => [
                'Land Administration',
                'Land Adjudication & Dispute Resolution',
                'Land Use Planning',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'Parliamentary Service Commission (PSC)' => [
            'category' => 'Constitutional Commission',
            'departments' => [
                'Legislative Services',
                'Committee Services',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'Judicial Service Commission (JSC)' => [
            'category' => 'Constitutional Commission',
            'departments' => [
                'Judicial Appointments & Discipline',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'Commission on Revenue Allocation (CRA)' => [
            'category' => 'Constitutional Commission',
            'departments' => [
                'Revenue Allocation Policy & Research',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'Public Service Commission (PSC)' => [
            'category' => 'Constitutional Commission',
            'departments' => [
                'Human Resource Management & Development (Public Service)',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'Salaries and Remuneration Commission (SRC)' => [
            'category' => 'Constitutional Commission',
            'departments' => [
                'Job Evaluation & Remuneration Policy',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'Teachers Service Commission (TSC)' => [
            'category' => 'Constitutional Commission',
            'departments' => [
                'Teacher Registration, Deployment & Discipline',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'National Police Service Commission (NPSC)' => [
            'category' => 'Constitutional Commission',
            'departments' => [
                'Police Recruitment, Promotions & Discipline',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'Ethics and Anti-Corruption Commission (EACC)' => [
            'category' => 'Independent Commission (Act of Parliament)',
            'departments' => [
                'Investigations',
                'Prevention & Education',
                'Asset Tracing & Recovery',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'National Gender and Equality Commission (NGEC)' => [
            'category' => 'Independent Commission (Act of Parliament)',
            'departments' => [
                'Gender Equality & Inclusion Monitoring',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'Commission on Administrative Justice / Office of the Ombudsman (CAJ)' => [
            'category' => 'Independent Commission (Act of Parliament)',
            'departments' => [
                'Public Complaints & Maladministration',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'Independent Policing Oversight Authority (IPOA)' => [
            'category' => 'Independent Commission (Act of Parliament)',
            'departments' => [
                'Police Conduct Oversight & Investigations',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'Office of the Auditor-General (OAG)' => [
            'category' => 'Independent Office',
            'departments' => [
                'National Government Audit',
                'County Government Audit',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'Office of the Controller of Budget (COB)' => [
            'category' => 'Independent Office',
            'departments' => [
                'National Budget Oversight',
                'County Budget Oversight',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'Office of the Director of Public Prosecutions (ODPP)' => [
            'category' => 'Independent Office',
            'departments' => [
                'Public Prosecutions',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
        'Office of the Attorney-General (AG)' => [
            'category' => 'Independent Office',
            'departments' => [
                'Government Legal Advisory',
                'Legislative Drafting',
                'Corporate Services & Administration',
                'Finance & Accounts',
                'Human Resource & Development',
                'ICT',
                'Legal Affairs',
                'Public Communications & Affairs',
            ],
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function counties(): array
    {
        return self::COUNTIES;
    }

    public static function isValidCounty(?string $county): bool
    {
        return $county !== null && in_array($county, self::COUNTIES, true);
    }

    /**
     * Same list regardless of which county - see class docblock.
     *
     * @return array<int, string>
     */
    public static function countyDepartments(): array
    {
        return self::COUNTY_DEPARTMENTS;
    }

    public static function isValidCountyDepartment(?string $department): bool
    {
        return $department !== null && in_array($department, self::COUNTY_DEPARTMENTS, true);
    }

    /**
     * @return array<string, array{category: string, departments: array<int, string>}>
     */
    public static function commissions(): array
    {
        return self::COMMISSIONS;
    }

    public static function isValidCommission(?string $commission): bool
    {
        return $commission !== null && array_key_exists($commission, self::COMMISSIONS);
    }

    /**
     * @return array<int, string>
     */
    public static function commissionDepartments(?string $commission): array
    {
        return self::COMMISSIONS[$commission]['departments'] ?? [];
    }

    public static function isValidCommissionDepartment(?string $commission, ?string $department): bool
    {
        return $department !== null && in_array($department, self::commissionDepartments($commission), true);
    }
}
