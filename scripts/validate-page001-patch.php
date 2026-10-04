<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'app/Http/Controllers/Auth/GoogleAuthController.php',
    'app/Services/Authentication/GoogleOAuthService.php',
    'app/Services/Company/ActiveCompanyResolver.php',
    'config/nexorin.php',
    'database/migrations/2026_08_01_000001_create_external_identities_table.php',
    'database/migrations/2026_08_01_000002_create_authentication_events_table.php',
    'public/css/nexorin-auth-v6.css',
    'public/images/nexorin-wordmark-symbol-refined.png',
    'public/js/nexorin-auth-v6.js',
    'resources/views/auth/login.blade.php',
    'resources/views/auth/workspace-select.blade.php',
];

$failures = [];
foreach ($required as $file) {
    if (! is_file($root.DIRECTORY_SEPARATOR.$file)) {
        $failures[] = "Missing: {$file}";
    }
}

$login = @file_get_contents($root.'/resources/views/auth/login.blade.php') ?: '';
$fortify = @file_get_contents($root.'/config/fortify.php') ?: '';
$routes = @file_get_contents($root.'/routes/web.php') ?: '';
$config = @file_get_contents($root.'/config/nexorin.php') ?: '';
$envExample = @file_get_contents($root.'/.env.example') ?: '';
$googleService = @file_get_contents($root.'/app/Services/Authentication/GoogleOAuthService.php') ?: '';

foreach ([
    'Google provider is conditionally rendered' => str_contains($login, '@if ($googleSsoEnabled)')
        && str_contains($login, 'Continue with Google'),
    'Password-only card modifier' => str_contains($login, 'access-card--password-only'),
    'No Microsoft SSO' => ! str_contains($login, 'Continue with Microsoft'),
    'No Okta SSO' => ! str_contains($login, 'Continue with Okta'),
    'Google disabled by default in config' => str_contains($config, "env('GOOGLE_SSO_ENABLED', false)"),
    'Google disabled by default in service' => str_contains($googleService, "google_enabled', false"),
    'Environment example defaults Google off' => preg_match('/^\s*GOOGLE_SSO_ENABLED\s*=\s*(?:false|"false"|\'false\')\s*$/mi', $envExample) === 1,
    'Google route retained for optional activation' => str_contains($routes, 'auth.google.redirect'),
    'Workspace route' => str_contains($routes, 'workspace.select'),
    'Public registration disabled' => ! str_contains($fortify, 'Features::registration()'),
] as $label => $passed) {
    if (! $passed) {
        $failures[] = "Contract failed: {$label}";
    }
}

if ($failures !== []) {
    if (! is_file($root.'/.env.example')) {
        $failures[] = 'Diagnostic: .env.example tidak ditemukan di root Laravel.';
    } elseif (preg_match('/^\s*GOOGLE_SSO_ENABLED\s*=\s*(.*?)\s*$/mi', $envExample, $match) === 1) {
        $failures[] = 'Diagnostic: nilai GOOGLE_SSO_ENABLED pada .env.example adalah '.trim($match[1]).'.';
    } else {
        $failures[] = 'Diagnostic: key GOOGLE_SSO_ENABLED tidak ditemukan pada .env.example.';
    }

    $runtimeEnv = @file_get_contents($root.'/.env') ?: '';
    if (preg_match('/^\s*GOOGLE_SSO_ENABLED\s*=\s*true\s*$/mi', $runtimeEnv) === 1) {
        $failures[] = 'Diagnostic: .env runtime masih mengaktifkan Google SSO.';
    }

    fwrite(STDERR, "PAGE-001 PATCH VALIDATION: FAIL\n".implode("\n", array_unique($failures))."\n");
    exit(1);
}

echo "PAGE-001 PATCH VALIDATION: PASS\n";
