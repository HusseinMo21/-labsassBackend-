<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PATHOLOGY LAB RECEIPT - {{ $receiptData['receipt_number'] ?? 'N/A' }}</title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 14px;
            line-height: 1.4;
            color: #000;
            background: white;
        }
        
        .receipt-container {
            width: 100%;
            max-width: 210mm;
            margin: 0 auto;
            background: white;
        }
        
        /* Header Section */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #000;
            padding: 20px 0;
            margin-bottom: 20px;
        }
        
        .lab-name {
            flex: 1;
        }
        
        .lab-name h1 {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #000;
        }
        
        .lab-name .subtitle {
            font-size: 16px;
            color: #666;
        }
        
        .receipt-title {
            text-align: center;
            flex: 2;
        }
        
        .receipt-title h2 {
            font-size: 28px;
            font-weight: bold;
            color: #000;
            margin: 0;
        }
        
        .receipt-info {
            flex: 1;
            text-align: right;
        }
        
        .receipt-info .info-item {
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .receipt-info .label {
            font-weight: bold;
            display: inline-block;
            width: 80px;
        }
        
        /* Main Information Section */
        .main-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .info-section h3 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #000;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .info-row .label {
            font-weight: bold;
            color: #333;
        }
        
        .info-row .value {
            color: #000;
            font-weight: 500;
        }
        
        /* Tests Table */
        .tests-section {
            margin-bottom: 30px;
        }
        
        .tests-section h3 {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #000;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }
        
        .tests-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .tests-table th,
        .tests-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .tests-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 14px;
            color: #000;
        }
        
        .tests-table td {
            font-size: 14px;
        }
        
        .tests-table .test-name {
            font-weight: 500;
        }
        
        .tests-table .test-price {
            text-align: right;
            font-weight: bold;
        }
        
        /* Financial Summary */
        .financial-summary {
            background: #f8f9fa;
            padding: 20px;
            border: 2px solid #000;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .financial-summary h3 {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #000;
            text-align: center;
        }
        
        .financial-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 16px;
            padding: 5px 0;
        }
        
        .financial-row .label {
            font-weight: bold;
            color: #333;
        }
        
        .financial-row .value {
            font-weight: bold;
            color: #000;
        }
        
        .financial-row.total {
            border-top: 2px solid #000;
            margin-top: 10px;
            padding-top: 10px;
            font-size: 18px;
        }
        
        .financial-row.final {
            background: #e9ecef;
            padding: 10px;
            border-radius: 3px;
            font-size: 20px;
        }
        
        .financial-row.remaining {
            color: #dc3545;
            font-size: 18px;
        }
        
        .financial-row.paid {
            color: #28a745;
            font-size: 18px;
        }
        
        /* Payment Breakdown */
        .payment-breakdown {
            background: #f8f9fa;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .payment-breakdown h3 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #000;
            text-align: center;
        }
        
        .payment-breakdown .breakdown-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .payment-breakdown .breakdown-row .label {
            font-weight: bold;
            color: #333;
        }
        
        .payment-breakdown .breakdown-row .value {
            font-weight: bold;
            color: #000;
        }
        
        /* Status Section */
        .status-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-partial {
            background-color: #fff3cd;
            color: #856404;
            border: 2px solid #ffeaa7;
        }
        
        .status-paid {
            background-color: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .status-unpaid {
            background-color: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        /* Barcode Section */
        .barcode-section {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .barcode-section h3 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #000;
        }
        
        .barcode {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            letter-spacing: 2px;
            background: white;
            padding: 10px;
            border: 1px solid #000;
            display: inline-block;
            margin: 10px 0;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #000;
        }
        
        .footer .thank-you {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #000;
        }
        
        .footer .print-info {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .footer .lab-info {
            font-size: 14px;
            color: #333;
            line-height: 1.6;
        }
        
        /* Print Styles */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .receipt-container {
                max-width: none;
                margin: 0;
            }
            
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Header Section -->
        <div class="header">
            <div class="lab-name">
                <h1>PATHOLOGY LAB</h1>
                <div class="subtitle">Specialized Medical Laboratory</div>
            </div>
            <div class="receipt-title">
                <h2>PATHOLOGY LAB RECEIPT</h2>
            </div>
            <div class="receipt-info">
                <div class="info-item">
                    <span class="label">Date:</span>
                    {{ $receiptData['date'] ?? 'N/A' }}
                </div>
                <div class="info-item">
                    <span class="label">Receipt #:</span>
                    {{ $receiptData['receipt_number'] ?? 'N/A' }}
                </div>
                <div class="info-item">
                    <span class="label">Lab #:</span>
                    {{ $receiptData['lab_number'] ?? 'N/A' }}
                </div>
            </div>
        </div>

        <!-- Main Information Section -->
        <div class="main-info">
            <div class="info-section">
                <h3>Patient Information</h3>
                <div class="info-row">
                    <span class="label">Name:</span>
                    <span class="value">{{ $receiptData['patient_name'] ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Age:</span>
                    <span class="value">{{ $receiptData['patient_age'] ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Phone:</span>
                    <span class="value">{{ $receiptData['patient_phone'] ?? 'N/A' }}</span>
                </div>
            </div>
            
            <div class="info-section">
                <h3>Visit Information</h3>
                <div class="info-row">
                    <span class="label">Visit ID:</span>
                    <span class="value">{{ $receiptData['visit_id'] ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Check-in By:</span>
                    <span class="value">{{ $receiptData['check_in_by'] ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Check-in At:</span>
                    <span class="value">{{ $receiptData['check_in_at'] ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        <!-- Tests Section -->
        <div class="tests-section">
            <h3>Tests ({{ count($receiptData['tests'] ?? []) }})</h3>
            <table class="tests-table">
                <thead>
                    <tr>
                        <th>Test Name</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    @if(isset($receiptData['tests']) && count($receiptData['tests']) > 0)
                        @foreach($receiptData['tests'] as $test)
                            <tr>
                                <td class="test-name">{{ $test['name'] ?? 'Unknown Test' }}</td>
                                <td class="test-price">EGP {{ number_format($test['price'] ?? 0, 2) }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="2" style="text-align: center; color: #666;">No tests found</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Financial Summary -->
        <div class="financial-summary">
            <h3>Financial Summary</h3>
            <div class="financial-row">
                <span class="label">Total:</span>
                <span class="value">EGP {{ number_format($receiptData['total_amount'] ?? 0, 2) }}</span>
            </div>
            <div class="financial-row">
                <span class="label">Discount:</span>
                <span class="value">EGP {{ number_format($receiptData['discount_amount'] ?? 0, 2) }}</span>
            </div>
            <div class="financial-row final">
                <span class="label">Final:</span>
                <span class="value">EGP {{ number_format($receiptData['final_amount'] ?? $receiptData['total_amount'] ?? 0, 2) }}</span>
            </div>
            <div class="financial-row paid">
                <span class="label">Paid:</span>
                <span class="value">EGP {{ number_format($receiptData['upfront_payment'] ?? 0, 2) }}</span>
            </div>
            <div class="financial-row remaining">
                <span class="label">Remaining:</span>
                <span class="value">EGP {{ number_format($receiptData['remaining_balance'] ?? 0, 2) }}</span>
            </div>
        </div>

        <!-- Payment Breakdown -->
        @if(isset($receiptData['payment_breakdown']) && !empty($receiptData['payment_breakdown']))
        <div class="payment-breakdown">
            <h3>Payment Breakdown</h3>
            @if(isset($receiptData['payment_breakdown']['cash']) && $receiptData['payment_breakdown']['cash'] > 0)
            <div class="breakdown-row">
                <span class="label">Paid Cash:</span>
                <span class="value">EGP {{ number_format($receiptData['payment_breakdown']['cash'], 2) }}</span>
            </div>
            @endif
            @if(isset($receiptData['payment_breakdown']['card']) && $receiptData['payment_breakdown']['card'] > 0)
            <div class="breakdown-row">
                <span class="label">Paid {{ $receiptData['payment_breakdown']['card_method'] ?? 'Card' }}:</span>
                <span class="value">EGP {{ number_format($receiptData['payment_breakdown']['card'], 2) }}</span>
            </div>
            @endif
        </div>
        @endif

        <!-- Status Section -->
        <div class="status-section">
            <div class="status-badge status-{{ strtolower($receiptData['billing_status'] ?? 'unpaid') }}">
                Status: {{ strtoupper($receiptData['billing_status'] ?? 'UNPAID') }}
            </div>
        </div>

        <!-- Barcode Section -->
        @if(isset($receiptData['barcode_text']) && $receiptData['barcode_text'] !== 'N/A')
        <div class="barcode-section">
            <h3>Lab Number Barcode</h3>
            <div class="barcode">{{ $receiptData['barcode_text'] }}</div>
            <div style="font-size: 12px; color: #666; margin-top: 5px;">{{ $receiptData['barcode_text'] }}</div>
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <div class="thank-you">Thank you for choosing our lab!</div>
            <div class="print-info">
                Printed by: {{ $receiptData['printed_by'] ?? 'System' }}<br>
                Printed at: {{ $receiptData['printed_at'] ?? now()->format('Y-m-d H:i:s') }}
            </div>
            <div class="lab-info">
                <strong>Pathology Lab</strong><br>
                Specialized Medical Laboratory Services<br>
                For inquiries, please contact us at your convenience
            </div>
        </div>

        <!-- Arabic Note -->
        <div style="margin-top: 20px; padding: 15px; background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(220, 38, 38, 0.2)); border: 2px solid #dc2626; border-radius: 6px; text-align: center; box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);">
            <div style="font-size: 12px; font-weight: bold; color: #dc2626; direction: rtl; text-align: center; line-height: 1.5; text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8); font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;">
                يتم الاحتفاظ بالبلوكات الشمعية لمدة ثلاث سنوات ولطلبها يتم التبليغ عنها مسبقا  ميعاد استلام النتيجة ووقت التسليم
            </div>
        </div>
    </div>
</body>
</html>
