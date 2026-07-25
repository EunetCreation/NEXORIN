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
    Write-Host "[APP67M.4] $Name" -ForegroundColor Cyan
    & $Action
    if ($LASTEXITCODE -ne 0) { throw "Step failed: $Name (exit code $LASTEXITCODE)" }
}

if (-not (Test-Path -LiteralPath '.\package.json')) {
    throw 'Run this script from the NEXORIN project root.'
}

Invoke-Step 'Building production SLD browser bundle...' { npm run build:sld }
Invoke-Step 'Clearing Laravel caches...' { php artisan optimize:clear }
Invoke-Step 'Running APP67M.4 deterministic contracts...' { npm run test:sld-app67m4 }
Invoke-Step 'Verifying the production browser bundle...' { npm run test:sld-app67m4:bundle }
Invoke-Step 'Running APP67M.3 regression contracts...' { npm run test:sld-app67m3 }
Invoke-Step 'Running APP67M.2 regression contracts...' { npm run test:sld-app67m2 }
Invoke-Step 'Running Geometry Foundation regression...' { npm run test:sld-geometry }
Invoke-Step 'Running APP67M.1 performance regression...' { npm run test:sld-app67m1-hf2 }
Invoke-Step 'Running production workflow closure regression...' { npm run test:sld-app67l4-closure }

if ($RunBrowser) {
    Invoke-Step 'Running complete browser regression...' { npm run test:sld }
}

Write-Host ""
Write-Host '[APP67M.4] Validation completed successfully.' -ForegroundColor Green
if (-not $RunBrowser) {
    Write-Host 'Run npm run test:sld for the complete 90-test browser regression.' -ForegroundColor Yellow
}
