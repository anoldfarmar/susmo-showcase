#requires -Version 7.2
[CmdletBinding()]
param()
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$localRoot = Join-Path $projectRoot '.local'
$runtime = Get-Content -LiteralPath (Join-Path $localRoot 'runtime.json') -Raw | ConvertFrom-Json
$env:PATH = "$(Split-Path -Parent $runtime.PhpExe);$env:PATH"
& $runtime.PhpExe (Join-Path $PSScriptRoot 'Prepare-Assets.php')
if ($LASTEXITCODE -ne 0) { throw 'Asset preparation failed.' }
$source = Join-Path $projectRoot 'plugins/susmo-catalog'
$target = Join-Path $localRoot 'www/wp-content/plugins/susmo-catalog'
if (Test-Path -LiteralPath $target) {
    $item = Get-Item -LiteralPath $target
    if ($item.LinkType -ne 'Junction' -or $item.Target -ne $source) { throw 'Unexpected catalog plugin path; no files overwritten.' }
} else { New-Item -ItemType Junction -Path $target -Target $source | Out-Null }
& $runtime.PhpExe (Join-Path $PSScriptRoot 'seed-susmo.php')
if ($LASTEXITCODE -ne 0) { throw 'Brand content migration failed. Check local logs; no database reset was attempted.' }
$apacheConfig = Get-Content -LiteralPath $runtime.ApacheConfig -Raw
if ($apacheConfig -match '(?m)^LogFormat "%h %l %u %t ') {
    $backupDir = Join-Path $localRoot 'backups'
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
    $backupFile = Join-Path $backupDir 'httpd-before-minimal-logging.conf'
    if (-not (Test-Path -LiteralPath $backupFile)) { Copy-Item -LiteralPath $runtime.ApacheConfig -Destination $backupFile }
    $apacheConfig = $apacheConfig -replace '(?m)^LogFormat .+ common\r?$', 'LogFormat "%{%Y-%m-%dT%H:%M:%S%z}t %m %U %>s %b" common'
    Set-Content -LiteralPath $runtime.ApacheConfig -Value $apacheConfig -Encoding utf8NoBOM
    & $runtime.ApacheExe -t -f $runtime.ApacheConfig
    if ($LASTEXITCODE -ne 0) { throw 'Apache configuration check failed; original configuration is backed up.' }
    $stateFile = Join-Path $localRoot 'run/processes.json'
    if (Test-Path -LiteralPath $stateFile) {
        $state = Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json
        if ($state.Apache) {
            $process = Get-Process -Id $state.Apache.Id -ErrorAction SilentlyContinue
            if ($process -and $process.Path -eq $runtime.ApacheExe -and $process.StartTime.ToUniversalTime().Ticks.ToString() -eq $state.Apache.StartedAtUtcTicks) {
                $restartEvent = [Threading.EventWaitHandle]::OpenExisting("ap$($process.Id)_restart")
                try { [void]$restartEvent.Set() } finally { $restartEvent.Dispose() }
                Write-Host 'Apache reloaded: access logs omit IP addresses, query strings, user agents and request bodies.'
            }
        }
    }
}
Write-Host 'susmo catalog: http://127.0.0.1:8080/'
