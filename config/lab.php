<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Lab Number Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for lab number generation and management.
    |
    */

    'start_sequence' => env('LAB_START_SEQUENCE', 1),

    /*
    |--------------------------------------------------------------------------
    | Barcode Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for barcode and QR code generation.
    |
    */

    'barcode' => [
        'format' => env('LAB_BARCODE_FORMAT', 'CODE128'),
        'width' => env('LAB_BARCODE_WIDTH', 2),
        'height' => env('LAB_BARCODE_HEIGHT', 50),
    ],

    'qr_code' => [
        'size' => env('LAB_QR_SIZE', 200),
        'margin' => env('LAB_QR_MARGIN', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for storing barcode and QR code files.
    |
    */

    'storage' => [
        'disk' => env('LAB_STORAGE_DISK', 'public'),
        'barcode_path' => 'barcodes',
        'qr_code_path' => 'qrcodes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Lab Request Status Configuration
    |--------------------------------------------------------------------------
    |
    | Available statuses for lab requests.
    |
    */

    'statuses' => [
        'pending' => 'Pending',
        'received' => 'Received',
        'in_progress' => 'In Progress',
        'under_review' => 'Under Review',
        'completed' => 'Completed',
        'delivered' => 'Delivered',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sample Suffix Configuration
    |--------------------------------------------------------------------------
    |
    | Available suffixes for lab requests.
    |
    */

    'suffixes' => [
        'm' => 'Morning',
        'h' => 'Afternoon',
    ],
];
