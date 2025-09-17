<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laboratory Visit Report</title>
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
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Patient Name</div>
                            <div style="font-size: 11px;">{{ $visit->patient ? $visit->patient->name : 'N/A' }}</div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Visit Number</div>
                            <div style="font-size: 11px;">{{ $visit->visit_number ?? 'N/A' }}</div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Visit Date</div>
                            <div style="font-size: 11px;">{{ $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('d/m/Y') : 'N/A' }}</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="width: 33.33%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Patient ID</div>
                            <div style="font-size: 11px;">{{ $visit->patient ? $visit->patient->id : 'N/A' }}</div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Phone</div>
                            <div style="font-size: 11px;">{{ $visit->patient ? $visit->patient->phone : 'N/A' }}</div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Gender</div>
                            <div style="font-size: 11px;">{{ $visit->patient ? $visit->patient->gender : 'N/A' }}</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="width: 33.33%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Referred Doctor</div>
                            <div style="font-size: 11px;">{{ $visit->referred_doctor ?? 'N/A' }}</div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Test Status</div>
                            <div style="font-size: 11px;">{{ $visit->test_status ?? 'N/A' }}</div>
                        </div>
                    </td>
                    <td style="width: 33.33%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Report Date</div>
                            <div style="font-size: 11px;">{{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Clinical Data -->
        @if($visit->clinical_data)
        <div style="margin-bottom: 20px;">
            <div style="background: #e8f4fd; border-left: 4px solid #3498db; padding: 10px; margin-bottom: 10px;">
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 5px; color: #2c3e50;">Clinical Data</div>
            </div>
            <div style="padding: 10px; background: #ffffff; border: 1px solid #e9ecef; line-height: 1.4;">
                {{ $visit->clinical_data }}
            </div>
        </div>
        @endif

        <!-- Specimen Information -->
        @if($visit->specimen_information)
        <div style="margin-bottom: 20px;">
            <div style="background: #f0f8ff; border-left: 4px solid #4169e1; padding: 10px; margin-bottom: 10px;">
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 5px; color: #1e3a8a;">SPECIMEN INFORMATION</div>
            </div>
            <div style="padding: 10px; background: #ffffff; border: 1px solid #e9ecef; line-height: 1.4;">
                {{ $visit->specimen_information }}
            </div>
        </div>
        @endif

        <!-- Gross Examination -->
        @if($visit->gross_examination)
        <div style="margin-bottom: 20px;">
            <div style="background: #f0fff0; border-left: 4px solid #32cd32; padding: 10px; margin-bottom: 10px;">
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 5px; color: #228b22;">GROSS EXAMINATION</div>
            </div>
            <div style="padding: 10px; background: #ffffff; border: 1px solid #e9ecef; line-height: 1.4;">
                {{ $visit->gross_examination }}
            </div>
        </div>
        @endif

        <!-- Microscopic Description -->
        @if($visit->microscopic_description)
        <div style="margin-bottom: 20px;">
            <div style="background: #e8f4fd; border-left: 4px solid #3498db; padding: 10px; margin-bottom: 10px;">
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 5px; color: #2c3e50;">Microscopic Description</div>
            </div>
            <div style="padding: 10px; background: #ffffff; border: 1px solid #e9ecef; line-height: 1.4;">
                {{ $visit->microscopic_description }}
            </div>
        </div>
        @endif

        <!-- Diagnosis -->
        @if($visit->diagnosis)
        <div style="margin-bottom: 20px;">
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin-bottom: 10px;">
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 5px; color: #856404;">Diagnosis</div>
            </div>
            <div style="padding: 10px; background: #ffffff; border: 1px solid #e9ecef; line-height: 1.4;">
                {{ $visit->diagnosis }}
            </div>
        </div>
        @endif

        <!-- Recommendations -->
        @if($visit->recommendations)
        <div style="margin-bottom: 20px;">
            <div style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 10px; margin-bottom: 10px;">
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 5px; color: #0c5460;">Recommendations</div>
            </div>
            <div style="padding: 10px; background: #ffffff; border: 1px solid #e9ecef; line-height: 1.4;">
                {{ $visit->recommendations }}
            </div>
        </div>
        @endif

        <!-- Test Results -->
        @if($visit->visitTests && count($visit->visitTests) > 0)
        <div style="margin-bottom: 20px;">
            <div style="background: #e8f4fd; border-left: 4px solid #3498db; padding: 10px; margin-bottom: 10px;">
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 5px; color: #2c3e50;">Test Results</div>
            </div>
            <div style="padding: 10px; background: #ffffff; border: 1px solid #e9ecef;">
                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="border: 1px solid #dee2e6; padding: 8px; text-align: left;">Test Name</th>
                            <th style="border: 1px solid #dee2e6; padding: 8px; text-align: left;">Result</th>
                            <th style="border: 1px solid #dee2e6; padding: 8px; text-align: left;">Status</th>
                            <th style="border: 1px solid #dee2e6; padding: 8px; text-align: left;">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($visit->visitTests as $visitTest)
                        <tr>
                            <td style="border: 1px solid #dee2e6; padding: 8px;">{{ $visitTest->labTest ? $visitTest->labTest->name : 'N/A' }}</td>
                            <td style="border: 1px solid #dee2e6; padding: 8px;">{{ $visitTest->result_value ?? 'N/A' }}</td>
                            <td style="border: 1px solid #dee2e6; padding: 8px;">{{ $visitTest->result_status ?? 'N/A' }}</td>
                            <td style="border: 1px solid #dee2e6; padding: 8px;">{{ $visitTest->result_notes ?? 'N/A' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- Footer -->
        <div style="position: absolute; bottom: 0; left: 0; right: 0; text-align: center; font-size: 10px; color: #6c757d; border-top: 1px solid #dee2e6; padding-top: 10px;">
            <div>Generated on {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }} | Laboratory Management System</div>
        </div>
    </div>

    @if($visit->image_path)
    <!-- Page Break for Image -->
    <pagebreak />
    
    <!-- Image Page -->
    <div style="position: absolute; top: 20px; left: 20px; right: 20px; bottom: 100px;">
        <!-- Image Header -->
        <div style="text-align: center; margin-bottom: 20px;">
            <h2 style="color: #2c3e50; font-size: 16px; font-weight: bold; margin: 0;">Lab Result Image</h2>
            <p style="color: #7f8c8d; font-size: 12px; margin: 5px 0 0 0;">
                Visit #: {{ $visit->visit_number ?? 'N/A' }} | 
                Patient: {{ $visit->patient ? $visit->patient->name : 'N/A' }} |
                Date: {{ $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('d/m/Y') : \Carbon\Carbon::now()->format('d/m/Y') }}
            </p>
        </div>
        
        <!-- Image Container -->
        <div style="text-align: center; border: 2px solid #bdc3c7; padding: 20px; background: #ffffff; min-height: 400px; display: flex; align-items: center; justify-content: center;">
            @if($visit->image_path)
                <img src="{{ storage_path('app/public/' . $visit->image_path) }}" 
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
                            <div style="font-size: 11px;">{{ $visit->image_filename ?? 'N/A' }}</div>
                        </div>
                    </td>
                    <td style="width: 50%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">File Size</div>
                            <div style="font-size: 11px;">{{ $visit->image_size ? number_format($visit->image_size / 1024 / 1024, 2) . ' MB' : 'N/A' }}</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="width: 50%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">Upload Date</div>
                            <div style="font-size: 11px;">{{ $visit->image_uploaded_at ? \Carbon\Carbon::parse($visit->image_uploaded_at)->format('d/m/Y H:i') : 'N/A' }}</div>
                        </div>
                    </td>
                    <td style="width: 50%; padding: 5px; vertical-align: top;">
                        <div>
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">File Type</div>
                            <div style="font-size: 11px;">{{ $visit->image_mime_type ?? 'N/A' }}</div>
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
