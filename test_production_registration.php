<?php

// Test patient registration on production server
$productionUrl = 'https://phplaravel-1526034-5875973.cloudwaysapps.com/api/patient-registration';

// Sample patient data
$patientData = [
    'name' => 'Test Patient',
    'phone' => '0123456789',
    'gender' => 'male',
    'age' => 30,
    'organization' => 'Test Hospital',
    'doctor' => 'Test Doctor',
    'sample_type' => 'Pathology',
    'sample_size' => 'صغيرة جدا',
    'number_of_samples' => 1,
    'medical_history' => 'لا',
    'previous_tests' => 'لا',
    'total_amount' => 1000,
    'amount_paid_cash' => 500,
    'amount_paid_card' => 500,
    'additional_payment_method' => 'InstaPay',
    'attendance_date' => date('Y-m-d'),
    'delivery_date' => date('Y-m-d', strtotime('+2 days')),
];

echo "=== Testing Patient Registration on Production ===\n";
echo "URL: " . $productionUrl . "\n";
echo "Data: " . json_encode($patientData, JSON_PRETTY_PRINT) . "\n";

// Make the request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $productionUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($patientData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer 10|osAzG6MwYSYD7yyROoFJX6n3J0Kl94UUZoA99uSta1b35375'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "\n=== Response ===\n";
echo "HTTP Code: " . $httpCode . "\n";

if ($error) {
    echo "cURL Error: " . $error . "\n";
} else {
    echo "Response Body: " . $response . "\n";
    
    // Try to decode JSON response
    $responseData = json_decode($response, true);
    if ($responseData) {
        echo "\n=== Parsed Response ===\n";
        echo json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
        
        if (isset($responseData['lab_number'])) {
            echo "\n✅ Registration successful! Lab Number: " . $responseData['lab_number'] . "\n";
        } else {
            echo "\n❌ Registration failed or incomplete\n";
        }
    }
}
