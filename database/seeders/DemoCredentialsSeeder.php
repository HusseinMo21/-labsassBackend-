<?php

namespace Database\Seeders;

use App\Models\Lab;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoCredentialsSeeder extends Seeder
{
    public function run(): void
    {
        $labs = Lab::orderBy('id')->get();
        if ($labs->isEmpty()) {
            $this->command->warn('No labs found. Run LabSeeder first.');
            return;
        }

        $credentials = [];
        $password = 'Password123!';

        foreach ($labs as $lab) {
            $domain = str_replace('-', '', $lab->slug);
            $baseEmail = "@{$domain}.com";

            // Admin (User model has 'password' => 'hashed' cast - pass plain password)
            User::updateOrCreate(
                ['lab_id' => $lab->id, 'email' => "admin{$baseEmail}"],
                [
                    'name' => "Admin - {$lab->name}",
                    'email' => "admin{$baseEmail}",
                    'password' => $password,
                    'role' => 'admin',
                    'is_active' => true,
                    'lab_id' => $lab->id,
                ]
            );
            $credentials[] = "Admin ({$lab->name}): admin{$baseEmail} / {$password}";

            // Staff
            User::updateOrCreate(
                ['lab_id' => $lab->id, 'email' => "staff{$baseEmail}"],
                [
                    'name' => "Staff - {$lab->name}",
                    'email' => "staff{$baseEmail}",
                    'password' => $password,
                    'role' => 'staff',
                    'is_active' => true,
                    'lab_id' => $lab->id,
                ]
            );
            $credentials[] = "Staff ({$lab->name}): staff{$baseEmail} / {$password}";

            // Doctor
            User::updateOrCreate(
                ['lab_id' => $lab->id, 'email' => "doctor{$baseEmail}"],
                [
                    'name' => "Doctor - {$lab->name}",
                    'email' => "doctor{$baseEmail}",
                    'password' => $password,
                    'role' => 'doctor',
                    'is_active' => true,
                    'lab_id' => $lab->id,
                ]
            );
            $credentials[] = "Doctor ({$lab->name}): doctor{$baseEmail} / {$password}";
        }

        // Default lab credentials (admin@default.com etc.) - for localhost / first lab
        $lab1 = $labs->first();
        if ($lab1) {
            foreach (['admin', 'staff', 'doctor'] as $role) {
                User::updateOrCreate(
                    ['lab_id' => $lab1->id, 'email' => "{$role}@default.com"],
                    [
                        'name' => ucfirst($role) . ' - Default Lab',
                        'email' => "{$role}@default.com",
                        'password' => $password,
                        'role' => $role,
                        'is_active' => true,
                        'lab_id' => $lab1->id,
                    ]
                );
            }
        }

        // Legacy Dr. Yasser Lab credentials (lab 1) - keep for backward compatibility
        if ($lab1 && $lab1->slug === 'dryasserlab') {
            User::updateOrCreate(
                ['lab_id' => $lab1->id, 'email' => 'admin@dryasserlab.com'],
                [
                    'name' => 'Admin User',
                    'email' => 'admin@dryasserlab.com',
                    'password' => Hash::make('DrYasserLab123456790@'),
                    'role' => 'admin',
                    'is_active' => true,
                    'lab_id' => $lab1->id,
                ]
            );
            User::updateOrCreate(
                ['lab_id' => $lab1->id, 'email' => 'zeinab@dryasserlab.com'],
                [
                    'name' => 'Zeinab',
                    'email' => 'zeinab@dryasserlab.com',
                    'password' => Hash::make('Zeinab12345678'),
                    'role' => 'staff',
                    'is_active' => true,
                    'lab_id' => $lab1->id,
                ]
            );
            User::updateOrCreate(
                ['lab_id' => $lab1->id, 'email' => 'menna@dryasserlab.com'],
                [
                    'name' => 'Menna',
                    'email' => 'menna@dryasserlab.com',
                    'password' => Hash::make('Menna12345678'),
                    'role' => 'staff',
                    'is_active' => true,
                    'lab_id' => $lab1->id,
                ]
            );
            User::updateOrCreate(
                ['lab_id' => $lab1->id, 'email' => 'doctor1@dryasserlab.com'],
                [
                    'name' => 'Doctor 1',
                    'email' => 'doctor1@dryasserlab.com',
                    'password' => Hash::make('Doctor123456'),
                    'role' => 'doctor',
                    'is_active' => true,
                    'lab_id' => $lab1->id,
                ]
            );
            User::updateOrCreate(
                ['lab_id' => $lab1->id, 'email' => 'doctor2@dryasserlab.com'],
                [
                    'name' => 'Doctor 2',
                    'email' => 'doctor2@dryasserlab.com',
                    'password' => Hash::make('Doctor123456'),
                    'role' => 'doctor',
                    'is_active' => true,
                    'lab_id' => $lab1->id,
                ]
            );
        }

        $this->command->info('Demo credentials seeded successfully!');
        $this->command->info('Platform Admin: platform@saaslab.com / password');
        $this->command->info('Dr. Yasser Lab: admin@dryasserlab.com / DrYasserLab123456790@');
        foreach ($credentials as $cred) {
            $this->command->info($cred);
        }
    }
}
