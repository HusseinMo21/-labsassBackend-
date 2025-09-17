<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Enhanced Laboratory Report</title>
</head>
<body style="font-family: Arial; font-size: 12px; margin: 0; padding: 0; color: black;">
    <!-- Spacer for pre-printed paper header -->
    <div style="height: 200px; width: 100%;"></div>
    
    <div style="position: absolute; top: 200px; left: 20px; right: 20px; bottom: 100px;">
        <!-- Patient Information -->
        <div style="background: #f8f9fa; border: 1px solid #e9ecef; padding: 15px; margin-bottom: 20px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 33.33%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Patient name</div>
                            <div style="font-size: 11px;">{{ $report->patient ? $report->patient->name : ($report->nos ?? 'N/A') }}</div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Age</div>
                            <div style="font-size: 11px;">{{ $report->age ?? 'N/A' }}</div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Lab no</div>
                            <div style="font-size: 11px;">{{ $report->lab_no ?? 'N/A' }}</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="width: 33.33%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Referred Doctor</div>
                            <div style="font-size: 11px;">{{ $report->reff ?? 'N/A' }}</div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Sex</div>
                            <div style="font-size: 11px;">{{ ucfirst($report->sex ?? 'N/A') }}</div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Date</div>
                            <div style="font-size: 11px;">{{ $report->report_date ? \Carbon\Carbon::parse($report->report_date)->format('d/m/Y') : \Carbon\Carbon::now()->format('d/m/Y') }}</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Clinical Information -->
        <div style="margin-bottom: 12px;">
            <div style="background: #3498db; color: white; padding: 8px 12px; font-size: 11px; font-weight: bold; text-transform: uppercase;">Clinical Information</div>
            <div style="border: 1px solid #e9ecef; border-top: none; padding: 10px; background: #ffffff;">
                <div style="font-size: 11px; line-height: 1.4;">{{ $report->clinical ?? 'Clinical information not provided' }}</div>
            </div>
        </div>
        
        <!-- Specimen Information -->
        <div style="margin-bottom: 12px;">
            <div style="background: #3498db; color: white; padding: 8px 12px; font-size: 11px; font-weight: bold; text-transform: uppercase;">Specimen Information</div>
            <div style="border: 1px solid #e9ecef; border-top: none; padding: 10px; background: #ffffff;">
                <div style="font-size: 11px; line-height: 1.4;">{{ $report->nature ?? 'Specimen details not provided' }}</div>
            </div>
        </div>
        
        <!-- Gross Examination -->
        <div style="margin-bottom: 12px;">
            <div style="background: #3498db; color: white; padding: 8px 12px; font-size: 11px; font-weight: bold; text-transform: uppercase;">Gross Examination</div>
            <div style="border: 1px solid #e9ecef; border-top: none; padding: 10px; background: #ffffff;">
                <div style="font-size: 11px; line-height: 1.4;">{{ $report->gross ?? 'Gross examination details not provided' }}</div>
            </div>
        </div>
        
        <!-- Microscopic Examination -->
        <div style="margin-bottom: 12px;">
            <div style="background: #3498db; color: white; padding: 8px 12px; font-size: 11px; font-weight: bold; text-transform: uppercase;">Microscopic Examination</div>
            <div style="border: 1px solid #e9ecef; border-top: none; padding: 10px; background: #ffffff;">
                <div style="font-size: 11px; line-height: 1.4;">{{ $report->micro ?? 'Microscopic examination details not provided' }}</div>
            </div>
        </div>
        
        <!-- Diagnosis -->
        <div style="margin-bottom: 12px;">
            <div style="background: #e74c3c; color: white; padding: 8px 12px; font-size: 11px; font-weight: bold; text-transform: uppercase;">Diagnosis</div>
            <div style="border: 1px solid #e9ecef; border-top: none; padding: 10px; background: #fff5f5;">
                <div style="font-size: 11px; line-height: 1.4; font-weight: bold; color: #c0392b;">{{ $report->conc ?? 'Diagnosis pending' }}</div>
            </div>
        </div>
        
        <!-- Recommendations -->
        <div style="margin-bottom: 12px;">
            <div style="background: #3498db; color: white; padding: 8px 12px; font-size: 11px; font-weight: bold; text-transform: uppercase;">Recommendations</div>
            <div style="border: 1px solid #e9ecef; border-top: none; padding: 10px; background: #ffffff;">
                <div style="font-size: 11px; line-height: 1.4;">{{ $report->reco ?? 'Recommendations pending' }}</div>
            </div>
        </div>
        
        <!-- Quality Control -->
        @if($report->qc_checks && is_array($report->qc_checks))
        <div style="margin-bottom: 12px;">
            <div style="background: #3498db; color: white; padding: 8px 12px; font-size: 11px; font-weight: bold; text-transform: uppercase;">Quality Control</div>
            <div style="border: 1px solid #e9ecef; border-top: none; padding: 10px; background: #ffffff;">
                <table style="width: 100%; border-collapse: collapse; margin-top: 8px;">
                    <thead>
                        <tr>
                            <th style="border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 10px; background: #f8f9fa; font-weight: bold;">Parameter</th>
                            <th style="border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 10px; background: #f8f9fa; font-weight: bold;">Result</th>
                            <th style="border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 10px; background: #f8f9fa; font-weight: bold;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report->qc_checks as $check => $value)
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 10px;">{{ ucfirst(str_replace('_', ' ', $check)) }}</td>
                            <td style="border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 10px;">{{ $value }}</td>
                            <td style="border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 10px;">
                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; background: #27ae60;"></span>
                                Pass
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
    
    @if($report->hasImage())
    <!-- Page Break for Image -->
    <pagebreak />
    
    <!-- Image Page -->
    <div style="position: absolute; top: 20px; left: 20px; right: 20px; bottom: 100px;">
        <!-- Image Header -->
        <div style="text-align: center; margin-bottom: 20px;">
            <h2 style="color: #2c3e50; font-size: 16px; font-weight: bold; margin: 0;">Lab Result Image</h2>
            <p style="color: #7f8c8d; font-size: 12px; margin: 5px 0 0 0;">
                Lab No: {{ $report->lab_no ?? 'N/A' }} | 
                Patient: {{ $report->patient ? $report->patient->name : ($report->nos ?? 'N/A') }} |
                Date: {{ $report->report_date ? \Carbon\Carbon::parse($report->report_date)->format('d/m/Y') : \Carbon\Carbon::now()->format('d/m/Y') }}
            </p>
        </div>
        
        <!-- Image Container -->
        <div style="text-align: center; border: 2px solid #bdc3c7; padding: 20px; background: #ffffff; min-height: 400px; display: flex; align-items: center; justify-content: center;">
            @if($report->image_path)
                <img src="{{ storage_path('app/public/' . $report->image_path) }}" 
                     alt="Lab Result Image" 
                     style="max-width: 100%; max-height: 500px; object-fit: contain; border: 1px solid #ecf0f1;" />
            @else
                <div style="color: #95a5a6; font-size: 14px;">
                    Image not available
                </div>
            @endif
        </div>
        
        <!-- Image Information -->
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #e9ecef;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 50%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Image Filename</div>
                            <div style="font-size: 11px;">{{ $report->image_filename ?? 'N/A' }}</div>
                        </div>
                    </td>
                    <td style="width: 50%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">File Size</div>
                            <div style="font-size: 11px;">{{ $report->getImageSizeFormatted() ?? 'N/A' }}</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="width: 50%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Uploaded By</div>
                            <div style="font-size: 11px;">{{ $report->imageUploadedBy ? $report->imageUploadedBy->name : 'N/A' }}</div>
                        </div>
                    </td>
                    <td style="width: 50%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Upload Date</div>
                            <div style="font-size: 11px;">{{ $report->image_uploaded_at ? \Carbon\Carbon::parse($report->image_uploaded_at)->format('d/m/Y H:i') : 'N/A' }}</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Image Description -->
        <div style="margin-top: 15px; padding: 10px; background: #e8f4fd; border-left: 4px solid #3498db;">
            <div style="font-weight: bold; font-size: 11px; margin-bottom: 5px; color: #2c3e50;">Image Description</div>
            <div style="font-size: 11px; line-height: 1.4; color: #34495e;">
                This image shows the lab result findings for the above patient. Please refer to the main report for detailed analysis and diagnosis.
            </div>
        </div>
    </div>
    @endif
</body>
</html>