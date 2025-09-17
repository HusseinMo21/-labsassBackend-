<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EnhancedReport;
use App\Models\Patient;
use App\Models\LabRequest;
use App\Models\User;

class CreateSampleEnhancedReports extends Command
{
    protected $signature = 'reports:create-sample';
    protected $description = 'Create sample enhanced reports for testing';

    public function handle()
    {
        $this->info('Creating sample enhanced reports...');

        // Get existing data
        $patients = Patient::all();
        $labRequests = LabRequest::all();
        $users = User::where('role', '!=', 'patient')->get();

        if ($patients->isEmpty() || $users->isEmpty()) {
            $this->error('No patients or users found. Please run the sample data creation first.');
            return;
        }

        $sampleReports = [
            [
                'nos' => 'P001',
                'reff' => 'REF-2025-001',
                'clinical' => 'Patient presents with chronic fatigue, weight loss, and night sweats. History of smoking for 20 years. Family history of cancer.',
                'nature' => 'Lymph node biopsy from cervical region. Specimen received in formalin.',
                'report_date' => now()->subDays(5),
                'lab_no' => 'RPT-2025-001',
                'age' => '45 years',
                'gross' => 'Received a single lymph node measuring 2.5 x 1.8 x 1.2 cm. The node is firm, tan-white in color with a smooth capsule. Cut surface shows homogeneous tan-white parenchyma.',
                'micro' => 'Sections show effacement of normal lymph node architecture by sheets of large atypical lymphocytes. The cells have irregular nuclear contours, prominent nucleoli, and abundant cytoplasm. Mitotic figures are frequent. No Reed-Sternberg cells identified.',
                'conc' => 'Non-Hodgkin lymphoma, diffuse large B-cell type. The tumor cells are positive for CD20 and CD79a, negative for CD3 and CD30.',
                'reco' => 'Recommend staging workup including CT chest/abdomen/pelvis, bone marrow biopsy, and LDH level. Consider referral to hematology/oncology for treatment planning.',
                'type' => 'pathology',
                'sex' => 'male',
                'recieving' => '2025-01-15',
                'discharge' => '2025-01-16',
                'priority' => 'high',
                'status' => 'approved',
                'confirm' => true,
                'print' => true,
            ],
            [
                'nos' => 'P002',
                'reff' => 'REF-2025-002',
                'clinical' => 'Routine health checkup. No specific complaints. Patient is a 35-year-old female with no significant medical history.',
                'nature' => 'Complete blood count and basic metabolic panel. Blood drawn in EDTA tube.',
                'report_date' => now()->subDays(3),
                'lab_no' => 'RPT-2025-002',
                'age' => '35 years',
                'gross' => 'Blood sample received in EDTA tube. No visible abnormalities.',
                'micro' => 'CBC shows: WBC 7.2 x 10³/μL (normal), RBC 4.2 x 10⁶/μL (normal), Hgb 12.8 g/dL (normal), Hct 38.5% (normal), Platelets 285 x 10³/μL (normal). Differential shows normal distribution of white blood cells.',
                'conc' => 'Complete blood count is within normal limits. No evidence of anemia, infection, or other hematologic abnormalities.',
                'reco' => 'Continue routine health maintenance. No further testing required at this time.',
                'type' => 'hematology',
                'sex' => 'female',
                'recieving' => '2025-01-17',
                'discharge' => '2025-01-17',
                'priority' => 'normal',
                'status' => 'delivered',
                'confirm' => true,
                'print' => true,
            ],
            [
                'nos' => 'P003',
                'reff' => 'REF-2025-003',
                'clinical' => 'Patient with diabetes mellitus type 2. Routine monitoring of blood glucose control. Currently on metformin.',
                'nature' => 'HbA1c and lipid panel. Blood drawn in serum separator tube.',
                'report_date' => now()->subDays(2),
                'lab_no' => 'RPT-2025-003',
                'age' => '58 years',
                'gross' => 'Blood sample received in serum separator tube. No visible abnormalities.',
                'micro' => 'HbA1c: 7.2% (elevated, target <7%). Lipid panel: Total cholesterol 185 mg/dL (normal), LDL 110 mg/dL (normal), HDL 45 mg/dL (normal), Triglycerides 150 mg/dL (normal).',
                'conc' => 'HbA1c is elevated at 7.2%, indicating suboptimal glycemic control over the past 2-3 months. Lipid panel is within normal limits.',
                'reco' => 'Consider intensifying diabetes management. May need adjustment of metformin dose or addition of second-line therapy. Recommend dietary counseling and regular exercise.',
                'type' => 'biochemistry',
                'sex' => 'male',
                'recieving' => '2025-01-18',
                'discharge' => '2025-01-18',
                'priority' => 'normal',
                'status' => 'under_review',
                'confirm' => false,
                'print' => false,
            ],
            [
                'nos' => 'P004',
                'reff' => 'REF-2025-004',
                'clinical' => 'Patient with suspected urinary tract infection. Symptoms include dysuria, frequency, and urgency. No fever.',
                'nature' => 'Urine culture and sensitivity. Midstream clean catch urine sample.',
                'report_date' => now()->subDays(1),
                'lab_no' => 'RPT-2025-004',
                'age' => '28 years',
                'gross' => 'Urine sample received in sterile container. Sample appears clear and yellow.',
                'micro' => 'Urine culture shows growth of Escherichia coli at >100,000 CFU/mL. Sensitivity testing shows resistance to ampicillin but sensitivity to ciprofloxacin, nitrofurantoin, and trimethoprim-sulfamethoxazole.',
                'conc' => 'Urinary tract infection caused by Escherichia coli. The organism is resistant to ampicillin but sensitive to other commonly used antibiotics.',
                'reco' => 'Treatment with ciprofloxacin 500mg twice daily for 7 days is recommended. Follow up if symptoms persist or worsen.',
                'type' => 'microbiology',
                'sex' => 'female',
                'recieving' => '2025-01-19',
                'discharge' => '2025-01-20',
                'priority' => 'high',
                'status' => 'approved',
                'confirm' => true,
                'print' => false,
            ],
            [
                'nos' => 'P005',
                'reff' => 'REF-2025-005',
                'clinical' => 'Patient with suspected autoimmune disease. Family history of rheumatoid arthritis. Joint pain and morning stiffness.',
                'nature' => 'Rheumatoid factor, anti-CCP, and ANA panel. Blood drawn in serum separator tube.',
                'report_date' => now(),
                'lab_no' => 'RPT-2025-005',
                'age' => '42 years',
                'gross' => 'Blood sample received in serum separator tube. No visible abnormalities.',
                'micro' => 'Rheumatoid factor: 45 IU/mL (elevated, normal <14). Anti-CCP: 85 U/mL (elevated, normal <5). ANA: Positive at 1:160 titer with speckled pattern. ESR: 45 mm/hr (elevated).',
                'conc' => 'Laboratory findings are consistent with rheumatoid arthritis. Positive rheumatoid factor, anti-CCP, and ANA with elevated ESR support the diagnosis.',
                'reco' => 'Referral to rheumatology is recommended for further evaluation and treatment. Consider starting disease-modifying antirheumatic drugs (DMARDs).',
                'type' => 'immunology',
                'sex' => 'female',
                'recieving' => '2025-01-20',
                'discharge' => '2025-01-21',
                'priority' => 'urgent',
                'status' => 'draft',
                'confirm' => false,
                'print' => false,
            ],
        ];

        foreach ($sampleReports as $index => $reportData) {
            // Assign random patient and user
            $reportData['patient_id'] = $patients->random()->id;
            $reportData['lab_request_id'] = $labRequests->isNotEmpty() ? $labRequests->random()->id : null;
            $reportData['created_by'] = $users->random()->id;
            
            // Assign reviewer and approver for completed reports
            if (in_array($reportData['status'], ['approved', 'printed', 'delivered'])) {
                $reportData['reviewed_by'] = $users->random()->id;
                $reportData['reviewed_at'] = now()->subDays(rand(1, 5));
                $reportData['approved_by'] = $users->random()->id;
                $reportData['approved_at'] = now()->subDays(rand(1, 3));
            }
            
            // Assign print time for printed reports
            if (in_array($reportData['status'], ['printed', 'delivered'])) {
                $reportData['printed_at'] = now()->subDays(rand(1, 2));
            }
            
            // Assign delivery time for delivered reports
            if ($reportData['status'] === 'delivered') {
                $reportData['delivered_at'] = now()->subDays(1);
            }

            $report = EnhancedReport::create($reportData);
            
            // Generate barcode
            $report->generateBarcode();
            
            $this->info("Created report: {$report->lab_no} - {$report->type} ({$report->status})");
        }

        $this->info('Sample enhanced reports created successfully!');
        $this->info('Total reports: ' . EnhancedReport::count());
    }
}
