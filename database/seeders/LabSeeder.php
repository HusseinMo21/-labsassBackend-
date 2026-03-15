<?php

namespace Database\Seeders;

use App\Models\Lab;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LabSeeder extends Seeder
{
    public function run(): void
    {
        $labs = [
            [
                'name' => 'Dr. Yasser Lab',
                'slug' => 'dryasserlab',
                'subdomain' => 'dryasserlab',
                'settings' => ['primary_color' => '#2A7AE4', 'report_header' => 'معمل د. ياسر للتحاليل'],
                'is_active' => true,
            ],
            [
                'name' => 'Cairo Central Lab',
                'slug' => 'cairo-lab',
                'subdomain' => 'cairo-lab',
                'settings' => ['primary_color' => '#1a73e8', 'report_header' => 'معمل القاهرة المركزي'],
                'is_active' => true,
            ],
            [
                'name' => 'Alexandria Medical Lab',
                'slug' => 'alex-lab',
                'subdomain' => 'alex-lab',
                'settings' => ['primary_color' => '#0d47a1', 'report_header' => 'معمل الإسكندرية الطبي'],
                'is_active' => true,
            ],
            [
                'name' => 'Giza Pathology Lab',
                'slug' => 'giza-lab',
                'subdomain' => 'giza-lab',
                'settings' => ['primary_color' => '#1565c0', 'report_header' => 'معمل الجيزة للباثولوجي'],
                'is_active' => true,
            ],
        ];

        foreach ($labs as $labData) {
            Lab::firstOrCreate(
                ['slug' => $labData['slug']],
                $labData
            );
        }

        // Platform admin (lab_id = null) - can manage labs via /api/labs
        User::firstOrCreate(
            ['email' => 'platform@saaslab.com', 'lab_id' => null],
            [
                'name' => 'Platform Admin',
                'role' => 'admin',
                'password' => Hash::make('password'),
                'is_active' => true,
                'lab_id' => null,
            ]
        );
    }
}
