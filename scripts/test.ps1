#!/usr/bin/env pwsh
# ============================================================
# test.ps1 — Script unificado de testes do KALIBRIUM
#
# Uso:
#   .\test.ps1                    # Todos (Windows sequencial)
#   .\test.ps1 -Docker            # Todos (Docker paralelo — RECOMENDADO)
#   .\test.ps1 -Suite smoke       # Apenas smoke (~2s)
#   .\test.ps1 -Suite unit        # Apenas unit (~30s)
#   .\test.ps1 -Suite feature     # Apenas feature
#   .\test.ps1 -Dirty             # Apenas testes alterados
#   .\test.ps1 -Profile           # Top 10 mais lentos
#   .\test.ps1 -Frontend          # Vitest (frontend)
#   .\test.ps1 -E2E               # Playwright (E2E)
#   .\test.ps1 -All               # Backend + Frontend + E2E
# ============================================================

param(
    [switch]$Docker,
    [switch]$Dirty,
    [switch]$Profile,
    [switch]$Frontend,
    [switch]$E2E,
    [switch]$All,
    [ValidateSet('smoke','unit','feature','critical','arch','')]
    [string]$Suite = ''
)

$ErrorActionPreference = 'Continue'
$root = Split-Path $PSScriptRoot -Parent

# ── Helper ──
function Write-Header($text) {
    Write-Host "`n============================================================" -ForegroundColor Cyan
    Write-Host " $text" -ForegroundColor Cyan
    Write-Host "============================================================" -ForegroundColor Cyan
}

function Get-ElapsedText($sw) {
    $elapsed = $sw.Elapsed
    if ($elapsed.TotalMinutes -ge 1) {
        return "{0:N0}min {1:N0}s" -f $elapsed.TotalMinutes, $elapsed.Seconds
    }
    return "{0:N1}s" -f $elapsed.TotalSeconds
}

# ── Backend Tests ──
function Invoke-BackendTests {
    Write-Header "BACKEND TESTS"
    $sw = [System.Diagnostics.Stopwatch]::StartNew()

    if ($Docker) {
        Write-Host "  Modo: Docker (paralelo, 8 processos)" -ForegroundColor Green

        $service = "backend-tests"
        if ($Suite)   { $service = "backend-tests-$Suite" }
        if ($Dirty)   { $service = "backend-tests-dirty" }
        if ($Profile) { $service = "backend-tests-profile" }

        docker compose -f "$root\docker-compose.test.yml" run --rm $service
    }
    else {
        Write-Host "  Modo: Windows (sequencial — sem pcntl)" -ForegroundColor Yellow
        Write-Host "  Dica: Use -Docker para rodar em paralelo!" -ForegroundColor DarkYellow

        $pestArgs = @("--no-coverage")
        if ($Suite)   { $pestArgs += "--testsuite=$($Suite.Substring(0,1).ToUpper() + $Suite.Substring(1))" }
        if ($Dirty)   { $pestArgs += "--dirty" }
        if ($Profile) { $pestArgs += "--profile" }

        Push-Location "$root\backend"
        php vendor/bin/pest @pestArgs
        Pop-Location
    }

    $sw.Stop()
    Write-Host "`n  Tempo: $(Get-ElapsedText $sw)" -ForegroundColor Magenta
}

# ── Frontend Tests ──
function Invoke-FrontendTests {
    Write-Header "FRONTEND TESTS (Vitest)"
    $sw = [System.Diagnostics.Stopwatch]::StartNew()

    Push-Location "$root\frontend"
    npx vitest run
    Pop-Location

    $sw.Stop()
    Write-Host "`n  Tempo: $(Get-ElapsedText $sw)" -ForegroundColor Magenta
}

# ── E2E Tests ──
function Invoke-E2ETests {
    Write-Header "E2E TESTS (Playwright)"
    $sw = [System.Diagnostics.Stopwatch]::StartNew()

    Push-Location "$root\frontend"
    npx playwright test
    Pop-Location

    $sw.Stop()
    Write-Host "`n  Tempo: $(Get-ElapsedText $sw)" -ForegroundColor Magenta
}

# ── Main ──
$totalSw = [System.Diagnostics.Stopwatch]::StartNew()

if ($All) {
    Invoke-BackendTests
    Invoke-FrontendTests
    Invoke-E2ETests
}
elseif ($Frontend) {
    Invoke-FrontendTests
}
elseif ($E2E) {
    Invoke-E2ETests
}
else {
    Invoke-BackendTests
}

$totalSw.Stop()
Write-Host "`n============================================================" -ForegroundColor Green
Write-Host " TOTAL: $(Get-ElapsedText $totalSw)" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Green
