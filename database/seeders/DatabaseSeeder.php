<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the application's database seeders.
     */
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            SuperAdminSeeder::class,
            DevelopmentCompanySeeder::class,
            DevelopmentRbacUsersSeeder::class,
        ]);
    }
}
