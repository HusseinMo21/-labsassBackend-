<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoCredentialsSeeder::class,
            UserSeeder::class,
            TestCategorySeeder::class,
        ]);
    }
} 