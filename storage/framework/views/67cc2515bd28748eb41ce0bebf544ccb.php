<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lab Report - <?php echo e($visit->visit_number); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .patient-info {
            margin-bottom: 30px;
        }
        .patient-info h3 {
            color: #007bff;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .patient-details {
            display: flex;
            justify-content: space-between;
        }
        .patient-left, .patient-right {
            width: 45%;
        }
        .patient-info p {
            margin: 3px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .result-normal {
            color: #28a745;
            font-weight: bold;
        }
        .result-high {
            color: #dc3545;
            font-weight: bold;
        }
        .result-low {
            color: #ffc107;
            font-weight: bold;
        }
        .result-critical {
            color: #dc3545;
            font-weight: bold;
            background-color: #f8d7da;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
        .report-info {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .report-info h4 {
            color: #007bff;
            margin-bottom: 10px;
        }
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 200px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 30px;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>MEDICAL LABORATORY</h1>
        <p>123 Healthcare Street, Medical City, MC 12345</p>
        <p>Phone: (555) 123-4567 | Email: info@medlab.com</p>
        <p>Website: www.medlab.com</p>
    </div>

    <div class="report-info">
        <h4>LABORATORY REPORT</h4>
        <p><strong>Report Date:</strong> <?php echo e(date('M d, Y', strtotime($visit->updated_at))); ?></p>
        <p><strong>Report Time:</strong> <?php echo e(date('h:i A', strtotime($visit->updated_at))); ?></p>
        <p><strong>Reported By:</strong> <?php echo e($visit->updatedBy->name ?? 'N/A'); ?></p>
    </div>

    <div class="patient-info">
        <h3>PATIENT INFORMATION</h3>
        <div class="patient-details">
            <div class="patient-left">
                <p><strong>Name:</strong> <?php echo e($visit->patient->name); ?></p>
                <p><strong>Patient ID:</strong> #<?php echo e($visit->patient->id); ?></p>
                <p><strong>Gender:</strong> <?php echo e(ucfirst($visit->patient->gender)); ?></p>
                <p><strong>Date of Birth:</strong> <?php echo e(date('M d, Y', strtotime($visit->patient->birth_date))); ?></p>
                <?php if($visit->patient->user): ?>
                <div style="margin-top:10px; padding:10px; background:#e9f7ef; border:1px solid #b2dfdb; border-radius:5px;">
                    <strong>Patient Portal Credentials:</strong><br>
                    Username: <?php echo e($visit->patient->user->name); ?><br>
                    <span>Password: (Provided at registration)</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="patient-right">
                <p><strong>Visit Number:</strong> <?php echo e($visit->visit_number); ?></p>
                <p><strong>Visit Date:</strong> <?php echo e(date('M d, Y', strtotime($visit->visit_date))); ?></p>
                <p><strong>Phone:</strong> <?php echo e($visit->patient->phone); ?></p>
                <p><strong>Address:</strong> <?php echo e($visit->patient->address); ?></p>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Test</th>
                <th>Result</th>
                <th>Unit</th>
                <th>Reference Range</th>
                <th>Status</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $visit->visitTests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $visitTest): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td>
                    <strong><?php echo e($visitTest->labTest->name); ?></strong><br>
                    <small><?php echo e($visitTest->labTest->code); ?></small>
                </td>
                <td><?php echo e($visitTest->result_value ?? 'Pending'); ?></td>
                <td><?php echo e($visitTest->labTest->unit ?? 'N/A'); ?></td>
                <td><?php echo e($visitTest->labTest->reference_range ?? 'N/A'); ?></td>
                <td>
                    <?php if($visitTest->result_status): ?>
                        <span class="result-<?php echo e($visitTest->result_status); ?>">
                            <?php echo e(ucfirst($visitTest->result_status)); ?>

                        </span>
                    <?php else: ?>
                        <span class="text-muted">Pending</span>
                    <?php endif; ?>
                </td>
                <td><?php echo e($visitTest->notes ?? '-'); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>

    <?php if($visit->notes): ?>
    <div style="margin-top: 20px; padding: 15px; background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">
        <h4 style="color: #856404; margin-bottom: 10px;">CLINICAL NOTES</h4>
        <p style="margin: 0; color: #856404;"><?php echo e($visit->notes); ?></p>
    </div>
    <?php endif; ?>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                <strong>Laboratory Technician</strong>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                <strong>Authorized Signature</strong>
            </div>
        </div>
    </div>

    <div class="footer">
        <p><strong>This report contains confidential medical information.</strong></p>
        <p>For any questions regarding this report, please contact us at (555) 123-4567</p>
        <p>Report generated on <?php echo e(date('M d, Y \a\t h:i A')); ?></p>
    </div>
</body>
</html> <?php /**PATH C:\Users\S7so1\Desktop\lab\backend\resources\views\reports\test_results.blade.php ENDPATH**/ ?>