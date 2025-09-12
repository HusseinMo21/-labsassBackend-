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
                'name' => 'Staff User',
                'email' => 'staff@lab.com',
                'phone' => '+1234567891',
                'role' => 'staff',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
            [
                'name' => 'Doctor User',
                'email' => 'doctor@lab.com',
                'phone' => '+1234567892',
                'role' => 'doctor',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
            [
                'name' => 'Patient User',
                'email' => 'patient@lab.com',
                'phone' => '+1234567893',
                'role' => 'patient',
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