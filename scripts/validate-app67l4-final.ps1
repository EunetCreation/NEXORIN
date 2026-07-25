param(
    [string]$PhpBinary = "php",
    [string]$ChromiumPath = "",
    [switch]$KeepDatabase
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Database = Join-Path $Root "storage\app\app67l4-final-closure.sqlite"

function Invoke-Checked {
    param([string]$Label, [scriptblock]$Command)
    Write-Host "`n[APP67L.4-FINAL] $Label" -ForegroundColor Cyan
    & $Command
    if ($LASTEXITCODE -ne 0) {
        throw "$Label failed with exit code $LASTEXITCODE."
    }
}

Push-Location $Root
try {
    if (Test-Path $Database) {
        Remove-Item $Database -Force
    }
    New-Item -ItemType File -Path $Database -Force | Out-Null

    $env:APP_ENV = "testing"
    $env:APP_KEY = "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA="
    $env:DB_CONNECTION = "sqlite"
    $env:DB_DATABASE = $Database
    $env:DB_URL = ""
    $env:CACHE_STORE = "array"
    $env:SESSION_DRIVER = "array"
    $env:QUEUE_CONNECTION = "sync"
    $env:MAIL_MAILER = "array"
    $env:NEXORIN_SLD_E2E_ONLY = "professional-topology"
    $env:NEXORIN_PHP_BINARY = $PhpBinary

    if ($ChromiumPath) {
        $env:PLAYWRIGHT_CHROMIUM_EXECUTABLE = $ChromiumPath
    }

    Invoke-Checked "Fresh migration and seeding on isolated SQLite" {
        & $PhpBinary artisan migrate:fresh --seed --force --no-interaction
    }
    Invoke-Checked "APP67L.4 production workflow" {
        & $PhpBinary scripts/test-app67l4-production-workflow.php
    }
    Invoke-Checked "APP67L.4 final closure" {
        & $PhpBinary scripts/test-app67l4-final-closure.php
    }
    Invoke-Checked "APP67L.3 six-sheet drawing regression" {
        & $PhpBinary scripts/test-app67l3-professional-a3.php
    }
    Invoke-Checked "APP67L.1 symbol regression" {
        & $PhpBinary scripts/test-app67l1-symbol-data.php
    }
    Invoke-Checked "APP67L.2 topology regression" {
        & $PhpBinary scripts/test-app67l2-professional-topology.php
    }
    Invoke-Checked "Professional-topology browser regression" {
        npm run test:sld
    }

    Write-Host "`n[APP67L.4-FINAL] PASS - fresh database, deterministic closure, and browser E2E completed." -ForegroundColor Green
}
finally {
    Pop-Location
    foreach ($name in @(
        "APP_ENV", "APP_KEY", "DB_CONNECTION", "DB_DATABASE", "DB_URL",
        "CACHE_STORE", "SESSION_DRIVER", "QUEUE_CONNECTION", "MAIL_MAILER",
        "NEXORIN_SLD_E2E_ONLY", "NEXORIN_PHP_BINARY", "PLAYWRIGHT_CHROMIUM_EXECUTABLE"
    )) {
        Remove-Item "Env:$name" -ErrorAction SilentlyContinue
    }

    if (-not $KeepDatabase -and (Test-Path $Database)) {
        Remove-Item $Database -Force
    }
}
