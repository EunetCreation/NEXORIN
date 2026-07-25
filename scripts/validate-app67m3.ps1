param(
    [switch]$RunBrowser
)

$ErrorActionPreference = 'Stop'

function Invoke-Step {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][scriptblock]$Action
    )
    Write-Host ""
    Write-Host "[APP67M.3] $Name" -ForegroundColor Cyan
    & $Action
    if ($LASTEXITCODE -ne 0) { throw "Step failed: $Name (exit code $LASTEXITCODE)" }
}

if (-not (Test-Path -LiteralPath '.\package.json')) {
    throw 'Run this script from the NEXORIN project root.'
}

Invoke-Step 'Building production SLD browser bundle...' { npm run build:sld }
Invoke-Step 'Clearing Laravel caches...' { php artisan optimize:clear }
Invoke-Step 'Running APP67M.3 deterministic contracts...' { npm run test:sld-app67m3 }
Invoke-Step 'Verifying the production browser bundle...' { npm run test:sld-app67m3:bundle }

if ($RunBrowser) {
    Invoke-Step 'Running complete browser regression...' { npm run test:sld }
}

Write-Host ""
Write-Host '[APP67M.3] Validation completed successfully.' -ForegroundColor Green
if (-not $RunBrowser) {
    Write-Host 'Run npm run test:sld for the complete 86-test browser regression.' -ForegroundColor Yellow
}
