<?php

namespace App\Support;

/**
 * Fixed Lot -> Category taxonomy taken from the boss's disposal/auction
 * award letter. Lot 1 = items sold by public auction; Lot 2 = waste
 * disposed of. A closed list (not free text) so every submission is
 * consistently classified from the moment an RM picks a Lot in the form.
 *
 * Lot 2's categories (a-e) are transcribed directly from the letter. Lot 1
 * was a single descriptive line - "Equipment, motor vehicles, assets,
 * stores, scrap, tires, etc." - broken out here into individual pickable
 * categories in the same spirit as Lot 2's list; flag to the boss if this
 * split doesn't match what he had in mind.
 */
class WasteCategories
{
    public const LOT_SALE = 1;

    public const LOT_DISPOSAL = 2;

    private const LOTS = [
        self::LOT_SALE => [
            'label' => 'Lot 1 - Sale by Public Auction',
            'short_label' => 'Lot 1 (Sale)',
            'categories' => [
                'equipment' => ['label' => 'Equipment', 'unit' => 'kg'],
                'motor_vehicles' => ['label' => 'Motor Vehicles', 'unit' => 'kg'],
                'assets' => ['label' => 'Assets', 'unit' => 'kg'],
                'stores' => ['label' => 'Stores', 'unit' => 'kg'],
                'scrap' => ['label' => 'Scrap', 'unit' => 'kg'],
                'tires' => ['label' => 'Tires', 'unit' => 'kg'],
                'other_sale' => ['label' => 'Other', 'unit' => 'kg'],
            ],
        ],
        self::LOT_DISPOSAL => [
            'label' => 'Lot 2 - Waste Disposal Management',
            'short_label' => 'Lot 2 (Disposal)',
            'categories' => [
                'medical_industrial' => ['label' => 'Medical & Industrial Waste', 'unit' => 'kg'],
                'liquid' => ['label' => 'Liquid Waste', 'unit' => 'ltr'],
                'ewaste' => ['label' => 'E-Waste', 'unit' => 'kg'],
                'solid' => ['label' => 'Solid Waste', 'unit' => 'ton'],
                'other_disposal' => ['label' => 'Any Other Waste', 'unit' => 'kg'],
            ],
        ],
    ];

    /**
     * @return array<int, array{label: string, short_label: string, categories: array}>
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

    /**
     * @return array<string, array{label: string, unit: string}>
     */
    public static function categoriesFor(?int $lot): array
    {
        return self::LOTS[$lot]['categories'] ?? [];
    }

    public static function categoryLabel(?int $lot, ?string $category): ?string
    {
        return self::LOTS[$lot]['categories'][$category]['label'] ?? null;
    }

    public static function unitFor(?int $lot, ?string $category): ?string
    {
        return self::LOTS[$lot]['categories'][$category]['unit'] ?? null;
    }

    public static function isValidCategory(?int $lot, ?string $category): bool
    {
        return $category !== null && array_key_exists($category, self::categoriesFor($lot));
    }
}
