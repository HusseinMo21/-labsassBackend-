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
                'name' => 'PATH',
                'code' => 'path',
                'description' => 'Pathology tests - tissue examination and diagnosis',
                'is_active' => true,
                'report_type' => 'pathology',
            ],
            [
                'name' => 'CYTHO',
                'code' => 'cytho',
                'description' => 'Cytology tests - cell examination and analysis',
                'is_active' => true,
                'report_type' => 'pathology',
            ],
            [
                'name' => 'IHC',
                'code' => 'ihc',
                'description' => 'Immunohistochemistry tests - protein detection in tissues',
                'is_active' => true,
                'report_type' => 'pathology',
            ],
            [
                'name' => 'REV',
                'code' => 'rev',
                'description' => 'Review tests - second opinion and consultation',
                'is_active' => true,
                'report_type' => 'pathology',
            ],
            [
                'name' => 'OTHER',
                'code' => 'other',
                'description' => 'Other specialized tests not covered by main categories',
                'is_active' => true,
                'report_type' => 'pathology',
            ],
            [
                'name' => 'PATH+IHC',
                'code' => 'path_ihc',
                'description' => 'Combined Pathology and Immunohistochemistry tests',
                'is_active' => true,
                'report_type' => 'pathology',
            ],
        ];

        foreach ($categories as $i => $categoryData) {
            TestCategory::updateOrCreate(
                [
                    'code' => $categoryData['code'],
                    'lab_id' => null,
                ],
                array_merge($categoryData, [
                    'lab_id' => null,
                    'sort_order' => 900 + ($i * 10),
                ])
            );
        }
    }
}