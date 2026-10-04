<?php

return [
    'authentication' => [
        'google_enabled' => (bool) env('GOOGLE_SSO_ENABLED', false),
        'active_company_session_key' => 'nexorin.active_company_id',
    ],

    'login_metrics' => [
        'solar_output' => env('NEXORIN_LOGIN_SOLAR_OUTPUT', '412.7'),
        'solar_output_unit' => env('NEXORIN_LOGIN_SOLAR_OUTPUT_UNIT', 'MWh'),
        'projected_generation' => env('NEXORIN_LOGIN_PROJECTED_GENERATION', '2.18'),
        'projected_generation_unit' => env('NEXORIN_LOGIN_PROJECTED_GENERATION_UNIT', 'TWh'),
        'active_projects' => env('NEXORIN_LOGIN_ACTIVE_PROJECTS', '72'),
        'total_capacity' => env('NEXORIN_LOGIN_TOTAL_CAPACITY', '1.42'),
        'total_capacity_unit' => env('NEXORIN_LOGIN_TOTAL_CAPACITY_UNIT', 'GWp'),
    ],
];
