<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Idempotent - safe to re-run. Only ever creates the bootstrap admin
     * account if it doesn't already exist; never touches RM accounts
     * created afterward through the admin panel.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@amac-circular.local'],
            [
                'name' => 'AMAC Admin',
                'password' => env('SEED_ADMIN_PASSWORD', 'ChangeMe!2026'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ]
        );

        $this->call(GovernmentHierarchySeeder::class);
    }
}
