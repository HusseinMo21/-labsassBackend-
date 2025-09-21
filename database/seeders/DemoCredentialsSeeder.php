<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DemoCredentialsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Demo Admin User
        User::updateOrCreate(
            ['email' => 'admin@dryasserlab.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@dryasserlab.com',
                'password' => Hash::make('DrYasserLab123456790@'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // Demo Staff Users
        User::updateOrCreate(
            ['email' => 'zeinab@dryasserlab.com'],
            [
                'name' => 'Zeinab',
                'email' => 'zeinab@dryasserlab.com',
                'password' => Hash::make('Zeinab12345678'),
                'role' => 'staff',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'menna@dryasserlab.com'],
            [
                'name' => 'Menna',
                'email' => 'menna@dryasserlab.com',
                'password' => Hash::make('Menna12345678'),
                'role' => 'staff',
                'email_verified_at' => now(),
            ]
        );

        // Demo Doctor Users
        User::updateOrCreate(
            ['email' => 'doctor1@dryasserlab.com'],
            [
                'name' => 'Doctor 1',
                'email' => 'doctor1@dryasserlab.com',
                'password' => Hash::make('Doctor123456'),
                'role' => 'doctor',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'doctor2@dryasserlab.com'],
            [
                'name' => 'Doctor 2',
                'email' => 'doctor2@dryasserlab.com',
                'password' => Hash::make('Doctor123456'),
                'role' => 'doctor',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Demo credentials seeded successfully!');
        $this->command->info('Admin: admin@dryasserlab.com / DrYasserLab123456790@');
        $this->command->info('Staff: zeinab@dryasserlab.com / Zeinab12345678');
        $this->command->info('Staff: menna@dryasserlab.com / Menna12345678');
        $this->command->info('Doctor: doctor1@dryasserlab.com / Doctor123456');
        $this->command->info('Doctor: doctor2@dryasserlab.com / Doctor123456');
    }
}