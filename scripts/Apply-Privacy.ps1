#requires -Version 7.2
[CmdletBinding()]
param()
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$runtime = Get-Content -LiteralPath (Join-Path $root '.local/runtime.json') -Raw | ConvertFrom-Json
$allowed = [IO.Path]::GetFullPath((Join-Path $root '.local')) + [IO.Path]::DirectorySeparatorChar
if (-not ([IO.Path]::GetFullPath($runtime.PhpExe)).StartsWith($allowed, [StringComparison]::OrdinalIgnoreCase)) { throw 'PHP runtime must belong to this workspace.' }
& $runtime.PhpExe (Join-Path $PSScriptRoot 'apply-privacy.php')
if ($LASTEXITCODE -ne 0) { throw 'Privacy policy update failed.' }
