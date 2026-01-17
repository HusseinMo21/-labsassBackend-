<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pathology Report - {{ $visit->labRequest->full_lab_no ?? $visit->visit_number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400..800;1,400..800&display=swap" rel="stylesheet">
    <style>
        @page {
            margin-top: 180px;
            margin-bottom: 120px;
            margin-left: 0;
            margin-right: 0;
            @if(!empty($backgroundImage))
            background-image: url('data:image/jpeg;base64,{{ $backgroundImage }}');
            background-image-resize: 6;
            @endif
        }
        
        body {
            /* Default to DejaVu Sans for ALL text - ensures Arabic works */
            font-family: 'dejavusans' !important;
            font-weight: 400;
            font-style: normal;
            padding: 0;
            margin: 0;
            line-height: 1.3;
            font-size: 12px;
            position: relative;
        }
        
        /* All text uses DejaVu Sans by default - ensures Arabic works */
        /* EB Garamond will be applied via inline styles to specific section titles only */
        
        /* Ensure patient info and labels use DejaVu Sans */
        .patient-info, .label, .value, .report-title, .section-title, body, * {
            font-family: 'dejavusans' !important;
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
            padding: 0 40px;
            padding-top: 60px;
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
            /* Explicitly use DejaVu Sans for Arabic text - MUST be first to prevent font fallback issues */
            font-family: 'dejavusans' !important;
            unicode-bidi: bidi-override;
            font-weight: bold;
            font-size: 14px;
        }
        
        /* Ensure all Arabic content uses DejaVu Sans */
        [dir="rtl"], [lang="ar"] {
            font-family: 'dejavusans' !important;
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
            }
            .content-container {
                position: relative !important;
                margin: 0 !important;
                padding: 0 40px !important;
                padding-top: 0 !important;
                padding-bottom: 0 !important;
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
                <td style="padding-left: 20px;"><span class="label">Gender:</span><span class="value">{{ ucfirst($visit->patient->gender ?? 'N/A') }}</span></td>
                <td style="padding-left: 20px;"><span class="label">Age:</span><span class="value">{{ $visit->patient->age ?? 'N/A' }} Year</span></td>
                <td style="padding-left: 20px;"><span class="label">Lab No:</span><span class="value">{{ $visit->labRequest->full_lab_no ?? $visit->lab_number ?? $visit->visit_number ?? 'N/A' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Referred By:</span><span class="value arabic-text">{{ $visit->patient->doctor_id ?? $visit->referred_doctor ?? 'N/A' }}</span></td>
                <td style="padding-left: 20px;"><span class="label">Attendance Date:</span><span class="value">{{ $attendance_date ?? 'N/A' }}</span></td>
                <td style="padding-left: 20px;"><span class="label">Barcode:</span>
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
        @php
            $clinicalData = $reportContent['clinical_data'] ?? '';
            // Parse clinical data - check if it's in structured format (numbered points)
            $clinicalDataLines = [];
            if (!empty($clinicalData)) {
                $lines = explode("\n", $clinicalData);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line)) {
                        // Check if line starts with number (e.g., "1-point" or "1- point")
                        if (preg_match('/^\d+[-.)]\s*(.+)$/', $line, $matches)) {
                            $clinicalDataLines[] = $matches[1];
                        } else {
                            // If not numbered, check if it starts with dash/bullet
                            if (preg_match('/^[-•]\s*(.+)$/', $line, $matches)) {
                                $clinicalDataLines[] = $matches[1];
                            } else {
                                // Plain line, use as is
                                $clinicalDataLines[] = $line;
                            }
                        }
                    }
                }
            }
        @endphp
        <div class="section-content">
            @if(!empty($clinicalDataLines))
                @foreach($clinicalDataLines as $index => $point)
                    <div style="margin-bottom: 8px;">{{ $index + 1 }}-{{ $point }}</div>
                @endforeach
            @else
                {{ $clinicalData ?: '---' }}
            @endif
        </div>
        @endif

        <!-- Nature of Specimen -->
        @php
            $showImageInNatureOfSpecimen = ($imagePlacement === 'nature_of_specimen' && isset($reportContent['image_placement']));
            // Helper function to parse structured data
            $parseStructuredData = function($data) {
                if (empty($data)) return [];
                $lines = explode("\n", $data);
                $parsedLines = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line)) {
                        if (preg_match('/^\d+[-.)]\s*(.+)$/', $line, $matches)) {
                            $parsedLines[] = $matches[1];
                        } elseif (preg_match('/^[-•]\s*(.+)$/', $line, $matches)) {
                            $parsedLines[] = $matches[1];
                        } else {
                            $parsedLines[] = $line;
                        }
                    }
                }
                return $parsedLines;
            };
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
            @php
                $natureData = $reportContent['nature_of_specimen'] ?? '';
                $natureLines = $parseStructuredData($natureData);
            @endphp
            <div class="section-content">
                @if(!empty($natureLines))
                    @foreach($natureLines as $index => $point)
                        <div style="margin-bottom: 8px;">{{ $index + 1 }}-{{ $point }}</div>
                    @endforeach
                @elseif($visit->visitTests && $visit->visitTests->count() > 0)
                    @foreach($visit->visitTests as $test)
                        {{ $test->labTest->name ?? 'Lab Test' }}@if(!$loop->last), @endif
                    @endforeach
                @else
                    {{ $natureData ?: '---' }}
                @endif
            </div>
            @endif
        @else
        @php
            $natureData = ($reportContent['nature_of_specimen'] ?? '');
            $natureLines = $parseStructuredData($natureData);
        @endphp
        <div class="section-content">
            @if(!empty($natureLines))
                @foreach($natureLines as $index => $point)
                    <div style="margin-bottom: 8px;">{{ $index + 1 }}-{{ $point }}</div>
                @endforeach
            @elseif($visit->visitTests && $visit->visitTests->count() > 0)
                @foreach($visit->visitTests as $test)
                    {{ $test->labTest->name ?? 'Lab Test' }}@if(!$loop->last), @endif
                @endforeach
            @else
                {{ $natureData ?: '---' }}
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
            @php
                $grossData = $reportContent['gross_pathology'] ?? '';
                $grossLines = $parseStructuredData($grossData);
            @endphp
            <div class="section-content">
                @if(!empty($grossLines))
                    @foreach($grossLines as $index => $point)
                        <div style="margin-bottom: 8px;">{{ $index + 1 }}-{{ $point }}</div>
                    @endforeach
                @else
                    {{ $grossData ?: '---' }}
                @endif
            </div>
            @endif
        @else
        @php
            $grossData = $reportContent['gross_pathology'] ?? '';
            $grossLines = $parseStructuredData($grossData);
        @endphp
        <div class="section-content">
            @if(!empty($grossLines))
                @foreach($grossLines as $index => $point)
                    <div style="margin-bottom: 8px;">{{ $index + 1 }}-{{ $point }}</div>
                @endforeach
            @else
                {{ $grossData ?: '---' }}
            @endif
        </div>
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
            @php
                $microscopicData = $reportContent['microscopic_examination'] ?? '';
                $microscopicLines = $parseStructuredData($microscopicData);
            @endphp
            <div class="section-content">
                @if(!empty($microscopicLines))
                    @foreach($microscopicLines as $index => $point)
                        <div style="margin-bottom: 8px;">{{ $index + 1 }}-{{ $point }}</div>
                    @endforeach
                @else
                    {{ $microscopicData ?: '---' }}
                @endif
            </div>
            @endif
        @else
        @php
            $microscopicData = $reportContent['microscopic_examination'] ?? '';
            $microscopicLines = $parseStructuredData($microscopicData);
        @endphp
        <div class="section-content">
            @if(!empty($microscopicLines))
                @foreach($microscopicLines as $index => $point)
                    <div style="margin-bottom: 8px;">{{ $index + 1 }}-{{ $point }}</div>
                @endforeach
            @else
                {{ $microscopicData ?: '---' }}
            @endif
        </div>
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
            @php
                $conclusionData = $reportContent['conclusion'] ?? '';
                $conclusionLines = $parseStructuredData($conclusionData);
            @endphp
            <div class="section-content diagnosis-section">
                @if(!empty($conclusionLines))
                    @foreach($conclusionLines as $index => $point)
                        <div style="margin-bottom: 8px;">{{ $index + 1 }}-{{ $point }}</div>
                    @endforeach
                @else
                    {{ $conclusionData ?: '---' }}
                @endif
            </div>
            @endif
        @else
        @php
            $conclusionData = $reportContent['conclusion'] ?? '';
            $conclusionLines = $parseStructuredData($conclusionData);
        @endphp
        <div class="section-content diagnosis-section">
            @if(!empty($conclusionLines))
                @foreach($conclusionLines as $index => $point)
                    <div style="margin-bottom: 8px;">{{ $index + 1 }}-{{ $point }}</div>
                @endforeach
            @else
                {{ $conclusionData ?: '---' }}
            @endif
        </div>
        @endif

        <!-- Recommendations & Notes -->
        @php
            $recommendationsData = $reportContent['recommendations'] ?? '';
            $recommendationsLines = $parseStructuredData($recommendationsData);
        @endphp
        <span class="section-title">RECOMMENDATIONS & NOTES:</span>
        <div class="section-content">
            @if(!empty($recommendationsLines))
                @foreach($recommendationsLines as $index => $point)
                    <div style="margin-bottom: 8px;">{{ $index + 1 }}-{{ $point }}</div>
                @endforeach
            @else
                {{ $recommendationsData ?: '---' }}
            @endif
        </div>

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
