<?php

namespace App\Enums;

/**
 * Default lab report layout for tests in this category when no per-test report_template is set.
 */
enum TestCategoryReportType: string
{
    case Numeric = 'numeric';
    case Text = 'text';
    case Culture = 'culture';
    case Paragraph = 'paragraph';
    /** Legacy surgical/pathology ERP: one report with clinical data, specimen, gross, micro, conclusion (not per-test table). */
    case Pathology = 'pathology';
    case Single = 'single';
    case Pcr = 'pcr';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
