<html>
<head>
    <style>
        body { 
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif; 
            direction: ltr;
        }
        .arabic-text {
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
            direction: rtl;
            text-align: right;
            unicode-bidi: bidi-override;
        }
        h2 { color: #1976d2; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
        .arabic-cell {
            direction: rtl;
            text-align: right;
            unicode-bidi: bidi-override;
        }
    </style>
</head>
<body>
    <h2>All Reports for <span class="arabic-text">{{ $patient->name }}</span></h2>
    <div><b>Patient ID:</b> {{ $patient->id }}</div>
    <div><b>Gender:</b> {{ $patient->gender }}</div>
    <div><b>Phone:</b> {{ $patient->phone }}</div>
    <div><b>Total Visits:</b> {{ $patient->visits->count() }}</div>
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
            @foreach($reports as $i => $report)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="arabic-cell">{{ $report['test_name'] }}</td>
                <td>{{ $report['visit_date'] }}</td>
                <td>{{ $report['result_value'] ?? '-' }}</td>
                <td>{{ $report['status'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html> 