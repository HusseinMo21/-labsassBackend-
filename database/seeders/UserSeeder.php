<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@lab.com',
                'phone' => '+1234567890',
                'role' => 'admin',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
            [
                'name' => 'Lab Technician',
                'email' => 'tech@lab.com',
                'phone' => '+1234567891',
                'role' => 'lab_tech',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
            [
                'name' => 'Accountant',
                'email' => 'accountant@lab.com',
                'phone' => '+1234567892',
                'role' => 'accountant',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
} 