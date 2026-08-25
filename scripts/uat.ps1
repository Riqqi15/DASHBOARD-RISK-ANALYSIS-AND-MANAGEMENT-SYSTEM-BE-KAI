[CmdletBinding()]
param(
    [ValidateSet('setup', 'start', 'check', 'stop')]
    [string] $Action = 'check'
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
$envFile = Join-Path $repoRoot '.env.uat'
$runtimeDirectory = Join-Path $repoRoot 'storage\uat'

Set-Location $repoRoot

function Invoke-Native {
    param(
        [Parameter(Mandatory)] [string] $File,
        [Parameter(Mandatory)] [string[]] $Arguments
    )

    & $File @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$File gagal dengan exit code $LASTEXITCODE."
    }
}

function Assert-UatEnvironment {
    if (-not (Test-Path -LiteralPath $envFile)) {
        Copy-Item -LiteralPath (Join-Path $repoRoot '.env.uat.example') -Destination $envFile
        throw '.env.uat dibuat. Ganti seluruh nilai change-this-before-setup, lalu jalankan setup lagi.'
    }

    if (Select-String -LiteralPath $envFile -Pattern 'change-this-before-setup' -Quiet) {
        throw 'Ganti seluruh nilai change-this-before-setup di .env.uat.'
    }
}

function Start-UatInfrastructure {
    Invoke-Native docker @(
        'compose', '--env-file', $envFile, '--profile', 'uat',
        'up', '-d', '--wait', 'mysql-uat', 'redis-uat', 'phpmyadmin-uat'
    )
}

function Start-UatProcess {
    param(
        [Parameter(Mandatory)] [string] $Name,
        [Parameter(Mandatory)] [string[]] $Arguments
    )

    New-Item -ItemType Directory -Path $runtimeDirectory -Force | Out-Null
    $pidFile = Join-Path $runtimeDirectory "$Name.pid"

    if (Test-Path -LiteralPath $pidFile) {
        $processId = [int] (Get-Content -Raw -LiteralPath $pidFile)
        $existing = Get-CimInstance Win32_Process -Filter "ProcessId = $processId" -ErrorAction SilentlyContinue
        if ($existing -and $existing.CommandLine -like '*--env=uat*') {
            Write-Host "$Name sudah berjalan (PID $processId)."
            return
        }
    }

    $startParameters = @{
        FilePath = 'php'
        ArgumentList = $Arguments
        WorkingDirectory = $repoRoot
        WindowStyle = 'Hidden'
        RedirectStandardOutput = Join-Path $repoRoot "storage\logs\$Name.log"
        RedirectStandardError = Join-Path $repoRoot "storage\logs\$Name-error.log"
        PassThru = $true
    }
    $process = Start-Process @startParameters

    Set-Content -LiteralPath $pidFile -Value $process.Id
    Write-Host "$Name berjalan (PID $($process.Id))."
}

function Start-UatApplication {
    Start-UatInfrastructure
    Start-UatProcess 'uat-server' @(
        'artisan', 'serve', '--env=uat', '--host=127.0.0.1', '--port=8100'
    )
    Start-UatProcess 'uat-worker' @(
        'artisan', 'queue:work', 'redis', '--env=uat',
        '--queue=rams-imports,default', '--tries=1', '--timeout=0', '--sleep=1'
    )
}

function Stop-UatProcess {
    param([Parameter(Mandatory)] [string] $Name)

    $pidFile = Join-Path $runtimeDirectory "$Name.pid"
    if (-not (Test-Path -LiteralPath $pidFile)) {
        return
    }

    $processId = [int] (Get-Content -Raw -LiteralPath $pidFile)
    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $processId" -ErrorAction SilentlyContinue
    if ($process -and $process.CommandLine -like '*--env=uat*') {
        Stop-Process -Id $processId -Force
    }

    Remove-Item -LiteralPath $pidFile -Force
}

function Test-UatProcess {
    param([Parameter(Mandatory)] [string] $Name)

    $pidFile = Join-Path $runtimeDirectory "$Name.pid"
    if (-not (Test-Path -LiteralPath $pidFile)) {
        throw "$Name tidak berjalan."
    }

    $processId = [int] (Get-Content -Raw -LiteralPath $pidFile)
    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $processId" -ErrorAction SilentlyContinue
    if (-not $process -or $process.CommandLine -notlike '*--env=uat*') {
        throw "$Name tidak berjalan."
    }

    Write-Host "$Name sehat (PID $processId)."
}

Assert-UatEnvironment

switch ($Action) {
    'setup' {
        Start-UatInfrastructure
        if (-not (Test-Path -LiteralPath (Join-Path $repoRoot 'vendor\autoload.php'))) {
            Invoke-Native composer @('install', '--no-interaction')
        }
        if (-not (Test-Path -LiteralPath (Join-Path $repoRoot 'node_modules\.bin\vite.cmd'))) {
            Invoke-Native npm @('ci')
        }
        Invoke-Native npm @('run', 'build')

        if (Select-String -LiteralPath $envFile -Pattern '^APP_KEY=$' -Quiet) {
            Invoke-Native php @('artisan', 'key:generate', '--env=uat', '--force')
        }

        Invoke-Native php @('artisan', 'migrate', '--seed', '--env=uat', '--force')
        Start-UatApplication
        Write-Host 'UAT siap: http://127.0.0.1:8100'
        Write-Host 'phpMyAdmin UAT: http://127.0.0.1:8081'
    }
    'start' {
        Start-UatApplication
    }
    'check' {
        Start-UatInfrastructure
        Invoke-Native php @('artisan', 'migrate:status', '--env=uat')
        Invoke-Native docker @(
            'compose', '--env-file', $envFile, '--profile', 'uat',
            'exec', '-T', 'redis-uat', 'redis-cli', 'ping'
        )
        Test-UatProcess 'uat-server'
        Test-UatProcess 'uat-worker'
        $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8100/login' -UseBasicParsing
        if ($response.StatusCode -ne 200) {
            throw "Login UAT mengembalikan HTTP $($response.StatusCode)."
        }
        Write-Host 'Login UAT sehat (HTTP 200).'
    }
    'stop' {
        Stop-UatProcess 'uat-worker'
        Stop-UatProcess 'uat-server'
        Invoke-Native docker @(
            'compose', '--env-file', $envFile, '--profile', 'uat',
            'stop', 'phpmyadmin-uat', 'redis-uat', 'mysql-uat'
        )
    }
}
