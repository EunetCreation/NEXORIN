<?php

return [
    'datasheet_parser' => [
        // Code-governed so stored parser metadata cannot be falsified by a stale .env value.
        'version' => \App\Services\Catalog\DatasheetParser::VERSION,
        'max_upload_kb' => (int) env('NEXORIN_DATASHEET_MAX_UPLOAD_KB', 10240),
        'pdftotext_binary' => env('NEXORIN_PDFTOTEXT_BINARY', 'pdftotext'),
        'process_timeout_seconds' => (int) env('NEXORIN_DATASHEET_PROCESS_TIMEOUT', 20),
        'maximum_text_length' => (int) env('NEXORIN_DATASHEET_MAX_TEXT_LENGTH', 200000),
        'temporary_directory' => env('NEXORIN_DATASHEET_TEMP_PATH', storage_path('framework/nexorin-datasheet-parser')),
    ],
];
