<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { 
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif; 
            font-size: 14px; 
            color: #222; 
            direction: ltr;
        }
        .arabic-text {
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
            direction: rtl;
            text-align: right;
            unicode-bidi: bidi-override;
            font-size: 16px;
            font-weight: bold;
            line-height: 1.4;
            letter-spacing: 0.5px;
        }
        .header { border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 2rem; font-weight: bold; color: #007bff; }
        .section { margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f8faff; }
        .right { text-align: right; }
        .arabic-cell {
            direction: rtl;
            text-align: right;
            unicode-bidi: bidi-override;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">INVOICE</div>
        <div>Invoice #: <b>{{ $invoice->invoice_number }}</b></div>
        <div>Date: {{ $invoice->invoice_date ? $invoice->invoice_date->format('Y-m-d') : '' }}</div>
    </div>
    <div class="section">
        <div style="margin-bottom: 8px;">
            <b>Patient:</b><br>
            <span class="arabic-text" style="font-size: 16px; font-weight: bold; margin-right: 20px;">{{ $invoice->visit->patient->name ?? '-' }}</span>
        </div>
        <div style="margin-bottom: 8px;">
            <b>Phone:</b> {{ $invoice->visit->patient->phone ?? '-' }}
        </div>
        <div style="margin-bottom: 8px;">
            <b>Visit #:</b> {{ $invoice->visit->visit_number ?? '-' }}
        </div>
    </div>
    
    @if($invoice->visit->patient->credentials)
    <div class="section" style="background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;">
        <h3 style="margin: 0 0 10px 0; color: #007bff; font-size: 16px;">🔐 Patient Portal Access</h3>
        <div style="font-family: 'Courier New', monospace; background: #fff; padding: 10px; border-radius: 3px; border: 1px solid #ddd;">
            <div><b>Username:</b> {{ $invoice->visit->patient->credentials->username }}</div>
            <div><b>Password:</b> {{ $invoice->visit->patient->credentials->original_password }}</div>
        </div>
        <div style="margin-top: 8px; font-size: 12px; color: #666;">
            Use these credentials to access your test results and reports online at our patient portal.
        </div>
    </div>
    @endif
    <div class="section">
        <table>
            <tr>
                <th>Description</th>
                <th class="right">Amount</th>
            </tr>
            <tr>
                <td>Lab Tests & Services</td>
                <td class="right">${{ number_format($invoice->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td>Discount</td>
                <td class="right">-${{ number_format($invoice->discount_amount, 2) }}</td>
            </tr>
            <tr>
                <td>Tax</td>
                <td class="right">${{ number_format($invoice->tax_amount, 2) }}</td>
            </tr>
            <tr>
                <th>Total</th>
                <th class="right">${{ number_format($invoice->total_amount - $invoice->discount_amount + $invoice->tax_amount, 2) }}</th>
            </tr>
        </table>
    </div>
    <div class="section">
        <b>Payments:</b>
        <table>
            <tr>
                <th>Date</th>
                <th>Method</th>
                <th class="right">Amount</th>
            </tr>
            @foreach($invoice->payments as $payment)
            <tr>
                <td>{{ $payment->paid_at ? $payment->paid_at->format('Y-m-d H:i') : '-' }}</td>
                <td>{{ ucfirst($payment->payment_method) }}</td>
                <td class="right">${{ number_format($payment->amount, 2) }}</td>
            </tr>
            @endforeach
        </table>
        <div><b>Amount Paid:</b> ${{ number_format($invoice->amount_paid, 2) }}</div>
        <div><b>Balance Due:</b> ${{ number_format($invoice->balance, 2) }}</div>
    </div>
    <div class="section" style="margin-top: 40px; color: #888; font-size: 0.95rem;">
        Thank you for choosing our laboratory.
    </div>
</body>
</html> 