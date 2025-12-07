<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pathology Report - {{ $visit->labRequest->full_lab_no ?? $visit->visit_number }}</title>
    <style>
        @page {
            margin: 0;
            @if(!empty($backgroundImage))
            background-image: url('data:image/jpeg;base64,{{ $backgroundImage }}');
            background-image-resize: 6;
            @endif
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
        }
        
        .content-container {
            position: relative;
            margin: 0;
            padding: 20px 40px;
            padding-top: 180px;
            padding-bottom: 150px;
            background: transparent;
            border: none;
            border-radius: 5px;
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
            border-spacing: 0;
            background-color: transparent;
        }
        
        .patient-info td {
            padding: 6px 0;
            border: none;
            font-size: 13px;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
            vertical-align: top;
            white-space: nowrap;
        }
        
        .patient-info .field {
            display: inline-block;
            white-space: nowrap;
        }
        
        .patient-info .label {
            font-weight: bold;
            font-size: 14px;
            background-color: transparent;
            color: #333;
            display: inline;
            padding-right: 2px;
        }
        
        .patient-info .value {
            background-color: transparent;
            color: #333;
            font-size: 13px;
            font-weight: bold;
            display: inline;
            padding-left: 0;
        }
        
        /* Barcode Styling */
        .barcode-container {
            text-align: center;
            padding: 5px;
        }
        
        .barcode-image {
            max-width: 200px;
            height: auto;
            display: block;
            margin: 0 auto 5px auto;
        }
        
        .barcode-text {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            font-weight: bold;
            color: #333;
            text-align: center;
        }
        
        /* Section Styles */
        .section-title {
            font-weight: bold;
            font-size: 18px;
            margin: 8px 0 4px 0;
            color: #333;
            text-decoration: none;
            padding: 8px 12px;
            background-color: rgba(255, 255, 255, 0.95);
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
            border: 2px solid #333;
            border-radius: 4px;
            display: inline-block;
            white-space: nowrap;
            box-sizing: content-box;
            width: fit-content;
        }
        
        .section-content {
            margin-bottom: 8px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            background-color: rgba(255, 255, 255, 0.95);
            min-height: 30px;
            text-align: left;
            line-height: 1.4;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
            font-size: 16px;
            border-radius: 4px;
            page-break-inside: auto;
            break-inside: auto;
            orphans: 2;
            widows: 2;
        }
        
        .section-title {
            page-break-after: avoid;
            break-after: avoid;
        }
        
        .arabic-text {
            direction: rtl;
            text-align: right;
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', 'Arial', sans-serif;
            unicode-bidi: bidi-override;
            font-weight: bold;
            font-size: 14px;
        }
        
        .diagnosis-section {
            border: 1px solid #ddd;
            background-color: rgba(255, 255, 255, 0.95);
            font-weight: bold;
            font-size: 16px;
            color: #333;
            border-radius: 4px;
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
    @if(!empty($backgroundImage))
    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; background-image: url('data:image/jpeg;base64,{{ $backgroundImage }}'); background-size: cover; background-position: center; background-repeat: no-repeat; pointer-events: none;"></div>
    @endif
    @php
        // Get report content and type of analysis
        $reportContent = null;
        $report = null;
        $typeOfAnalysis = 'Pathology'; // Default value
        if ($visit->labRequest && $visit->labRequest->reports && $visit->labRequest->reports->count() > 0) {
            // Get the latest completed report, or fall back to the latest report
            $report = $visit->labRequest->reports->where('status', 'completed')->sortByDesc('id')->first() 
                     ?? $visit->labRequest->reports->sortByDesc('id')->first();
            if ($report) {
                $reportContent = json_decode($report->content, true);
                $typeOfAnalysis = $reportContent['type_of_analysis'] ?? 'Pathology';
            }
        }
        // Generate report title based on type of analysis
        $reportTitle = strtoupper($typeOfAnalysis) . ' REPORT';
    @endphp
    <div class="main-content">
        <div class="content-container">
            <div class="report-title">{{ $reportTitle }}</div>

        <!-- Patient Information -->
        <table class="patient-info">
            <tr>
                <td><span class="label">Name:</span><span class="value arabic-text">{{ $visit->patient->name ?? 'N/A' }}</span></td>
                <td style="padding-left: 30px;"><span class="label">Lab No:</span><span class="value">{{ $visit->labRequest->full_lab_no ?? $visit->lab_number ?? $visit->visit_number ?? 'N/A' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Gender:</span><span class="value">{{ ucfirst($visit->patient->gender ?? 'N/A') }}</span></td>
                <td style="padding-left: 30px;"><span class="label">Attendance Date:</span><span class="value">{{ $attendance_date ?? 'N/A' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Age:</span><span class="value">{{ $visit->patient->age ?? 'N/A' }} Year</span></td>
            </tr>
            <tr>
                <td><span class="label">Referred By:</span><span class="value arabic-text">{{ $visit->patient->doctor_id ?? $visit->referred_doctor ?? 'N/A' }}</span></td>
                <td style="padding-left: 30px;"><span class="label">Barcode:</span>
                    <div class="barcode-container" style="display: inline-block; margin-left: 2px;">
                        @php
                            $barcodeValue = $visit->labRequest->full_lab_no ?? $visit->lab_number ?? $visit->visit_number ?? 'N/A';
                            $barcodeValue = str_replace(['-', ' ', '/'], '', $barcodeValue);
                            
                            // Generate barcode using picqer/php-barcode-generator
                            $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
                            $barcodeImage = $generator->getBarcode($barcodeValue, $generator::TYPE_CODE_128);
                            $barcodeBase64 = base64_encode($barcodeImage);
                        @endphp
                        <img src="data:image/png;base64,{{ $barcodeBase64 }}" alt="Barcode" class="barcode-image" />
                        <div class="barcode-text">{{ $barcodeValue }}</div>
                    </div>
                </td>
            </tr>
        </table>

        @php
            // Reuse report content if already loaded, otherwise load it
            if (!isset($reportContent)) {
                $reportContent = null;
                $report = null;
                if ($visit->labRequest && $visit->labRequest->reports && $visit->labRequest->reports->count() > 0) {
                    // Get the latest completed report, or fall back to the latest report
                    $report = $visit->labRequest->reports->where('status', 'completed')->sortByDesc('id')->first() 
                             ?? $visit->labRequest->reports->sortByDesc('id')->first();
                    if ($report) {
                        $reportContent = json_decode($report->content, true);
                    }
                }
            }
            $imagePlacement = $reportContent['image_placement'] ?? 'end_of_report';
        @endphp

        <!-- Clinical Data -->
        @php
            $showImageInClinicalData = ($imagePlacement === 'clinical_data' && isset($reportContent['image_placement']));
        @endphp
        <span class="section-title">CLINICAL DATA:</span>
        @if($showImageInClinicalData && $report && $report->image_path)
            @php
                $imagePath = storage_path('app/public/' . $report->image_path);
                $imageBase64 = null;
                $imageMimeType = 'image/jpeg';
                if (file_exists($imagePath)) {
                    $imageData = file_get_contents($imagePath);
                    $imageBase64 = base64_encode($imageData);
                    $imageMimeType = $report->image_mime_type ?? 'image/jpeg';
                }
            @endphp
            @if($imageBase64)
            <div class="section-content" style="text-align: center; margin: 20px 0;">
                <img src="data:{{ $imageMimeType }};base64,{{ $imageBase64 }}" 
                     alt="Clinical Data Image" 
                     style="max-width: 100%; height: auto; max-height: 600px; border: 1px solid #ddd; border-radius: 4px;" />
            </div>
            @else
            <div class="section-content">{{ $reportContent['clinical_data'] ?? '---' }}</div>
            @endif
        @else
        <div class="section-content">{{ $reportContent['clinical_data'] ?? '---' }}</div>
        @endif

        <!-- Nature of Specimen -->
        @php
            $showImageInNatureOfSpecimen = ($imagePlacement === 'nature_of_specimen' && isset($reportContent['image_placement']));
        @endphp
        <span class="section-title">NATURE OF SPECIMENS:</span>
        @if($showImageInNatureOfSpecimen && $report && $report->image_path)
            @php
                $imagePath = storage_path('app/public/' . $report->image_path);
                $imageBase64 = null;
                $imageMimeType = 'image/jpeg';
                if (file_exists($imagePath)) {
                    $imageData = file_get_contents($imagePath);
                    $imageBase64 = base64_encode($imageData);
                    $imageMimeType = $report->image_mime_type ?? 'image/jpeg';
                }
            @endphp
            @if($imageBase64)
            <div class="section-content" style="text-align: center; margin: 20px 0;">
                <img src="data:{{ $imageMimeType }};base64,{{ $imageBase64 }}" 
                     alt="Nature of Specimen Image" 
                     style="max-width: 100%; height: auto; max-height: 600px; border: 1px solid #ddd; border-radius: 4px;" />
            </div>
            @else
            <div class="section-content">
                @if($reportContent && isset($reportContent['nature_of_specimen']) && $reportContent['nature_of_specimen'])
                    {{ $reportContent['nature_of_specimen'] }}
                @elseif($visit->visitTests && $visit->visitTests->count() > 0)
                    @foreach($visit->visitTests as $test)
                        {{ $test->labTest->name ?? 'Lab Test' }}@if(!$loop->last), @endif
                    @endforeach
                @else
                    ---
                @endif
            </div>
            @endif
        @else
        <div class="section-content">
            @if($reportContent && isset($reportContent['nature_of_specimen']) && $reportContent['nature_of_specimen'])
                {{ $reportContent['nature_of_specimen'] }}
            @elseif($visit->visitTests && $visit->visitTests->count() > 0)
                @foreach($visit->visitTests as $test)
                    {{ $test->labTest->name ?? 'Lab Test' }}@if(!$loop->last), @endif
                @endforeach
            @else
                ---
            @endif
        </div>
        @endif

        <!-- Gross Pathology -->
        @php
            $showImageInGrossPathology = ($imagePlacement === 'gross_pathology' && isset($reportContent['image_placement']));
        @endphp
        <span class="section-title">GROSS EXAMINATION:</span>
        @if($showImageInGrossPathology && $report && $report->image_path)
            @php
                $imagePath = storage_path('app/public/' . $report->image_path);
                $imageBase64 = null;
                $imageMimeType = 'image/jpeg';
                if (file_exists($imagePath)) {
                    $imageData = file_get_contents($imagePath);
                    $imageBase64 = base64_encode($imageData);
                    $imageMimeType = $report->image_mime_type ?? 'image/jpeg';
                }
            @endphp
            @if($imageBase64)
            <div class="section-content" style="text-align: center; margin: 20px 0;">
                <img src="data:{{ $imageMimeType }};base64,{{ $imageBase64 }}" 
                     alt="Gross Pathology Image" 
                     style="max-width: 100%; height: auto; max-height: 600px; border: 1px solid #ddd; border-radius: 4px;" />
            </div>
            @else
            <div class="section-content">{{ $reportContent['gross_pathology'] ?? '---' }}</div>
            @endif
        @else
        <div class="section-content">{{ $reportContent['gross_pathology'] ?? '---' }}</div>
        @endif

        <!-- Microscopic Examination -->
        @php
            $showImageInMicroscopic = ($imagePlacement === 'microscopic_examination' && isset($reportContent['image_placement']));
        @endphp
        <span class="section-title">MICROSCOPIC EXAMINATION:</span>
        @if($showImageInMicroscopic && $report && $report->image_path)
            @php
                $imagePath = storage_path('app/public/' . $report->image_path);
                $imageBase64 = null;
                $imageMimeType = 'image/jpeg';
                if (file_exists($imagePath)) {
                    $imageData = file_get_contents($imagePath);
                    $imageBase64 = base64_encode($imageData);
                    $imageMimeType = $report->image_mime_type ?? 'image/jpeg';
                }
            @endphp
            @if($imageBase64)
            <div class="section-content" style="text-align: center; margin: 20px 0;">
                <img src="data:{{ $imageMimeType }};base64,{{ $imageBase64 }}" 
                     alt="Microscopic Examination Image" 
                     style="max-width: 100%; height: auto; max-height: 600px; border: 1px solid #ddd; border-radius: 4px;" />
            </div>
            @else
            <div class="section-content">{{ $reportContent['microscopic_examination'] ?? '---' }}</div>
            @endif
        @else
        <div class="section-content">{{ $reportContent['microscopic_examination'] ?? '---' }}</div>
        @endif

        <!-- Diagnosis -->
        @php
            $showImageInConclusion = ($imagePlacement === 'conclusion' && isset($reportContent['image_placement']));
        @endphp
        <span class="section-title">DIAGNOSIS:</span>
        @if($showImageInConclusion && $report && $report->image_path)
            @php
                $imagePath = storage_path('app/public/' . $report->image_path);
                $imageBase64 = null;
                $imageMimeType = 'image/jpeg';
                if (file_exists($imagePath)) {
                    $imageData = file_get_contents($imagePath);
                    $imageBase64 = base64_encode($imageData);
                    $imageMimeType = $report->image_mime_type ?? 'image/jpeg';
                }
            @endphp
            @if($imageBase64)
            <div class="section-content" style="text-align: center; margin: 20px 0;">
                <img src="data:{{ $imageMimeType }};base64,{{ $imageBase64 }}" 
                     alt="Conclusion Image" 
                     style="max-width: 100%; height: auto; max-height: 600px; border: 1px solid #ddd; border-radius: 4px;" />
            </div>
            @else
            <div class="section-content diagnosis-section">{{ $reportContent['conclusion'] ?? '---' }}</div>
            @endif
        @else
        <div class="section-content diagnosis-section">{{ $reportContent['conclusion'] ?? '---' }}</div>
        @endif

        <!-- Recommendations & Notes -->
        <span class="section-title">RECOMMENDATIONS & NOTES:</span>
        <div class="section-content">{{ $reportContent['recommendations'] ?? '---' }}</div>

        <!-- Pathology Image (only if placement is end_of_report) -->
        @php
            $showImageAtEnd = ($imagePlacement === 'end_of_report' || !isset($reportContent['image_placement']));
            $imagePath = null;
            $imageBase64 = null;
            $imageMimeType = 'image/jpeg';
            if ($showImageAtEnd && $visit->labRequest && $visit->labRequest->reports && $visit->labRequest->reports->count() > 0) {
                if (!isset($report)) {
                    $report = $visit->labRequest->reports->where('status', 'completed')->sortByDesc('id')->first() 
                             ?? $visit->labRequest->reports->sortByDesc('id')->first();
                }
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
        
        @if($showImageAtEnd && $imageBase64)
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
