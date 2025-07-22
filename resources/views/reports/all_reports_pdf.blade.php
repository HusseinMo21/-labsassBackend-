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
    <h2>All Reports for {{ $patient->name }}</h2>
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
                <td>{{ $report['test_name'] }}</td>
                <td>{{ $report['visit_date'] }}</td>
                <td>{{ $report['result_value'] ?? '-' }}</td>
                <td>{{ $report['status'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html> 