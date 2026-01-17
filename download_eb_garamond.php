<?php

/**
 * Script to download and setup EB Garamond font for mPDF
 * Run this once: php download_eb_garamond.php
 */

$fontDir = __DIR__ . '/storage/fonts';
$ttfontsDir = __DIR__ . '/vendor/mpdf/mpdf/ttfonts';

// Create directories if they don't exist
if (!is_dir($fontDir)) {
    mkdir($fontDir, 0755, true);
}

// EB Garamond font URLs from Google Fonts
$fonts = [
    'EBGaramond-Regular.ttf' => 'https://github.com/google/fonts/raw/main/ofl/ebgaramond/EBGaramond%5Bwght%5D.ttf',
    'EBGaramond-Bold.ttf' => 'https://github.com/google/fonts/raw/main/ofl/ebgaramond/static/EBGaramond-Bold.ttf',
    'EBGaramond-Italic.ttf' => 'https://github.com/google/fonts/raw/main/ofl/ebgaramond/static/EBGaramond-Italic.ttf',
    'EBGaramond-BoldItalic.ttf' => 'https://github.com/google/fonts/raw/main/ofl/ebgaramond/static/EBGaramond-BoldItalic.ttf',
];

echo "Downloading EB Garamond fonts...\n";

foreach ($fonts as $filename => $url) {
    $localPath = $fontDir . '/' . $filename;
    $ttfPath = $ttfontsDir . '/' . $filename;
    
    if (file_exists($localPath)) {
        echo "✓ {$filename} already exists\n";
    } else {
        echo "Downloading {$filename}...\n";
        $fontData = @file_get_contents($url);
        if ($fontData === false) {
            echo "✗ Failed to download {$filename}\n";
            continue;
        }
        file_put_contents($localPath, $fontData);
        echo "✓ Downloaded {$filename}\n";
    }
    
    // Copy to mPDF ttfonts directory
    if (is_dir($ttfontsDir)) {
        copy($localPath, $ttfPath);
        echo "✓ Copied {$filename} to mPDF fonts directory\n";
    }
}

echo "\nFont setup complete!\n";
echo "Fonts are in: {$fontDir}\n";
echo "Fonts copied to: {$ttfontsDir}\n";
echo "\nNext step: Update your mPDF configuration to use 'ebgaramond' as the font name.\n";


