<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\User;
use App\Support\WasteCategories;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Opt-in test data for demoing/testing the RM + Lot/Category flow on a real
 * environment - deliberately NOT part of DatabaseSeeder, which only ever
 * bootstraps the real admin account. Run explicitly:
 *
 *   php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder --force
 *
 * Every entity name is prefixed "[DEMO]" so it's obvious in the UI and easy
 * to find/delete later. Idempotent for the RM accounts (firstOrCreate); safe
 * to re-run, though re-running adds another batch of demo collections each
 * time - delete via `Collection::where('entity_name', 'like', '[DEMO]%')->delete()`
 * and `User::where('email', 'like', '%@demo.amac-circular.local')->delete()`
 * when you're done testing.
 */
class DemoDataSeeder extends Seeder
{
    private const RMS = [
        ['name' => 'Alice Wanjiru', 'email' => 'alice@demo.amac-circular.local'],
        ['name' => 'Brian Otieno', 'email' => 'brian@demo.amac-circular.local'],
        ['name' => 'Grace Mutua', 'email' => 'grace@demo.amac-circular.local'],
    ];

    private const ENTITIES = [
        '[DEMO] Ministry of Health',
        '[DEMO] Ministry of Education',
        '[DEMO] Nairobi County Government',
        '[DEMO] Kenya Revenue Authority',
        '[DEMO] Ministry of Interior',
        '[DEMO] Mombasa County Government',
    ];

    public function run(): void
    {
        $password = env('SEED_DEMO_PASSWORD', 'DemoRm#2026');

        $users = collect(self::RMS)->map(fn (array $rm) => User::firstOrCreate(
            ['email' => $rm['email']],
            [
                'name' => $rm['name'],
                'password' => $password,
                'role' => User::ROLE_RM,
                'is_active' => true,
            ]
        ));

        $lots = WasteCategories::lots();
        $created = 0;

        foreach (self::ENTITIES as $entity) {
            // 2-4 submissions per entity, spread across the last 90 days.
            $count = random_int(2, 4);

            for ($i = 0; $i < $count; $i++) {
                $lotKey = array_rand($lots);
                $lot = $lots[$lotKey];
                $categories = $lot['categories'];
                $categoryKey = array_rand($categories);
                $category = $categories[$categoryKey];

                if ($lot['has_subcategories']) {
                    $subKey = array_rand($category['subcategories']);
                    $units = $category['subcategories'][$subKey]['units'];
                } else {
                    $subKey = null;
                    $units = $category['units'];
                }

                $unit = $units[array_rand($units)];

                $quantity = match ($unit) {
                    'litres', 'm3' => random_int(20, 500),
                    'tonnes' => random_int(1, 20),
                    'units' => random_int(1, 12),
                    default => random_int(1, 300),
                };

                Collection::create([
                    'entity_name' => $entity,
                    'lot' => $lotKey,
                    'category' => $categoryKey,
                    'subcategory' => $subKey,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'user_id' => $users->random()->id,
                    'relationship_manager' => $users->random()->name,
                    'contact_person_name' => 'Demo Contact',
                    'contact_person_number' => '0700000000',
                    'collection_date' => Carbon::now()->subDays(random_int(0, 90)),
                    'collected_by' => $users->random()->name,
                ]);

                $created++;
            }
        }

        $this->command?->info("Demo seeding done: {$users->count()} RM account(s), {$created} collection(s). RM password: {$password}");
    }
}
