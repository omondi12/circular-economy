<?php

namespace App\Support;

/**
 * Lot -> Category -> Subcategory -> Unit of Measure, per the boss's "AMAC
 * Lot Category Proposal" brief. Replaces the earlier flat Lot -> Category
 * (always kg) shape - government assets aren't all weighed, so Lot 1 now
 * classifies WHAT is being disposed of (category/subcategory) separately
 * from HOW it's counted (a unit the RM picks from that subcategory's valid
 * list), rather than forcing everything into kilograms.
 *
 * Lot 2 (waste) keeps its original 2-level shape (Category -> Units) per
 * the brief - "Lot 2 is generally acceptable... keep it mostly as it is" -
 * waste genuinely is measured by weight/volume, so no subcategory split was
 * requested there.
 *
 * Five Lot 1 categories (Plant & Machinery, Stores & Materials, Scrap
 * Materials, Tyres & Automotive Parts, Other Government Assets) were listed
 * in the brief with no subcategories underneath - each gets one generic
 * "General ..." subcategory here so the Lot -> Category -> Subcategory ->
 * Unit shape stays uniform across all of Lot 1; flag to the boss if he'd
 * rather see these broken out further.
 */
class WasteCategories
{
    public const LOT_SALE = 1;

    public const LOT_DISPOSAL = 2;

    /**
     * Canonical unit keys stored on a Collection row -> display label.
     * "Units" (whole items, e.g. a vehicle) is kept distinct from "Pieces"
     * (individual items, e.g. a chair) to match the brief's own examples
     * ("4 Vehicles" vs "120 Chairs") rather than collapsing them into one.
     */
    public const UNIT_LABELS = [
        'kg' => 'kg',
        'tonnes' => 'Tonnes',
        'litres' => 'Litres',
        'm3' => 'm³',
        'pieces' => 'Pieces',
        'units' => 'Units',
        'cartons' => 'Cartons',
        'sets' => 'Sets',
    ];

    private const LOTS = [
        self::LOT_SALE => [
            'label' => 'Lot 1 - Sale by Public Auction',
            'short_label' => 'Lot 1 (Sale)',
            'has_subcategories' => true,
            'categories' => [
                'motor_vehicles' => [
                    'label' => 'Motor Vehicles',
                    'subcategories' => [
                        'cars' => ['label' => 'Cars', 'units' => ['units']],
                        'pickups' => ['label' => 'Pickups', 'units' => ['units']],
                        'buses' => ['label' => 'Buses', 'units' => ['units']],
                        'trucks' => ['label' => 'Trucks', 'units' => ['units']],
                        'motorcycles' => ['label' => 'Motorcycles', 'units' => ['units']],
                        'heavy_machinery' => ['label' => 'Heavy Machinery', 'units' => ['units']],
                    ],
                ],
                'ict_electronics' => [
                    'label' => 'ICT & Electronic Equipment',
                    'subcategories' => [
                        'desktop_computers' => ['label' => 'Desktop Computers', 'units' => ['pieces']],
                        'laptops' => ['label' => 'Laptops', 'units' => ['pieces']],
                        'printers' => ['label' => 'Printers', 'units' => ['pieces']],
                        'photocopiers' => ['label' => 'Photocopiers', 'units' => ['pieces']],
                        'servers' => ['label' => 'Servers', 'units' => ['pieces']],
                        'monitors' => ['label' => 'Monitors', 'units' => ['pieces']],
                        'other_electronics' => ['label' => 'Other Electronics', 'units' => ['pieces']],
                    ],
                ],
                'furniture_fittings' => [
                    'label' => 'Furniture & Fittings',
                    'subcategories' => [
                        'chairs' => ['label' => 'Chairs', 'units' => ['pieces']],
                        'desks' => ['label' => 'Desks', 'units' => ['pieces']],
                        'tables' => ['label' => 'Tables', 'units' => ['pieces']],
                        'cabinets' => ['label' => 'Cabinets', 'units' => ['pieces']],
                        'filing_cabinets' => ['label' => 'Filing Cabinets', 'units' => ['pieces']],
                        'other_furniture' => ['label' => 'Other Furniture', 'units' => ['pieces']],
                    ],
                ],
                'office_equipment' => [
                    'label' => 'Office & General Equipment',
                    'subcategories' => [
                        'generators' => ['label' => 'Generators', 'units' => ['pieces']],
                        'air_conditioners' => ['label' => 'Air Conditioners', 'units' => ['pieces']],
                        'refrigerators' => ['label' => 'Refrigerators', 'units' => ['pieces']],
                        'water_dispensers' => ['label' => 'Water Dispensers', 'units' => ['pieces']],
                        'other_equipment' => ['label' => 'Other Equipment', 'units' => ['pieces']],
                    ],
                ],
                'plant_machinery' => [
                    'label' => 'Plant & Machinery',
                    'subcategories' => [
                        'general' => ['label' => 'General Plant & Machinery', 'units' => ['pieces', 'units']],
                    ],
                ],
                'stores_materials' => [
                    'label' => 'Stores & Materials',
                    'subcategories' => [
                        'general' => ['label' => 'General Stores & Materials', 'units' => ['cartons', 'kg', 'pieces']],
                    ],
                ],
                'scrap_materials' => [
                    'label' => 'Scrap Materials',
                    'subcategories' => [
                        'general' => ['label' => 'General Scrap Materials', 'units' => ['kg', 'tonnes']],
                    ],
                ],
                'tyres_automotive' => [
                    'label' => 'Tyres & Automotive Parts',
                    'subcategories' => [
                        'general' => ['label' => 'General Tyres & Automotive Parts', 'units' => ['pieces', 'sets', 'kg']],
                    ],
                ],
                'other_assets' => [
                    'label' => 'Other Government Assets',
                    'subcategories' => [
                        'general' => ['label' => 'Other', 'units' => ['pieces', 'units', 'kg', 'tonnes']],
                    ],
                ],
            ],
        ],
        self::LOT_DISPOSAL => [
            'label' => 'Lot 2 - Waste Disposal Management',
            'short_label' => 'Lot 2 (Disposal)',
            'has_subcategories' => false,
            'categories' => [
                'medical_waste' => ['label' => 'Medical Waste', 'units' => ['kg']],
                'industrial_hazardous' => ['label' => 'Industrial / Hazardous Waste', 'units' => ['kg', 'tonnes']],
                'liquid_waste' => ['label' => 'Liquid Waste', 'units' => ['litres', 'm3']],
                'ewaste' => ['label' => 'E-Waste', 'units' => ['kg', 'tonnes']],
                'solid_waste' => ['label' => 'Solid Waste', 'units' => ['kg', 'tonnes']],
                'construction_waste' => ['label' => 'Construction Waste', 'units' => ['tonnes', 'm3']],
                'other_waste' => ['label' => 'Other Waste', 'units' => ['kg', 'tonnes', 'litres', 'm3']],
            ],
        ],
    ];

    /**
     * @return array<int, array{label: string, short_label: string, has_subcategories: bool, categories: array}>
     */
    public static function lots(): array
    {
        return self::LOTS;
    }

    public static function lotLabel(?int $lot): ?string
    {
        return self::LOTS[$lot]['label'] ?? null;
    }

    public static function shortLotLabel(?int $lot): ?string
    {
        return self::LOTS[$lot]['short_label'] ?? null;
    }

    public static function hasSubcategories(?int $lot): bool
    {
        return self::LOTS[$lot]['has_subcategories'] ?? false;
    }

    /**
     * @return array<string, array{label: string, subcategories?: array, units?: array<string>}>
     */
    public static function categoriesFor(?int $lot): array
    {
        return self::LOTS[$lot]['categories'] ?? [];
    }

    public static function categoryLabel(?int $lot, ?string $category): ?string
    {
        return self::LOTS[$lot]['categories'][$category]['label'] ?? null;
    }

    public static function isValidCategory(?int $lot, ?string $category): bool
    {
        return $category !== null && array_key_exists($category, self::categoriesFor($lot));
    }

    /**
     * @return array<string, array{label: string, units: array<string>}>
     */
    public static function subcategoriesFor(?int $lot, ?string $category): array
    {
        return self::LOTS[$lot]['categories'][$category]['subcategories'] ?? [];
    }

    public static function subcategoryLabel(?int $lot, ?string $category, ?string $subcategory): ?string
    {
        return self::LOTS[$lot]['categories'][$category]['subcategories'][$subcategory]['label'] ?? null;
    }

    public static function isValidSubcategory(?int $lot, ?string $category, ?string $subcategory): bool
    {
        return $subcategory !== null && array_key_exists($subcategory, self::subcategoriesFor($lot, $category));
    }

    /**
     * Valid unit keys for a Lot 1 category+subcategory, or a Lot 2 category
     * directly ($subcategory ignored - Lot 2 has no subcategory level).
     *
     * @return array<string>
     */
    public static function unitsFor(?int $lot, ?string $category, ?string $subcategory = null): array
    {
        if (self::hasSubcategories($lot)) {
            return self::LOTS[$lot]['categories'][$category]['subcategories'][$subcategory]['units'] ?? [];
        }

        return self::LOTS[$lot]['categories'][$category]['units'] ?? [];
    }

    public static function isValidUnit(?int $lot, ?string $category, ?string $subcategory, ?string $unit): bool
    {
        return $unit !== null && in_array($unit, self::unitsFor($lot, $category, $subcategory), true);
    }

    public static function unitLabel(?string $unit): ?string
    {
        return self::UNIT_LABELS[$unit] ?? $unit;
    }
}
