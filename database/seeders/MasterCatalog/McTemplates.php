<?php

declare(strict_types=1);

namespace Database\Seeders\MasterCatalog;

/**
 * Report layouts for lab_tests.report_template (JSON). Matches frontend parameter_table schema.
 */
final class McTemplates
{
    /**
     * @param  list<array{k:string,l:string,u?:string,r?:string,i?:string,o?:list<string>,multiline?:bool,unit_options?:list<string>,reference_options?:list<string>}>  $lines
     */
    public static function panel(string $title, array $lines): array
    {
        $parameters = [];
        foreach ($lines as $line) {
            $p = [
                'key' => $line['k'],
                'label' => $line['l'],
            ];
            if (! empty($line['u'])) {
                $p['unit'] = $line['u'];
            }
            if (! empty($line['r'])) {
                $p['reference'] = $line['r'];
            }
            if (! empty($line['i'])) {
                $p['input'] = $line['i'];
            }
            if (! empty($line['o'])) {
                $p['options'] = $line['o'];
            }
            if (! empty($line['multiline'])) {
                $p['multiline'] = true;
            }
            if (! empty($line['unit_options']) && is_array($line['unit_options'])) {
                $p['unit_options'] = array_values($line['unit_options']);
            }
            if (! empty($line['reference_options']) && is_array($line['reference_options'])) {
                $p['reference_options'] = array_values($line['reference_options']);
            }
            $parameters[] = $p;
        }

        return [
            'layout' => 'parameter_table',
            'title' => $title,
            'parameters' => $parameters,
        ];
    }

    public static function single(string $label, ?string $reference = null, ?string $unit = null): array
    {
        $line = ['k' => 'value', 'l' => $label];
        if ($unit) {
            $line['u'] = $unit;
        }
        if ($reference) {
            $line['r'] = $reference;
        }

        return self::panel('', [$line]);
    }

    /** @param  list<string>  $options */
    public static function qualitative(string $title, string $key, string $label, array $options = ['Negative', 'Positive', 'Equivocal', 'Not performed']): array
    {
        return self::panel($title, [
            ['k' => $key, 'l' => $label, 'i' => 'select', 'o' => $options],
        ]);
    }

    public static function cbc(): array
    {
        return self::panel('Complete blood count (CBC)', [
            ['k' => 'wbc', 'l' => 'WBC', 'u' => '×10³/µL', 'r' => '4.0–11.0'],
            ['k' => 'rbc', 'l' => 'RBC', 'u' => '×10⁶/µL', 'r' => '4.2–5.9 (M), 3.8–5.2 (F)'],
            ['k' => 'hgb', 'l' => 'Hemoglobin', 'u' => 'g/dL', 'r' => '13–17 (M), 12–16 (F)'],
            ['k' => 'hct', 'l' => 'Hematocrit', 'u' => '%', 'r' => '41–53 (M), 36–46 (F)'],
            ['k' => 'mcv', 'l' => 'MCV', 'u' => 'fL', 'r' => '80–100'],
            ['k' => 'mch', 'l' => 'MCH', 'u' => 'pg', 'r' => '27–33'],
            ['k' => 'mchc', 'l' => 'MCHC', 'u' => 'g/dL', 'r' => '32–36'],
            ['k' => 'rdw', 'l' => 'RDW-CV', 'u' => '%', 'r' => '11.5–14.5'],
            ['k' => 'plt', 'l' => 'Platelets', 'u' => '×10³/µL', 'r' => '150–400'],
            ['k' => 'mpv', 'l' => 'MPV', 'u' => 'fL', 'r' => '7.4–10.4'],
            ['k' => 'neut_pct', 'l' => 'Neutrophils', 'u' => '%', 'r' => '40–70'],
            ['k' => 'lymph_pct', 'l' => 'Lymphocytes', 'u' => '%', 'r' => '20–45'],
            ['k' => 'mono_pct', 'l' => 'Monocytes', 'u' => '%', 'r' => '2–10'],
            ['k' => 'eos_pct', 'l' => 'Eosinophils', 'u' => '%', 'r' => '0–6'],
            ['k' => 'baso_pct', 'l' => 'Basophils', 'u' => '%', 'r' => '0–2'],
            ['k' => 'neut_abs', 'l' => 'Neutrophils (absolute)', 'u' => '×10³/µL', 'r' => '1.8–7.7'],
            ['k' => 'lymph_abs', 'l' => 'Lymphocytes (absolute)', 'u' => '×10³/µL', 'r' => '1.0–4.8'],
        ]);
    }

    public static function bmp(): array
    {
        return self::panel('Basic metabolic panel', [
            ['k' => 'glu', 'l' => 'Glucose', 'u' => 'mg/dL', 'r' => '70–100 (fasting)'],
            ['k' => 'bun', 'l' => 'BUN', 'u' => 'mg/dL', 'r' => '7–20'],
            ['k' => 'creat', 'l' => 'Creatinine', 'u' => 'mg/dL', 'r' => '0.7–1.3 (M), 0.6–1.1 (F)'],
            ['k' => 'egfr', 'l' => 'eGFR', 'u' => 'mL/min/1.73m²', 'r' => '>60'],
            ['k' => 'na', 'l' => 'Sodium', 'u' => 'mmol/L', 'r' => '136–145'],
            ['k' => 'k', 'l' => 'Potassium', 'u' => 'mmol/L', 'r' => '3.5–5.1'],
            ['k' => 'cl', 'l' => 'Chloride', 'u' => 'mmol/L', 'r' => '98–107'],
            ['k' => 'co2', 'l' => 'CO₂ (bicarbonate)', 'u' => 'mmol/L', 'r' => '22–29'],
        ]);
    }

    public static function cmp(): array
    {
        return self::cmpFull();
    }

    public static function cmpFull(): array
    {
        $bmp = [
            ['k' => 'glu', 'l' => 'Glucose', 'u' => 'mg/dL', 'r' => '70–100 (fasting)'],
            ['k' => 'bun', 'l' => 'BUN', 'u' => 'mg/dL', 'r' => '7–20'],
            ['k' => 'creat', 'l' => 'Creatinine', 'u' => 'mg/dL', 'r' => '0.7–1.3'],
            ['k' => 'egfr', 'l' => 'eGFR', 'u' => 'mL/min/1.73m²', 'r' => '>60'],
            ['k' => 'ca', 'l' => 'Calcium', 'u' => 'mg/dL', 'r' => '8.5–10.5'],
            ['k' => 'na', 'l' => 'Sodium', 'u' => 'mmol/L', 'r' => '136–145'],
            ['k' => 'k', 'l' => 'Potassium', 'u' => 'mmol/L', 'r' => '3.5–5.1'],
            ['k' => 'cl', 'l' => 'Chloride', 'u' => 'mmol/L', 'r' => '98–107'],
            ['k' => 'co2', 'l' => 'CO₂ (bicarbonate)', 'u' => 'mmol/L', 'r' => '22–29'],
            ['k' => 'alb', 'l' => 'Albumin', 'u' => 'g/dL', 'r' => '3.5–5.0'],
            ['k' => 'tp', 'l' => 'Total protein', 'u' => 'g/dL', 'r' => '6.0–8.3'],
            ['k' => 'alb_glob', 'l' => 'A/G ratio', 'u' => '', 'r' => '1.0–2.5'],
            ['k' => 'tbil', 'l' => 'Total bilirubin', 'u' => 'mg/dL', 'r' => '0.1–1.2'],
            ['k' => 'alp', 'l' => 'ALP', 'u' => 'U/L', 'r' => '44–147'],
            ['k' => 'alt', 'l' => 'ALT (SGPT)', 'u' => 'U/L', 'r' => '7–56'],
            ['k' => 'ast', 'l' => 'AST (SGOT)', 'u' => 'U/L', 'r' => '10–40'],
        ];

        return self::panel('Comprehensive metabolic panel', $bmp);
    }

    public static function lft(): array
    {
        return self::panel('Liver function tests', [
            ['k' => 'alt', 'l' => 'ALT (SGPT)', 'u' => 'U/L', 'r' => '7–56'],
            ['k' => 'ast', 'l' => 'AST (SGOT)', 'u' => 'U/L', 'r' => '10–40'],
            ['k' => 'alp', 'l' => 'Alkaline phosphatase', 'u' => 'U/L', 'r' => '44–147'],
            ['k' => 'ggt', 'l' => 'GGT', 'u' => 'U/L', 'r' => '9–48'],
            ['k' => 'tbil', 'l' => 'Total bilirubin', 'u' => 'mg/dL', 'r' => '0.1–1.2'],
            ['k' => 'dbil', 'l' => 'Direct bilirubin', 'u' => 'mg/dL', 'r' => '0–0.3'],
            ['k' => 'tp', 'l' => 'Total protein', 'u' => 'g/dL', 'r' => '6.0–8.3'],
            ['k' => 'alb', 'l' => 'Albumin', 'u' => 'g/dL', 'r' => '3.5–5.0'],
        ]);
    }

    public static function renal(): array
    {
        return self::panel('Renal function', [
            ['k' => 'bun', 'l' => 'BUN', 'u' => 'mg/dL', 'r' => '7–20'],
            ['k' => 'creat', 'l' => 'Creatinine', 'u' => 'mg/dL', 'r' => '0.7–1.3'],
            ['k' => 'egfr', 'l' => 'eGFR', 'u' => 'mL/min/1.73m²', 'r' => '>60'],
            ['k' => 'cys_c', 'l' => 'Cystatin C', 'u' => 'mg/L', 'r' => '0.57–1.12'],
            ['k' => 'ua', 'l' => 'Uric acid', 'u' => 'mg/dL', 'r' => '3.5–7.2 (M), 2.6–6.0 (F)'],
        ]);
    }

    public static function lipid(): array
    {
        return self::panel('Lipid profile', [
            ['k' => 'chol', 'l' => 'Total cholesterol', 'u' => 'mg/dL', 'r' => '<200'],
            ['k' => 'hdl', 'l' => 'HDL-C', 'u' => 'mg/dL', 'r' => '>40 (M), >50 (F)'],
            ['k' => 'ldl', 'l' => 'LDL-C', 'u' => 'mg/dL', 'r' => '<100 (optimal)'],
            ['k' => 'tg', 'l' => 'Triglycerides', 'u' => 'mg/dL', 'r' => '<150'],
            ['k' => 'vldl', 'l' => 'VLDL-C', 'u' => 'mg/dL', 'r' => '5–40'],
            ['k' => 'non_hdl', 'l' => 'Non-HDL-C', 'u' => 'mg/dL', 'r' => '<130'],
            ['k' => 'ratio', 'l' => 'Total/HDL ratio', 'u' => '', 'r' => '<5'],
        ]);
    }

    public static function electrolytes(): array
    {
        return self::panel('Electrolytes', [
            ['k' => 'na', 'l' => 'Sodium', 'u' => 'mmol/L', 'r' => '136–145'],
            ['k' => 'k', 'l' => 'Potassium', 'u' => 'mmol/L', 'r' => '3.5–5.1'],
            ['k' => 'cl', 'l' => 'Chloride', 'u' => 'mmol/L', 'r' => '98–107'],
            ['k' => 'co2', 'l' => 'Bicarbonate', 'u' => 'mmol/L', 'r' => '22–29'],
            ['k' => 'mg', 'l' => 'Magnesium', 'u' => 'mg/dL', 'r' => '1.7–2.2'],
            ['k' => 'phos', 'l' => 'Phosphorus', 'u' => 'mg/dL', 'r' => '2.5–4.5'],
        ]);
    }

    public static function thyroid(): array
    {
        return self::panel('Thyroid profile', [
            ['k' => 'tsh', 'l' => 'TSH', 'u' => 'µIU/mL', 'r' => '0.4–4.0'],
            ['k' => 'ft4', 'l' => 'Free T4', 'u' => 'ng/dL', 'r' => '0.8–1.8'],
            ['k' => 'ft3', 'l' => 'Free T3', 'u' => 'pg/mL', 'r' => '2.3–4.2'],
            ['k' => 'tt4', 'l' => 'Total T4', 'u' => 'µg/dL', 'r' => '5.0–12.0'],
            ['k' => 'tt3', 'l' => 'Total T3', 'u' => 'ng/dL', 'r' => '80–200'],
            ['k' => 'anti_tpo', 'l' => 'Anti-TPO', 'u' => 'IU/mL', 'r' => '<35'],
            ['k' => 'anti_tg', 'l' => 'Anti-thyroglobulin', 'u' => 'IU/mL', 'r' => '<40'],
        ]);
    }

    public static function hba1c(): array
    {
        return self::panel('Glycemic control', [
            ['k' => 'hba1c', 'l' => 'HbA1c', 'u' => '%', 'r' => '<5.7 normal, 5.7–6.4 prediabetes'],
            ['k' => 'eag', 'l' => 'eAG', 'u' => 'mg/dL', 'r' => '—'],
        ]);
    }

    public static function ironStudies(): array
    {
        return self::panel('Iron studies', [
            ['k' => 'iron', 'l' => 'Serum iron', 'u' => 'µg/dL', 'r' => '60–170'],
            ['k' => 'tibc', 'l' => 'TIBC', 'u' => 'µg/dL', 'r' => '250–450'],
            ['k' => 'uibc', 'l' => 'UIBC', 'u' => 'µg/dL', 'r' => '111–343'],
            ['k' => 'sat', 'l' => 'Transferrin saturation', 'u' => '%', 'r' => '20–50'],
            ['k' => 'ferr', 'l' => 'Ferritin', 'u' => 'ng/mL', 'r' => '20–250 (M), 10–120 (F)'],
        ]);
    }

    public static function b12Folate(): array
    {
        return self::panel('B12 & folate', [
            ['k' => 'b12', 'l' => 'Vitamin B12', 'u' => 'pg/mL', 'r' => '200–900'],
            ['k' => 'folate', 'l' => 'Folate (serum)', 'u' => 'ng/mL', 'r' => '>3.0'],
            ['k' => 'rbc_folate', 'l' => 'RBC folate', 'u' => 'ng/mL', 'r' => '280–791'],
        ]);
    }

    public static function coagulation(): array
    {
        return self::panel('Coagulation screen', [
            ['k' => 'pt', 'l' => 'PT', 'u' => 'sec', 'r' => '11–13.5'],
            ['k' => 'inr', 'l' => 'INR', 'u' => '', 'r' => '0.8–1.1 (therapeutic 2–3)'],
            ['k' => 'aptt', 'l' => 'aPTT', 'u' => 'sec', 'r' => '25–35'],
            ['k' => 'fib', 'l' => 'Fibrinogen', 'u' => 'mg/dL', 'r' => '200–400'],
            ['k' => 'ddimer', 'l' => 'D-dimer', 'u' => 'ng/mL FEU', 'r' => '<500'],
        ]);
    }

    public static function cardiac(): array
    {
        return self::panel('Cardiac biomarkers', [
            ['k' => 'trop_i', 'l' => 'Troponin I', 'u' => 'ng/L', 'r' => 'method-specific'],
            ['k' => 'trop_t', 'l' => 'Troponin T', 'u' => 'ng/L', 'r' => 'method-specific'],
            ['k' => 'ck', 'l' => 'CK total', 'u' => 'U/L', 'r' => '30–200'],
            ['k' => 'ckmb', 'l' => 'CK-MB', 'u' => 'ng/mL', 'r' => '<5'],
            ['k' => 'bnp', 'l' => 'BNP', 'u' => 'pg/mL', 'r' => '<100'],
            ['k' => 'ntprobnp', 'l' => 'NT-proBNP', 'u' => 'pg/mL', 'r' => '<125 (<75y)'],
            ['k' => 'hs_crp', 'l' => 'hs-CRP', 'u' => 'mg/L', 'r' => '<2.0'],
        ]);
    }

    public static function urinalysisDipstick(): array
    {
        $negPos = ['Negative', 'Trace', '1+', '2+', '3+', '4+', 'Not performed'];
        $qual = ['Negative', 'Positive', 'Not performed'];

        return self::panel('Urinalysis — dipstick', [
            ['k' => 'color', 'l' => 'Color', 'i' => 'select', 'o' => ['Yellow', 'Amber', 'Red', 'Brown', 'Other']],
            ['k' => 'clarity', 'l' => 'Clarity', 'i' => 'select', 'o' => ['Clear', 'Slightly hazy', 'Cloudy', 'Turbid']],
            ['k' => 'sg', 'l' => 'Specific gravity', 'u' => '', 'r' => '1.005–1.030'],
            ['k' => 'ph', 'l' => 'pH', 'u' => '', 'r' => '4.5–8.0'],
            ['k' => 'protein', 'l' => 'Protein', 'i' => 'select', 'o' => $negPos],
            ['k' => 'glucose', 'l' => 'Glucose', 'i' => 'select', 'o' => $negPos],
            ['k' => 'ketones', 'l' => 'Ketones', 'i' => 'select', 'o' => $negPos],
            ['k' => 'blood', 'l' => 'Blood', 'i' => 'select', 'o' => $negPos],
            ['k' => 'bilirubin', 'l' => 'Bilirubin', 'i' => 'select', 'o' => $qual],
            ['k' => 'urobilinogen', 'l' => 'Urobilinogen', 'i' => 'select', 'o' => ['Normal', 'Increased', 'Not performed']],
            ['k' => 'nitrite', 'l' => 'Nitrite', 'i' => 'select', 'o' => $qual],
            ['k' => 'leuk', 'l' => 'Leukocyte esterase', 'i' => 'select', 'o' => $qual],
        ]);
    }

    public static function urinalysisMicroscopy(): array
    {
        return self::panel('Urinalysis — microscopy', [
            ['k' => 'rbc_hpf', 'l' => 'RBC', 'u' => '/HPF', 'r' => '0–3'],
            ['k' => 'wbc_hpf', 'l' => 'WBC', 'u' => '/HPF', 'r' => '0–5'],
            ['k' => 'epi', 'l' => 'Squamous epithelial', 'u' => '/HPF', 'r' => 'occasional'],
            ['k' => 'casts', 'l' => 'Casts', 'u' => '/LPF', 'r' => 'none seen'],
            ['k' => 'crystals', 'l' => 'Crystals', 'u' => '', 'r' => 'none'],
            ['k' => 'bacteria', 'l' => 'Bacteria', 'u' => '', 'r' => 'none/few'],
            ['k' => 'yeast', 'l' => 'Yeast', 'u' => '', 'r' => 'none'],
        ]);
    }

    public static function stoolOandP(): array
    {
        $seenPresent = ['None seen', 'Present', 'Not performed'];
        $notSeenPresent = ['Not seen', 'Present', 'Not performed'];
        $uDash = ['—', 'N/A'];
        $uHpf = ['/HPF', '/LPF', '—'];
        $refRbc = ['0–2', '0–3', '0–5', '1–3', '—'];
        $refPus = ['0–5', '0–10', '1–5', '5–10', '—'];
        $npRef = ['None seen', 'Present', 'Not performed', 'Rare', '—'];
        $consistencyRef = ['Formed', 'Soft', 'Loose', 'Watery', '—'];
        $colorRef = ['Brown', 'Yellow', 'Green', 'Black', 'Red', 'Other', '—'];
        $occultRef = ['Negative', 'Positive', 'Not performed', '—'];
        $mucusRef = array_merge($notSeenPresent, ['+', '++', '—']);

        return self::panel('Stool O&P Examination', [
            ['k' => 'consistency', 'l' => 'Consistency', 'i' => 'select', 'o' => ['Formed', 'Soft', 'Loose', 'Watery'], 'unit_options' => $uDash, 'reference_options' => $consistencyRef],
            ['k' => 'color', 'l' => 'Color', 'i' => 'select', 'o' => ['Brown', 'Yellow', 'Green', 'Black', 'Red', 'Other'], 'unit_options' => $uDash, 'reference_options' => $colorRef],
            ['k' => 'occult_blood', 'l' => 'Occult blood', 'i' => 'select', 'o' => ['Negative', 'Positive', 'Not performed'], 'unit_options' => $uDash, 'reference_options' => $occultRef],
            ['k' => 'ova', 'l' => 'Ova', 'i' => 'select', 'o' => $seenPresent, 'unit_options' => $uDash, 'reference_options' => $npRef],
            ['k' => 'parasites', 'l' => 'Parasites', 'i' => 'select', 'o' => $seenPresent, 'unit_options' => $uDash, 'reference_options' => $npRef],
            ['k' => 'cysts', 'l' => 'Cysts', 'i' => 'select', 'o' => $seenPresent, 'unit_options' => $uDash, 'reference_options' => $npRef],
            ['k' => 'trophozoites', 'l' => 'Trophozoites', 'i' => 'select', 'o' => $seenPresent, 'unit_options' => $uDash, 'reference_options' => $npRef],
            ['k' => 'rbcs', 'l' => 'RBCs', 'u' => '/HPF', 'r' => '0–2', 'unit_options' => $uHpf, 'reference_options' => $refRbc],
            ['k' => 'pus_cells', 'l' => 'Pus cells', 'u' => '/HPF', 'r' => '0–5', 'unit_options' => $uHpf, 'reference_options' => $refPus],
            ['k' => 'mucus', 'l' => 'Mucus', 'i' => 'select', 'o' => $notSeenPresent, 'unit_options' => $uDash, 'reference_options' => $mucusRef],
            ['k' => 'yeast', 'l' => 'Yeast', 'i' => 'select', 'o' => $notSeenPresent, 'unit_options' => $uDash, 'reference_options' => $mucusRef],
            ['k' => 'fat_globules', 'l' => 'Fat globules', 'i' => 'select', 'o' => $notSeenPresent, 'unit_options' => $uDash, 'reference_options' => $mucusRef],
            ['k' => 'undigested_food', 'l' => 'Undigested food', 'i' => 'select', 'o' => ['None', 'Few', 'Moderate', 'Not performed'], 'unit_options' => $uDash, 'reference_options' => ['None', 'Few', 'Moderate', 'Not performed', '—']],
            ['k' => 'comment', 'l' => 'Comment', 'r' => 'e.g. No ova or parasites detected.', 'multiline' => true, 'unit_options' => $uDash, 'reference_options' => ['—', 'No ova or parasites detected.', 'Ova and/or parasites seen — see description.', 'Further evaluation recommended.']],
        ]);
    }

    public static function hepatitisPanel(): array
    {
        $q = ['Negative', 'Positive', 'Borderline', 'Not performed'];

        return self::panel('Viral hepatitis serology', [
            ['k' => 'hbsag', 'l' => 'HBsAg', 'i' => 'select', 'o' => $q],
            ['k' => 'antihbs', 'l' => 'Anti-HBs', 'i' => 'select', 'o' => $q],
            ['k' => 'antihbc_total', 'l' => 'Anti-HBc total', 'i' => 'select', 'o' => $q],
            ['k' => 'antihbc_igm', 'l' => 'Anti-HBc IgM', 'i' => 'select', 'o' => $q],
            ['k' => 'hbeag', 'l' => 'HBeAg', 'i' => 'select', 'o' => $q],
            ['k' => 'antihbe', 'l' => 'Anti-HBe', 'i' => 'select', 'o' => $q],
            ['k' => 'antihcv', 'l' => 'Anti-HCV', 'i' => 'select', 'o' => $q],
            ['k' => 'antihav_igm', 'l' => 'Anti-HAV IgM', 'i' => 'select', 'o' => $q],
            ['k' => 'antihav_total', 'l' => 'Anti-HAV total', 'i' => 'select', 'o' => $q],
        ]);
    }

    public static function hivPanel(): array
    {
        $q = ['Non-reactive', 'Reactive', 'Indeterminate', 'Not performed'];

        return self::panel('HIV testing', [
            ['k' => 'hiv_ab', 'l' => 'HIV Ag/Ab combo', 'i' => 'select', 'o' => $q],
            ['k' => 'hiv_pcr', 'l' => 'HIV RNA (PCR)', 'u' => 'copies/mL', 'r' => 'not detected'],
        ]);
    }

    public static function torchPanel(): array
    {
        $q = ['Negative', 'Positive', 'Equivocal', 'Not performed'];

        return self::panel('TORCH / perinatal serology', [
            ['k' => 'toxo_igg', 'l' => 'Toxoplasma IgG', 'i' => 'select', 'o' => $q],
            ['k' => 'toxo_igm', 'l' => 'Toxoplasma IgM', 'i' => 'select', 'o' => $q],
            ['k' => 'rub_igg', 'l' => 'Rubella IgG', 'i' => 'select', 'o' => $q],
            ['k' => 'rub_igm', 'l' => 'Rubella IgM', 'i' => 'select', 'o' => $q],
            ['k' => 'cmv_igg', 'l' => 'CMV IgG', 'i' => 'select', 'o' => $q],
            ['k' => 'cmv_igm', 'l' => 'CMV IgM', 'i' => 'select', 'o' => $q],
            ['k' => 'hsv1_igg', 'l' => 'HSV-1 IgG', 'i' => 'select', 'o' => $q],
            ['k' => 'hsv2_igg', 'l' => 'HSV-2 IgG', 'i' => 'select', 'o' => $q],
        ]);
    }

    public static function tumorMarkers(): array
    {
        return self::panel('Tumor markers (example panel)', [
            ['k' => 'psa', 'l' => 'PSA total', 'u' => 'ng/mL', 'r' => '<4.0'],
            ['k' => 'psa_free', 'l' => 'PSA free', 'u' => 'ng/mL', 'r' => '—'],
            ['k' => 'cea', 'l' => 'CEA', 'u' => 'ng/mL', 'r' => '<3.0 (non-smoker)'],
            ['k' => 'ca125', 'l' => 'CA 125', 'u' => 'U/mL', 'r' => '<35'],
            ['k' => 'ca199', 'l' => 'CA 19-9', 'u' => 'U/mL', 'r' => '<37'],
            ['k' => 'afp', 'l' => 'AFP', 'u' => 'ng/mL', 'r' => '<10'],
            ['k' => 'ca153', 'l' => 'CA 15-3', 'u' => 'U/mL', 'r' => '<30'],
        ]);
    }

    public static function vitaminsPanel(): array
    {
        return self::panel('Vitamins & minerals', [
            ['k' => 'vit_d', 'l' => '25-OH Vitamin D', 'u' => 'ng/mL', 'r' => '30–100'],
            ['k' => 'vit_b12', 'l' => 'Vitamin B12', 'u' => 'pg/mL', 'r' => '200–900'],
            ['k' => 'folate', 'l' => 'Folate', 'u' => 'ng/mL', 'r' => '>3.0'],
            ['k' => 'ferritin', 'l' => 'Ferritin', 'u' => 'ng/mL', 'r' => '20–250'],
            ['k' => 'iron', 'l' => 'Iron', 'u' => 'µg/dL', 'r' => '60–170'],
            ['k' => 'zinc', 'l' => 'Zinc', 'u' => 'µg/dL', 'r' => '60–130'],
            ['k' => 'mag', 'l' => 'Magnesium', 'u' => 'mg/dL', 'r' => '1.7–2.2'],
        ]);
    }

    public static function boneMetabolism(): array
    {
        return self::panel('Bone metabolism', [
            ['k' => 'ca', 'l' => 'Calcium', 'u' => 'mg/dL', 'r' => '8.5–10.5'],
            ['k' => 'phos', 'l' => 'Phosphorus', 'u' => 'mg/dL', 'r' => '2.5–4.5'],
            ['k' => 'alp_bone', 'l' => 'ALP (bone-specific)', 'u' => 'U/L', 'r' => 'method-specific'],
            ['k' => 'pth', 'l' => 'PTH intact', 'u' => 'pg/mL', 'r' => '15–65'],
            ['k' => 'vit_d', 'l' => '25-OH Vitamin D', 'u' => 'ng/mL', 'r' => '30–100'],
        ]);
    }

    public static function pancreatic(): array
    {
        return self::panel('Pancreatic enzymes', [
            ['k' => 'amylase', 'l' => 'Amylase', 'u' => 'U/L', 'r' => '30–110'],
            ['k' => 'lipase', 'l' => 'Lipase', 'u' => 'U/L', 'r' => '7–60'],
        ]);
    }

    public static function bloodGas(): array
    {
        return self::panel('Arterial blood gas', [
            ['k' => 'ph', 'l' => 'pH', 'u' => '', 'r' => '7.35–7.45'],
            ['k' => 'pco2', 'l' => 'pCO₂', 'u' => 'mmHg', 'r' => '35–45'],
            ['k' => 'po2', 'l' => 'pO₂', 'u' => 'mmHg', 'r' => '80–100'],
            ['k' => 'hco3', 'l' => 'HCO₃⁻', 'u' => 'mmol/L', 'r' => '22–26'],
            ['k' => 'be', 'l' => 'Base excess', 'u' => 'mmol/L', 'r' => '−2 to +2'],
            ['k' => 'lactate', 'l' => 'Lactate', 'u' => 'mmol/L', 'r' => '0.5–2.2'],
            ['k' => 'o2sat', 'l' => 'O₂ saturation', 'u' => '%', 'r' => '95–100'],
        ]);
    }

    public static function csfBasic(): array
    {
        return self::panel('CSF analysis', [
            ['k' => 'appearance', 'l' => 'Appearance', 'i' => 'select', 'o' => ['Clear', 'Cloudy', 'Xanthochromic', 'Bloody']],
            ['k' => 'opening_pressure', 'l' => 'Opening pressure', 'u' => 'cmH₂O', 'r' => '6–20'],
            ['k' => 'wbc', 'l' => 'WBC count', 'u' => '/µL', 'r' => '0–5'],
            ['k' => 'rbc', 'l' => 'RBC count', 'u' => '/µL', 'r' => '0'],
            ['k' => 'glucose', 'l' => 'Glucose', 'u' => 'mg/dL', 'r' => '40–70'],
            ['k' => 'protein', 'l' => 'Protein', 'u' => 'mg/dL', 'r' => '15–45'],
        ]);
    }

    public static function semenAnalysis(): array
    {
        return self::panel('Semen analysis (basic)', [
            ['k' => 'volume', 'l' => 'Volume', 'u' => 'mL', 'r' => '≥1.5'],
            ['k' => 'conc', 'l' => 'Concentration', 'u' => '×10⁶/mL', 'r' => '≥15'],
            ['k' => 'motility', 'l' => 'Progressive motility', 'u' => '%', 'r' => '≥32'],
            ['k' => 'morphology', 'l' => 'Normal morphology (strict)', 'u' => '%', 'r' => '≥4'],
            ['k' => 'ph', 'l' => 'pH', 'u' => '', 'r' => '≥7.2'],
        ]);
    }

    public static function cultureReport(): array
    {
        return self::panel('Culture & sensitivity', [
            ['k' => 'specimen', 'l' => 'Specimen type', 'u' => '', 'r' => '—'],
            ['k' => 'gram', 'l' => 'Gram stain', 'u' => '', 'r' => '—'],
            ['k' => 'growth', 'l' => 'Growth / significance', 'u' => '', 'r' => '—'],
            ['k' => 'organism', 'l' => 'Organism(s)', 'u' => '', 'r' => '—'],
            ['k' => 'sensitivity', 'l' => 'Antibiogram / MIC notes', 'u' => '', 'r' => '—'],
        ]);
    }

    public static function helicobacter(): array
    {
        $q = ['Negative', 'Positive', 'Borderline', 'Not performed'];

        return self::panel('Helicobacter pylori', [
            ['k' => 'igg', 'l' => 'Anti–H. pylori IgG', 'i' => 'select', 'o' => $q],
            ['k' => 'igm', 'l' => 'Anti–H. pylori IgM', 'i' => 'select', 'o' => $q],
            ['k' => 'iga', 'l' => 'Anti–H. pylori IgA', 'i' => 'select', 'o' => $q],
            ['k' => 'urea_breath', 'l' => 'Urea breath test', 'i' => 'select', 'o' => ['Negative', 'Positive', 'Not performed']],
            ['k' => 'stool_ag', 'l' => 'Stool antigen', 'i' => 'select', 'o' => ['Negative', 'Positive', 'Not performed']],
            ['k' => 'method', 'l' => 'Method / kit', 'u' => '', 'r' => '—'],
        ]);
    }

    public static function syphilisPanel(): array
    {
        $q = ['Non-reactive', 'Reactive', 'Indeterminate', 'Not performed'];

        return self::panel('Syphilis serology', [
            ['k' => 'rpr', 'l' => 'RPR / VDRL screen', 'i' => 'select', 'o' => $q],
            ['k' => 'titer', 'l' => 'RPR titer', 'u' => '', 'r' => '—'],
            ['k' => 'tppa', 'l' => 'TPPA / TPHA', 'i' => 'select', 'o' => $q],
            ['k' => 'fta', 'l' => 'FTA-ABS', 'i' => 'select', 'o' => $q],
        ]);
    }

    public static function autoimmuneBasic(): array
    {
        $q = ['Negative', 'Positive', 'Weak positive', 'Not performed'];

        return self::panel('Autoimmune screen', [
            ['k' => 'ana', 'l' => 'ANA', 'i' => 'select', 'o' => $q],
            ['k' => 'ana_pattern', 'l' => 'ANA pattern', 'u' => '', 'r' => '—'],
            ['k' => 'rf', 'l' => 'Rheumatoid factor', 'u' => 'IU/mL', 'r' => '<14'],
            ['k' => 'ccp', 'l' => 'Anti-CCP', 'u' => 'U/mL', 'r' => '<20'],
        ]);
    }

    public static function celiacPanel(): array
    {
        $q = ['Negative', 'Positive', 'Borderline', 'Not performed'];

        return self::panel('Celiac serology', [
            ['k' => 'ttg_iga', 'l' => 'tTG-IgA', 'i' => 'select', 'o' => $q],
            ['k' => 'ttg_igg', 'l' => 'tTG-IgG', 'i' => 'select', 'o' => $q],
            ['k' => 'ema', 'l' => 'EMA IgA', 'i' => 'select', 'o' => $q],
            ['k' => 'total_iga', 'l' => 'Total IgA', 'u' => 'mg/dL', 'r' => '70–400'],
        ]);
    }

    public static function bloodGroup(): array
    {
        return self::panel('ABO & Rh', [
            ['k' => 'abo', 'l' => 'ABO group', 'i' => 'select', 'o' => ['A', 'B', 'AB', 'O', 'Not determined']],
            ['k' => 'rh', 'l' => 'Rh(D)', 'i' => 'select', 'o' => ['Positive', 'Negative', 'Weak D', 'Not determined']],
            ['k' => 'antibody_screen', 'l' => 'Antibody screen', 'i' => 'select', 'o' => ['Negative', 'Positive', 'Not performed']],
        ]);
    }

    public static function pcrQualitative(): array
    {
        return self::panel('Molecular — qualitative PCR', [
            ['k' => 'result', 'l' => 'Result', 'i' => 'select', 'o' => ['Not detected', 'Detected', 'Inhibited', 'Invalid']],
            ['k' => 'ct', 'l' => 'Ct / comment', 'u' => '', 'r' => '—'],
        ]);
    }

    public static function pcrViralLoad(): array
    {
        return self::panel('Molecular — quantitative', [
            ['k' => 'copies', 'l' => 'Copies/mL', 'u' => 'copies/mL', 'r' => 'not detected / LLOQ'],
            ['k' => 'log', 'l' => 'Log₁₀', 'u' => '', 'r' => '—'],
            ['k' => 'iu', 'l' => 'IU/mL (if applicable)', 'u' => 'IU/mL', 'r' => '—'],
        ]);
    }

    public static function therapeuticDrug(): array
    {
        return self::panel('Therapeutic drug monitoring', [
            ['k' => 'level', 'l' => 'Drug level', 'u' => 'µg/mL', 'r' => 'therapeutic range — method-specific'],
            ['k' => 'trough', 'l' => 'Trough (Y/N)', 'i' => 'select', 'o' => ['Yes', 'No', 'N/A']],
            ['k' => 'peak', 'l' => 'Peak (Y/N)', 'i' => 'select', 'o' => ['Yes', 'No', 'N/A']],
        ]);
    }

    public static function hormoneFemale(): array
    {
        return self::panel('Female hormones (cycle-dependent)', [
            ['k' => 'fsh', 'l' => 'FSH', 'u' => 'mIU/mL', 'r' => 'follicular 3.5–12.5'],
            ['k' => 'lh', 'l' => 'LH', 'u' => 'mIU/mL', 'r' => 'follicular 2.4–12.6'],
            ['k' => 'e2', 'l' => 'Estradiol', 'u' => 'pg/mL', 'r' => 'cycle-dependent'],
            ['k' => 'prog', 'l' => 'Progesterone', 'u' => 'ng/mL', 'r' => 'luteal 5–20'],
            ['k' => 'prol', 'l' => 'Prolactin', 'u' => 'ng/mL', 'r' => '4.8–23.3 (F)'],
        ]);
    }

    public static function hormoneMale(): array
    {
        return self::panel('Male hormones', [
            ['k' => 'testo_total', 'l' => 'Testosterone total', 'u' => 'ng/dL', 'r' => '300–1000'],
            ['k' => 'testo_free', 'l' => 'Testosterone free', 'u' => 'pg/mL', 'r' => '9.3–26.5'],
            ['k' => 'shbg', 'l' => 'SHBG', 'u' => 'nmol/L', 'r' => '10–57'],
            ['k' => 'psa', 'l' => 'PSA total', 'u' => 'ng/mL', 'r' => '<4.0'],
        ]);
    }

    public static function cortisolActh(): array
    {
        return self::panel('Adrenal axis', [
            ['k' => 'cort_am', 'l' => 'Cortisol (AM)', 'u' => 'µg/dL', 'r' => '6.2–19.4'],
            ['k' => 'cort_pm', 'l' => 'Cortisol (PM)', 'u' => 'µg/dL', 'r' => '2.3–11.9'],
            ['k' => 'acth', 'l' => 'ACTH', 'u' => 'pg/mL', 'r' => '7.2–63.3'],
            ['k' => 'aldosterone', 'l' => 'Aldosterone', 'u' => 'ng/dL', 'r' => 'upright 4–31'],
        ]);
    }

    public static function diabetesPanel(): array
    {
        return self::panel('Diabetes-related', [
            ['k' => 'glu_f', 'l' => 'Glucose fasting', 'u' => 'mg/dL', 'r' => '70–100'],
            ['k' => 'glu_r', 'l' => 'Glucose random', 'u' => 'mg/dL', 'r' => '<140 (2h PP)'],
            ['k' => 'hba1c', 'l' => 'HbA1c', 'u' => '%', 'r' => '<5.7'],
            ['k' => 'fructosamine', 'l' => 'Fructosamine', 'u' => 'µmol/L', 'r' => '200–285'],
            ['k' => 'insulin', 'l' => 'Insulin', 'u' => 'µIU/mL', 'r' => '2.6–24.9'],
            ['k' => 'c_peptide', 'l' => 'C-peptide', 'u' => 'ng/mL', 'r' => '0.9–7.1'],
        ]);
    }

    public static function proteinElectrophoresis(): array
    {
        return self::panel('Serum protein electrophoresis', [
            ['k' => 'albumin_pct', 'l' => 'Albumin', 'u' => '%', 'r' => '55–65'],
            ['k' => 'alpha1', 'l' => 'Alpha-1', 'u' => '%', 'r' => '2.7–4.5'],
            ['k' => 'alpha2', 'l' => 'Alpha-2', 'u' => '%', 'r' => '4.3–11.2'],
            ['k' => 'beta', 'l' => 'Beta', 'u' => '%', 'r' => '8.0–13.5'],
            ['k' => 'gamma', 'l' => 'Gamma', 'u' => '%', 'r' => '11–18'],
            ['k' => 'm_spike', 'l' => 'M-spike (if present)', 'u' => 'g/dL', 'r' => 'none'],
        ]);
    }

    public static function ammoniaLactate(): array
    {
        return self::panel('Critical care metabolites', [
            ['k' => 'nh3', 'l' => 'Ammonia', 'u' => 'µmol/L', 'r' => '11–35'],
            ['k' => 'lactate', 'l' => 'Lactate', 'u' => 'mmol/L', 'r' => '0.5–2.2'],
        ]);
    }

    public static function osmolality(): array
    {
        return self::panel('Osmolality', [
            ['k' => 'ser_osm', 'l' => 'Serum osmolality', 'u' => 'mOsm/kg', 'r' => '275–295'],
            ['k' => 'ur_osm', 'l' => 'Urine osmolality', 'u' => 'mOsm/kg', 'r' => '50–1200'],
        ]);
    }

    public static function ckTotal(): array
    {
        return self::single('CK total', '30–200', 'U/L');
    }

    /**
     * @return list<array{k:string,l:string,u?:string,r?:string}>
     */
    private static function histopathNarrativeCore(): array
    {
        return [
            ['k' => 'clinical', 'l' => 'Clinical information', 'u' => '', 'r' => '—'],
            ['k' => 'specimen', 'l' => 'Specimen / site', 'u' => '', 'r' => '—'],
            ['k' => 'gross', 'l' => 'Gross description', 'u' => '', 'r' => '—'],
            ['k' => 'micro', 'l' => 'Microscopic description', 'u' => '', 'r' => '—'],
            ['k' => 'diagnosis', 'l' => 'Diagnosis', 'u' => '', 'r' => '—'],
            ['k' => 'ihc', 'l' => 'Immunohistochemistry summary', 'u' => '', 'r' => '—'],
            ['k' => 'comment', 'l' => 'Comment / synoptic', 'u' => '', 'r' => '—'],
        ];
    }

    public static function histopathStandard(): array
    {
        return self::panel('Histopathology — tissue / biopsy', self::histopathNarrativeCore());
    }

    public static function papSmearCytology(): array
    {
        $bethesda = ['Negative for intraepithelial lesion', 'ASC-US', 'LSIL', 'HSIL', 'AGC', 'AIS', 'Squamous carcinoma', 'Unsatisfactory', 'Not performed'];

        return self::panel('Cervical cytology (Pap smear)', [
            ['k' => 'adequacy', 'l' => 'Specimen adequacy', 'i' => 'select', 'o' => ['Satisfactory for evaluation', 'Limited', 'Unsatisfactory']],
            ['k' => 'interpretation', 'l' => 'Interpretation (summary)', 'i' => 'select', 'o' => $bethesda],
            ['k' => 'hpv', 'l' => 'HPV test (if done)', 'i' => 'select', 'o' => ['Not performed', 'Negative', 'Positive (other)', '16/18 positive']],
            ['k' => 'comment', 'l' => 'Comments', 'u' => '', 'r' => '—'],
        ]);
    }

    public static function fnacCytology(): array
    {
        return self::panel('Fine-needle aspiration (FNAC)', [
            ['k' => 'site', 'l' => 'Site / lesion', 'u' => '', 'r' => '—'],
            ['k' => 'passes', 'l' => 'Passes / adequacy', 'u' => '', 'r' => '—'],
            ['k' => 'diagnosis', 'l' => 'Cytologic diagnosis', 'i' => 'select', 'o' => ['Benign', 'Atypical', 'Suspicious', 'Malignant', 'Non-diagnostic', 'See comment']],
            ['k' => 'micro', 'l' => 'Cellular description', 'u' => '', 'r' => '—'],
            ['k' => 'recommendation', 'l' => 'Recommendation', 'u' => '', 'r' => '—'],
        ]);
    }

    public static function bodyFluidCytology(): array
    {
        return self::panel('Body fluid cytology', [
            ['k' => 'fluid_type', 'l' => 'Fluid type', 'i' => 'select', 'o' => ['Pleural', 'Peritoneal', 'Pericardial', 'Synovial', 'Urine', 'Bronchial washing', 'Other']],
            ['k' => 'cell_count', 'l' => 'Total nucleated cells', 'u' => '/µL', 'r' => '—'],
            ['k' => 'differential', 'l' => 'Differential / description', 'u' => '', 'r' => '—'],
            ['k' => 'malignancy', 'l' => 'Malignant cells', 'i' => 'select', 'o' => ['Not seen', 'Suspicious', 'Positive', 'Not performed']],
        ]);
    }

    public static function drugsOfAbuseUrinePanel(): array
    {
        $r = ['Negative', 'Positive', 'Invalid / adulterated'];

        return self::panel('Drugs of abuse — urine screen', [
            ['k' => 'amp', 'l' => 'Amphetamines / methamphetamine', 'i' => 'select', 'o' => $r],
            ['k' => 'coc', 'l' => 'Cocaine metabolites', 'i' => 'select', 'o' => $r],
            ['k' => 'opi', 'l' => 'Opiates / opioids', 'i' => 'select', 'o' => $r],
            ['k' => 'thc', 'l' => 'Cannabinoids (THC)', 'i' => 'select', 'o' => $r],
            ['k' => 'bzo', 'l' => 'Benzodiazepines', 'i' => 'select', 'o' => $r],
            ['k' => 'barb', 'l' => 'Barbiturates', 'i' => 'select', 'o' => $r],
            ['k' => 'pcp', 'l' => 'PCP', 'i' => 'select', 'o' => $r],
            ['k' => 'mtd', 'l' => 'Methadone', 'i' => 'select', 'o' => $r],
            ['k' => 'oxy', 'l' => 'Oxycodone', 'i' => 'select', 'o' => $r],
            ['k' => 'bup', 'l' => 'Buprenorphine', 'i' => 'select', 'o' => $r],
            ['k' => 'mdma', 'l' => 'MDMA / ecstasy', 'i' => 'select', 'o' => $r],
            ['k' => 'comment', 'l' => 'Confirmatory / notes', 'u' => '', 'r' => '—'],
        ]);
    }

    public static function heavyMetalsBlood(): array
    {
        return self::panel('Heavy metals — blood', [
            ['k' => 'pb', 'l' => 'Lead', 'u' => 'µg/dL', 'r' => '<5 (CDC ref)'],
            ['k' => 'hg', 'l' => 'Mercury', 'u' => 'µg/L', 'r' => 'lab-specific'],
            ['k' => 'as', 'l' => 'Arsenic (inorganic)', 'u' => 'µg/L', 'r' => 'lab-specific'],
            ['k' => 'cd', 'l' => 'Cadmium', 'u' => 'µg/L', 'r' => 'lab-specific'],
        ]);
    }

    public static function carbonMonoxidePoisoning(): array
    {
        return self::panel('Carbon monoxide exposure', [
            ['k' => 'cohb', 'l' => 'Carboxyhemoglobin', 'u' => '%', 'r' => '<3 non-smoker'],
            ['k' => 'clinical', 'l' => 'Clinical correlation', 'u' => '', 'r' => '—'],
        ]);
    }

    public static function allergyFoodIgEPanel(): array
    {
        $cls = ['Class 0 / negative', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Not performed'];

        return self::panel('Food-specific IgE panel (example allergens)', [
            ['k' => 'milk', 'l' => 'Milk (f2)', 'i' => 'select', 'o' => $cls],
            ['k' => 'egg', 'l' => 'Egg white (f1)', 'i' => 'select', 'o' => $cls],
            ['k' => 'peanut', 'l' => 'Peanut (f13)', 'i' => 'select', 'o' => $cls],
            ['k' => 'tree_nuts', 'l' => 'Tree nut mix', 'i' => 'select', 'o' => $cls],
            ['k' => 'wheat', 'l' => 'Wheat (f4)', 'i' => 'select', 'o' => $cls],
            ['k' => 'soy', 'l' => 'Soy (f14)', 'i' => 'select', 'o' => $cls],
            ['k' => 'fish', 'l' => 'Fish mix', 'i' => 'select', 'o' => $cls],
            ['k' => 'shellfish', 'l' => 'Shellfish mix', 'i' => 'select', 'o' => $cls],
            ['k' => 'total_ige', 'l' => 'Total IgE', 'u' => 'IU/mL', 'r' => '<100'],
        ]);
    }

    public static function allergyInhalantIgEPanel(): array
    {
        $cls = ['Class 0 / negative', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Not performed'];

        return self::panel('Inhalant-specific IgE panel (example)', [
            ['k' => 'dust', 'l' => 'Dust mite (D1/D2)', 'i' => 'select', 'o' => $cls],
            ['k' => 'grass', 'l' => 'Grass pollens', 'i' => 'select', 'o' => $cls],
            ['k' => 'tree', 'l' => 'Tree pollens', 'i' => 'select', 'o' => $cls],
            ['k' => 'weed', 'l' => 'Weed pollens', 'i' => 'select', 'o' => $cls],
            ['k' => 'cat', 'l' => 'Cat dander (e1)', 'i' => 'select', 'o' => $cls],
            ['k' => 'dog', 'l' => 'Dog dander (e5)', 'i' => 'select', 'o' => $cls],
            ['k' => 'mold', 'l' => 'Mold mix', 'i' => 'select', 'o' => $cls],
            ['k' => 'total_ige', 'l' => 'Total IgE', 'u' => 'IU/mL', 'r' => '<100'],
        ]);
    }

    public static function immunoglobulinQuantitation(): array
    {
        return self::panel('Immunoglobulins (quantitative)', [
            ['k' => 'igg', 'l' => 'IgG', 'u' => 'mg/dL', 'r' => '700–1600'],
            ['k' => 'iga', 'l' => 'IgA', 'u' => 'mg/dL', 'r' => '70–400'],
            ['k' => 'igm', 'l' => 'IgM', 'u' => 'mg/dL', 'r' => '40–230'],
            ['k' => 'ige', 'l' => 'IgE total', 'u' => 'IU/mL', 'r' => '<100'],
        ]);
    }

    public static function complementActivity(): array
    {
        return self::panel('Complement', [
            ['k' => 'c3', 'l' => 'C3', 'u' => 'mg/dL', 'r' => '90–180'],
            ['k' => 'c4', 'l' => 'C4', 'u' => 'mg/dL', 'r' => '10–40'],
            ['k' => 'ch50', 'l' => 'CH50 (total hemolytic)', 'u' => 'U/mL', 'r' => 'method-specific'],
        ]);
    }
}
