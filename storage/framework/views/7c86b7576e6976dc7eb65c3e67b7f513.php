<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pathology Report - <?php echo e($visit->visit_number); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #007bff;
            padding: 20px 30px 10px 30px;
        }
        .header .lab-info {
            flex: 1;
        }
        .header .logo {
            width: 120px;
            height: 120px;
            object-fit: contain;
        }
        .report-title {
            text-align: center;
            font-size: 2rem;
            font-weight: bold;
            margin: 20px 0 10px 0;
            letter-spacing: 1px;
        }
        .patient-table {
            width: 90%;
            margin: 0 auto 20px auto;
            border-collapse: collapse;
        }
        .patient-table td {
            padding: 6px 12px;
            border: none;
            font-size: 1rem;
        }
        .section-label {
            font-weight: bold;
            margin-top: 18px;
            margin-bottom: 6px;
            font-size: 1.08rem;
        }
        .section-box {
            border: 1.5px solid #007bff;
            border-radius: 6px;
            padding: 12px 16px;
            margin: 10px 0 18px 0;
            background: #f8faff;
        }
        .diagnosis-box {
            border: 2px solid #dc3545;
            background: #fff3f3;
            border-radius: 8px;
            padding: 16px 20px;
            margin: 18px 0 10px 0;
            font-size: 1.15rem;
            font-weight: bold;
        }
        .signature-block {
            margin-top: 40px;
            text-align: right;
            padding-right: 60px;
        }
        .signature {
            margin-top: 40px;
            font-size: 1rem;
        }
        .barcode-block {
            margin-top: 30px;
            text-align: center;
        }
        .credentials {
            margin-top: 10px;
            font-size: 1rem;
            text-align: center;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #888;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="lab-info">
            <div style="font-size:1.2rem;font-weight:bold;">MEDICAL LABORATORY</div>
            <div style="margin-top:4px;">Dr. Yasser M. ElDowik</div>
            <div style="font-size:0.98rem;">Consultant of Pathology</div>
            <div style="font-size:0.95rem;">123 Healthcare Street, Medical City</div>
            <div style="font-size:0.95rem;">Phone: (555) 123-4567 | Email: info@medlab.com</div>
        </div>
        <div>
            
            <img src="<?php echo e(public_path('logo.png')); ?>" alt="Lab Logo" class="logo">
        </div>
    </div>
    <div class="report-title">Pathology Report</div>

    <table class="patient-table">
        <tr>
            <td><strong>Patient Name:</strong></td>
            <td><?php echo e($visit->patient->name); ?></td>
            <td><strong>Age:</strong></td>
            <td><?php echo e($visit->patient->birth_date ? $visit->patient->birth_date->age : '-'); ?></td>
        </tr>
        <tr>
            <td><strong>Sex:</strong></td>
            <td><?php echo e(ucfirst($visit->patient->gender)); ?></td>
            <td><strong>Date:</strong></td>
            <td><?php echo e(date('M d, Y', strtotime($visit->visit_date))); ?></td>
        </tr>
        <tr>
            <td><strong>Lab Number:</strong></td>
            <td><?php echo e($visit->visit_number); ?></td>
            <td><strong>Referred Doctor:</strong></td>
            <td><?php echo e($visit->referred_doctor ?? 'N/A'); ?></td>
        </tr>
    </table>

    <div class="section-label">Clinical Data</div>
    <div class="section-box">
        <?php echo e($visit->clinical_data ?? '---'); ?>

    </div>

    <div class="section-label">Microscopic Description</div>
    <div class="section-box">
        <?php echo e($visit->microscopic_description ?? '---'); ?>

    </div>

    <div class="section-label">Diagnosis</div>
    <div class="diagnosis-box">
        <?php echo e($visit->diagnosis ?? '---'); ?>

    </div>

    <div class="section-label">Recommendations / Notes</div>
    <div class="section-box">
        <?php echo e($visit->recommendations ?? $visit->notes ?? '---'); ?>

    </div>

    <div class="barcode-block">
        <?php if(isset($barcode_base64)): ?>
            <img src="data:image/png;base64,<?php echo e($barcode_base64); ?>" alt="Sample Barcode" style="max-width:320px;" />
        <?php else: ?>
            
        <?php endif; ?>
        <div class="credentials">
            <strong>Patient Portal Login:</strong><br>
            Username: <?php echo e($visit->patient->user->name ?? 'N/A'); ?><br>
            Password: (Provided at registration)
        </div>
    </div>

    <div class="signature-block">
        <div class="signature">
            <strong>Dr. Yasser M. ElDowik</strong><br>
            Consultant of Pathology<br>
            <span style="font-size:0.95rem;">Date: <?php echo e(date('M d, Y')); ?></span>
        </div>
    </div>

    <div class="footer">
        <p>This report contains confidential medical information. For questions, contact us at (555) 123-4567</p>
        <p>Report generated on <?php echo e(date('M d, Y \a\t h:i A')); ?></p>
    </div>
</body>
</html> <?php /**PATH C:\Users\S7so1\Desktop\lab\backend\resources\views\reports\pathology_report.blade.php ENDPATH**/ ?>