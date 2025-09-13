<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pathology Report - {{ $visit->labRequest->lab_no ?? $visit->visit_number }}</title>
    <style>
        @page {
            margin: 2.0in 0.5in 0.8in 0.5in;
        }
        
        body {
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            padding: 0;
            line-height: 1.4;
            background-color: #fafafa;
        }
        
        
        /* Report Title */
        .report-title {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            margin: 15px 0 15px 0;
            color: #1e3a8a;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
            letter-spacing: 1px;
        }
        
        /* Patient Information Table */
        .patient-info {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
            background-color: white;
            border-radius: 6px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .patient-info td {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            font-size: 11px;
        }
        
        .patient-info .label {
            font-weight: bold;
            width: 25%;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .patient-info .value {
            background-color: white;
        }
        
        /* Section Styles */
        .section-title {
            font-weight: bold;
            font-size: 13px;
            margin: 12px 0 6px 0;
            color: #1e3a8a;
            border-left: 3px solid #1e3a8a;
            padding-left: 8px;
            background-color: #f8f9ff;
            padding: 6px 8px;
            border-radius: 3px;
        }
        
        .section-content {
            margin-bottom: 12px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            min-height: 35px;
            text-align: left;
            line-height: 1.5;
        }
        
        .arabic-text {
            direction: rtl;
            text-align: right;
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
            unicode-bidi: bidi-override;
        }
        
        .diagnosis-section {
            border: 2px solid #dc2626;
            background-color: #fef2f2;
            font-weight: bold;
            font-size: 12px;
            color: #dc2626;
        }
        
        
        
        /* Signature Section */
        .signature-section {
            margin-top: 20px;
            text-align: right;
            padding: 15px 30px 15px 15px;
            background-color: white;
            border-radius: 6px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
            border-top: 2px solid #1e3a8a;
        }
        
        .signature-name {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 5px;
            color: #1e3a8a;
        }
        
        .signature-title {
            font-size: 11px;
            margin-bottom: 8px;
            color: #666;
        }
        
        .signature-date {
            font-size: 10px;
            color: #888;
        }
        
    </style>
</head>
<body>


    <!-- Report Title -->
    <div class="report-title">PATHOLOGY REPORT</div>

    <!-- Patient Information -->
    <table class="patient-info">
        <tr>
            <td class="label">Patient's Name:</td>
            <td class="value arabic-text">{{ $visit->patient->name }}</td>
            <td class="label">Age:</td>
            <td class="value">{{ $visit->patient->birth_date ? $visit->patient->birth_date->age . 'y' : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Sex:</td>
            <td class="value">{{ ucfirst($visit->patient->gender) }}</td>
            <td class="label">Date:</td>
            <td class="value">{{ \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d') }}</td>
        </tr>
        <tr>
            <td class="label">Referred Doctor:</td>
            <td class="value arabic-text">{{ $visit->referred_doctor ?? 'N/A' }}</td>
            <td class="label">Lab no:</td>
            <td class="value">{{ $visit->labRequest->lab_no ?? ($visit->lab_request_id ? 'Loading...' : 'N/A') }}</td>
        </tr>
    </table>


    <!-- Clinical Data -->
    @if($visit->clinical_data)
    <div class="section-title">Clinical Data:</div>
    <div class="section-content">{{ $visit->clinical_data }}</div>
    @endif

    <!-- Nature of Specimen -->
    @if($visit->visitTests->count() > 0)
    <div class="section-title">Nature of specimen:</div>
    <div class="section-content">
        @foreach($visit->visitTests as $test)
            {{ $test->labTest->name ?? 'Lab Test' }}@if(!$loop->last), @endif
        @endforeach
    </div>
    @endif

    <!-- Gross Pathology -->
    @if($visit->microscopic_description)
    <div class="section-title">Gross Pathology:</div>
    <div class="section-content">{{ $visit->microscopic_description }}</div>
    @endif

    <!-- Microscopic Examination -->
    @if($visit->microscopic_description)
    <div class="section-title">Microscopic examination:</div>
    <div class="section-content">{{ $visit->microscopic_description }}</div>
    @endif

    <!-- Diagnosis -->
    @if($visit->diagnosis)
    <div class="section-title">DIAGNOSIS:</div>
    <div class="section-content diagnosis-section">{{ $visit->diagnosis }}</div>
    @endif

    <!-- Recommendations & Notes -->
    @if($visit->recommendations)
    <div class="section-title">Recommendations & Notes:</div>
    <div class="section-content">{{ $visit->recommendations }}</div>
    @endif

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-name">Dr. Yasser M. El Dowik</div>
        <div class="signature-title">Ass. Professor of Histopathology</div>
        <div class="signature-date">Date: {{ \Carbon\Carbon::now()->format('Y-m-d') }}</div>
    </div>

</body>
</html>
