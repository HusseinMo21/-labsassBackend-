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
            line-height: 1.6;
            font-size: 11px;
            position: relative;
            color: #2c3e50;
            direction: rtl;
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
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 3px solid #1e3a8a;
        }
        
        .receipt-title {
            font-size: 22px;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 2px;
            margin-bottom: 5px;
            direction: rtl;
            text-align: center;
            width: 100%;
        }
        
        /* Patient Info - Compact Grid */
        .patient-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 10px;
            direction: rtl;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            direction: rtl;
            flex-direction: row-reverse;
        }
        
        .info-label {
            font-weight: 700;
            color: #1e3a8a;
            min-width: 120px;
            margin-left: 8px;
            text-align: right;
        }
        
        .info-value {
            color: #2c3e50;
            flex: 1;
            text-align: right;
            font-weight: 500;
        }
        
        .arabic-text {
            direction: rtl;
            text-align: right;
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
        }
        
        /* Section Headers */
        .section-header {
            font-size: 12px;
            font-weight: 700;
            color: #1e3a8a;
            margin: 15px 0 8px 0;
            padding-bottom: 5px;
            border-bottom: 2px solid #1e3a8a;
            text-align: right;
            direction: rtl;
        }
        
        /* Tests Table - Minimal */
        .tests-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 10px;
            direction: rtl;
        }
        
        .tests-table th {
            background-color: #f8f9fa;
            color: #1e3a8a;
            font-weight: 700;
            padding: 8px 10px;
            text-align: right;
            border-bottom: 2px solid #1e3a8a;
            direction: rtl;
        }
        
        .tests-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #e9ecef;
            text-align: right;
            direction: rtl;
        }
        
        .tests-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Financial Summary - Clean */
        .financial-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
            font-size: 10px;
            direction: rtl;
        }
        
        .financial-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            direction: rtl;
            flex-direction: row-reverse;
        }
        
        .financial-label {
            font-weight: 700;
            color: #495057;
            text-align: right;
        }
        
        .financial-value {
            font-weight: 700;
            color: #2c3e50;
            text-align: left;
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
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 12px;
            font-size: 10px;
            direction: rtl;
        }
        
        .payment-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            direction: rtl;
            flex-direction: row-reverse;
        }
        
        /* Payment Status Badge */
        .status-badge {
            display: inline-block;
            padding: 8px 15px;
            background-color: #28a745;
            color: white;
            font-weight: 700;
            font-size: 12px;
            border-radius: 4px;
            text-align: center;
            width: 100%;
            margin-top: 10px;
            direction: rtl;
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
            direction: rtl;
        }
        
        .text-left {
            text-align: left;
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
                <div class="receipt-title arabic-text">{{ isset($isFinalPayment) && $isFinalPayment ? 'إيصال الدفع النهائي' : 'إيصال الدفع' }}</div>
            </div>

            <!-- Patient Information -->
            <div class="patient-info-grid">
                <div class="info-item">
                    <span class="info-label arabic-text">اسم المريض:</span>
                    <span class="info-value arabic-text">{{ $receiptData['patient_name'] ?? 'غير متوفر' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label arabic-text">السن:</span>
                    <span class="info-value">{{ $receiptData['patient_age'] ?? 'غير متوفر' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label arabic-text">رقم الهاتف:</span>
                    <span class="info-value">{{ $receiptData['patient_phone'] ?? 'غير متوفر' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label arabic-text">التاريخ:</span>
                    <span class="info-value">{{ $receiptData['date'] ?? 'غير متوفر' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label arabic-text">رقم الإيصال:</span>
                    <span class="info-value">{{ $receiptData['receipt_number'] ?? 'غير متوفر' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label arabic-text">رقم المختبر:</span>
                    <span class="info-value">{{ $receiptData['lab_number'] ?? 'غير متوفر' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label arabic-text">تاريخ الحضور:</span>
                    <span class="info-value">{{ $receiptData['attendance_date'] ?? 'غير متوفر' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label arabic-text">تاريخ التسليم:</span>
                    <span class="info-value">{{ $receiptData['delivery_date'] ?? 'غير متوفر' }}</span>
                </div>
            </div>

            <!-- Tests Ordered -->
            <div class="section-header arabic-text">التحاليل المطلوبة ({{ count($receiptData['tests'] ?? []) > 0 ? count($receiptData['tests']) : 1 }}):</div>
            <table class="tests-table">
                <thead>
                    <tr>
                        <th class="arabic-text">اسم التحليل</th>
                        <th class="arabic-text">الفئة</th>
                        <th class="arabic-text" style="text-align: left;">السعر</th>
                    </tr>
                </thead>
                <tbody>
                    @if(!empty($receiptData['tests']))
                        @foreach($receiptData['tests'] as $test)
                            <tr>
                                <td class="arabic-text">{{ $test['name'] ?? 'غير متوفر' }}</td>
                                <td class="arabic-text">{{ $test['category'] ?? 'غير متوفر' }}</td>
                                <td style="text-align: left;">{{ number_format($test['price'] ?? 0, 2) }} جنيه</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td class="arabic-text">frozen</td>
                            <td class="arabic-text">نوع العينة</td>
                            <td style="text-align: left;">{{ number_format($receiptData['total_amount'] ?? 0, 2) }} جنيه</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <!-- Financial Summary -->
            <div class="section-header arabic-text">الملخص المالي:</div>
            <div class="financial-grid">
                <div class="financial-row">
                    <span class="financial-label arabic-text">المبلغ الإجمالي:</span>
                    <span class="financial-value">{{ number_format($receiptData['total_amount'] ?? 0, 2) }} جنيه</span>
                </div>
                <div class="financial-row">
                    <span class="financial-label arabic-text">الخصم:</span>
                    <span class="financial-value">{{ number_format($receiptData['discount_amount'] ?? 0, 2) }} جنيه</span>
                </div>
                <div class="financial-row total-row">
                    <span class="financial-label arabic-text">المبلغ النهائي:</span>
                    <span class="financial-value">{{ number_format($receiptData['final_amount'] ?? 0, 2) }} جنيه</span>
                </div>
                <div class="financial-row total-row">
                    <span class="financial-label arabic-text">المبلغ المدفوع:</span>
                    <span class="financial-value">{{ number_format($receiptData['upfront_payment'] ?? 0, 2) }} جنيه</span>
                </div>
                <div class="financial-row">
                    <span class="financial-label arabic-text">الرصيد المتبقي:</span>
                    <span class="financial-value">{{ number_format($receiptData['remaining_balance'] ?? 0, 2) }} جنيه</span>
                </div>
                @php
                    // Function to translate payment method to Arabic
                    function translatePaymentMethod($method) {
                        $translations = [
                            'cash' => 'كاش',
                            'Cash' => 'كاش',
                            'Fawry' => 'فوري',
                            'fawry' => 'فوري',
                            'VodafoneCash' => 'فودافون كاش',
                            'vodafoneCash' => 'فودافون كاش',
                            'InstaPay' => 'انستا باي',
                            'instapay' => 'انستا باي',
                            'InstaPay' => 'انستا باي',
                            'Card' => 'بطاقة',
                            'card' => 'بطاقة',
                            'Other' => 'أخرى',
                            'other' => 'أخرى',
                        ];
                        return $translations[$method] ?? $method;
                    }
                    
                    // Build payment methods list from payment breakdown
                    $paymentMethodsList = [];
                    if (!empty($receiptData['payment_breakdown'])) {
                        if (isset($receiptData['payment_breakdown']['cash']) && $receiptData['payment_breakdown']['cash'] > 0) {
                            $paymentMethodsList[] = translatePaymentMethod('cash');
                        }
                        if (isset($receiptData['payment_breakdown']['card']) && $receiptData['payment_breakdown']['card'] > 0) {
                            $cardMethod = $receiptData['payment_breakdown']['card_method'] ?? ($receiptData['payment_method'] ?? 'Card');
                            $paymentMethodsList[] = translatePaymentMethod($cardMethod);
                        }
                    }
                    // If no breakdown, use the payment_method field
                    if (empty($paymentMethodsList) && isset($receiptData['payment_method'])) {
                        $paymentMethodsList[] = translatePaymentMethod($receiptData['payment_method']);
                    }
                    $paymentMethodsDisplay = !empty($paymentMethodsList) ? implode(' + ', $paymentMethodsList) : 'غير متوفر';
                @endphp
                <div class="financial-row">
                    <span class="financial-label arabic-text">طريقة الدفع:</span>
                    <span class="financial-value arabic-text">{{ $paymentMethodsDisplay }}</span>
                </div>
            </div>

            <!-- Payment Breakdown -->
            @if (!empty($receiptData['payment_breakdown']))
            <div class="section-header arabic-text">تفاصيل الدفع:</div>
            <div class="payment-breakdown">
                @if (isset($receiptData['payment_breakdown']['cash']) && $receiptData['payment_breakdown']['cash'] > 0)
                    <div class="payment-item">
                        <span class="arabic-text">المدفوع نقداً:</span>
                        <span class="font-bold">{{ number_format($receiptData['payment_breakdown']['cash'], 2) }} جنيه</span>
                    </div>
                @endif
                @if (isset($receiptData['payment_breakdown']['card']) && $receiptData['payment_breakdown']['card'] > 0)
                    @php
                        $cardMethod = $receiptData['payment_breakdown']['card_method'] ?? ($receiptData['payment_method'] ?? 'Card');
                        $cardMethodArabic = translatePaymentMethod($cardMethod);
                    @endphp
                    <div class="payment-item">
                        <span class="arabic-text">المدفوع بـ {{ $cardMethodArabic }}:</span>
                        <span class="font-bold">{{ number_format($receiptData['payment_breakdown']['card'], 2) }} جنيه</span>
                    </div>
                @endif
                @foreach($receiptData['payment_breakdown'] as $method => $amount)
                    @if($method !== 'cash' && $method !== 'card' && $method !== 'card_method' && is_numeric($amount) && $amount > 0)
                        @php
                            $methodArabic = translatePaymentMethod($method);
                        @endphp
                        <div class="payment-item">
                            <span class="arabic-text">المدفوع بـ {{ $methodArabic }}:</span>
                            <span class="font-bold">{{ number_format($amount, 2) }} جنيه</span>
                        </div>
                    @endif
                @endforeach
            </div>
            @endif

            <!-- Payment Status -->
            <div class="section-header arabic-text">حالة الدفع:</div>
            <div class="status-badge arabic-text">
                @php
                    $status = strtolower($receiptData['billing_status'] ?? 'unpaid');
                    $statusArabic = [
                        'paid' => 'مدفوع',
                        'partial' => 'مدفوع جزئياً',
                        'unpaid' => 'غير مدفوع'
                    ];
                    echo $statusArabic[$status] ?? strtoupper($receiptData['billing_status'] ?? 'UNPAID');
                @endphp
            </div>

            <!-- Patient Credentials (only for final payment) -->
            @if(isset($isFinalPayment) && $isFinalPayment && isset($receiptData['patient_credentials']) && $receiptData['patient_credentials'])
                <div class="section-header arabic-text">بيانات الدخول للمريض:</div>
                <div class="payment-breakdown">
                    <div class="payment-item">
                        <span class="arabic-text">اسم المستخدم:</span>
                        <span class="font-bold">{{ $receiptData['patient_credentials']['username'] ?? 'غير متوفر' }}</span>
                    </div>
                    <div class="payment-item">
                        <span class="arabic-text">كلمة المرور:</span>
                        <span class="font-bold">{{ $receiptData['patient_credentials']['password'] ?? 'غير متوفر' }}</span>
                    </div>
                </div>
            @endif

            <!-- Footer -->
            <div class="receipt-footer arabic-text">
                <div class="footer-item">طبع بواسطة: {{ $receiptData['printed_by'] ?? 'النظام' }}</div>
                <div class="footer-item">نظام مختبر الباثولوجي</div>
                <div class="footer-item">طبع في: {{ $receiptData['printed_at'] ?? now()->format('Y-m-d H:i:s') }}</div>
                <div class="footer-item">رقم الزيارة: {{ $receiptData['visit_id'] ?? 'غير متوفر' }}</div>
            </div>

            <!-- Arabic Note -->
            <div class="arabic-note">
                يتم الاحتفاظ بالبلوكات الشمعية لمدة ثلاث سنوات ولطلبها يتم التبليغ عنها مسبقا ميعاد استلام النتيجة ووقت التسليم
            </div>
        </div>
    </div>
</body>
</html>
