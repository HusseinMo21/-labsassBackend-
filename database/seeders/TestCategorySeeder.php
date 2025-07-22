<?php

namespace Database\Seeders;

use App\Models\TestCategory;
use Illuminate\Database\Seeder;

class TestCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Blood Tests',
                'description' => 'Complete blood count and blood chemistry tests',
                'is_active' => true,
            ],
            [
                'name' => 'Urine Tests',
                'description' => 'Urinalysis and urine culture tests',
                'is_active' => true,
            ],
            [
                'name' => 'Biochemistry',
                'description' => 'Blood chemistry and metabolic tests',
                'is_active' => true,
            ],
            [
                'name' => 'Microbiology',
                'description' => 'Bacterial culture and sensitivity tests',
                'is_active' => true,
            ],
            [
                'name' => 'Immunology',
                'description' => 'Immune system and antibody tests',
                'is_active' => true,
            ],
            [
                'name' => 'Hormone Tests',
                'description' => 'Endocrine and hormone level tests',
                'is_active' => true,
            ],
            [
                'name' => 'Cardiac Markers',
                'description' => 'Heart function and cardiac enzyme tests',
                'is_active' => true,
            ],
            [
                'name' => 'Tumor Markers',
                'description' => 'Cancer screening and monitoring tests',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $categoryData) {
            TestCategory::updateOrCreate(
                ['name' => $categoryData['name']],
                $categoryData
            );
        }
    }
} 