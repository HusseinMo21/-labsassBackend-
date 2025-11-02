<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pathology Report - {{ $visit->labRequest->full_lab_no ?? $visit->visit_number }}</title>
    <style>
        @page {
            margin: 0;
        }
        
        body {
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
            padding: 0;
            margin: 0;
            line-height: 1.3;
            font-size: 12px;
            position: relative;
        }
        
        .main-content {
            position: relative;
            z-index: 1;
            margin: 0;
            padding: 0;
            height: 100vh;
        }
        
        .content-container {
            position: absolute;
            top: 120px;
            left: 40px;
            right: 40px;
            bottom: 150px;
            background: transparent;
            border: none;
            border-radius: 5px;
            padding: 20px;
            padding-top: 180px;
            overflow: hidden;
            box-sizing: border-box;
        }
        
        /* Report Title */
        .report-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin: 5px 0 10px 0;
            color: #1e3a8a;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
            letter-spacing: 1px;
            background-color: rgba(255, 255, 255, 0.9);
            border: 2px solid #333;
            border-radius: 4px;
            padding: 6px;
        }
        
        /* Patient Information Table */
        .patient-info {
            width: 100%;
            margin-bottom: 8px;
            border-collapse: collapse;
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #333;
        }
        
        .patient-info td {
            padding: 3px 6px;
            border: 1px solid #333;
            font-size: 10px;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        .patient-info .label {
            font-weight: bold;
            width: 25%;
            background-color: transparent;
            color: #333;
        }
        
        .patient-info .value {
            background-color: transparent;
        }
        
        /* Section Styles */
        .section-title {
            font-weight: bold;
            font-size: 11px;
            margin: 6px 0 3px 0;
            color: #1e3a8a;
            border-left: 2px solid #1e3a8a;
            padding: 3px 6px;
            background-color: transparent;
            border: 1px solid #333;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        .section-content {
            margin-bottom: 6px;
            padding: 6px 8px;
            border: 1px solid #333;
            background-color: rgba(255, 255, 255, 0.9);
            min-height: 20px;
            text-align: left;
            line-height: 1.3;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
            font-size: 10px;
        }
        
        .arabic-text {
            direction: rtl;
            text-align: right;
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
            unicode-bidi: bidi-override;
        }
        
        .diagnosis-section {
            border: 2px solid #dc2626;
            background-color: rgba(255, 255, 255, 0.9);
            font-weight: bold;
            font-size: 12px;
            color: #dc2626;
        }
        
        /* Signature Section */
        .signature-section {
            margin-top: 8px;
            text-align: right;
            padding: 6px;
            background-color: rgba(255, 255, 255, 0.9);
            border-top: 1px solid #333;
        }
        
        .signature-name {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 1px;
            color: #1e3a8a;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        .signature-title {
            font-size: 9px;
            margin-bottom: 1px;
            color: #666;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        .signature-date {
            font-size: 8px;
            color: #888;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        /* Print Specific Styles */
        @media print {
            .main-content {
                position: relative !important;
                z-index: 1 !important;
                height: 100vh !important;
            }
            .content-container {
                position: absolute !important;
                top: 120px !important;
                left: 40px !important;
                right: 40px !important;
                bottom: 150px !important;
                padding-top: 180px !important;
                background: transparent !important;
                border: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="content-container">
            <div class="report-title">PATHOLOGY REPORT</div>

            <!-- Patient Information -->
            <table class="patient-info">
        <tr>
            <td class="label">Patient's Name:</td>
            <td class="value arabic-text">{{ $visit->patient->name ?? 'N/A' }}</td>
            <td class="label">Age:</td>
            <td class="value">{{ $visit->patient->age ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Sex:</td>
            <td class="value">{{ ucfirst($visit->patient->gender ?? 'N/A') }}</td>
            <td class="label">Date:</td>
            <td class="value">{{ $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d') : 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Referred Doctor:</td>
            <td class="value arabic-text">{{ $visit->patient->doctor_id ?? $visit->referred_doctor ?? 'N/A' }}</td>
            <td class="label">Lab No:</td>
            <td class="value">{{ $visit->labRequest->full_lab_no ?? $visit->lab_number ?? $visit->visit_number ?? 'N/A' }}</td>
        </tr>
    </table>

    @php
        $reportContent = null;
        if ($visit->labRequest && $visit->labRequest->reports && $visit->labRequest->reports->count() > 0) {
            // Get the latest completed report, or fall back to the latest report
            $report = $visit->labRequest->reports->where('status', 'completed')->sortByDesc('id')->first() 
                     ?? $visit->labRequest->reports->sortByDesc('id')->first();
            $reportContent = json_decode($report->content, true);
        }
    @endphp

    <!-- Clinical Data -->
    @if($reportContent && isset($reportContent['clinical_data']))
    <div class="section-title">Clinical Data:</div>
    <div class="section-content">{{ $reportContent['clinical_data'] }}</div>
    @endif

    <!-- Nature of Specimen -->
    @if($reportContent && isset($reportContent['nature_of_specimen']))
    <div class="section-title">Nature of specimen:</div>
    <div class="section-content">{{ $reportContent['nature_of_specimen'] }}</div>
    @elseif($visit->visitTests && $visit->visitTests->count() > 0)
    <div class="section-title">Nature of specimen:</div>
    <div class="section-content">
        @foreach($visit->visitTests as $test)
            {{ $test->labTest->name ?? 'Lab Test' }}@if(!$loop->last), @endif
        @endforeach
    </div>
    @endif

    <!-- Gross Pathology -->
    @if($reportContent && isset($reportContent['gross_pathology']))
    <div class="section-title">Gross Pathology:</div>
    <div class="section-content">{{ $reportContent['gross_pathology'] }}</div>
    @endif

    <!-- Microscopic Examination -->
    @if($reportContent && isset($reportContent['microscopic_examination']))
    <div class="section-title">Microscopic examination:</div>
    <div class="section-content">{{ $reportContent['microscopic_examination'] }}</div>
    @endif

    <!-- Diagnosis -->
    @if($reportContent && isset($reportContent['conclusion']))
    <div class="section-title">DIAGNOSIS:</div>
    <div class="section-content diagnosis-section">{{ $reportContent['conclusion'] }}</div>
    @endif

    <!-- Recommendations & Notes -->
    @if($reportContent && isset($reportContent['recommendations']))
    <div class="section-title">Recommendations & Notes:</div>
    <div class="section-content">{{ $reportContent['recommendations'] }}</div>
    @endif

    <!-- Pathology Image -->
    @php
        $imagePath = null;
        $imageBase64 = null;
        $imageMimeType = 'image/jpeg';
        if ($visit->labRequest && $visit->labRequest->reports && $visit->labRequest->reports->count() > 0) {
            $report = $visit->labRequest->reports->where('status', 'completed')->sortByDesc('id')->first() 
                     ?? $visit->labRequest->reports->sortByDesc('id')->first();
            if ($report && $report->image_path) {
                $imagePath = storage_path('app/public/' . $report->image_path);
                if (file_exists($imagePath)) {
                    $imageData = file_get_contents($imagePath);
                    $imageBase64 = base64_encode($imageData);
                    $imageMimeType = $report->image_mime_type ?? 'image/jpeg';
                }
            }
        }
    @endphp
    
    @if($imageBase64)
    <div class="section-title" style="margin-top: 30px;">PATHOLOGY IMAGE:</div>
    <div class="section-content" style="text-align: center; margin: 20px 0;">
        <img src="data:{{ $imageMimeType }};base64,{{ $imageBase64 }}" 
             alt="Pathology Image" 
             style="max-width: 100%; height: auto; max-height: 600px; border: 1px solid #ddd; border-radius: 4px;" />
    </div>
    @endif

            <!-- Signature Section -->
            <div class="signature-section">
                <div class="signature-name">Dr. Yasser M. El Dowik</div>
                <div class="signature-title">Ass. Professor of Histopathology</div>
                <div class="signature-date">Date: {{ \Carbon\Carbon::now()->format('Y-m-d') }}</div>
            </div>
        </div>
    </div>
</body>
</html>
