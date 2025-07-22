<html>
<head>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        h2 { color: #1976d2; }
        .section { margin-bottom: 18px; }
        .label { font-weight: bold; }
    </style>
</head>
<body>
    <h2>Lab Test Report</h2>
    <div class="section">
        <span class="label">Patient Name:</span> <?php echo e($test->visit->patient->name); ?><br>
        <span class="label">Patient ID:</span> <?php echo e($test->visit->patient->id); ?><br>
        <span class="label">Gender:</span> <?php echo e($test->visit->patient->gender); ?><br>
        <span class="label">Phone:</span> <?php echo e($test->visit->patient->phone); ?><br>
    </div>
    <div class="section">
        <span class="label">Test Name:</span> <?php echo e($test->labTest->name); ?><br>
        <span class="label">Visit Date:</span> <?php echo e($test->visit->visit_date); ?><br>
        <span class="label">Result:</span> <?php echo e($test->result_value ?? '-'); ?><br>
        <span class="label">Result Status:</span> <?php echo e($test->result_status ?? '-'); ?><br>
        <span class="label">Status:</span> <?php echo e($test->status); ?><br>
    </div>
</body>
</html> <?php /**PATH C:\Users\S7so1\Desktop\lab\backend\resources\views\reports\single_report_pdf.blade.php ENDPATH**/ ?>