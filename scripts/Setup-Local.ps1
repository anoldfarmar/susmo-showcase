#requires -Version 7.2
[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$localRoot = Join-Path $projectRoot '.local'
$configDir = Join-Path $localRoot 'config'
$dataDir = Join-Path $localRoot 'data/mariadb'
$downloadsDir = Join-Path $localRoot 'downloads'
$manifestPath = Join-Path $localRoot 'runtime.json'

if (-not $IsWindows -or -not [Environment]::Is64BitOperatingSystem) {
    throw 'This setup requires Windows x64 and PowerShell 7.'
}
if (Test-Path -LiteralPath $manifestPath) {
    Write-Host 'Already configured. Existing configuration and database were preserved.'
    Write-Host 'Next: powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\dev.ps1 -Action Start'
    return
}

$drive = [IO.DriveInfo]::new([IO.Path]::GetPathRoot($projectRoot))
if ($drive.AvailableFreeSpace -lt 3GB) {
    throw 'At least 3 GB of free space is required on the project drive before setup.'
}

# Apache Lounge VS18 currently requires this shared Microsoft runtime or later.
$runtimeKey = 'HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64'
$vcRuntime = Get-ItemProperty -LiteralPath $runtimeKey -ErrorAction SilentlyContinue
if (-not $vcRuntime -or $vcRuntime.Installed -ne 1 -or
    [version]($vcRuntime.Version.TrimStart('v')) -lt [version]'14.51.36247') {
    throw 'Update Microsoft VC++ x64 Runtime first: https://aka.ms/vc14/vc_redist.x64.exe (see docs/local-setup.md).'
}

foreach ($folder in @('config', 'data', 'logs', 'tmp', 'www', 'run', 'runtime', 'downloads')) {
    New-Item -ItemType Directory -Path (Join-Path $localRoot $folder) -Force | Out-Null
}

function Resolve-RuntimeArchive([string]$Name) {
    $shortName = Join-Path $downloadsDir "$Name.zip"
    if (Test-Path -LiteralPath $shortName -PathType Leaf) { return $shortName }
    $patterns = @{
        php = '^php-8\.4(?:\.\d+(?:-\d+)?)?-Win32-vs17-x64(?:-latest)?\.zip$'
        apache = '^httpd-2\.4\..*-win64-vs\d+\.zip$'
        mariadb = '^mariadb-11\.4\.\d+-winx64\.zip$'
    }
    $matches = @(Get-ChildItem -LiteralPath $downloadsDir -File | Where-Object { $_.Name -match $patterns[$Name] })
    if ($matches.Count -eq 1) { return $matches[0].FullName }
    if ($matches.Count -gt 1) {
        throw "Multiple $Name ZIPs found. Choose one and name it $Name.zip. No file was changed."
    }
    if ($Name -eq 'mariadb') {
        throw 'Missing MariaDB 11.4 Windows x64 ZIP. Select Server 11.4 > Windows > x86_64 > ZIP at https://mariadb.org/download/ . An MSI package or another version cannot be used here; renaming an MSI to ZIP will not work.'
    }
    throw "Missing $Name ZIP in .local/downloads. Use the matching original package filename or $Name.zip; see docs/local-setup.md."
}

function Expand-Runtime {
    param([string]$Name, [string]$RelativeExecutable)
    $destination = Join-Path $localRoot "runtime/$Name"
    $candidate = @()
    if (Test-Path -LiteralPath $destination) {
        $candidate = @(Get-ChildItem -LiteralPath $destination -Directory | ForEach-Object {
            Join-Path $_.FullName $RelativeExecutable
        }) + @(Join-Path $destination $RelativeExecutable)
        $candidate = @($candidate | Where-Object { Test-Path -LiteralPath $_ })
    }
    if (@($candidate).Count -eq 0) {
        $archive = $archives[$Name]
        if (-not (Test-Path -LiteralPath $archive)) {
            throw "Missing $archive. Download the matching x64 ZIP described in docs/local-setup.md."
        }
        New-Item -ItemType Directory -Path $destination -Force | Out-Null
        Expand-Archive -LiteralPath $archive -DestinationPath $destination -Force
        $candidate = @(Get-ChildItem -LiteralPath $destination -Directory | ForEach-Object {
            Join-Path $_.FullName $RelativeExecutable
        }) + @(Join-Path $destination $RelativeExecutable)
        $candidate = @($candidate | Where-Object { Test-Path -LiteralPath $_ })
    }
    if (@($candidate).Count -ne 1) {
        throw "Expected exactly one $RelativeExecutable inside $destination. Check the ZIP package."
    }
    return [IO.Path]::GetFullPath($candidate[0])
}

$archives = @{}
foreach ($name in @('php', 'apache', 'mariadb')) {
    $archives[$name] = Resolve-RuntimeArchive $name
}

$phpExe = Expand-Runtime 'php' 'php.exe'
$apacheExe = Expand-Runtime 'apache' 'bin/httpd.exe'
$dbExe = Expand-Runtime 'mariadb' 'bin/mariadbd.exe'
$phpDir = Split-Path -Parent $phpExe
$apacheDir = Split-Path -Parent (Split-Path -Parent $apacheExe)
$dbDir = Split-Path -Parent (Split-Path -Parent $dbExe)
$env:PATH = "$phpDir;$env:PATH"

if (-not (Test-Path -LiteralPath (Join-Path $phpDir 'php8apache2_4.dll'))) {
    throw 'PHP must be the x64 Thread Safe ZIP, not NTS, source, SDK or debug pack.'
}
$phpVersion = & $phpExe -n -r 'echo PHP_VERSION;'
if ($LASTEXITCODE -ne 0 -or $phpVersion -notmatch '^8\.4\.') {
    throw 'Expected a working PHP 8.4 x64 runtime.'
}
$dbVersion = (& $dbExe --no-defaults --version | Out-String).Trim()
if ($LASTEXITCODE -ne 0 -or $dbVersion -notmatch '\b11\.4\.') {
    throw 'Expected the MariaDB 11.4 Windows x64 ZIP.'
}
$apacheVersion = (& $apacheExe -v | Out-String).Trim()
if ($LASTEXITCODE -ne 0 -or $apacheVersion -notmatch 'Apache/2\.4\.') {
    throw 'Expected a working Apache 2.4 x64 runtime.'
}

function Forward-Path([string]$Path) { return $Path.Replace('\', '/') }
$phpPath = Forward-Path $phpDir
$apachePath = Forward-Path $apacheDir
$wwwPath = Forward-Path (Join-Path $localRoot 'www')
$logPath = Forward-Path (Join-Path $localRoot 'logs')
$tmpPath = Forward-Path (Join-Path $localRoot 'tmp')
$runPath = Forward-Path (Join-Path $localRoot 'run')

@"
[PHP]
extension_dir = "$phpPath/ext"
extension = mysqli
extension = curl
extension = mbstring
extension = gd
extension = intl
extension = openssl
extension = zip
extension = fileinfo
extension = exif
memory_limit = 256M
upload_max_filesize = 32M
post_max_size = 40M
max_execution_time = 120
date.timezone = UTC
display_errors = On
display_startup_errors = On
error_reporting = E_ALL
log_errors = On
error_log = "$logPath/php-error.log"
sys_temp_dir = "$tmpPath"
upload_tmp_dir = "$tmpPath"
session.save_path = "$tmpPath"
"@ | Set-Content -LiteralPath (Join-Path $phpDir 'php.ini') -Encoding utf8NoBOM

$extensionCheck = @'
$required = ["mysqli", "curl", "mbstring", "gd", "intl", "openssl", "zip", "fileinfo", "dom", "xml", "json", "session"];
$missing = array_filter($required, fn($name) => !extension_loaded($name));
if ($missing) { fwrite(STDERR, "Missing: " . implode(", ", $missing)); exit(1); }
echo "Required PHP extensions: OK\n";
'@
& $phpExe -c (Join-Path $phpDir 'php.ini') -r $extensionCheck
if ($LASTEXITCODE -ne 0) { throw 'PHP extension check failed.' }

$apacheConfig = Join-Path $configDir 'httpd.conf'
@"
ServerRoot "$apachePath"
Listen 127.0.0.1:8080
ServerName 127.0.0.1:8080
PidFile "$runPath/httpd.pid"
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule log_config_module modules/mod_log_config.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadFile "$phpPath/php8ts.dll"
LoadModule php_module "$phpPath/php8apache2_4.dll"
PHPIniDir "$phpPath"
DocumentRoot "$wwwPath"
DirectoryIndex index.php index.html
TypesConfig "$apachePath/conf/mime.types"
ErrorLog "$logPath/apache-error.log"
LogLevel warn
LogFormat "%{%Y-%m-%dT%H:%M:%S%z}t %m %U %>s %b" common
CustomLog "$logPath/apache-access.log" common
ServerTokens Prod
ServerSignature Off
Timeout 60
ThreadsPerChild 16
MaxConnectionsPerChild 0
EnableSendfile Off
AcceptFilter http none
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "$wwwPath">
    Options FollowSymLinks
    AllowOverride All
    Require local
</Directory>
<FilesMatch "^(\.|wp-config\.php$)">
    Require all denied
</FilesMatch>
<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
"@ | Set-Content -LiteralPath $apacheConfig -Encoding utf8NoBOM

& $apacheExe -t -f $apacheConfig
if ($LASTEXITCODE -ne 0) { throw 'Apache configuration check failed.' }

$secretPath = Join-Path $localRoot 'secrets.json'
if (Test-Path -LiteralPath $secretPath) {
    $secrets = Get-Content -LiteralPath $secretPath -Raw | ConvertFrom-Json
} else {
    if (Test-Path -LiteralPath (Join-Path $dataDir 'mysql')) {
        throw 'Existing database found without its credentials. Setup will not reset it.'
    }
    $secrets = [pscustomobject]@{
        RootPassword = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24))
    }
    $secrets | ConvertTo-Json | Set-Content -LiteralPath $secretPath -Encoding utf8NoBOM
}
if ($secrets.RootPassword -notmatch '^[0-9A-F]{48}$') {
    throw 'Unexpected local credential format. Existing credentials were preserved; inspect locally.'
}

$databaseConfig = Join-Path $configDir 'my.ini'
@"
[mysqld]
basedir="$(Forward-Path $dbDir)"
datadir="$(Forward-Path $dataDir)"
port=3307
bind-address=127.0.0.1
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
innodb-buffer-pool-size=128M
max-connections=30
skip-log-bin
tmpdir="$tmpPath"
log-error="$logPath/mariadb-error.log"
[client]
host=127.0.0.1
port=3307
protocol=tcp
"@ | Set-Content -LiteralPath $databaseConfig -Encoding utf8NoBOM

@"
[client]
host=127.0.0.1
port=3307
protocol=tcp
user=root
password=$($secrets.RootPassword)
"@ | Set-Content -LiteralPath (Join-Path $configDir 'root-client.cnf') -Encoding utf8NoBOM

if (-not (Test-Path -LiteralPath (Join-Path $dataDir 'mysql'))) {
    if ((Test-Path -LiteralPath $dataDir) -and @(Get-ChildItem -LiteralPath $dataDir -Force).Count -gt 0) {
        throw 'Database directory is nonempty but initialization is incomplete. No data was removed. Check .local/logs/database-init.log.'
    }
    $initExe = Join-Path $dbDir 'bin/mariadb-install-db.exe'
    $initOutput = & $initExe "--datadir=$dataDir" "--password=$($secrets.RootPassword)" '--port=3307' "--config=$databaseConfig" 2>&1
    $initExitCode = $LASTEXITCODE
    ($initOutput | Out-String).Replace($secrets.RootPassword, '[REDACTED]') |
        Set-Content -LiteralPath (Join-Path $localRoot 'logs/database-init.log') -Encoding utf8NoBOM
    if ($initExitCode -ne 0) { throw 'Database initialization failed. Check .local/logs/database-init.log.' }
}

$healthFile = Join-Path $localRoot 'www/health.php'
if (-not (Test-Path -LiteralPath $healthFile)) {
    @'
<?php
header('Content-Type: application/json; charset=utf-8');
$required = ['mysqli', 'curl', 'mbstring', 'gd', 'intl', 'openssl', 'zip', 'fileinfo', 'dom', 'xml', 'json', 'session'];
$missing = array_values(array_filter($required, fn($name) => !extension_loaded($name)));
http_response_code($missing ? 500 : 200);
echo json_encode(['status' => $missing ? 'error' : 'ok', 'php' => PHP_VERSION, 'missing_extensions' => $missing]);
'@ | Set-Content -LiteralPath $healthFile -Encoding utf8NoBOM
}
$indexFile = Join-Path $localRoot 'www/index.html'
if (-not (Test-Path -LiteralPath $indexFile) -and -not (Test-Path -LiteralPath (Join-Path $localRoot 'www/index.php'))) {
    '<!doctype html><html lang="en"><meta charset="utf-8"><title>Local environment</title><h1>Local environment is ready</h1><p>WordPress installation is the next step.</p></html>' |
        Set-Content -LiteralPath $indexFile -Encoding utf8NoBOM
}

$packageHashes = @{}
foreach ($name in @('php', 'apache', 'mariadb')) {
    $packageHashes[$name] = (Get-FileHash -LiteralPath $archives[$name] -Algorithm SHA256).Hash
}
[ordered]@{
    PhpExe = $phpExe
    ApacheExe = $apacheExe
    DatabaseExe = $dbExe
    ApacheConfig = $apacheConfig
    DatabaseConfig = $databaseConfig
    RootClientConfig = Join-Path $configDir 'root-client.cnf'
    PhpVersion = $phpVersion
    ApacheVersion = $apacheVersion
    DatabaseVersion = $dbVersion
    PackageSHA256 = $packageHashes
    ConfiguredAt = [DateTimeOffset]::UtcNow.ToString('o')
} | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $manifestPath -Encoding utf8NoBOM

Write-Host 'Configuration finished. Credentials were saved locally and were not printed.'
Write-Host 'No Windows services, global PATH entries or firewall rules were installed.'
Write-Host 'Next: powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\dev.ps1 -Action Start'
