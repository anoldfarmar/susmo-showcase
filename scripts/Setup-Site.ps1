#requires -Version 7.2
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$projectRoot = Split-Path -Parent $PSScriptRoot
$localRoot = Join-Path $projectRoot '.local'
$runtime = Get-Content -LiteralPath (Join-Path $localRoot 'runtime.json') -Raw | ConvertFrom-Json
$env:PATH = "$(Split-Path -Parent $runtime.PhpExe);$env:PATH"
& node --use-env-proxy (Join-Path $PSScriptRoot 'Download-Site.mjs')
if ($LASTEXITCODE -ne 0) { throw 'Official package download failed. No database installation was performed.' }
$www = Join-Path $localRoot 'www'
$staging = Join-Path $localRoot 'site-staging'
foreach ($package in @(
    @{Name='wordpress'; Marker=(Join-Path $www 'wp-includes/version.php'); Target=$www; Inner='wordpress'},
    @{Name='woocommerce'; Marker=(Join-Path $www 'wp-content/plugins/woocommerce/woocommerce.php'); Target=(Join-Path $www 'wp-content/plugins/woocommerce'); Inner='woocommerce'},
    @{Name='storefront'; Marker=(Join-Path $www 'wp-content/themes/storefront/style.css'); Target=(Join-Path $www 'wp-content/themes/storefront'); Inner='storefront'}
)) {
    if (Test-Path -LiteralPath $package.Marker) { continue }
    $unpacked = Join-Path $staging $package.Name
    Expand-Archive -LiteralPath (Join-Path $localRoot "downloads/$($package.Name).zip") -DestinationPath $unpacked -Force
    New-Item -ItemType Directory -Path $package.Target -Force | Out-Null
    Copy-Item -Path (Join-Path $unpacked "$($package.Inner)/*") -Destination $package.Target -Recurse -Force
}
$themeLink = Join-Path $www 'wp-content/themes/local-store'
$themeSource = Join-Path $projectRoot 'theme'
if (Test-Path -LiteralPath $themeLink) {
    $existing = Get-Item -LiteralPath $themeLink
    if ($existing.LinkType -ne 'Junction' -or $existing.Target -ne $themeSource) {
        throw 'Existing local-store theme path is not the expected source junction. Nothing was overwritten.'
    }
} else {
    New-Item -ItemType Junction -Path $themeLink -Target $themeSource | Out-Null
}
$catalogSource = Join-Path $projectRoot 'plugins/susmo-catalog'
$catalogTarget = Join-Path $www 'wp-content/plugins/susmo-catalog'
if (Test-Path -LiteralPath $catalogTarget) {
    $item = Get-Item -LiteralPath $catalogTarget
    if ($item.LinkType -ne 'Junction' -or $item.Target -ne $catalogSource) { throw 'Unexpected catalog plugin path; no files overwritten.' }
} else { New-Item -ItemType Junction -Path $catalogTarget -Target $catalogSource | Out-Null }
& $runtime.PhpExe (Join-Path $PSScriptRoot 'Prepare-Assets.php')
if ($LASTEXITCODE -ne 0) { throw 'Brand asset preparation failed.' }
foreach ($phase in @('install', 'seed', 'routes', 'check')) {
    & $runtime.PhpExe (Join-Path $PSScriptRoot 'site-bootstrap.php') $phase
    if ($LASTEXITCODE -ne 0) { throw "Site setup phase failed: $phase. Existing data was preserved." }
}
$rewriteFile = Join-Path $www '.htaccess'
if (-not (Test-Path -LiteralPath $rewriteFile)) {
    @'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
'@ | Set-Content -LiteralPath $rewriteFile -Encoding utf8NoBOM
}
Write-Host 'Local store: http://127.0.0.1:8080'
Write-Host 'Admin: http://127.0.0.1:8080/wp-admin/'
Write-Host 'Login details: .local/site-access.json (private; do not paste into chat or commit).'
