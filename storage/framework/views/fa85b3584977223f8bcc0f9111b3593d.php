<html>
<head>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        h2 { color: #1976d2; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h2>All Reports for <?php echo e($patient->name); ?></h2>
    <div><b>Patient ID:</b> <?php echo e($patient->id); ?></div>
    <div><b>Gender:</b> <?php echo e($patient->gender); ?></div>
    <div><b>Phone:</b> <?php echo e($patient->phone); ?></div>
    <div><b>Total Visits:</b> <?php echo e($patient->visits->count()); ?></div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Test Name</th>
                <th>Visit Date</th>
                <th>Result</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $reports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><?php echo e($i + 1); ?></td>
                <td><?php echo e($report['test_name']); ?></td>
                <td><?php echo e($report['visit_date']); ?></td>
                <td><?php echo e($report['result_value'] ?? '-'); ?></td>
                <td><?php echo e($report['status']); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</body>
</html> <?php /**PATH C:\Users\S7so1\Desktop\lab\backend\resources\views\reports\all_reports_pdf.blade.php ENDPATH**/ ?>