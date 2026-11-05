#requires -Version 5.1
[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [ValidateSet('Setup', 'Start', 'Stop', 'Check', 'Site', 'Brand', 'Privacy')]
    [string]$Action
)

$ErrorActionPreference = 'Stop'
# PowerShell 7 is available in Codex's bundled runtime, even when it is absent
# from a normal Windows terminal's PATH. Do not change the global PATH.
$candidates = @()
$command = Get-Command pwsh.exe -ErrorAction SilentlyContinue | Select-Object -First 1
if ($command) { $candidates += $command.Source }
$candidates += Join-Path $env:ProgramFiles 'PowerShell\7\pwsh.exe'
$candidates += Join-Path $env:USERPROFILE '.cache\codex-runtimes\codex-primary-runtime\dependencies\native\powershell\pwsh.exe'
$shellExe = $null
foreach ($candidate in ($candidates | Select-Object -Unique)) {
    if (-not (Test-Path -LiteralPath $candidate -PathType Leaf)) { continue }
    $versionText = & $candidate -NoProfile -Command '$PSVersionTable.PSVersion.ToString()'
    if ($LASTEXITCODE -eq 0 -and [version]$versionText -ge [version]'7.2') {
        $shellExe = $candidate
        break
    }
}
if (-not $shellExe) {
    throw 'PowerShell 7.2+ was not found. See docs/local-setup.md; do not install other project dependencies yet.'
}
if ($Action -eq 'Privacy') {
    & $shellExe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'Apply-Privacy.ps1')
} elseif ($Action -eq 'Brand') {
    & $shellExe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'Apply-Susmo.ps1')
} elseif ($Action -eq 'Site') {
    & $shellExe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'Setup-Site.ps1')
} elseif ($Action -eq 'Setup') {
    & $shellExe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'Setup-Local.ps1')
} else {
    & $shellExe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'Local.ps1') -Action $Action
}
exit $LASTEXITCODE
