<html>
<head>
    <style>
        body { 
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif; 
            font-size: 12px; 
            direction: ltr;
        }
        .arabic-text {
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
            direction: rtl;
            text-align: right;
            unicode-bidi: bidi-override;
        }
        h1 { color: #1976d2; text-align: center; margin-bottom: 30px; }
        h2 { color: #1976d2; border-bottom: 2px solid #1976d2; padding-bottom: 5px; }
        .section { margin-bottom: 20px; }
        .label { font-weight: bold; color: #333; }
        .value { margin-left: 10px; }
        .arabic-value { 
            margin-right: 10px; 
            direction: rtl; 
            text-align: right;
            unicode-bidi: bidi-override;
        }
        .patient-info { background-color: #f5f5f5; padding: 15px; border-radius: 5px; }
        .test-info { background-color: #e3f2fd; padding: 15px; border-radius: 5px; }
        .report-section { margin: 20px 0; }
        .report-content { margin-top: 10px; line-height: 1.6; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #666; }
        .visit-id { font-size: 14px; font-weight: bold; color: #1976d2; }
    </style>
</head>
<body>
    <h1>PATHOLOGY LABORATORY REPORT</h1>
    
    <div class="visit-id">Visit ID: {{ $test->visit->visit_id ?? 'N/A' }}</div>
    
    <div class="section patient-info">
        <h2>Patient Information</h2>
        <div class="report-content">
            <span class="label">Patient Name:</span> <span class="arabic-value">{{ $test->visit->patient->name }}</span><br>
            <span class="label">Patient ID:</span> <span class="value">{{ $test->visit->patient->id }}</span><br>
            <span class="label">Gender:</span> <span class="value">{{ $test->visit->patient->gender }}</span><br>
            <span class="label">Date of Birth:</span> <span class="value">{{ $test->visit->patient->date_of_birth ?? 'N/A' }}</span><br>
            <span class="label">Phone:</span> <span class="value">{{ $test->visit->patient->phone }}</span><br>
            <span class="label">Address:</span> <span class="value">{{ $test->visit->patient->address ?? 'N/A' }}</span><br>
        </div>
    </div>

    <div class="section test-info">
        <h2>Test Information</h2>
        <div class="report-content">
            <span class="label">Test Name:</span> <span class="arabic-value">{{ $test->labTest->name }}</span><br>
            <span class="label">Test Category:</span> <span class="arabic-value">{{ $test->labTest->category->name ?? 'N/A' }}</span><br>
            <span class="label">Visit Date:</span> <span class="value">{{ \Carbon\Carbon::parse($test->visit->visit_date)->format('d/m/Y') }}</span><br>
            <span class="label">Report Date:</span> <span class="value">{{ \Carbon\Carbon::now()->format('d/m/Y') }}</span><br>
            <span class="label">Referred Doctor:</span> <span class="value">{{ $test->visit->referred_doctor ?? 'N/A' }}</span><br>
        </div>
    </div>

    @if($test->visit->clinical_data || $test->visit->microscopic_description || $test->visit->diagnosis || $test->visit->recommendations)
    <div class="section report-section">
        <h2>Pathology Report</h2>
        
        @if($test->visit->clinical_data)
        <div class="report-content">
            <span class="label">Clinical Data:</span><br>
            <div class="arabic-text" style="margin-right: 20px; margin-top: 5px;">{{ $test->visit->clinical_data }}</div>
        </div>
        @endif

        @if($test->visit->microscopic_description)
        <div class="report-content">
            <span class="label">Microscopic Description:</span><br>
            <div class="arabic-text" style="margin-right: 20px; margin-top: 5px;">{{ $test->visit->microscopic_description }}</div>
        </div>
        @endif

        @if($test->visit->diagnosis)
        <div class="report-content">
            <span class="label">Diagnosis:</span><br>
            <div class="arabic-text" style="margin-right: 20px; margin-top: 5px; font-weight: bold; color: #d32f2f;">{{ $test->visit->diagnosis }}</div>
        </div>
        @endif

        @if($test->visit->recommendations)
        <div class="report-content">
            <span class="label">Recommendations:</span><br>
            <div class="arabic-text" style="margin-right: 20px; margin-top: 5px;">{{ $test->visit->recommendations }}</div>
        </div>
        @endif
    </div>
    @endif

    @if($test->result_value || $test->result_status)
    <div class="section report-section">
        <h2>Test Results</h2>
        <div class="report-content">
            @if($test->result_value)
            <span class="label">Result Value:</span> <span class="value">{{ $test->result_value }}</span><br>
            @endif
            @if($test->result_status)
            <span class="label">Result Status:</span> <span class="value">{{ $test->result_status }}</span><br>
            @endif
            @if($test->labTest->normal_range)
            <span class="label">Normal Range:</span> <span class="value">{{ $test->labTest->normal_range }}</span><br>
            @endif
            @if($test->labTest->unit)
            <span class="label">Unit:</span> <span class="value">{{ $test->labTest->unit }}</span><br>
            @endif
        </div>
    </div>
    @endif

    <div class="footer">
        <p>This report was generated on {{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }}</p>
        <p>For any queries, please contact the laboratory.</p>
    </div>
</body>
</html> 