{{-- Thermal receipt 80×200mm — RTL Arabic with LTR islands for numbers, barcode, and Latin text. --}}
@php
    $lb = $labBranding ?? [
        'display_name' => 'Laboratory',
        'tagline' => '',
        'address' => '',
        'phone' => '',
        'email' => '',
        'vat' => '',
        'website' => '',
        'doc_label' => 'إيصال تسجيل / دفع',
        'currency_label' => 'جنيه',
    ];
    $cur = $lb['currency_label'] ?? 'جنيه';

    $sampleType = trim((string) ($receiptData['sample_type'] ?? ''));
    $sampleSize = trim((string) ($receiptData['sample_size'] ?? ''));
    $sampleCount = trim((string) ($receiptData['number_of_samples'] ?? ''));
    $hasSpecimen = $sampleType !== '' || $sampleSize !== '' || $sampleCount !== '';

    $billKey = strtolower(trim((string) ($receiptData['billing_status'] ?? '')));
    $billingAr = [
        'unpaid' => 'غير مدفوع',
        'paid' => 'مدفوع',
        'partial' => 'مدفوع جزئياً',
        'partial payment' => 'مدفوع جزئياً',
        'pending' => 'قيد الانتظار',
        'payment completed' => 'اكتمل الدفع',
        'complete' => 'مكتمل',
        'completed' => 'مكتمل',
    ][$billKey] ?? ($receiptData['billing_status'] ?? '—');

    $payKey = strtolower(trim((string) ($receiptData['payment_method'] ?? '')));
    $paymentAr = [
        'cash' => 'نقدي',
        'card' => 'بطاقة',
        'credit card' => 'بطاقة ائتمان',
        'debit card' => 'بطاقة خصم',
        'bank transfer' => 'تحويل بنكي',
        'mixed' => 'مختلط',
    ][$payKey] ?? ($receiptData['payment_method'] ?? '—');

    $bc = $receiptData['barcode'] ?? null;
    $bcIsSvg = is_string($bc) && str_contains($bc, '<svg');
    $bcIsPngB64 = is_string($bc) && ! str_contains($bc, '<') && strlen($bc) > 80 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $bc);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $lb['display_name'] }} — {{ $lb['doc_label'] }}</title>
    <style>
        @page { size: 80mm 200mm; margin: 3mm 5mm 3mm 3mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5px;
            font-weight: 600;
            line-height: 1.35;
            margin: 0;
            padding: 0;
            width: 70mm;
            max-width: 70mm;
            direction: rtl;
            color: #000;
            background: #fff;
        }
        .wrap { max-width: 70mm; margin: 0 auto; }

        .brand-block {
            text-align: center;
            padding-bottom: 5px;
            margin-bottom: 5px;
            border-bottom: 2px solid #000;
        }
        .brand-name {
            font-size: 13px;
            font-weight: 900;
            line-height: 1.25;
            unicode-bidi: plaintext;
        }
        .brand-tag {
            font-size: 8px;
            font-weight: 700;
            color: #333;
            margin-top: 2px;
        }
        .brand-doc {
            font-size: 9px;
            font-weight: 800;
            margin-top: 4px;
            letter-spacing: 0.02em;
        }

        .id-box {
            border: 1px solid #000;
            padding: 4px 6px;
            margin: 0 0 6px;
            text-align: center;
        }
        .id-box .lbl {
            display: block;
            font-size: 7.5px;
            font-weight: 800;
            color: #333;
            margin-bottom: 1px;
        }
        .id-box .num {
            display: block;
            font-size: 10.5px;
            font-weight: 900;
            direction: ltr;
            unicode-bidi: embed;
            text-align: center;
        }
        .id-box .num.sm { font-size: 10px; margin-top: 3px; }

        .sec {
            margin: 6px 0 4px;
            padding: 2px 4px;
            font-size: 8.5px;
            font-weight: 900;
            text-align: center;
            background: #e8e8e8;
            border: 1px solid #000;
        }

        table.rows {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            font-weight: 700;
            margin-bottom: 2px;
        }
        table.rows td {
            padding: 2px 0;
            vertical-align: top;
            border-bottom: 1px dotted #bbb;
        }
        table.rows td.k {
            width: 38%;
            font-weight: 800;
            white-space: nowrap;
            padding-left: 2mm;
        }
        table.rows td.v {
            width: 62%;
            font-weight: 700;
            word-wrap: break-word;
            text-align: right;
        }
        .latin { direction: ltr; unicode-bidi: embed; display: inline-block; text-align: left; max-width: 100%; }

        .sep { border: none; border-top: 1px dashed #000; margin: 5px 0; height: 0; }

        .svc {
            padding: 4px 0;
            border-bottom: 1px dotted #888;
        }
        .svc:last-of-type { border-bottom: none; }
        .svc-title {
            font-size: 9px;
            font-weight: 800;
            word-wrap: break-word;
            text-align: start;
            unicode-bidi: plaintext;
        }
        .svc-cat {
            font-size: 7.5px;
            font-weight: 600;
            color: #444;
            margin-top: 1px;
            text-align: start;
            unicode-bidi: plaintext;
        }
        .svc-price {
            font-size: 10px;
            font-weight: 900;
            margin-top: 3px;
            direction: ltr;
            unicode-bidi: embed;
            text-align: right;
        }

        table.pay {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            font-weight: 700;
            margin-bottom: 2px;
        }
        table.pay td { padding: 2px 0; vertical-align: top; }
        table.pay td.pk { font-weight: 800; width: 48%; }
        table.pay td.pv {
            width: 52%;
            direction: ltr;
            unicode-bidi: embed;
            text-align: right;
            font-weight: 800;
        }

        .total {
            margin-top: 6px;
            padding: 5px 4px;
            border: 2px solid #000;
            text-align: center;
            font-size: 11px;
            font-weight: 900;
        }
        .total .amt {
            direction: ltr;
            unicode-bidi: embed;
            display: inline-block;
        }

        .barcode-box {
            margin: 8px 0 4px;
            padding: 6px 2px 4px;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            direction: ltr;
            unicode-bidi: isolate;
            text-align: center;
        }
        .barcode-box svg { max-width: 100%; height: auto; display: block; margin: 0 auto; }
        .barcode-box img { max-width: 100%; height: auto; display: block; margin: 0 auto; }
        .barcode-caption {
            margin-top: 4px;
            font-size: 9px;
            font-weight: 800;
            direction: ltr;
            unicode-bidi: embed;
            text-align: center;
        }

        .thank {
            text-align: center;
            margin: 10px 0 6px;
            font-size: 11px;
            font-weight: 900;
            unicode-bidi: isolate;
        }

        .footer {
            text-align: center;
            font-size: 7.5px;
            font-weight: 700;
            color: #333;
            border-top: 1px dashed #000;
            padding-top: 5px;
            line-height: 1.35;
        }
        .footer-line { margin: 2px 0; }
        .print-meta {
            margin-top: 4px;
            font-size: 7px;
            color: #555;
            direction: rtl;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand-block">
        <div class="brand-name">{{ $lb['display_name'] }}</div>
        @if(!empty($lb['tagline']))
            <div class="brand-tag">{{ $lb['tagline'] }}</div>
        @endif
        <div class="brand-doc">{{ $lb['doc_label'] }}</div>
    </div>

    <div class="id-box">
        <span class="lbl">رقم الزيارة</span>
        <span class="num">{{ $receiptData['receipt_number'] ?? '—' }}</span>
        <span class="lbl" style="margin-top:4px;">رقم العينة</span>
        <span class="num sm">{{ $receiptData['lab_number'] ?? '—' }}</span>
    </div>

    <div class="sec">بيانات الزيارة والمريض</div>
    <table class="rows">
        <tr><td class="k">التاريخ</td><td class="v"><span class="latin">{{ $receiptData['date'] ?? ($receiptData['attendance_date'] ?? '—') }}</span></td></tr>
        <tr><td class="k">اسم المريض</td><td class="v">{{ $receiptData['patient_name'] ?? '—' }}</td></tr>
        <tr><td class="k">السن</td><td class="v"><span class="latin">{{ $receiptData['patient_age'] ?? '—' }}</span></td></tr>
        <tr><td class="k">الجوال</td><td class="v"><span class="latin">{{ $receiptData['patient_phone'] ?? '—' }}</span></td></tr>
        <tr><td class="k">النوع</td><td class="v">@if(isset($receiptData['patient_gender'])){{ $receiptData['patient_gender'] == 'male' ? 'ذكر' : ($receiptData['patient_gender'] == 'female' ? 'أنثى' : $receiptData['patient_gender']) }}@else — @endif</td></tr>
        @if(!empty($receiptData['organization']))
        <tr><td class="k">الجهة</td><td class="v">{{ $receiptData['organization'] }}</td></tr>
        @endif
        <tr><td class="k">الطبيب المحيل</td><td class="v">{{ $receiptData['doctor_name'] ?? ($receiptData['referring_doctor'] ?? '—') }}</td></tr>
        <tr><td class="k">يوم الحضور</td><td class="v">{{ $receiptData['attendance_day'] ?? '—' }}</td></tr>
        <tr><td class="k">تاريخ الحضور</td><td class="v"><span class="latin">{{ $receiptData['attendance_date'] ?? ($receiptData['date'] ?? '—') }}</span></td></tr>
        <tr><td class="k">موعد التسليم</td><td class="v"><span class="latin">{{ $receiptData['delivery_date'] ?? ($receiptData['expected_delivery_date'] ?? '—') }}</span></td></tr>
        <tr><td class="k">يوم التسليم</td><td class="v">{{ $receiptData['delivery_day'] ?? '—' }}</td></tr>
    </table>

    @if($hasSpecimen)
    <div class="sec">بيانات العينة</div>
    <table class="rows">
        @if($sampleType !== '')
        <tr><td class="k">نوع العينة</td><td class="v">{{ $receiptData['sample_type'] }}</td></tr>
        @endif
        @if($sampleSize !== '')
        <tr><td class="k">حجم العينة</td><td class="v">{{ $receiptData['sample_size'] }}</td></tr>
        @endif
        @if($sampleCount !== '')
        <tr><td class="k">عدد العينات</td><td class="v"><span class="latin">{{ $receiptData['number_of_samples'] }}</span></td></tr>
        @endif
    </table>
    @endif

    <table class="rows">
        <tr><td class="k">سجل طبي سابق</td><td class="v">{{ !empty($receiptData['medical_history']) ? 'نعم' : 'لا' }}</td></tr>
        @if(!empty($receiptData['previous_tests']))
        <tr><td class="k">تحاليل سابقة</td><td class="v">{{ $receiptData['previous_tests'] }}</td></tr>
        @endif
    </table>

    <hr class="sep" />

    @if(isset($receiptData['tests']) && count($receiptData['tests']) > 0)
        <div class="sec">الخدمات والتحاليل</div>
        @foreach($receiptData['tests'] as $test)
            @php
                $t = is_array($test) ? $test : (array) $test;
                $nm = $t['name'] ?? '—';
                $cat = trim((string) ($t['category'] ?? ''));
                $showCat = $cat !== '' && ! in_array($cat, ['Unknown', 'Sample Type', 'Unknown Test'], true);
                $pr = isset($t['price']) ? number_format((float) $t['price'], 0, '.', '') : '0';
            @endphp
            <div class="svc">
                <div class="svc-title">• {{ $nm }}</div>
                @if($showCat)
                    <div class="svc-cat">{{ $cat }}</div>
                @endif
                <div class="svc-price">{{ $pr }} {{ $cur }}</div>
            </div>
        @endforeach
        <hr class="sep" />
    @endif

    <div class="sec">ملخص الدفع</div>
    <table class="pay">
        <tr><td class="pk">إجمالي المبلغ</td><td class="pv">{{ number_format($receiptData['total_amount'] ?? 0, 0, '.', '') }} {{ $cur }}</td></tr>
        @if(isset($receiptData['discount_amount']) && (float) $receiptData['discount_amount'] > 0)
        <tr><td class="pk">الخصم</td><td class="pv">{{ number_format($receiptData['discount_amount'], 0, '.', '') }} {{ $cur }}</td></tr>
        @endif
        <tr><td class="pk">المدفوع</td><td class="pv">{{ number_format($receiptData['upfront_payment'] ?? 0, 0, '.', '') }} {{ $cur }}</td></tr>
        @if(isset($receiptData['payment_breakdown']))
            @php
                $pb = $receiptData['payment_breakdown'];
                $cashAmount = floatval($pb['cash'] ?? 0);
                $cardAmount = floatval($pb['card'] ?? 0);
                $cardMethod = $pb['card_method'] ?? 'Card';
            @endphp
            @if($cashAmount > 0)
            <tr><td class="pk">منها نقداً</td><td class="pv">{{ number_format($cashAmount, 0, '.', '') }} {{ $cur }}</td></tr>
            @endif
            @if($cardAmount > 0)
            <tr><td class="pk">{{ $cardMethod == 'Card' ? 'منها بالبطاقة' : $cardMethod }}</td><td class="pv">{{ number_format($cardAmount, 0, '.', '') }} {{ $cur }}</td></tr>
            @endif
        @endif
        <tr><td class="pk">المتبقي</td><td class="pv">{{ number_format($receiptData['remaining_balance'] ?? 0, 0, '.', '') }} {{ $cur }}</td></tr>
        <tr><td class="pk">حالة الفاتورة</td><td class="pv" style="direction:rtl;unicode-bidi:embed;text-align:right;">{{ $billingAr }}</td></tr>
        <tr><td class="pk">طريقة الدفع</td><td class="pv" style="direction:rtl;unicode-bidi:embed;text-align:right;">{{ $paymentAr }}</td></tr>
    </table>

    <div class="total">
        المبلغ النهائي: <span class="amt">{{ number_format($receiptData['final_amount'] ?? ($receiptData['total_amount'] ?? 0), 0, '.', '') }} {{ $cur }}</span>
    </div>

    @if(!empty($bc) && is_string($bc))
    <div class="barcode-box">
        @if($bcIsSvg)
            {!! $bc !!}
        @elseif($bcIsPngB64)
            <img src="data:image/png;base64,{{ preg_replace('/\s+/', '', $bc) }}" alt="" />
        @endif
        @if(!empty($receiptData['barcode_text']))
            <div class="barcode-caption">{{ $receiptData['barcode_text'] }}</div>
        @endif
    </div>
    @endif

    @if(isset($receiptData['patient_credentials']) && is_array($receiptData['patient_credentials']))
        @php
            $cred = $receiptData['patient_credentials'];
            $u = $cred['username'] ?? null;
            $p = $cred['password'] ?? null;
        @endphp
        @if($u || $p)
            <hr class="sep" />
            <div class="sec">بيانات المتابعة الإلكترونية</div>
            <table class="rows">
                @if($u)<tr><td class="k">المستخدم</td><td class="v"><span class="latin">{{ $u }}</span></td></tr>@endif
                @if($p)<tr><td class="k">كلمة المرور</td><td class="v"><span class="latin">{{ $p }}</span></td></tr>@endif
            </table>
        @endif
    @endif

    <p class="thank">شكراً لثقتكم</p>

    <div class="footer">
        @if(!empty($lb['address']))
            <div class="footer-line">{{ $lb['address'] }}</div>
        @endif
        @php
            $contactBits = array_filter([$lb['phone'] ?? '', $lb['email'] ?? '', $lb['website'] ?? '']);
        @endphp
        @if(count($contactBits))
            <div class="footer-line">{{ implode(' — ', $contactBits) }}</div>
        @endif
        @if(!empty($lb['vat']))
            <div class="footer-line">الرقم الضريبي: <span class="latin">{{ $lb['vat'] }}</span></div>
        @endif
        <div class="print-meta">
            طُبع بتاريخ: <span class="latin">{{ $receiptData['printed_at'] ?? '' }}</span>
            @if(!empty($receiptData['printed_by']))
                — <span class="latin">{{ $receiptData['printed_by'] }}</span>
            @endif
        </div>
    </div>
</div>
</body>
</html>
