<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice <?php echo e($invoice->invoice_number); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #222; }
        .header { border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 2rem; font-weight: bold; color: #007bff; }
        .section { margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f8faff; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">INVOICE</div>
        <div>Invoice #: <b><?php echo e($invoice->invoice_number); ?></b></div>
        <div>Date: <?php echo e($invoice->invoice_date ? $invoice->invoice_date->format('Y-m-d') : ''); ?></div>
    </div>
    <div class="section">
        <b>Patient:</b> <?php echo e($invoice->visit->patient->name ?? '-'); ?><br>
        <b>Phone:</b> <?php echo e($invoice->visit->patient->phone ?? '-'); ?><br>
        <b>Visit #:</b> <?php echo e($invoice->visit->visit_number ?? '-'); ?>

    </div>
    <div class="section">
        <table>
            <tr>
                <th>Description</th>
                <th class="right">Amount</th>
            </tr>
            <tr>
                <td>Lab Tests & Services</td>
                <td class="right">$<?php echo e(number_format($invoice->total_amount, 2)); ?></td>
            </tr>
            <tr>
                <td>Discount</td>
                <td class="right">-$<?php echo e(number_format($invoice->discount_amount, 2)); ?></td>
            </tr>
            <tr>
                <td>Tax</td>
                <td class="right">$<?php echo e(number_format($invoice->tax_amount, 2)); ?></td>
            </tr>
            <tr>
                <th>Total</th>
                <th class="right">$<?php echo e(number_format($invoice->total_amount - $invoice->discount_amount + $invoice->tax_amount, 2)); ?></th>
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
            <?php $__currentLoopData = $invoice->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><?php echo e($payment->paid_at ? $payment->paid_at->format('Y-m-d H:i') : '-'); ?></td>
                <td><?php echo e(ucfirst($payment->payment_method)); ?></td>
                <td class="right">$<?php echo e(number_format($payment->amount, 2)); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </table>
        <div><b>Amount Paid:</b> $<?php echo e(number_format($invoice->amount_paid, 2)); ?></div>
        <div><b>Balance Due:</b> $<?php echo e(number_format($invoice->balance, 2)); ?></div>
    </div>
    <div class="section" style="margin-top: 40px; color: #888; font-size: 0.95rem;">
        Thank you for choosing our laboratory.
    </div>
</body>
</html> <?php /**PATH C:\Users\S7so1\Desktop\lab\backend\resources\views/invoices/pdf.blade.php ENDPATH**/ ?>