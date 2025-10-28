<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Payment Receipt - {{ $receiptData['receipt_number'] ?? 'N/A' }}</title>
    <style>
        @page {
            margin: 0;
        }
        
        body {
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
            padding: 0;
            margin: 0;
            line-height: 1.3;
            font-size: 12px;
            position: relative;
        }
        
        .main-content {
            position: relative;
            z-index: 1;
            margin: 0;
            padding: 0;
            height: 100vh;
        }
        
        .content-container {
            position: absolute;
            top: 120px;
            left: 40px;
            right: 40px;
            bottom: 150px;
            background: transparent;
            border: none;
            border-radius: 5px;
            padding: 20px;
            padding-top: 180px;
            overflow: hidden;
            box-sizing: border-box;
        }
        
        /* Report Title */
        .report-title {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            margin: 5px 0 15px 0;
            color: #dc2626;
            text-shadow: 2px 2px 4px rgba(255, 255, 255, 0.9);
            letter-spacing: 1px;
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(220, 38, 38, 0.2));
            border: 2px solid #dc2626;
            border-radius: 6px;
            padding: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Patient Information Table */
        .patient-info {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
            background-color: rgba(255, 255, 255, 0.95);
            border: 2px solid #dc2626;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        .patient-info td {
            padding: 6px 10px;
            border: 1px solid #dc2626;
            font-size: 11px;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        .patient-info .label {
            font-weight: bold;
            width: 25%;
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(220, 38, 38, 0.2));
            color: #dc2626;
            text-align: center;
        }
        
        .patient-info .value {
            background-color: rgba(255, 255, 255, 0.8);
            color: #333;
        }
        
        /* Section Styles */
        .section-title {
            font-weight: bold;
            font-size: 13px;
            margin: 8px 0 5px 0;
            color: #dc2626;
            border-left: 4px solid #dc2626;
            padding: 8px 12px;
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(220, 38, 38, 0.2));
            border: 2px solid #dc2626;
            border-radius: 4px;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .section-content {
            margin-bottom: 8px;
            padding: 10px 12px;
            border: 2px solid #dc2626;
            background-color: rgba(255, 255, 255, 0.95);
            min-height: 25px;
            text-align: left;
            line-height: 1.4;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
            font-size: 11px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .arabic-text {
            direction: rtl;
            text-align: right;
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
            unicode-bidi: bidi-override;
        }
        
        .financial-section {
            border: 3px solid #dc2626;
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(220, 38, 38, 0.2));
            font-weight: bold;
            font-size: 14px;
            color: #dc2626;
            border-radius: 6px;
            box-shadow: 0 3px 6px rgba(220, 38, 38, 0.3);
            text-align: center;
            padding: 8px;
        }
        
        /* Tests Table */
        .tests-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            background-color: rgba(255, 255, 255, 0.95);
            border: 2px solid #dc2626;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        .tests-table th, .tests-table td {
            padding: 8px 12px;
            border: 1px solid #dc2626;
            font-size: 11px;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        .tests-table th {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.2), rgba(220, 38, 38, 0.3));
            font-weight: bold;
            color: #dc2626;
            text-align: center;
        }
        
        .tests-table td {
            background-color: rgba(255, 255, 255, 0.8);
        }
        
        /* Signature Section */
        .signature-section {
            margin-top: 12px;
            text-align: right;
            padding: 12px;
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(220, 38, 38, 0.2));
            border: 2px solid #dc2626;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        .signature-name {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 3px;
            color: #dc2626;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        .signature-title {
            font-size: 10px;
            margin-bottom: 3px;
            color: #666;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        .signature-date {
            font-size: 9px;
            color: #888;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        /* Arabic Note Section */
        .arabic-note {
            margin-top: 15px;
            padding: 15px;
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(220, 38, 38, 0.2));
            border: 2px solid #dc2626;
            border-radius: 6px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
        }
        
        .arabic-note-text {
            font-size: 12px;
            font-weight: bold;
            color: #dc2626;
            direction: rtl;
            text-align: center;
            line-height: 1.5;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
        }

        /* Info Row Styling */
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 3px 0;
        }

        .info-row .label {
            font-weight: bold;
            color: #dc2626;
            min-width: 120px;
        }

        .info-row .value {
            color: #333;
            text-align: right;
        }
        
        /* Print Specific Styles */
        @media print {
            .main-content {
                position: relative !important;
                z-index: 1 !important;
                height: 100vh !important;
            }
            .content-container {
                position: absolute !important;
                top: 120px !important;
                left: 40px !important;
                right: 40px !important;
                bottom: 150px !important;
                padding-top: 180px !important;
                background: transparent !important;
                border: none !important;
            }
        }
    </style>
</head>
<body>
    @if(!empty($backgroundImage))
    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; background-image: url('data:image/jpeg;base64,{{ $backgroundImage }}'); background-size: cover; background-position: center; background-repeat: no-repeat; pointer-events: none;"></div>
    @endif
    <div class="main-content">
        <div class="content-container">
            <div class="report-title">FINAL PAYMENT RECEIPT</div>

            <!-- Patient Information -->
            <table class="patient-info">
                <tr>
                    <td class="label">Patient's Name:</td>
                    <td class="value arabic-text">{{ $receiptData['patient_name'] ?? 'N/A' }}</td>
                    <td class="label">Age:</td>
                    <td class="value">{{ $receiptData['patient_age'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Phone:</td>
                    <td class="value">{{ $receiptData['patient_phone'] ?? 'N/A' }}</td>
                    <td class="label">Date:</td>
                    <td class="value">{{ $receiptData['date'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Receipt No:</td>
                    <td class="value">{{ $receiptData['receipt_number'] ?? 'N/A' }}</td>
                    <td class="label">Lab No:</td>
                    <td class="value">{{ $receiptData['lab_number'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Visit ID:</td>
                    <td class="value">{{ $receiptData['visit_id'] ?? 'N/A' }}</td>
                    <td class="label">Status:</td>
                    <td class="value">PAYMENT COMPLETED</td>
                </tr>
            </table>

            <!-- Tests Ordered -->
            <div class="section-title">Tests Ordered ({{ count($receiptData['tests'] ?? []) }}):</div>
            <table class="tests-table">
                <thead>
                    <tr>
                        <th>Test Name</th>
                        <th>Category</th>
                        <th style="text-align: right;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (($receiptData['tests'] ?? []) as $test)
                        <tr>
                            <td>{{ $test['name'] ?? 'N/A' }}</td>
                            <td>{{ $test['category'] ?? 'N/A' }}</td>
                            <td style="text-align: right;">EGP {{ number_format($test['price'] ?? 0, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Financial Summary -->
            <div class="section-title">Financial Summary:</div>
            <div class="section-content">
                <table class="financial-summary-table">
                    <tr>
                        <td class="label">Total Amount:</td>
                        <td class="value">EGP {{ number_format($receiptData['total_amount'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Discount:</td>
                        <td class="value">EGP {{ number_format($receiptData['discount_amount'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Final Amount:</td>
                        <td class="value">EGP {{ number_format($receiptData['final_amount'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Amount Paid Before:</td>
                        <td class="value">EGP {{ number_format($receiptData['paid_before'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Final Payment:</td>
                        <td class="value">EGP {{ number_format($receiptData['paid_now'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Total Paid:</td>
                        <td class="value">EGP {{ number_format(($receiptData['paid_before'] ?? 0) + ($receiptData['paid_now'] ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Remaining Balance:</td>
                        <td class="value">EGP {{ number_format($receiptData['remaining_balance'] ?? 0, 2) }}</td>
                    </tr>
                </table>
            </div>

            <!-- Payment Method -->
            <div class="section-title">Payment Method:</div>
            <div class="section-content">
                <strong>{{ strtoupper($receiptData['payment_method'] ?? 'CASH') }}</strong>
            </div>

            <!-- Payment Status -->
            <div class="section-title">Payment Status:</div>
            <div class="section-content financial-section">
                <strong>PAYMENT COMPLETED</strong>
            </div>

            <!-- Patient Credentials -->
            @if(isset($receiptData['patient_credentials']) && $receiptData['patient_credentials'])
                <div class="section-title">Patient Portal Access:</div>
                <div class="section-content">
                    <div class="info-row">
                        <span class="label">Username:</span>
                        <span class="value">{{ $receiptData['patient_credentials']['username'] ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Password:</span>
                        <span class="value">{{ $receiptData['patient_credentials']['password'] ?? 'N/A' }}</span>
                    </div>
                </div>
            @endif

            <!-- Expected Delivery -->
            @if(isset($receiptData['expected_delivery_date']) && $receiptData['expected_delivery_date'] !== 'N/A')
                <div class="section-title">Expected Delivery:</div>
                <div class="section-content">
                    <strong>{{ $receiptData['expected_delivery_date'] }}</strong>
                </div>
            @endif

            <!-- Barcode -->
            @if(isset($receiptData['barcode']) && $receiptData['barcode'] !== 'N/A')
                <div class="section-title">Barcode:</div>
                <div class="section-content" style="text-align: center;">
                    <strong>{{ $receiptData['barcode'] }}</strong>
                </div>
            @endif

            <!-- Signature Section -->
            <div class="signature-section">
                <div class="signature-name">Printed by: {{ $receiptData['check_in_by'] ?? 'System' }}</div>
                <div class="signature-title">Pathology Lab System</div>
                <div class="signature-date">Printed at: {{ $receiptData['check_in_at'] ?? now()->format('Y-m-d H:i:s') }}</div>
                <div class="signature-date">Visit ID: {{ $receiptData['visit_id'] ?? 'N/A' }}</div>
            </div>

            <!-- Arabic Note -->
            <div class="arabic-note">
                <div class="arabic-note-text">
                    يتم الاحتفاظ بالبلوكات الشمعية لمدة ثلاث سنوات ولطلبها يتم التبليغ عنها مسبقا  ميعاد استلام النتيجة ووقت التسليم
                </div>
            </div>
        </div>
    </div>
</body>
</html>
