<?php

namespace Database\Seeders;

use App\Models\Patient;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // Create 50 sample patients
        for ($i = 0; $i < 50; $i++) {
            Patient::create([
                'name' => $faker->name(),
                'gender' => $faker->randomElement(['male', 'female', 'other']),
                'birth_date' => $faker->dateTimeBetween('-80 years', '-18 years'),
                'phone' => $faker->phoneNumber(),
                'address' => $faker->address(),
                'emergency_contact' => $faker->optional(0.7)->name(),
                'emergency_phone' => $faker->optional(0.7)->phoneNumber(),
                'medical_history' => $faker->optional(0.3)->paragraph(),
                'allergies' => $faker->optional(0.2)->randomElement([
                    'Penicillin',
                    'Sulfa drugs',
                    'Latex',
                    'Peanuts',
                    'Shellfish',
                    'None known'
                ]),
            ]);
        }
    }
} 