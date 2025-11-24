<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إيصال الدفع</title>
    <style>
        @page {
            margin: 0;
            size: 210mm 148.5mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            margin: 0;
            padding: 3px 5px;
            width: 100%;
            height: 148.5mm;
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
            font-size: 18px;
            line-height: 1.3;
            color: #000;
            direction: rtl;
            background-color: #F7F7F7;
            overflow: hidden;
        }
        
        .content {
            position: relative;
            z-index: 1;
            width: 100%;
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
            padding: 0 10px;
            vertical-align: top;
            text-align: right;
        }
        
        .patient-label {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 2px;
        }
        
        .patient-value {
            font-size: 18px;
        }
        
        .financial-section {
            border-top: 1px solid #ccc;
            padding-top: 3px;
            margin-top: 4px;
            margin-bottom: 4px;
            font-size: 18px;
        }
        
        .financial-row {
            display: table;
            width: 100%;
            margin: 0;
            padding: 0;
        }
        
        .financial-label {
            display: table-cell;
            font-weight: bold;
            text-align: right;
            width: 60%;
            padding-right: 5px;
        }
        
        .financial-value {
            display: table-cell;
            text-align: right;
            width: 40%;
        }
        
        .footer-section {
            border-top: 1px solid #ccc;
            padding-top: 3px;
            margin-top: 4px;
            text-align: center;
            font-size: 16px;
            line-height: 1.4;
        }
        
        .footer-line {
            margin: 0;
            padding: 0;
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
            <tr>
                <td>
                    <div class="patient-label">الاسم</div>
                    <div class="patient-value">{{ $receiptData['patient_name'] ?? 'غير متوفر' }}</div>
                </td>
                <td>
                    <div class="patient-label">الجهة</div>
                    <div class="patient-value">{{ $receiptData['organization'] ?? '' }}</div>
                </td>
                <td>
                    <div class="patient-label">الدكتور المرسل</div>
                    <div class="patient-value">{{ $receiptData['doctor_name'] ?? ($receiptData['referring_doctor'] ?? 'غير متوفر') }}</div>
                </td>
                <td>
                    <div class="patient-label">السن</div>
                    <div class="patient-value">{{ $receiptData['patient_age'] ?? 'غير متوفر' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="patient-label">النوع</div>
                    <div class="patient-value">
                        @if(isset($receiptData['patient_gender']))
                            {{ $receiptData['patient_gender'] == 'male' ? 'ذكر' : ($receiptData['patient_gender'] == 'female' ? 'انثي' : $receiptData['patient_gender']) }}
                        @else
                            غير متوفر
                        @endif
                    </div>
                </td>
                <td>
                    <div class="patient-label">رقم الموبايل</div>
                    <div class="patient-value">{{ $receiptData['patient_phone'] ?? '' }}</div>
                </td>
                <td>
                    <div class="patient-label">اليوم</div>
                    <div class="patient-value">{{ $receiptData['attendance_day'] ?? 'السبت' }}</div>
                </td>
                <td>
                    <div class="patient-label">وقت الحضور</div>
                    <div class="patient-value">{{ $receiptData['attendance_date'] ?? ($receiptData['date'] ?? 'غير متوفر') }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="patient-label">نوع العينة</div>
                    <div class="patient-value">{{ $receiptData['sample_type'] ?? 'Pathology' }}</div>
                </td>
                <td>
                    <div class="patient-label">عدد العينات</div>
                    <div class="patient-value">{{ $receiptData['number_of_samples'] ?? '1' }}</div>
                </td>
                <td>
                    <div class="patient-label">رقم العينة</div>
                    <div class="patient-value">{{ $receiptData['lab_number'] ?? 'غير متوفر' }}</div>
                </td>
                <td>
                    <div class="patient-label">ميعاد التسليم</div>
                    <div class="patient-value">{{ $receiptData['delivery_date'] ?? 'غير متوفر' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="patient-label">حجم العينة</div>
                    <div class="patient-value">{{ $receiptData['sample_size'] ?? '1' }}</div>
                </td>
                <td>
                    <div class="patient-label">هل يوجد تاريخ مرضي ؟</div>
                    <div class="patient-value">{{ $receiptData['medical_history'] ? 'Yes' : 'No' }}</div>
                </td>
                <td colspan="2">
                    <div class="patient-label">هل سبق لك تحاليل باتولوجي</div>
                    <div class="patient-value">{{ $receiptData['previous_tests'] ?? '' }}</div>
                </td>
            </tr>
        </table>

        <!-- Financial Summary -->
        <div class="financial-section arabic-text">
            <div class="financial-row">
                <span class="financial-label">أجمالي المبلغ :</span>
                <span class="financial-value">{{ number_format($receiptData['total_amount'] ?? 0, 0) }}</span>
            </div>
            <div class="financial-row">
                <span class="financial-label">المبلغ المدفوع :</span>
                <span class="financial-value">{{ number_format($receiptData['upfront_payment'] ?? 0, 0) }}</span>
            </div>
            <div class="financial-row">
                <span class="financial-label">المبلغ المتبقي :</span>
                <span class="financial-value">{{ number_format($receiptData['remaining_balance'] ?? 0, 0) }}</span>
            </div>
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
