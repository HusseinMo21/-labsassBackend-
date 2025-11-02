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
            line-height: 1.5;
            font-size: 11px;
            position: relative;
            color: #2c3e50;
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
            top: 100px;
            left: 35px;
            right: 35px;
            bottom: 120px;
            background: transparent;
            padding: 15px;
            box-sizing: border-box;
        }
        
        /* Header */
        .receipt-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1e3a8a;
        }
        
        .receipt-title {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        
        /* Patient Info - Compact Grid */
        .patient-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
            font-size: 10px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
        }
        
        .info-label {
            font-weight: 600;
            color: #1e3a8a;
            min-width: 75px;
            margin-right: 5px;
        }
        
        .info-value {
            color: #2c3e50;
            flex: 1;
        }
        
        .arabic-text {
            direction: rtl;
            text-align: right;
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
        }
        
        /* Section Headers */
        .section-header {
            font-size: 11px;
            font-weight: 700;
            color: #1e3a8a;
            margin: 12px 0 6px 0;
            padding-bottom: 4px;
            border-bottom: 1px solid #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Tests Table - Minimal */
        .tests-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 10px;
        }
        
        .tests-table th {
            background-color: #f8f9fa;
            color: #1e3a8a;
            font-weight: 600;
            padding: 6px 8px;
            text-align: left;
            border-bottom: 2px solid #1e3a8a;
        }
        
        .tests-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .tests-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Financial Summary - Clean */
        .financial-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-bottom: 10px;
            font-size: 10px;
        }
        
        .financial-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }
        
        .financial-label {
            font-weight: 600;
            color: #495057;
        }
        
        .financial-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .total-row {
            border-top: 2px solid #1e3a8a;
            margin-top: 4px;
            padding-top: 6px;
        }
        
        .total-row .financial-label,
        .total-row .financial-value {
            font-size: 11px;
            font-weight: 700;
            color: #1e3a8a;
        }
        
        /* Payment Breakdown */
        .payment-breakdown {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 10px;
        }
        
        .payment-item {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
        }
        
        /* Payment Status Badge */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            background-color: #28a745;
            color: white;
            font-weight: 700;
            font-size: 11px;
            border-radius: 4px;
            text-align: center;
            width: 100%;
            margin-top: 8px;
        }
        
        /* Footer */
        .receipt-footer {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
            font-size: 9px;
            color: #6c757d;
            text-align: center;
        }
        
        .footer-item {
            margin: 3px 0;
        }
        
        /* Arabic Note */
        .arabic-note {
            margin-top: 12px;
            padding: 10px;
            background-color: #fff3cd;
            border-left: 3px solid #ffc107;
            border-radius: 3px;
            font-size: 10px;
            direction: rtl;
            text-align: right;
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
        }
        
        /* Utilities */
        .text-right {
            text-align: right;
        }
        
        .font-bold {
            font-weight: 700;
        }
        
        @media print {
            .main-content {
                position: relative !important;
                z-index: 1 !important;
                height: 100vh !important;
            }
            .content-container {
                position: absolute !important;
                top: 100px !important;
                left: 35px !important;
                right: 35px !important;
                bottom: 120px !important;
                background: transparent !important;
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
            <!-- Header -->
            <div class="receipt-header">
                <div class="receipt-title">{{ isset($isFinalPayment) && $isFinalPayment ? 'FINAL PAYMENT RECEIPT' : 'PAYMENT RECEIPT' }}</div>
            </div>

            <!-- Patient Information -->
            <div class="patient-info-grid">
                <div class="info-item">
                    <span class="info-label">Patient Name:</span>
                    <span class="info-value arabic-text">{{ $receiptData['patient_name'] ?? 'N/A' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Age:</span>
                    <span class="info-value">{{ $receiptData['patient_age'] ?? 'N/A' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone:</span>
                    <span class="info-value">{{ $receiptData['patient_phone'] ?? 'N/A' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date:</span>
                    <span class="info-value">{{ $receiptData['date'] ?? 'N/A' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Receipt #:</span>
                    <span class="info-value">{{ $receiptData['receipt_number'] ?? 'N/A' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Lab #:</span>
                    <span class="info-value">{{ $receiptData['lab_number'] ?? 'N/A' }}</span>
                </div>
            </div>

            <!-- Tests Ordered -->
            <div class="section-header">Tests Ordered ({{ count($receiptData['tests'] ?? []) > 0 ? count($receiptData['tests']) : 1 }}):</div>
            <table class="tests-table">
                <thead>
                    <tr>
                        <th>Test Name</th>
                        <th>Category</th>
                        <th class="text-right">Price</th>
                    </tr>
                </thead>
                <tbody>
                    @if(!empty($receiptData['tests']))
                        @foreach($receiptData['tests'] as $test)
                            <tr>
                                <td>{{ $test['name'] ?? 'N/A' }}</td>
                                <td>{{ $test['category'] ?? 'N/A' }}</td>
                                <td class="text-right">EGP {{ number_format($test['price'] ?? 0, 2) }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td>frozen</td>
                            <td>Sample Type</td>
                            <td class="text-right">EGP {{ number_format($receiptData['total_amount'] ?? 0, 2) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <!-- Financial Summary -->
            <div class="section-header">Financial Summary:</div>
            <div class="financial-grid">
                <div class="financial-row">
                    <span class="financial-label">Total Amount:</span>
                    <span class="financial-value">EGP {{ number_format($receiptData['total_amount'] ?? 0, 2) }}</span>
                </div>
                <div class="financial-row">
                    <span class="financial-label">Discount:</span>
                    <span class="financial-value">EGP {{ number_format($receiptData['discount_amount'] ?? 0, 2) }}</span>
                </div>
                <div class="financial-row total-row">
                    <span class="financial-label">Final Amount:</span>
                    <span class="financial-value">EGP {{ number_format($receiptData['final_amount'] ?? 0, 2) }}</span>
                </div>
                <div class="financial-row total-row">
                    <span class="financial-label">Total Paid:</span>
                    <span class="financial-value">EGP {{ number_format($receiptData['upfront_payment'] ?? 0, 2) }}</span>
                </div>
                <div class="financial-row">
                    <span class="financial-label">Remaining Balance:</span>
                    <span class="financial-value">EGP {{ number_format($receiptData['remaining_balance'] ?? 0, 2) }}</span>
                </div>
                <div class="financial-row">
                    <span class="financial-label">Payment Method:</span>
                    <span class="financial-value">{{ $receiptData['payment_method'] ?? 'N/A' }}</span>
                </div>
            </div>

            <!-- Payment Breakdown -->
            @if (!empty($receiptData['payment_breakdown']))
            <div class="section-header">Payment Breakdown:</div>
            <div class="payment-breakdown">
                @if (isset($receiptData['payment_breakdown']['cash']) && $receiptData['payment_breakdown']['cash'] > 0)
                    <div class="payment-item">
                        <span>Paid Cash:</span>
                        <span class="font-bold">EGP {{ number_format($receiptData['payment_breakdown']['cash'], 2) }}</span>
                    </div>
                @endif
                @if (isset($receiptData['payment_breakdown']['card']) && $receiptData['payment_breakdown']['card'] > 0)
                    @php
                        $cardMethod = $receiptData['payment_breakdown']['card_method'] ?? ($receiptData['payment_method'] ?? 'Card');
                    @endphp
                    <div class="payment-item">
                        <span>Paid with {{ $cardMethod }}:</span>
                        <span class="font-bold">EGP {{ number_format($receiptData['payment_breakdown']['card'], 2) }}</span>
                    </div>
                @endif
                @foreach($receiptData['payment_breakdown'] as $method => $amount)
                    @if($method !== 'cash' && $method !== 'card' && $method !== 'card_method' && is_numeric($amount) && $amount > 0)
                        <div class="payment-item">
                            <span>Paid with {{ ucfirst($method) }}:</span>
                            <span class="font-bold">EGP {{ number_format($amount, 2) }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
            @endif

            <!-- Payment Status -->
            <div class="section-header">Payment Status:</div>
            <div class="status-badge">{{ strtoupper($receiptData['billing_status'] ?? 'UNPAID') }}</div>

            <!-- Patient Credentials (only for final payment) -->
            @if(isset($isFinalPayment) && $isFinalPayment && isset($receiptData['patient_credentials']) && $receiptData['patient_credentials'])
                <div class="section-header">Patient Portal Access:</div>
                <div class="payment-breakdown">
                    <div class="payment-item">
                        <span>Username:</span>
                        <span class="font-bold">{{ $receiptData['patient_credentials']['username'] ?? 'N/A' }}</span>
                    </div>
                    <div class="payment-item">
                        <span>Password:</span>
                        <span class="font-bold">{{ $receiptData['patient_credentials']['password'] ?? 'N/A' }}</span>
                    </div>
                </div>
            @endif

            <!-- Footer -->
            <div class="receipt-footer">
                <div class="footer-item">Printed by: {{ $receiptData['printed_by'] ?? 'System' }}</div>
                <div class="footer-item">Pathology Lab System</div>
                <div class="footer-item">Printed at: {{ $receiptData['printed_at'] ?? now()->format('Y-m-d H:i:s') }}</div>
                <div class="footer-item">Visit ID: {{ $receiptData['visit_id'] ?? 'N/A' }}</div>
            </div>

            <!-- Arabic Note -->
            <div class="arabic-note">
                يتم الاحتفاظ بالبلوكات الشمعية لمدة ثلاث سنوات ولطلبها يتم التبليغ عنها مسبقا ميعاد استلام النتيجة ووقت التسليم
            </div>
        </div>
    </div>
</body>
</html>
