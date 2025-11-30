<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إيصال الدفع</title>
    <style>
        @page {
            margin: 0;
            margin-bottom: 0;
            size: 210mm 130mm;
            background-color: #F7F7F7;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            margin: 0;
            padding: 3px 5px 0 5px;
            width: 100%;
            height: 130mm;
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
            font-size: 18px;
            line-height: 1.3;
            color: #000;
            direction: rtl;
            background-color: #F7F7F7;
            overflow: hidden;
            padding-bottom: 0;
        }
        
        .content {
            position: relative;
            z-index: 1;
            width: 100%;
            padding-bottom: 0;
            margin-bottom: 0;
        }
        
        .header-table {
            width: 100%;
            margin-top: 8px;
            margin-bottom: 10px;
            padding-top: 8px;
            padding-bottom: 8px;
            padding-left: 8px;
            padding-right: 8px;
            border-collapse: collapse;
            border-bottom: 1px solid #ccc;
        }
        
        .header-table td {
            padding-right: 3px;
            padding-left: 3px;
            vertical-align: top;
        }
        
        .logo-cell {
            width: 80px;
            padding-right: 3px;
            padding-left: 0;
        }
        
        .logo-cell img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            display: block;
            margin: 0;
        }
        
        .header-text-cell {
            text-align: right;
            direction: rtl;
            padding-left: 3px;
            padding-right: 0;
        }
        
        .lab-name {
            font-size: 22px;
            font-weight: bold;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        
        .doctor-name {
            font-size: 20px;
            font-weight: bold;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        
        .doctor-title {
            font-size: 18px;
            line-height: 1.2;
            margin: 0;
            padding: 0;
        }
        
        .patient-table {
            width: 100%;
            margin: 4px 0;
            padding: 0;
            border-collapse: collapse;
            font-size: 18px;
            line-height: 1.3;
        }
        
        .patient-table td {
            padding: 2px 8px;
            vertical-align: top;
            text-align: right;
        }
        
        .patient-label {
            font-weight: bold;
            font-size: 16px;
            display: inline;
            margin-left: 5px;
        }
        
        .patient-value {
            font-size: 16px;
            display: inline;
        }
        
        .financial-section {
            border-top: 1px solid #ccc;
            padding-top: 3px;
            margin-top: 4px;
            margin-bottom: 2px;
            font-size: 18px;
        }
        
        .financial-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            padding: 0;
        }
        
        .financial-table td {
            padding: 2px 8px;
            text-align: right;
            vertical-align: top;
            font-size: 16px;
        }
        
        .financial-label {
            font-weight: bold;
            white-space: nowrap;
        }
        
        .financial-value {
            font-weight: normal;
            white-space: nowrap;
        }
        
        .financial-row {
            display: table;
            width: 100%;
            margin: 0;
            padding: 0;
        }
        
        .footer-section {
            border-top: 1px solid #ccc;
            padding-top: 2px;
            margin-top: 2px;
            padding-bottom: 0;
            margin-bottom: 0;
            text-align: center;
            font-size: 16px;
            line-height: 1.1;
        }
        
        .footer-line {
            margin: 0;
            padding: 0;
            line-height: 1.1;
        }
        
        .arabic-text {
            direction: rtl;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="content">
        <!-- Header - Logo and Text Side by Side -->
        <table class="header-table" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td class="logo-cell">
                    @if(isset($logoImage))
                        <img src="data:image/jpeg;base64,{{ $logoImage }}" alt="Logo" style="width: 70px; height: 70px; object-fit: contain; display: block; margin: 0;" />
                    @else
                        <img src="{{ public_path('templete/logoreceipt.jpg') }}" alt="Logo" style="width: 70px; height: 70px; object-fit: contain; display: block; margin: 0;" />
                    @endif
                </td>
                <td class="header-text-cell arabic-text">
                    <div class="lab-name arabic-text" style="margin: 0; padding: 0;">المعمل التخصصي لتحاليل الأورام والانسجة</div>
                    <div class="doctor-name arabic-text" style="margin: 0; padding: 0;">د. ياسر محمد الدويك</div>
                    <div class="doctor-title arabic-text" style="margin: 0; padding: 0;">مدرس و استشاري تحاليل الاورام والانسجة</div>
                    <div class="doctor-title arabic-text" style="margin: 0; padding: 0;">كلية الطب جامعة الأزهر</div>
                    <div class="doctor-title arabic-text" style="margin: 0; padding: 0;">دكتوراه الفحص الخلوي للانسجة والأورام والسوائل</div>
                </td>
            </tr>
        </table>

        <!-- Patient Information -->
        <table class="patient-table arabic-text" cellpadding="0" cellspacing="0" border="0">
            <!-- Row 1: رقم الموبايل | lab no | الاسم -->
            <tr>
                <td>
                    <span class="patient-label">رقم الموبايل:</span>
                    <span class="patient-value">{{ $receiptData['patient_phone'] ?? '' }}</span>
                </td>
                <td>
                    <span class="patient-label">رقم العينة:</span>
                    <span class="patient-value">{{ $receiptData['lab_number'] ?? 'غير متوفر' }}</span>
                </td>
                <td>
                    <span class="patient-label">الاسم:</span>
                    <span class="patient-value">{{ $receiptData['patient_name'] ?? 'غير متوفر' }}</span>
                </td>
            </tr>
            <!-- Row 2: السن | الجهة | النوع | الدكتور المرسل -->
            <tr>
                <td>
                    <span class="patient-label">السن:</span>
                    <span class="patient-value">{{ $receiptData['patient_age'] ?? 'غير متوفر' }}</span>
                </td>
                <td>
                    <span class="patient-label">الجهة:</span>
                    <span class="patient-value">{{ $receiptData['organization'] ?? '' }}</span>
                </td>
                <td>
                    <span class="patient-label">النوع:</span>
                    <span class="patient-value">
                        @if(isset($receiptData['patient_gender']))
                            {{ $receiptData['patient_gender'] == 'male' ? 'ذكر' : ($receiptData['patient_gender'] == 'female' ? 'انثي' : $receiptData['patient_gender']) }}
                        @else
                            غير متوفر
                        @endif
                    </span>
                </td>
                <td>
                    <span class="patient-label">الدكتور المرسل:</span>
                    <span class="patient-value">{{ $receiptData['doctor_name'] ?? ($receiptData['referring_doctor'] ?? 'غير متوفر') }}</span>
                </td>
            </tr>
            <!-- Row 3: اليوم | ميعاد التسليم | اليوم | وقت الحضور -->
            <tr>
                <td>
                    <span class="patient-label">اليوم:</span>
                    <span class="patient-value">{{ $receiptData['attendance_day'] ?? 'السبت' }}</span>
                </td>
                <td>
                    <span class="patient-label">ميعاد التسليم:</span>
                    <span class="patient-value">{{ $receiptData['delivery_date'] ?? 'غير متوفر' }}</span>
                </td>
                <td>
                    <span class="patient-label">اليوم:</span>
                    <span class="patient-value">{{ $receiptData['delivery_day'] ?? 'السبت' }}</span>
                </td>
                <td>
                    <span class="patient-label">وقت الحضور:</span>
                    <span class="patient-value">{{ $receiptData['attendance_date'] ?? ($receiptData['date'] ?? 'غير متوفر') }}</span>
                </td>
            </tr>
            <!-- Row 4: حجم العينة | عدد العينات | نوع العينة -->
            <tr>
                <td>
                    <span class="patient-label">حجم العينة:</span>
                    <span class="patient-value">{{ $receiptData['sample_size'] ?? '1' }}</span>
                </td>
                <td>
                    <span class="patient-label">عدد العينات:</span>
                    <span class="patient-value">{{ $receiptData['number_of_samples'] ?? '1' }}</span>
                </td>
                <td>
                    <span class="patient-label">نوع العينة:</span>
                    <span class="patient-value">{{ $receiptData['sample_type'] ?? 'Pathology' }}</span>
                </td>
                <td></td>
            </tr>
            <!-- Row 5: باقي الحقول -->
            <tr>
                <td>
                    <span class="patient-label">هل يوجد تاريخ مرضي:</span>
                    <span class="patient-value">{{ $receiptData['medical_history'] ? 'Yes' : 'No' }}</span>
                </td>
                <td colspan="3">
                    <span class="patient-label">هل سبق لك تحاليل باتولوجي:</span>
                    <span class="patient-value">{{ $receiptData['previous_tests'] ?? '' }}</span>
                </td>
            </tr>
            @if(isset($receiptData['patient_credentials']) && $receiptData['patient_credentials'])
                @php
                    $credentials = $receiptData['patient_credentials'];
                    $username = $credentials['username'] ?? null;
                    $password = $credentials['password'] ?? null;
                @endphp
                @if($username || $password)
                <tr>
                    <td>
                        <span class="patient-label">اسم المستخدم:</span>
                        <span class="patient-value" style="font-weight: bold; font-size: 16px;">{{ $username ?? 'غير متوفر' }}</span>
                    </td>
                    <td colspan="3">
                        <span class="patient-label">كلمة المرور:</span>
                        <span class="patient-value" style="font-weight: bold; font-size: 16px;">{{ $password ?? 'غير متوفر' }}</span>
                    </td>
                </tr>
                @endif
            @endif
        </table>

        <!-- Financial Summary -->
        <div class="financial-section arabic-text">
            <table class="financial-table" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td class="financial-label">أجمالي المبلغ :</td>
                    <td class="financial-value">{{ number_format($receiptData['total_amount'] ?? 0, 0) }} جنيه</td>
                    @if(isset($receiptData['discount_amount']) && $receiptData['discount_amount'] > 0)
                    <td class="financial-label">الخصم :</td>
                    <td class="financial-value">{{ number_format($receiptData['discount_amount'] ?? 0, 0) }} جنيه</td>
                    @endif
                </tr>
                <tr>
                    <td class="financial-label">المبلغ المدفوع :</td>
                    <td class="financial-value">{{ number_format($receiptData['upfront_payment'] ?? 0, 0) }} جنيه</td>
                    @if(isset($receiptData['payment_breakdown']))
                        @php
                            $paymentBreakdown = $receiptData['payment_breakdown'];
                            $cashAmount = floatval($paymentBreakdown['cash'] ?? 0);
                            $cardAmount = floatval($paymentBreakdown['card'] ?? 0);
                            $cardMethod = $paymentBreakdown['card_method'] ?? 'Card';
                        @endphp
                        @if($cashAmount > 0)
                        <td class="financial-label">- نقدي :</td>
                        <td class="financial-value">{{ number_format($cashAmount, 0) }} جنيه</td>
                        @endif
                        @if($cardAmount > 0)
                        <td class="financial-label">- {{ $cardMethod == 'Card' ? 'بطاقة' : $cardMethod }} :</td>
                        <td class="financial-value">{{ number_format($cardAmount, 0) }} جنيه</td>
                        @endif
                    @endif
                    <td class="financial-label">المبلغ المتبقي :</td>
                    <td class="financial-value">{{ number_format($receiptData['remaining_balance'] ?? 0, 0) }} جنيه</td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer-section arabic-text">
            <div class="footer-line">جناكليس امام كنيسة الكتاب المقدس 5 شارع محمد ناجي متفرع من شارع ابوقير الاسكندرية</div>
            <div class="footer-line">03/5805512 - 01270259292 - 01029558529</div>
            <div class="footer-line">حالات سحب الابرة (FNAC) بالحجز المسبق لسرعة تسليم النتيجة</div>
            <div class="footer-line">Y.Eldowik.SPCL@gmail.com</div>
        </div>
    </div>
</body>
</html>

