#requires -Version 7.2
[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [ValidateSet('Start', 'Stop', 'Check')]
    [string]$Action
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$localRoot = Join-Path $projectRoot '.local'
$manifestPath = Join-Path $localRoot 'runtime.json'
if (-not (Test-Path -LiteralPath $manifestPath)) {
    throw 'Run scripts/Setup-Local.ps1 first. See docs/local-setup.md.'
}
$runtime = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
$allowedRoot = [IO.Path]::GetFullPath($localRoot) + [IO.Path]::DirectorySeparatorChar
foreach ($property in @('PhpExe', 'ApacheExe', 'DatabaseExe', 'ApacheConfig', 'DatabaseConfig', 'RootClientConfig')) {
    $candidate = [IO.Path]::GetFullPath([string]$runtime.$property)
    if (-not $candidate.StartsWith($allowedRoot, [StringComparison]::OrdinalIgnoreCase) -or
        -not (Test-Path -LiteralPath $candidate -PathType Leaf)) {
        throw "Invalid/missing project runtime path: $property. No process was started or stopped."
    }
}
$env:PATH = "$(Split-Path -Parent $runtime.PhpExe);$env:PATH"
$statePath = Join-Path $localRoot 'run/processes.json'
$dbBin = Split-Path -Parent $runtime.DatabaseExe
$dbClient = Join-Path $dbBin 'mariadb.exe'
$dbAdmin = Join-Path $dbBin 'mariadb-admin.exe'
$state = @{ Database = $null; Apache = $null }
if (Test-Path -LiteralPath $statePath) {
    $state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json -AsHashtable
}

function Get-ManagedProcess([string]$Name, [string]$ExpectedExe) {
    if (-not $state[$Name]) { return $null }
    $entry = $state[$Name]
    $process = Get-Process -Id ([int]$entry.Id) -ErrorAction SilentlyContinue
    if ($process -and [StringComparer]::OrdinalIgnoreCase.Equals($process.Path, $ExpectedExe) -and
        $process.StartTime.ToUniversalTime().Ticks.ToString() -eq [string]$entry.StartedAtUtcTicks) {
        return $process
    }
    return $null
}

function Test-Port([int]$Port) {
    $client = [Net.Sockets.TcpClient]::new()
    try { $client.Connect('127.0.0.1', $Port); return $true }
    catch { return $false }
    finally { $client.Dispose() }
}

function Save-State {
    $state | ConvertTo-Json | Set-Content -LiteralPath $statePath -Encoding utf8NoBOM
}

function Send-ApacheShutdown([int]$ProcessId) {
    # Apache's WinNT MPM exposes this event for external shutdown of console instances:
    # https://github.com/apache/httpd/blob/2.4.x/server/mpm/winnt/mpm_winnt.c
    # Call only after verifying this project's executable path and process start time.
    $shutdownEvent = [Threading.EventWaitHandle]::OpenExisting("ap${ProcessId}_shutdown")
    try {
        if (-not $shutdownEvent.Set()) { throw 'Apache shutdown event could not be signaled.' }
    } finally { $shutdownEvent.Dispose() }
}

if ($Action -eq 'Start') {
    foreach ($service in @(
        @{ Name = 'Database'; Exe = $runtime.DatabaseExe; Port = 3307; Args = @("--defaults-file=`"$($runtime.DatabaseConfig)`"", '--console') },
        @{ Name = 'Apache'; Exe = $runtime.ApacheExe; Port = 8080; Args = @('-f', "`"$($runtime.ApacheConfig)`"") }
    )) {
        if (Get-ManagedProcess $service.Name $service.Exe) { continue }
        if (Test-Port $service.Port) {
            throw "Port $($service.Port) is already occupied by an unmanaged process. No process was stopped."
        }
        $process = Start-Process -FilePath $service.Exe -ArgumentList $service.Args -WindowStyle Hidden -PassThru `
            -WorkingDirectory (Split-Path -Parent $service.Exe) `
            -RedirectStandardOutput (Join-Path $localRoot "logs/$($service.Name)-stdout.log") `
            -RedirectStandardError (Join-Path $localRoot "logs/$($service.Name)-stderr.log")
        $state[$service.Name] = @{
            Id = $process.Id
            StartedAtUtcTicks = $process.StartTime.ToUniversalTime().Ticks.ToString()
        }
        Save-State
        $deadline = [DateTime]::UtcNow.AddSeconds(30)
        do {
            if (-not (Get-ManagedProcess $service.Name $service.Exe)) {
                throw "$($service.Name) exited. Check .local/logs. Run the Stop command to stop the other managed service if needed."
            }
            if (Test-Port $service.Port) { break }
            Start-Sleep -Milliseconds 500
        } while ([DateTime]::UtcNow -lt $deadline)
        if (-not (Test-Port $service.Port)) {
            throw "$($service.Name) did not become ready within 30 seconds. Check .local/logs."
        }
    }
}

if ($Action -eq 'Stop') {
    $process = Get-ManagedProcess 'Apache' $runtime.ApacheExe
    if ($process) {
        Send-ApacheShutdown $process.Id
        if ($process -and -not $process.WaitForExit(15000)) { throw 'Apache is still stopping. Try again shortly.' }
    }
    if (Get-ManagedProcess 'Database' $runtime.DatabaseExe) {
        & $dbAdmin "--defaults-extra-file=$($runtime.RootClientConfig)" shutdown
        if ($LASTEXITCODE -ne 0) { throw 'Database graceful shutdown failed. No process was force-killed.' }
        $process = Get-ManagedProcess 'Database' $runtime.DatabaseExe
        if ($process -and -not $process.WaitForExit(15000)) { throw 'Database is still stopping. Try again shortly.' }
    }
    $state.Database = $null
    $state.Apache = $null
    Save-State
    Write-Host 'Managed local services stopped. Database files and uploads were preserved.'
    return
}

if (-not (Get-ManagedProcess 'Database' $runtime.DatabaseExe) -or
    -not (Get-ManagedProcess 'Apache' $runtime.ApacheExe)) {
    throw 'Local services are not running under this project. Run Local.ps1 -Action Start first.'
}
& $runtime.PhpExe -v
if ($LASTEXITCODE -ne 0) { throw 'PHP runtime check failed.' }
& $runtime.ApacheExe -t -f $runtime.ApacheConfig
if ($LASTEXITCODE -ne 0) { throw 'Apache configuration check failed.' }
& $dbClient "--defaults-extra-file=$($runtime.RootClientConfig)" '--connect-timeout=5' -e 'SELECT VERSION() AS database_version, 1 AS connection_ok;'
if ($LASTEXITCODE -ne 0) { throw 'Database authentication/query check failed.' }
$health = Invoke-RestMethod -Uri 'http://127.0.0.1:8080/health.php' -TimeoutSec 15 -NoProxy
if ($health.status -ne 'ok' -or @($health.missing_extensions).Count -ne 0) {
    throw 'PHP web runtime/extension check failed.'
}
Write-Host "Web PHP: $($health.php); required extensions: OK; authenticated database query: OK"
Write-Host 'Local website: http://127.0.0.1:8080'
