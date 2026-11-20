<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Binary Executables Paths
    |--------------------------------------------------------------------------
    */
    'libreoffice_binary' => env('LIBREOFFICE_BINARY', 'C:\\Program Files\\LibreOffice\\program\\soffice.exe'),
    'poppler_pdftotext' => env('POPPLER_PDFTOTEXT', base_path('bin/poppler/poppler-26.09.0/Library/bin/pdftotext.exe')),
    'poppler_pdfinfo' => env('POPPLER_PDFINFO', base_path('bin/poppler/poppler-26.09.0/Library/bin/pdfinfo.exe')),

    /*
    |--------------------------------------------------------------------------
    | Upload & Processing Limits
    |--------------------------------------------------------------------------
    */
    'max_file_size_kb' => env('NOTARY_MAX_FILE_SIZE_KB', 25600), // 25 MB
    'max_page_limit' => env('NOTARY_MAX_PAGE_LIMIT', 100),
    'process_timeout_seconds' => env('NOTARY_PROCESS_TIMEOUT_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Default Stamp Configuration
    |--------------------------------------------------------------------------
    | 3 mandatory elements in red:
    | 1. Notary name
    | 2. Single horizontal red line
    | 3. Text "NOTARIS KOTA DENPASAR"
    | No box, circle, or outer decorative lines.
    */
    'default_stamp' => [
        'notary_name' => 'ROBERT, S.H., M.Kn.',
        'city_title' => 'NOTARIS KOTA DENPASAR',
        'color' => '#DC2626', // Red
        'font_family' => 'Helvetica',
        'font_size_name' => 11,
        'font_size_city' => 9,
        'line_thickness' => 1.2,
        'line_length' => 180, // in points
        'element_spacing' => 4, // in points
        'target_pages' => 'first_page', // 'first_page', 'all_pages', 'custom'
        'custom_pages' => '',
        'preset' => 'top_left', // 'top_left', 'bottom_left', 'bottom_center', 'signature_area', 'custom'
        'x_percent' => 8.0,
        'y_percent' => 8.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Dynamic Draft Line Configuration
    |--------------------------------------------------------------------------
    */
    'default_draft_lines' => [
        'enabled' => true,
        'color' => '#DC2626', // Red by default for notary draft
        'thickness' => 1.2, // pt
        'offset' => 8.0, // pt distance to the left of text block (dynamically placed)
        'padding_vertical' => 1.0, // pt vertical padding for line top/bottom
        'paragraph_threshold' => 1.6, // line spacing multiplier to detect paragraph break
        'ignore_headers_footers' => true,
        'header_margin_percent' => 5.0, // Top 5% of page considered header area
        'footer_margin_percent' => 5.0, // Bottom 5% of page considered footer area
        'target_pages' => 'all_pages', // 'all_pages', 'first_page', 'custom'
        'custom_pages' => '',
        'line_style' => 'dashed', // 'solid' or 'dashed' — dashed looks cleaner for draft lines
        'dash_length' => 4.0, // pt dash length (only used when line_style = 'dashed')
        'dash_gap' => 3.0,    // pt gap between dashes
    ],
];
