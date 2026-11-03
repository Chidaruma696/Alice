<#
.SYNOPSIS
  Build the Alice installer: download pinned dependencies, verify hashes,
  stage the payload, compile with Inno Setup.

.USAGE
  powershell -ExecutionPolicy Bypass -File installer\build.ps1
  Options:
    -SkipCompile   stage the payload only (no ISCC)
    -Clean         delete installer\payload before staging (default: yes)

  Output: installer\dist\Alice-<version>-setup.exe
  Requires: Windows PowerShell 5.1+, Inno Setup 6 (ISCC.exe on PATH or in Program Files).
#>
[CmdletBinding()]
param(
    [switch] $SkipCompile
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$installer = $PSScriptRoot
$downloads = Join-Path $installer 'downloads'
$payload = Join-Path $installer 'payload'
$dist = Join-Path $installer 'dist'

function Fail([string] $message) {
    Write-Host "ERROR: $message" -ForegroundColor Red
    exit 1
}

# --- version from src/App.php ------------------------------------------------
$appSource = Get-Content (Join-Path $root 'src\App.php') -Raw
if ($appSource -notmatch "VERSION\s*=\s*'([^']+)'") { Fail 'cannot read App::VERSION from src/App.php' }
$version = $Matches[1]
Write-Host "Alice $version"

# --- dependencies --------------------------------------------------------------
$deps = Get-Content (Join-Path $installer 'deps.json') -Raw | ConvertFrom-Json
New-Item -ItemType Directory -Force $downloads | Out-Null

function Get-Dependency($dep) {
    $target = Join-Path $downloads $dep.file
    if (-not (Test-Path $target)) {
        $urls = @($dep.url)
        if ($dep.PSObject.Properties['archive_url']) { $urls += $dep.archive_url }
        $ok = $false
        foreach ($url in $urls) {
            Write-Host "downloading $url"
            try {
                [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
                Invoke-WebRequest -Uri $url -OutFile $target -UseBasicParsing
                $ok = $true
                break
            } catch {
                Write-Host "  failed: $($_.Exception.Message)"
                if (Test-Path $target) { Remove-Item $target -Force }
            }
        }
        if (-not $ok) { Fail "could not download $($dep.file)" }
    }
    $actual = (Get-FileHash -Algorithm SHA256 $target).Hash.ToLowerInvariant()
    if ($actual -ne $dep.sha256.ToLowerInvariant()) {
        Remove-Item $target -Force
        Fail "SHA-256 mismatch for $($dep.file): expected $($dep.sha256), got $actual. The file was deleted; no installer was produced."
    }
    Write-Host "verified $($dep.file)"
    return $target
}

$phpZip = Get-Dependency $deps.php
$winsw = Get-Dependency $deps.winsw
$vcredist = Get-Dependency $deps.vcredist

# --- payload -------------------------------------------------------------------
if (Test-Path $payload) {
    # the folder itself may be some shell's current directory; emptying it is enough
    Get-ChildItem $payload -Force | Remove-Item -Recurse -Force
}
New-Item -ItemType Directory -Force (Join-Path $payload 'php') | Out-Null
New-Item -ItemType Directory -Force (Join-Path $payload 'app') | Out-Null

Write-Host 'staging PHP'
Expand-Archive -Path $phpZip -DestinationPath (Join-Path $payload 'php') -Force
Copy-Item (Join-Path $installer 'php.ini') (Join-Path $payload 'php\php.ini') -Force
if (-not (Test-Path (Join-Path $payload 'php\php.exe'))) { Fail 'php.exe missing after extraction' }

Write-Host 'staging service wrapper'
Copy-Item $winsw (Join-Path $payload 'alice-service.exe') -Force
Copy-Item (Join-Path $installer 'winsw.xml') (Join-Path $payload 'alice-service.xml.template') -Force

Write-Host 'staging Visual C++ runtime installer'
Copy-Item $vcredist (Join-Path $payload 'VC_redist.x64.exe') -Force

Write-Host 'drawing icon'
& powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $installer 'make-icon.ps1') -Out (Join-Path $installer 'alice.ico') | Out-Null
if (-not (Test-Path (Join-Path $installer 'alice.ico'))) { Fail 'alice.ico was not generated' }

Write-Host 'staging application'
foreach ($dir in @('public', 'src', 'templates', 'lang', 'tools', 'docs')) {
    Copy-Item (Join-Path $root $dir) (Join-Path $payload "app\$dir") -Recurse -Force
}
foreach ($file in @('LICENSE', 'README.md', 'README.es.md', 'CHANGELOG.md')) {
    $src = Join-Path $root $file
    if (Test-Path $src) { Copy-Item $src (Join-Path $payload 'app') -Force }
}
# Every adapters\<name>\ present in this checkout ships inside the installer (example always;
# office adapters only exist locally, so a build from a clean clone bundles just the example).
$adapterDirs = Get-ChildItem (Join-Path $root 'adapters') -Directory | Where-Object { Test-Path (Join-Path $_.FullName 'adapter.php') }
foreach ($dir in $adapterDirs) {
    New-Item -ItemType Directory -Force (Join-Path $payload "adapters\$($dir.Name)") | Out-Null
    Copy-Item (Join-Path $dir.FullName '*') (Join-Path $payload "adapters\$($dir.Name)") -Recurse -Force
}
Write-Host "staging adapters: $(($adapterDirs | ForEach-Object { $_.Name }) -join ', ')"

# sanity: the bundled PHP runs the app
$checkScript = Join-Path $payload 'check.php'
@'
<?php
require __DIR__ . '/app/src/bootstrap.php';
echo Alice\App::VERSION, ' iconv=', (int) function_exists('iconv'), ' mb=', (int) function_exists('mb_strlen'),
    ' intl=', (int) class_exists('IntlTimeZone'), ' pdo_mysql=', (int) in_array('mysql', PDO::getAvailableDrivers()), "\n";
'@ | Set-Content -Path $checkScript -Encoding ASCII
$check = (& (Join-Path $payload 'php\php.exe') -c (Join-Path $payload 'php\php.ini') $checkScript 2>&1 | Out-String).Trim()
Remove-Item $checkScript -Force
Write-Host "bundled PHP check: $check"
if (-not $check -or $check -notmatch "^$([regex]::Escape($version)) iconv=1 mb=1 intl=1 pdo_mysql=1") { Fail 'bundled PHP did not run the app or is missing a required extension (see php.ini)' }

if ($SkipCompile) {
    Write-Host 'payload staged; compile skipped'
    exit 0
}

# --- compile -------------------------------------------------------------------
$iscc = Get-Command ISCC.exe -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source
if (-not $iscc) {
    foreach ($candidate in @("${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe", "$env:ProgramFiles\Inno Setup 6\ISCC.exe", "${env:ProgramFiles(x86)}\Inno Setup 7\ISCC.exe", "$env:ProgramFiles\Inno Setup 7\ISCC.exe")) {
        if (Test-Path $candidate) { $iscc = $candidate; break }
    }
}
if (-not $iscc) { Fail 'ISCC.exe (Inno Setup 6) not found; install it or add it to PATH' }

New-Item -ItemType Directory -Force $dist | Out-Null
Write-Host "compiling with $iscc"
& $iscc "/DAppVersion=$version" "/O$dist" (Join-Path $installer 'alice.iss')
if ($LASTEXITCODE -ne 0) { Fail "ISCC exited with code $LASTEXITCODE" }

$setup = Join-Path $dist "Alice-$version-setup.exe"
if (-not (Test-Path $setup)) { Fail "expected output not found: $setup" }
Write-Host "built $setup ($([math]::Round((Get-Item $setup).Length / 1MB, 1)) MB)" -ForegroundColor Green
