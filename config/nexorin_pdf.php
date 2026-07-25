<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NEXORIN Premium PDF Rendering
    |--------------------------------------------------------------------------
    */

    'node_binary' => env('NEXORIN_NODE_BINARY', 'node'),

    'chromium_path' => env('NEXORIN_CHROMIUM_PATH'),

    'render_script' => env('NEXORIN_PDF_RENDER_SCRIPT', base_path('scripts/render-pdf.mjs')),

    'timeout_seconds' => (int) env('NEXORIN_PDF_TIMEOUT', 120),

    'navigation_timeout_ms' => (int) env('NEXORIN_PDF_NAVIGATION_TIMEOUT', 60000),

    'viewport_width' => (int) env('NEXORIN_PDF_VIEWPORT_WIDTH', 1240),

    'viewport_height' => (int) env('NEXORIN_PDF_VIEWPORT_HEIGHT', 1754),

    'keep_temp_html' => (bool) env('NEXORIN_PDF_KEEP_TEMP_HTML', false),

    /*
    | Writable temp directory for Windows process pipes, Node, and Chromium.
    | This prevents C:\Windows\sf_proc_*.out.lock permission errors.
    */
    'temp_dir' => env('NEXORIN_PDF_TEMP_DIR', storage_path('app/pdf-renderer/process-temp')),

    /*
    | Do not silently return the old/basic PDF from the premium route.
    | Set this true only when production needs emergency fallback.
    */
    'allow_legacy_fallback' => (bool) env('NEXORIN_PREMIUM_PDF_ALLOW_FALLBACK', false),

    /*
    | SLD PDF must normally use the same SVG renderer as the browser and SVG
    | download. Enable this only for emergency operation when Chromium cannot
    | run and visual parity is less important than producing a file.
    */
    'sld_allow_vector_fallback' => (bool) env('NEXORIN_SLD_ALLOW_VECTOR_FALLBACK', false),
];
