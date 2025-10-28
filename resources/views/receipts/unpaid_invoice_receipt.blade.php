<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - {{ $receiptData['receipt_number'] ?? 'N/A' }}</title>
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
            color: #1e3a8a;
            text-shadow: 2px 2px 4px rgba(255, 255, 255, 0.9);
            letter-spacing: 2px;
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.1), rgba(30, 58, 138, 0.2));
            border: 3px solid #1e3a8a;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Patient Information Table */
        .patient-info {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
            background-color: rgba(255, 255, 255, 0.95);
            border: 2px solid #1e3a8a;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        .patient-info td {
            padding: 6px 10px;
            border: 1px solid #1e3a8a;
            font-size: 11px;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        .patient-info .label {
            font-weight: bold;
            width: 25%;
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.1), rgba(30, 58, 138, 0.2));
            color: #1e3a8a;
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
            color: #1e3a8a;
            border-left: 4px solid #1e3a8a;
            padding: 8px 12px;
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.1), rgba(30, 58, 138, 0.2));
            border: 2px solid #1e3a8a;
            border-radius: 4px;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .section-content {
            margin-bottom: 8px;
            padding: 10px 12px;
            border: 2px solid #1e3a8a;
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
            border: 2px solid #1e3a8a;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        .tests-table th, .tests-table td {
            padding: 8px 12px;
            border: 1px solid #1e3a8a;
            font-size: 11px;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        .tests-table th {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.2), rgba(30, 58, 138, 0.3));
            font-weight: bold;
            color: #1e3a8a;
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
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.1), rgba(30, 58, 138, 0.2));
            border: 2px solid #1e3a8a;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        .signature-name {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 3px;
            color: #1e3a8a;
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
                   <div class="report-title">{{ isset($isFinalPayment) && $isFinalPayment ? 'FINAL PAYMENT RECEIPT' : 'PAYMENT RECEIPT' }}</div>

            <!-- Patient Information -->
            <table class="patient-info">
                <tr>
                    <td class="label">Patient Name:</td>
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
                    <td class="label">Receipt #:</td>
                    <td class="value">{{ $receiptData['receipt_number'] ?? 'N/A' }}</td>
                    <td class="label">Lab #:</td>
                    <td class="value">{{ $receiptData['lab_number'] ?? 'N/A' }}</td>
                </tr>
            </table>

            <!-- Tests Ordered -->
            <div class="section-title">Tests Ordered ({{ count($receiptData['tests'] ?? []) > 0 ? count($receiptData['tests']) : 1 }}):</div>
            <table class="tests-table">
                <thead>
                    <tr>
                        <th>Test Name</th>
                        <th>Category</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    @if(!empty($receiptData['tests']))
                        @foreach($receiptData['tests'] as $test)
                            <tr>
                                <td>{{ $test['name'] ?? 'N/A' }}</td>
                                <td>{{ $test['category'] ?? 'N/A' }}</td>
                                <td>EGP {{ number_format($test['price'] ?? 0, 2) }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td>Pathology</td>
                            <td>Sample Type</td>
                            <td>EGP {{ number_format($receiptData['total_amount'] ?? 0, 2) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <!-- Financial Summary -->
            <div class="section-title">Financial Summary:</div>
            <div class="section-content">
                <table class="patient-info">
                    <tr>
                        <td class="label">Total Amount:</td>
                        <td class="value">EGP {{ number_format($receiptData['total_amount'] ?? 0, 2) }}</td>
                        <td class="label">Discount:</td>
                        <td class="value">EGP {{ number_format($receiptData['discount_amount'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Final Amount:</td>
                        <td class="value">EGP {{ number_format($receiptData['final_amount'] ?? 0, 2) }}</td>
                        <td class="label">Total Paid:</td>
                        <td class="value">EGP {{ number_format($receiptData['upfront_payment'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Remaining Balance:</td>
                        <td class="value">EGP {{ number_format($receiptData['remaining_balance'] ?? 0, 2) }}</td>
                        <td class="label">Payment Method:</td>
                        <td class="value">{{ $receiptData['payment_method'] ?? 'N/A' }}</td>
                    </tr>
                </table>
            </div>

            <!-- Payment Breakdown -->
            @if (!empty($receiptData['payment_breakdown']))
            <div class="section-title">Payment Breakdown:</div>
            <div class="section-content">
                @if (isset($receiptData['payment_breakdown']['cash']) && $receiptData['payment_breakdown']['cash'] > 0)
                    <div>Paid Cash: EGP {{ number_format($receiptData['payment_breakdown']['cash'], 2) }}</div>
                @endif
                @if (isset($receiptData['payment_breakdown']['card']) && $receiptData['payment_breakdown']['card'] > 0)
                    <div>Paid with {{ $receiptData['payment_breakdown']['card_method'] ?? 'Card' }}: EGP {{ number_format($receiptData['payment_breakdown']['card'], 2) }}</div>
                @endif
            </div>
            @endif

            <!-- Payment Status -->
            <div class="section-title">Payment Status:</div>
            <div class="section-content financial-section">
                <strong>{{ strtoupper($receiptData['billing_status'] ?? 'UNPAID') }}</strong>
            </div>

            <!-- Patient Credentials (only for final payment) -->
            @if(isset($isFinalPayment) && $isFinalPayment && isset($receiptData['patient_credentials']) && $receiptData['patient_credentials'])
                <div class="section-title">Patient Portal Access:</div>
                <div class="section-content">
                    <div class="info-row">
                        <span class="label">Username:</span>
                        <span class="value" style="font-family: monospace; background: #f9f9f9; padding: 2px 6px; border: 1px solid #ddd; border-radius: 3px;">{{ $receiptData['patient_credentials']['username'] ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Password:</span>
                        <span class="value" style="font-family: monospace; background: #f9f9f9; padding: 2px 6px; border: 1px solid #ddd; border-radius: 3px;">{{ $receiptData['patient_credentials']['password'] ?? 'N/A' }}</span>
                    </div>
                </div>
            @endif

            <!-- Signature Section (only for normal receipts, not final payment) -->
            @if(!isset($isFinalPayment) || !$isFinalPayment)
            <div class="signature-section">
                <div class="signature-name">Printed by: {{ $receiptData['printed_by'] ?? 'System' }}</div>
                <div class="signature-title">Pathology Lab System</div>
                <div class="signature-date">Printed at: {{ $receiptData['printed_at'] ?? now()->format('Y-m-d H:i:s') }}</div>
                <div class="signature-date">Visit ID: {{ $receiptData['visit_id'] ?? 'N/A' }}</div>
            </div>
            @endif

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