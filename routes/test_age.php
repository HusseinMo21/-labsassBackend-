<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Models\Patient;

Route::get('/test-age-column', function() {
    // Check column type
    $columnInfo = DB::select("SHOW COLUMNS FROM `patient` WHERE Field = 'age'");
    
    // Get latest patient
    $latestPatient = Patient::latest()->first();
    
    return response()->json([
        'column_info' => $columnInfo,
        'latest_patient' => [
            'id' => $latestPatient?->id,
            'name' => $latestPatient?->name,
            'age_from_model' => $latestPatient?->age,
            'age_from_attributes' => $latestPatient?->getAttributes()['age'] ?? null,
            'age_from_db_raw' => $latestPatient ? DB::table('patient')->where('id', $latestPatient->id)->value('age') : null,
        ],
        'test_insert' => [
            'attempting_to_insert' => '25M,5D',
            'column_type' => $columnInfo[0]->Type ?? 'unknown',
        ]
    ]);
});

