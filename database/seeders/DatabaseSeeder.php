<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DelayReasonSeeder::class,
            DemoAgencySeeder::class,
            HrDemoSeeder::class,
            DemoShowcaseSeeder::class,
        ]);
    }
}
