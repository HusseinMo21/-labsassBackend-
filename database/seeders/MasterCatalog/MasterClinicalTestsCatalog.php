<?php

declare(strict_types=1);

namespace Database\Seeders\MasterCatalog;

/**
 * Large reference master catalog: clinical lab tests + report_template for each.
 * Not every possible analyte worldwide — a broad, practical hospital/menu set (~500+ rows).
 */
final class MasterClinicalTestsCatalog
{
    /**
     * Platform clinical categories. `report_type` = default ERP report layout when a test has no `report_template` JSON.
     *
     * @return list<array{name:string,code:string,description:string,is_active:bool,lab_id:null,sort_order:int,report_type:string}>
     */
    public static function categories(): array
    {
        return [
            ['name' => 'Hematology', 'code' => 'clin_hem', 'description' => 'Core: CBC, indices, iron, hemoglobinopathy, ESR/CRP', 'is_active' => true, 'lab_id' => null, 'sort_order' => 10, 'report_type' => 'numeric'],
            ['name' => 'Clinical Chemistry', 'code' => 'clin_chem', 'description' => 'Core: electrolytes, enzymes, proteins, general chemistry', 'is_active' => true, 'lab_id' => null, 'sort_order' => 20, 'report_type' => 'numeric'],
            ['name' => 'Microbiology', 'code' => 'clin_mic', 'description' => 'Cultures, stains, rapid antigen, identification', 'is_active' => true, 'lab_id' => null, 'sort_order' => 30, 'report_type' => 'culture'],
            ['name' => 'Serology', 'code' => 'clin_sero', 'description' => 'General serology (non-infectious / mixed)', 'is_active' => true, 'lab_id' => null, 'sort_order' => 40, 'report_type' => 'single'],
            ['name' => 'Immunology', 'code' => 'clin_imm', 'description' => 'Autoimmune, immunoglobulins, complement, celiac-related', 'is_active' => true, 'lab_id' => null, 'sort_order' => 50, 'report_type' => 'single'],
            ['name' => 'Coagulation', 'code' => 'clin_coag', 'description' => 'PT/INR, aPTT, factors, thrombophilia', 'is_active' => true, 'lab_id' => null, 'sort_order' => 60, 'report_type' => 'numeric'],
            ['name' => 'Urinalysis', 'code' => 'clin_uri', 'description' => 'Urine dipstick, microscopy, urine chemistries', 'is_active' => true, 'lab_id' => null, 'sort_order' => 70, 'report_type' => 'text'],
            ['name' => 'Stool & GI', 'code' => 'clin_stool', 'description' => 'O&P, calprotectin, FOBT, GI pathogens', 'is_active' => true, 'lab_id' => null, 'sort_order' => 80, 'report_type' => 'text'],
            ['name' => 'Endocrinology & Diabetes', 'code' => 'clin_end', 'description' => 'Thyroid, adrenal, glucose control, pituitary', 'is_active' => true, 'lab_id' => null, 'sort_order' => 90, 'report_type' => 'single'],
            ['name' => 'Hormones & Fertility', 'code' => 'clin_hom', 'description' => 'FSH, LH, sex steroids, fertility, pregnancy tests', 'is_active' => true, 'lab_id' => null, 'sort_order' => 100, 'report_type' => 'single'],
            ['name' => 'Tumor Markers', 'code' => 'clin_tum', 'description' => 'Oncology serum markers', 'is_active' => true, 'lab_id' => null, 'sort_order' => 110, 'report_type' => 'single'],
            ['name' => 'Vitamins & Trace Elements', 'code' => 'clin_vit', 'description' => 'Vitamins, minerals, nutritional status', 'is_active' => true, 'lab_id' => null, 'sort_order' => 120, 'report_type' => 'single'],
            ['name' => 'Molecular Diagnostics', 'code' => 'clin_mol', 'description' => 'PCR, viral load, pharmacogenomics', 'is_active' => true, 'lab_id' => null, 'sort_order' => 130, 'report_type' => 'pcr'],
            ['name' => 'Histopathology', 'code' => 'clin_hist', 'description' => 'Tissue biopsies, resections, margins, IHC summaries', 'is_active' => true, 'lab_id' => null, 'sort_order' => 140, 'report_type' => 'paragraph'],
            ['name' => 'Cytology', 'code' => 'clin_cyto', 'description' => 'Pap smear, FNAC, fluid cytology', 'is_active' => true, 'lab_id' => null, 'sort_order' => 150, 'report_type' => 'paragraph'],
            ['name' => 'Toxicology', 'code' => 'clin_tox', 'description' => 'Drugs of abuse, poisons, heavy metals, CO exposure', 'is_active' => true, 'lab_id' => null, 'sort_order' => 160, 'report_type' => 'single'],
            ['name' => 'Allergy Testing', 'code' => 'clin_alg', 'description' => 'Specific IgE, food & inhalant panels, total IgE', 'is_active' => true, 'lab_id' => null, 'sort_order' => 170, 'report_type' => 'text'],
            ['name' => 'Cardiac Markers', 'code' => 'clin_car', 'description' => 'Troponin, BNP, cardiac enzymes', 'is_active' => true, 'lab_id' => null, 'sort_order' => 180, 'report_type' => 'numeric'],
            ['name' => 'CSF & Special Fluids', 'code' => 'clin_csf', 'description' => 'CSF, synovial, pleural, ascitic chemistry / counts', 'is_active' => true, 'lab_id' => null, 'sort_order' => 190, 'report_type' => 'numeric'],
            ['name' => 'Therapeutic Drug Monitoring', 'code' => 'clin_tdm', 'description' => 'Anti-epileptics, immunosuppressants, antibiotics (levels)', 'is_active' => true, 'lab_id' => null, 'sort_order' => 200, 'report_type' => 'single'],
            ['name' => 'Infectious Diseases', 'code' => 'clin_inf', 'description' => 'HIV, hepatitis, TORCH, tropical, respiratory viruses', 'is_active' => true, 'lab_id' => null, 'sort_order' => 210, 'report_type' => 'single'],
            ['name' => 'Blood Bank / Immunohematology', 'code' => 'clin_btx', 'description' => 'ABO/Rh, antibody screen, crossmatch — blood bank & transfusion medicine', 'is_active' => true, 'lab_id' => null, 'sort_order' => 220, 'report_type' => 'text'],
            ['name' => 'Miscellaneous Clinical', 'code' => 'clin_misc', 'description' => 'Osmolality, critical care misc.', 'is_active' => true, 'lab_id' => null, 'sort_order' => 230, 'report_type' => 'numeric'],
        ];
    }

    /**
     * @return list<array{code:string,name:string,category_code:string,price:float,description:string,turnaround_time_hours:int,report_template:array}>
     */
    public static function tests(): array
    {
        return array_merge(
            self::panelProfiles(),
            self::hematologyExtras(),
            self::chemistryFromTable(),
            self::endocrineFromTable(),
            self::infectiousSerologyFromTable(),
            self::immunologySerologyFromTable(),
            self::residualSerologyFromTable(),
            self::microbiologyFromTable(),
            self::urineAndFluids(),
            self::coagulationFromTable(),
            self::cardiacAndLipidExtras(),
            self::tumorFromTable(),
            self::vitaminsFromTable(),
            self::tdmFromTable(),
            self::toxicologyFromTable(),
            self::hormonesRepro(),
            self::molecularFromTable(),
            self::stoolGiFromTable(),
            self::histopathologyNarrative(),
            self::enterpriseHistopathologyExtras(),
            self::enterpriseCytologyExtras(),
            self::enterpriseAllergyExtras(),
            self::enterpriseImmunologyExtras(),
            self::enterpriseToxicologyExtras(),
        );
    }

    /** @return list<array{code:string,name:string,category_code:string,price:float,description:string,turnaround_time_hours:int,report_template:array}> */
    private static function panelProfiles(): array
    {
        $p = static fn (string $code, string $name, string $cat, float $price, array $tpl) => [
            'code' => $code,
            'name' => $name,
            'category_code' => $cat,
            'price' => $price,
            'description' => $name,
            'turnaround_time_hours' => 24,
            'report_template' => $tpl,
        ];

        return [
            $p('MC-HEM-CBC', 'Complete Blood Count (CBC) with differential', 'clin_hem', 35, McTemplates::cbc()),
            $p('MC-HEM-BMP', 'Basic metabolic panel (BMP)', 'clin_chem', 45, McTemplates::bmp()),
            $p('MC-HEM-CMP', 'Comprehensive metabolic panel (CMP)', 'clin_chem', 55, McTemplates::cmpFull()),
            $p('MC-CHM-LFT', 'Liver function panel (LFT)', 'clin_chem', 40, McTemplates::lft()),
            $p('MC-CHM-RFP', 'Renal function panel', 'clin_chem', 35, McTemplates::renal()),
            $p('MC-CHM-LIP', 'Lipid profile', 'clin_chem', 40, McTemplates::lipid()),
            $p('MC-CHM-ELY', 'Electrolyte panel', 'clin_chem', 30, McTemplates::electrolytes()),
            $p('MC-END-THY', 'Thyroid profile', 'clin_end', 50, McTemplates::thyroid()),
            $p('MC-END-A1C', 'HbA1c with eAG', 'clin_end', 35, McTemplates::hba1c()),
            $p('MC-HEM-IRON', 'Iron studies panel', 'clin_hem', 45, McTemplates::ironStudies()),
            $p('MC-HEM-B12F', 'Vitamin B12 & folate panel', 'clin_hem', 40, McTemplates::b12Folate()),
            $p('MC-COA-SCR', 'Coagulation screen (PT/INR, aPTT, fibrinogen, D-dimer)', 'clin_coag', 55, McTemplates::coagulation()),
            $p('MC-CAR-CMB', 'Cardiac biomarker panel', 'clin_car', 65, McTemplates::cardiac()),
            $p('MC-URI-DIP', 'Urinalysis — dipstick', 'clin_uri', 15, McTemplates::urinalysisDipstick()),
            $p('MC-URI-MIC', 'Urinalysis — microscopy', 'clin_uri', 20, McTemplates::urinalysisMicroscopy()),
            $p('MC-STO-OP', 'Stool O&P Examination', 'clin_stool', 45, McTemplates::stoolOandP()),
            $p('MC-INF-HEP', 'Viral hepatitis serology panel', 'clin_inf', 85, McTemplates::hepatitisPanel()),
            $p('MC-INF-HIV', 'HIV testing panel', 'clin_inf', 55, McTemplates::hivPanel()),
            $p('MC-INF-TOR', 'TORCH / perinatal serology panel', 'clin_inf', 120, McTemplates::torchPanel()),
            $p('MC-TUM-PAN', 'Tumor markers panel (common)', 'clin_tum', 150, McTemplates::tumorMarkers()),
            $p('MC-VIT-PAN', 'Vitamins & minerals panel', 'clin_vit', 95, McTemplates::vitaminsPanel()),
            $p('MC-CHM-BON', 'Bone metabolism panel', 'clin_chem', 70, McTemplates::boneMetabolism()),
            $p('MC-CHM-PAN', 'Pancreatic enzymes (amylase & lipase)', 'clin_chem', 35, McTemplates::pancreatic()),
            $p('MC-CHM-ABG', 'Arterial blood gas', 'clin_chem', 55, McTemplates::bloodGas()),
            $p('MC-CSF-BAS', 'CSF basic analysis', 'clin_csf', 60, McTemplates::csfBasic()),
            $p('MC-HOM-SMN', 'Semen analysis (basic)', 'clin_hom', 80, McTemplates::semenAnalysis()),
            $p('MC-MIC-CUL', 'Culture & sensitivity report', 'clin_mic', 75, McTemplates::cultureReport()),
            $p('MC-INF-HPY', 'Helicobacter pylori profile', 'clin_inf', 40, McTemplates::helicobacter()),
            $p('MC-INF-SYP', 'Syphilis serology panel', 'clin_inf', 35, McTemplates::syphilisPanel()),
            $p('MC-IMM-ANA', 'Autoimmune screen (ANA, RF, anti-CCP)', 'clin_imm', 65, McTemplates::autoimmuneBasic()),
            $p('MC-IMM-CEL', 'Celiac serology panel', 'clin_imm', 55, McTemplates::celiacPanel()),
            $p('MC-HIST-STD', 'Histopathology — standard narrative', 'clin_hist', 200, McTemplates::histopathStandard()),
            $p('MC-CYTO-PAP', 'Pap smear (cervical cytology)', 'clin_cyto', 45, McTemplates::papSmearCytology()),
            $p('MC-CYTO-FNAC', 'FNAC — fine-needle aspiration cytology', 'clin_cyto', 55, McTemplates::fnacCytology()),
            $p('MC-CYTO-FL', 'Body fluid cytology', 'clin_cyto', 50, McTemplates::bodyFluidCytology()),
            $p('MC-TOX-DOA', 'Drugs of abuse — urine panel', 'clin_tox', 40, McTemplates::drugsOfAbuseUrinePanel()),
            $p('MC-ALG-FOOD', 'Food allergy — specific IgE panel (example)', 'clin_alg', 95, McTemplates::allergyFoodIgEPanel()),
            $p('MC-ALG-INH', 'Inhalant allergy — specific IgE panel (example)', 'clin_alg', 95, McTemplates::allergyInhalantIgEPanel()),
            $p('MC-IMM-IG', 'Immunoglobulins (IgG, IgA, IgM, IgE)', 'clin_imm', 45, McTemplates::immunoglobulinQuantitation()),
            $p('MC-IMM-CMP', 'Complement (C3, C4, CH50)', 'clin_imm', 40, McTemplates::complementActivity()),
            $p('MC-BTX-ABO', 'ABO & Rh grouping', 'clin_btx', 25, McTemplates::bloodGroup()),
            $p('MC-MOL-PCRQ', 'Molecular — qualitative PCR (generic)', 'clin_mol', 90, McTemplates::pcrQualitative()),
            $p('MC-MOL-VL', 'Molecular — viral load (generic)', 'clin_mol', 120, McTemplates::pcrViralLoad()),
            $p('MC-TDM-GEN', 'Therapeutic drug monitoring (generic)', 'clin_tdm', 55, McTemplates::therapeuticDrug()),
            $p('MC-HOM-FEM', 'Female hormone panel', 'clin_hom', 85, McTemplates::hormoneFemale()),
            $p('MC-HOM-MAL', 'Male hormone panel', 'clin_hom', 85, McTemplates::hormoneMale()),
            $p('MC-END-ADR', 'Adrenal axis (cortisol / ACTH)', 'clin_end', 75, McTemplates::cortisolActh()),
            $p('MC-END-DM', 'Diabetes-related analytes panel', 'clin_end', 50, McTemplates::diabetesPanel()),
            $p('MC-CHM-SPE', 'Serum protein electrophoresis', 'clin_chem', 95, McTemplates::proteinElectrophoresis()),
            $p('MC-CHM-CRIT', 'Ammonia & lactate', 'clin_chem', 55, McTemplates::ammoniaLactate()),
            $p('MC-MISC-OSM', 'Serum & urine osmolality', 'clin_misc', 40, McTemplates::osmolality()),
            $p('MC-CAR-CK', 'Creatine kinase (CK) total', 'clin_car', 20, McTemplates::ckTotal()),
        ];
    }

    /** @return list<array{code:string,name:string,category_code:string,price:float,description:string,turnaround_time_hours:int,report_template:array}> */
    private static function hematologyExtras(): array
    {
        $rows = [
            ['ESR', 'Erythrocyte sedimentation rate (ESR)', '0–20 (M), 0–30 (F)', 'mm/hr', 12],
            ['RET', 'Reticulocyte count', '0.5–2.5', '%', 18],
            ['RET_ABS', 'Reticulocytes absolute', '25–75', '×10⁹/L', 22],
            ['HGB_A1', 'Hemoglobin A', '95–98', '%', 45],
            ['HGB_A2', 'Hemoglobin A2', '1.5–3.5', '%', 45],
            ['HGB_F', 'Hemoglobin F', '<1', '%', 45],
            ['SICKLE', 'Sickle cell solubility / screen', 'Negative', '', 25],
            ['G6PD', 'G6PD enzyme activity', 'method-specific', 'U/g Hb', 55],
            ['LDH', 'LDH', '140–280', 'U/L', 15],
            ['HAPT', 'Haptoglobin', '30–200', 'mg/dL', 28],
            ['BIL_NEO', 'Neonatal bilirubin (total)', '<12 day 1', 'mg/dL', 20],
        ];

        return self::mapSimple('HEM', 'clin_hem', $rows, 15);
    }

    /**
     * @param  list<list{0:string,1:string,2:string,3:string,4:float}>  $rows
     * @return list<array{code:string,name:string,category_code:string,price:float,description:string,turnaround_time_hours:int,report_template:array}>
     */
    private static function mapSimple(string $prefix, string $categoryCode, array $rows, float $defaultPrice): array
    {
        $out = [];
        $n = 1;
        foreach ($rows as $r) {
            $price = $r[4] ?? $defaultPrice;
            $out[] = [
                'code' => 'MC-'.$prefix.'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'name' => $r[1],
                'category_code' => $categoryCode,
                'price' => $price,
                'description' => $r[1],
                'turnaround_time_hours' => 24,
                'report_template' => McTemplates::single($r[1].' — result', $r[2], $r[3] ?: null),
            ];
            $n++;
        }

        return $out;
    }

    /** @return list<array{code:string,name:string,category_code:string,price:float,description:string,turnaround_time_hours:int,report_template:array}> */
    private static function chemistryFromTable(): array
    {
        $rows = [
            ['GLU_F', 'Glucose (fasting)', '70–100', 'mg/dL', 8],
            ['GLU_R', 'Glucose (random)', '70–140', 'mg/dL', 8],
            ['BUN', 'Blood urea nitrogen (BUN)', '7–20', 'mg/dL', 8],
            ['CREAT', 'Creatinine', '0.7–1.3', 'mg/dL', 8],
            ['EGFR', 'eGFR (CKD-EPI)', '>60', 'mL/min/1.73m²', 8],
            ['UA', 'Uric acid', '3.5–7.2', 'mg/dL', 10],
            ['CA', 'Calcium (total)', '8.5–10.5', 'mg/dL', 10],
            ['CA_ION', 'Calcium (ionized)', '4.5–5.3', 'mg/dL', 15],
            ['MG', 'Magnesium', '1.7–2.2', 'mg/dL', 10],
            ['PHOS', 'Phosphorus', '2.5–4.5', 'mg/dL', 10],
            ['NA', 'Sodium', '136–145', 'mmol/L', 8],
            ['K', 'Potassium', '3.5–5.1', 'mmol/L', 8],
            ['CL', 'Chloride', '98–107', 'mmol/L', 8],
            ['CO2', 'Bicarbonate (CO₂)', '22–29', 'mmol/L', 8],
            ['ANION', 'Anion gap', '8–16', 'mmol/L', 8],
            ['OSM_S', 'Serum osmolality', '275–295', 'mOsm/kg', 18],
            ['ALT', 'ALT (SGPT)', '7–56', 'U/L', 10],
            ['AST', 'AST (SGOT)', '10–40', 'U/L', 10],
            ['ALP', 'Alkaline phosphatase', '44–147', 'U/L', 10],
            ['GGT', 'Gamma-GT (GGT)', '9–48', 'U/L', 12],
            ['TBIL', 'Total bilirubin', '0.1–1.2', 'mg/dL', 10],
            ['DBIL', 'Direct bilirubin', '0–0.3', 'mg/dL', 10],
            ['TP', 'Total protein', '6.0–8.3', 'g/dL', 10],
            ['ALB', 'Albumin', '3.5–5.0', 'g/dL', 10],
            ['GLOB', 'Globulin (calculated)', '2.3–3.5', 'g/dL', 8],
            ['AG_RATIO', 'A/G ratio', '1.0–2.5', '', 8],
            ['AMY', 'Amylase', '30–110', 'U/L', 12],
            ['LIP', 'Lipase', '7–60', 'U/L', 12],
            ['CK', 'Creatine kinase (CK) total', '30–200', 'U/L', 12],
            ['CKMB_MASS', 'CK-MB (mass)', '<5', 'ng/mL', 25],
            ['LDH', 'Lactate dehydrogenase', '140–280', 'U/L', 12],
            ['CRP', 'C-reactive protein', '<3.0', 'mg/L', 15],
            ['HSCRP', 'High-sensitivity CRP', '<2.0', 'mg/L', 18],
            ['RF', 'Rheumatoid factor', '<14', 'IU/mL', 18],
            ['PREALB', 'Prealbumin', '20–40', 'mg/dL', 22],
            ['A1M', 'Alpha-1 antitrypsin', '90–200', 'mg/dL', 28],
            ['CERU', 'Ceruloplasmin', '20–60', 'mg/dL', 28],
            ['CHOL', 'Cholesterol (total)', '<200', 'mg/dL', 10],
            ['HDL', 'HDL cholesterol', '>40', 'mg/dL', 10],
            ['LDL', 'LDL cholesterol', '<100', 'mg/dL', 10],
            ['TG', 'Triglycerides', '<150', 'mg/dL', 10],
            ['VLDL', 'VLDL cholesterol', '5–40', 'mg/dL', 10],
            ['APOA1', 'Apolipoprotein A1', '120–180', 'mg/dL', 35],
            ['APOB', 'Apolipoprotein B', '60–130', 'mg/dL', 35],
            ['LPA', 'Lipoprotein (a)', '<30', 'mg/dL', 45],
            ['HOMO', 'Homocysteine', '5–15', 'µmol/L', 35],
            ['LACT', 'Lactate', '0.5–2.2', 'mmol/L', 20],
            ['NH3', 'Ammonia', '11–35', 'µmol/L', 25],
            ['PROBNP', 'NT-proBNP', '<125', 'pg/mL', 45],
            ['BNP', 'BNP', '<100', 'pg/mL', 45],
            ['TNI', 'Troponin I', 'method-specific', 'ng/L', 40],
            ['TNT', 'Troponin T', 'method-specific', 'ng/L', 40],
            ['MYO', 'Myoglobin', '25–72', 'ng/mL', 35],
        ];

        return self::mapSimple('CHM', 'clin_chem', $rows, 10);
    }

    private static function endocrineFromTable(): array
    {
        $rows = [
            ['TSH', 'TSH', '0.4–4.0', 'µIU/mL', 18],
            ['FT4', 'Free T4', '0.8–1.8', 'ng/dL', 22],
            ['FT3', 'Free T3', '2.3–4.2', 'pg/mL', 22],
            ['TT4', 'Total T4', '5.0–12.0', 'µg/dL', 18],
            ['TT3', 'Total T3', '80–200', 'ng/dL', 18],
            ['ATPO', 'Anti-thyroid peroxidase', '<35', 'IU/mL', 28],
            ['ATG', 'Anti-thyroglobulin', '<40', 'IU/mL', 28],
            ['TRAB', 'TSH receptor antibodies', '<1.75', 'IU/L', 55],
            ['CORT_AM', 'Cortisol (8 AM)', '6.2–19.4', 'µg/dL', 25],
            ['ACTH', 'ACTH', '7.2–63.3', 'pg/mL', 35],
            ['ALD', 'Aldosterone', '4–31', 'ng/dL', 35],
            ['RENIN', 'Plasma renin activity', 'lab-specific', 'ng/mL/hr', 45],
            ['INS', 'Insulin', '2.6–24.9', 'µIU/mL', 22],
            ['CPEP', 'C-peptide', '0.9–7.1', 'ng/mL', 22],
            ['HBA1C', 'HbA1c', '<5.7', '%', 22],
            ['FRUC', 'Fructosamine', '200–285', 'µmol/L', 22],
            ['GH', 'Growth hormone', '0.05–3.0', 'ng/mL', 40],
            ['IGF1', 'IGF-1', 'age-specific', 'ng/mL', 45],
            ['PTH', 'PTH intact', '15–65', 'pg/mL', 28],
            ['CALCIT', 'Calcitonin', '<10', 'pg/mL', 45],
            ['VMA', 'VMA (urine 24h)', '<13', 'mg/24h', 55],
            ['METAN', 'Metanephrines (plasma)', 'lab-specific', 'pg/mL', 75],
        ];

        return self::mapSimple('END', 'clin_end', $rows, 20);
    }

    private static function infectiousSerologyFromTable(): array
    {
        $q = ['Negative', 'Positive', 'Borderline', 'Not performed'];
        $out = [];
        $n = 1;
        $add = static function (string $name) use (&$out, &$n, $q) {
            $out[] = [
                'code' => 'MC-INF-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'category_code' => 'clin_inf',
                'price' => 28,
                'description' => $name,
                'turnaround_time_hours' => 24,
                'report_template' => McTemplates::panel($name, [
                    ['k' => 'result', 'l' => 'Result', 'i' => 'select', 'o' => $q],
                    ['k' => 'titer', 'l' => 'Titer / index / comment', 'u' => '', 'r' => '—'],
                ]),
            ];
            $n++;
        };

        foreach ([
            'CMV IgG', 'CMV IgM', 'EBV VCA IgG', 'EBV VCA IgM', 'EBNA IgG',
            'HSV-1 IgG', 'HSV-2 IgG', 'Varicella-zoster IgG', 'Measles IgG', 'Mumps IgG',
            'Rubella IgG', 'Toxoplasma IgG', 'Toxoplasma IgM', 'Brucella agglutination',
            'ASO titer', 'Anti-DNase B', 'Monospot / heterophile antibody',
            'Chlamydia trachomatis IgG', 'Chlamydia trachomatis IgM', 'Mycoplasma pneumoniae IgM',
            'Legionella urinary antigen', 'Cryptococcal antigen', 'Galactomannan (Aspergillus)',
            'Beta-D-glucan', 'Leishmania serology', 'Schistosoma serology', 'Strongyloides IgG',
            'Echinococcus serology', 'Amoeba serology', 'Widal test (interpret with caution)',
            'Weil-Felix reaction', 'Dengue NS1 antigen', 'Dengue IgM', 'Dengue IgG',
            'Chikungunya IgM', 'Zika IgM', 'West Nile IgM', 'HTLV-I/II antibody',
            'COVID-19 IgG', 'COVID-19 IgM', 'COVID-19 total antibody', 'Influenza A/B antigen',
            'RSV antigen', 'Adenovirus antigen', 'Rotavirus antigen', 'Norovirus antigen',
            'HIV-1 RNA qualitative', 'HBV DNA qualitative', 'HCV RNA qualitative',
            'VDRL / RPR screen', 'TPPA', 'FTA-ABS', 'Chagas (Trypanosoma) IgG',
            'Leptospira MAT', 'Rickettsia IFA', 'Bartonella IgG', 'Francisella tularensis antibody',
        ] as $name) {
            $add($name);
        }

        return $out;
    }

    private static function immunologySerologyFromTable(): array
    {
        $q = ['Negative', 'Positive', 'Borderline', 'Not performed'];
        $out = [];
        $n = 1;
        $add = static function (string $name) use (&$out, &$n, $q) {
            $out[] = [
                'code' => 'MC-IMM-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'category_code' => 'clin_imm',
                'price' => 30,
                'description' => $name,
                'turnaround_time_hours' => 24,
                'report_template' => McTemplates::panel($name, [
                    ['k' => 'result', 'l' => 'Result', 'i' => 'select', 'o' => $q],
                    ['k' => 'titer', 'l' => 'Titer / index / comment', 'u' => '', 'r' => '—'],
                ]),
            ];
            $n++;
        };

        foreach ([
            'ANA screen', 'dsDNA antibody', 'ENA panel (extractable nuclear antigens)',
            'Anti-Sm', 'Anti-RNP', 'Anti-Ro/SSA', 'Anti-La/SSB', 'Anti-Scl-70',
            'Anti-centromere', 'Anti-Jo-1', 'Anti-Mi-2', 'c-ANCA', 'p-ANCA',
            'Anti-CCP', 'Anti-mitochondrial M2', 'Anti-LKM', 'Anti-smooth muscle',
            'Tissue transglutaminase IgA', 'Tissue transglutaminase IgG', 'EMA IgA',
            'Total IgA (celiac workup)', 'Total IgG', 'Total IgM', 'Total IgE',
            'Specific IgE — food mix', 'Specific IgE — inhalant mix', 'Specific IgE — custom',
            'Cold agglutinins', 'Direct antiglobulin (Coombs) indirect', 'Donath-Landsteiner',
        ] as $name) {
            $add($name);
        }

        return $out;
    }

    private static function residualSerologyFromTable(): array
    {
        $q = ['Negative', 'Positive', 'Borderline', 'Not performed'];
        $out = [];
        $n = 1;
        foreach (['Pregnancy test (urine qualitative)', 'Pregnancy test (serum quantitative β-hCG)'] as $name) {
            $out[] = [
                'code' => 'MC-SER-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'category_code' => 'clin_hom',
                'price' => 18,
                'description' => $name,
                'turnaround_time_hours' => 24,
                'report_template' => McTemplates::panel($name, [
                    ['k' => 'result', 'l' => 'Result', 'i' => 'select', 'o' => $q],
                    ['k' => 'titer', 'l' => 'Quantitative / comment', 'u' => '', 'r' => '—'],
                ]),
            ];
            $n++;
        }

        return $out;
    }

    private static function microbiologyFromTable(): array
    {
        $out = [];
        $n = 1;
        foreach ([
            'Blood culture — aerobic bottle',
            'Blood culture — anaerobic bottle',
            'Urine culture',
            'Sputum culture',
            'Throat culture (Group A strep)',
            'Wound culture',
            'Stool culture (enteric pathogens)',
            'CSF culture',
            'Fungal culture',
            'Mycobacterial culture (AFB)',
            'MRSA nasal PCR screen',
            'C. difficile toxin / PCR',
            'Legionella culture',
            'Gonorrhea culture / NAAT',
            'Chlamydia NAAT',
            'Trichomonas wet mount / NAAT',
            'Vaginal culture',
            'Eye culture',
            'Ear culture',
            'Catheter tip culture',
            'Anaerobic culture',
            'Campylobacter stool culture',
            'Salmonella / Shigella stool culture',
            'VRE screen culture',
            'ESBL screen culture',
        ] as $name) {
            $out[] = [
                'code' => 'MC-MIC-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'category_code' => 'clin_mic',
                'price' => 45,
                'description' => $name,
                'turnaround_time_hours' => 48,
                'report_template' => McTemplates::cultureReport(),
            ];
            $n++;
        }

        return $out;
    }

    private static function urineAndFluids(): array
    {
        $out = [];
        $n = 1;
        foreach ([
            ['Urine protein (random)', 'mg/dL', '<150', 12],
            ['Urine protein 24h', 'g/24h', '<0.15', 18],
            ['Urine microalbumin', 'mg/L', '<30', 18],
            ['Urine albumin/creatinine ratio', 'mg/g', '<30', 18],
            ['Urine creatinine (random)', 'mg/dL', 'varies', 10],
            ['Urine sodium (random)', 'mmol/L', 'varies', 12],
            ['Urine potassium (random)', 'mmol/L', 'varies', 12],
            ['Urine osmolality', 'mOsm/kg', '50–1200', 15],
            ['Urine citrate', 'mg/24h', 'lab-specific', 35],
            ['Urine oxalate', 'mg/24h', 'lab-specific', 35],
            ['Urine uric acid 24h', 'mg/24h', '250–750', 22],
            ['Urine calcium 24h', 'mg/24h', '100–300', 22],
            ['Urine phosphorus 24h', 'g/24h', '0.4–1.3', 22],
            ['Synovial fluid — crystal analysis', 'description', 'none', 40],
            ['Synovial fluid — cell count', '/µL', 'varies', 35],
            ['Pleural fluid — protein', 'g/dL', 'varies', 25],
            ['Pleural fluid — LDH', 'U/L', 'varies', 25],
            ['Ascitic fluid — albumin', 'g/dL', 'varies', 25],
            ['Ascitic fluid — SAAG', 'g/dL', '≥1.1 portal HTN', 25],
            ['Pericardial fluid analysis', 'comment', '—', 45],
        ] as $row) {
            $out[] = [
                'code' => 'MC-URI-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'name' => $row[0],
                'category_code' => 'clin_uri',
                'price' => (float) $row[3],
                'description' => $row[0],
                'turnaround_time_hours' => 24,
                'report_template' => McTemplates::single($row[0].' — result', $row[2], $row[1]),
            ];
            $n++;
        }

        return $out;
    }

    private static function coagulationFromTable(): array
    {
        $rows = [
            ['PT', 'Prothrombin time (PT)', '11–13.5', 'sec', 15],
            ['INR', 'INR', '0.8–1.1', '', 15],
            ['APTT', 'Activated partial thromboplastin time', '25–35', 'sec', 15],
            ['FIB', 'Fibrinogen', '200–400', 'mg/dL', 18],
            ['DDIM', 'D-dimer', '<500', 'ng/mL FEU', 25],
            ['ATIII', 'Antithrombin III', '80–120', '%', 35],
            ['PROT_C', 'Protein C activity', '70–140', '%', 55],
            ['PROT_S', 'Protein S activity', '65–140', '%', 55],
            ['FV_LEID', 'Factor V Leiden mutation', 'Negative/Positive', '', 120],
            ['PROTHR', 'Prothrombin G20210A', 'Negative/Positive', '', 120],
            ['ANTIC', 'Anticardiolipin IgG', 'GPL', 'method-specific', 40],
            ['ANTICM', 'Anticardiolipin IgM', 'MPL', 'method-specific', 40],
            ['LAC', 'Lupus anticoagulant', 'Negative/Positive', '', 65],
            ['BT', 'Bleeding time (template)', '2–9', 'min', 20],
            ['PLT_FUNC', 'Platelet function (PFA)', 'closure time', 'sec', 45],
        ];

        return self::mapSimple('COA', 'clin_coag', $rows, 20);
    }

    private static function cardiacAndLipidExtras(): array
    {
        return []; // panels already cover most; chemistry table has lipids/cardiac singles
    }

    private static function tumorFromTable(): array
    {
        $rows = [
            ['PSA_T', 'PSA total', '<4.0', 'ng/mL', 22],
            ['PSA_F', 'PSA free', 'method-specific', 'ng/mL', 28],
            ['CEA', 'CEA', '<3.0', 'ng/mL', 25],
            ['CA125', 'CA 125', '<35', 'U/mL', 28],
            ['CA199', 'CA 19-9', '<37', 'U/mL', 28],
            ['CA153', 'CA 15-3', '<30', 'U/mL', 28],
            ['AFP', 'Alpha-fetoprotein', '<10', 'ng/mL', 25],
            ['CA2729', 'CA 27.29', '<38', 'U/mL', 35],
            ['S100', 'S100 protein', 'method-specific', 'µg/L', 55],
            ['NSE', 'NSE', '<16', 'ng/mL', 45],
            ['CYFRA', 'CYFRA 21-1', '<3.3', 'ng/mL', 55],
            ['SCC', 'Squamous cell carcinoma antigen', '<1.5', 'ng/mL', 45],
            ['HE4', 'HE4', 'age-dependent', 'pmol/L', 65],
            ['ROMA', 'ROMA index', 'risk score', '', 75],
            ['B2M', 'Beta-2 microglobulin', '0.8–2.2', 'mg/L', 28],
            ['LDH_TUM', 'LDH (tumor monitoring)', '140–280', 'U/L', 12],
        ];

        return self::mapSimple('TUM', 'clin_tum', $rows, 28);
    }

    private static function vitaminsFromTable(): array
    {
        $rows = [
            ['VITD', '25-OH Vitamin D', '30–100', 'ng/mL', 28],
            ['VITB12', 'Vitamin B12', '200–900', 'pg/mL', 22],
            ['FOL_S', 'Folate (serum)', '>3.0', 'ng/mL', 22],
            ['VITA', 'Vitamin A (retinol)', '30–95', 'µg/dL', 45],
            ['VITE', 'Vitamin E (alpha-tocopherol)', '5.5–17', 'mg/L', 45],
            ['VITK', 'Vitamin K1', '0.1–2.2', 'ng/mL', 55],
            ['VITC', 'Vitamin C', '0.4–2.0', 'mg/dL', 40],
            ['THIAM', 'Thiamine (B1)', 'method-specific', 'nmol/L', 55],
            ['RIBO', 'Riboflavin (B2)', 'method-specific', 'µg/L', 55],
            ['PYRID', 'Pyridoxine (B6)', '5–50', 'ng/mL', 55],
            ['FOL_RBC', 'RBC folate', '280–791', 'ng/mL', 28],
            ['ZN', 'Zinc', '60–130', 'µg/dL', 22],
            ['CU', 'Copper', '70–140', 'µg/dL', 22],
            ['SE', 'Selenium', '70–150', 'µg/L', 55],
            ['CR', 'Chromium', 'method-specific', 'µg/L', 65],
        ];

        return self::mapSimple('VIT', 'clin_vit', $rows, 25);
    }

    private static function tdmFromTable(): array
    {
        $rows = [
            ['DIG', 'Digoxin', '0.8–2.0', 'ng/mL', 35],
            ['PHNY', 'Phenytoin', '10–20', 'µg/mL', 35],
            ['CARB', 'Carbamazepine', '4–12', 'µg/mL', 35],
            ['VALP', 'Valproic acid', '50–100', 'µg/mL', 35],
            ['LITH', 'Lithium', '0.6–1.2', 'mEq/L', 30],
            ['THEO', 'Theophylline', '10–20', 'µg/mL', 35],
            ['VANC', 'Vancomycin (trough)', '10–20', 'µg/mL', 35],
            ['GENT', 'Gentamicin (trough)', '<2', 'µg/mL', 35],
            ['TOBR', 'Tobramycin (trough)', '<2', 'µg/mL', 35],
            ['AMIK', 'Amikacin (trough)', '5–30', 'µg/mL', 35],
            ['CYCLO', 'Cyclosporine (trough)', '100–400', 'ng/mL', 55],
            ['TACRO', 'Tacrolimus (trough)', '5–20', 'ng/mL', 55],
            ['SIRO', 'Sirolimus', '5–15', 'ng/mL', 65],
            ['MTX', 'Methotrexate', 'protocol-specific', 'µmol/L', 55],
        ];

        return self::mapSimple('TDM', 'clin_tdm', $rows, 30);
    }

    private static function toxicologyFromTable(): array
    {
        $rows = [
            ['ETOH', 'Ethanol', 'negative', 'mg/dL', 25],
            ['SAL', 'Salicylate', 'therapeutic 150–300', 'mg/dL', 25],
            ['ACET', 'Acetaminophen', 'therapeutic <30', 'µg/mL', 25],
            ['BARB', 'Barbiturate screen', 'negative/positive', '', 35],
            ['BZO', 'Benzodiazepine screen', 'negative/positive', '', 35],
            ['OPI', 'Opiate / opioid screen', 'negative/positive', '', 35],
            ['COC', 'Cocaine metabolite', 'negative/positive', '', 35],
            ['AMP', 'Amphetamines screen', 'negative/positive', '', 35],
            ['THC', 'Cannabinoids (THC)', 'negative/positive', '', 35],
            ['PCP', 'Phencyclidine', 'negative/positive', '', 35],
        ];

        return self::mapSimple('TOX', 'clin_tox', $rows, 30);
    }

    private static function hormonesRepro(): array
    {
        $rows = [
            ['FSH', 'FSH', 'cycle-dependent', 'mIU/mL', 22],
            ['LH', 'LH', 'cycle-dependent', 'mIU/mL', 22],
            ['E2', 'Estradiol', 'cycle-dependent', 'pg/mL', 22],
            ['P4', 'Progesterone', 'cycle-dependent', 'ng/mL', 22],
            ['PRL', 'Prolactin', '4.8–23.3', 'ng/mL', 22],
            ['TESTO', 'Testosterone total', '300–1000', 'ng/dL', 22],
            ['TESTOF', 'Testosterone free', '9.3–26.5', 'pg/mL', 28],
            ['SHBG', 'SHBG', '10–57', 'nmol/L', 28],
            ['DHEAS', 'DHEA-sulfate', 'age-dependent', 'µg/dL', 25],
            ['ANDRO', 'Androstenedione', 'method-specific', 'ng/dL', 35],
            ['17OH', '17-OH progesterone', 'method-specific', 'ng/dL', 45],
            ['AMH', 'Anti-Müllerian hormone', 'age-specific', 'ng/mL', 65],
            ['INH_B', 'Inhibin B', 'method-specific', 'pg/mL', 75],
            ['BHCG', 'Beta-hCG (quantitative)', 'method-specific', 'mIU/mL', 22],
        ];

        return self::mapSimple('HOM', 'clin_hom', $rows, 25);
    }

    private static function molecularFromTable(): array
    {
        $out = [];
        $n = 1;
        foreach ([
            'HBV DNA quantitative',
            'HCV RNA quantitative',
            'HIV RNA quantitative (viral load)',
            'CMV DNA quantitative',
            'EBV DNA quantitative',
            'BKV DNA quantitative',
            'Adenovirus DNA',
            'HSV DNA (CSF)',
            'VZV DNA (CSV/lesion)',
            'Enterovirus RNA (CSF)',
            'Parechovirus RNA',
            'SARS-CoV-2 RNA (PCR)',
            'Influenza A/B RNA PCR',
            'RSV RNA PCR',
            'Group A strep DNA (throat)',
            'MRSA PCR (nasal)',
            'C. difficile toxin B gene PCR',
            'Norovirus RNA',
            'Rotavirus RNA',
            'Parvovirus B19 DNA',
            'Bartonella PCR',
            'Toxoplasma DNA',
            'Malaria PCR',
            'Factor II prothrombin mutation',
            'MTHFR C677T variant',
            'CYP2C19 pharmacogenomics',
            'CYP2D6 pharmacogenomics',
            'VKORC1 pharmacogenomics',
            'HLA-B*57:01 (abacavir)',
            'HLA-B*1502 (carbamazepine)',
            'CFTR common mutation panel',
            'BRCA1/BRCA2 targeted variants',
            'JAK2 V617F',
            'BCR-ABL1 quantitative',
            'FLT3-ITD',
            'NPM1 mutation',
        ] as $name) {
            $out[] = [
                'code' => 'MC-MOL-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'category_code' => 'clin_mol',
                'price' => 95,
                'description' => $name,
                'turnaround_time_hours' => 72,
                'report_template' => str_contains(strtolower($name), 'quantitative') || str_contains(strtolower($name), 'viral load')
                    ? McTemplates::pcrViralLoad()
                    : McTemplates::pcrQualitative(),
            ];
            $n++;
        }

        return $out;
    }

    private static function stoolGiFromTable(): array
    {
        $rows = [
            ['FOBT', 'Fecal occult blood (gFOBT)', 'Negative', '', 15],
            ['FIT', 'Fecal immunochemical test (FIT)', 'method-specific', 'µg/g', 22],
            ['CALP', 'Fecal calprotectin', '<50', 'µg/g', 45],
            ['ELAST', 'Fecal elastase', '>200', 'µg/g', 55],
            ['FECA1', 'Fecal alpha-1 antitrypsin', 'method-specific', 'mg/g', 55],
        ];
        $out = self::mapSimple('STO', 'clin_stool', $rows, 25);

        $out[] = [
            'code' => 'MC-STO-099',
            'name' => 'Stool culture — enteric pathogens (report)',
            'category_code' => 'clin_stool',
            'price' => 55,
            'description' => 'Stool culture',
            'turnaround_time_hours' => 72,
            'report_template' => McTemplates::cultureReport(),
        ];

        return $out;
    }

    private static function histopathologyNarrative(): array
    {
        return [[
            'code' => 'MC-PAT-GEN',
            'name' => 'Surgical pathology — narrative report',
            'category_code' => 'clin_hist',
            'price' => 200,
            'description' => 'General histopathology narrative (specimen, gross, micro, diagnosis, IHC summary)',
            'turnaround_time_hours' => 120,
            'report_template' => McTemplates::histopathStandard(),
        ]];
    }

    /** @return list<array{code:string,name:string,category_code:string,price:float,description:string,turnaround_time_hours:int,report_template:array}> */
    private static function enterpriseHistopathologyExtras(): array
    {
        return [
            [
                'code' => 'MC-HIST-BM',
                'name' => 'Bone marrow biopsy — histopathology',
                'category_code' => 'clin_hist',
                'price' => 250,
                'description' => 'Bone marrow core / aspirate — histology report',
                'turnaround_time_hours' => 120,
                'report_template' => McTemplates::histopathStandard(),
            ],
            [
                'code' => 'MC-HIST-FZ',
                'name' => 'Frozen section — intraoperative consultation',
                'category_code' => 'clin_hist',
                'price' => 180,
                'description' => 'Frozen section diagnosis',
                'turnaround_time_hours' => 4,
                'report_template' => McTemplates::panel('Frozen section', [
                    ['k' => 'site', 'l' => 'Site / procedure', 'u' => '', 'r' => '—'],
                    ['k' => 'diagnosis', 'l' => 'Frozen diagnosis', 'u' => '', 'r' => '—'],
                    ['k' => 'deferral', 'l' => 'Deferred to permanent sections', 'i' => 'select', 'o' => ['No', 'Yes', 'N/A']],
                    ['k' => 'comment', 'l' => 'Comment', 'u' => '', 'r' => '—'],
                ]),
            ],
        ];
    }

    /** @return list<array{code:string,name:string,category_code:string,price:float,description:string,turnaround_time_hours:int,report_template:array}> */
    private static function enterpriseCytologyExtras(): array
    {
        $uro = ['Negative for high-grade urothelial carcinoma', 'Atypical urothelial cells', 'Suspicious', 'Positive for malignancy', 'Unsatisfactory', 'Not performed'];

        return [[
            'code' => 'MC-CYTO-UR',
            'name' => 'Urine cytology',
            'category_code' => 'clin_cyto',
            'price' => 40,
            'description' => 'Urine cytology for urothelial neoplasia',
            'turnaround_time_hours' => 48,
            'report_template' => McTemplates::panel('Urine cytology', [
                ['k' => 'adequacy', 'l' => 'Specimen adequacy', 'i' => 'select', 'o' => ['Adequate', 'Limited', 'Unsatisfactory']],
                ['k' => 'interpretation', 'l' => 'Interpretation', 'i' => 'select', 'o' => $uro],
                ['k' => 'comment', 'l' => 'Comments', 'u' => '', 'r' => '—'],
            ]),
        ]];
    }

    /** @return list<array{code:string,name:string,category_code:string,price:float,description:string,turnaround_time_hours:int,report_template:array}> */
    private static function enterpriseAllergyExtras(): array
    {
        return [[
            'code' => 'MC-ALG-IGE',
            'name' => 'Total IgE (allergy screen)',
            'category_code' => 'clin_alg',
            'price' => 22,
            'description' => 'Serum total IgE',
            'turnaround_time_hours' => 24,
            'report_template' => McTemplates::single('Total IgE', '<100', 'IU/mL'),
        ]];
    }

    /** @return list<array{code:string,name:string,category_code:string,price:float,description:string,turnaround_time_hours:int,report_template:array}> */
    private static function enterpriseImmunologyExtras(): array
    {
        return [[
            'code' => 'MC-IMM-TRY',
            'name' => 'Tryptase (mast cell activation)',
            'category_code' => 'clin_imm',
            'price' => 45,
            'description' => 'Serum tryptase',
            'turnaround_time_hours' => 24,
            'report_template' => McTemplates::single('Tryptase', '<11.4', 'ng/mL'),
        ]];
    }

    /** @return list<array{code:string,name:string,category_code:string,price:float,description:string,turnaround_time_hours:int,report_template:array}> */
    private static function enterpriseToxicologyExtras(): array
    {
        return [
            [
                'code' => 'MC-TOX-HM',
                'name' => 'Heavy metals — blood panel',
                'category_code' => 'clin_tox',
                'price' => 95,
                'description' => 'Lead, mercury, arsenic, cadmium (example panel)',
                'turnaround_time_hours' => 72,
                'report_template' => McTemplates::heavyMetalsBlood(),
            ],
            [
                'code' => 'MC-TOX-CO',
                'name' => 'Carboxyhemoglobin (CO poisoning)',
                'category_code' => 'clin_tox',
                'price' => 35,
                'description' => 'CO exposure / smoke inhalation',
                'turnaround_time_hours' => 4,
                'report_template' => McTemplates::carbonMonoxidePoisoning(),
            ],
        ];
    }
}
