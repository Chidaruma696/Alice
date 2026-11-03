<#
.SYNOPSIS
  Collect what matters when Alice does not run on a PC: Windows and .NET version,
  Visual C++ runtime, service state, port, whether the bundled PHP starts, and the
  tails of the logs. Writes everything to alice-diagnose.txt on the Desktop and
  prints it. Needs no administrator rights.

.USAGE
  powershell -ExecutionPolicy Bypass -File "C:\Program Files\Alice\tools\diagnose.ps1"
#>
[CmdletBinding()]
param()

$root = Split-Path -Parent $PSScriptRoot
$out = Join-Path ([Environment]::GetFolderPath('Desktop')) 'alice-diagnose.txt'
$lines = New-Object System.Collections.Generic.List[string]
function Say([string] $s) { $lines.Add($s); Write-Host $s }
function Section([string] $s) { Say ''; Say "== $s" }

Say "Alice diagnose $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  root=$root"

Section 'Windows'
$os = Get-CimInstance Win32_OperatingSystem
Say "$($os.Caption) $($os.Version) build $($os.BuildNumber) ($($os.OSArchitecture))"
Say "PowerShell $($PSVersionTable.PSVersion)"
$net = (Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\NET Framework Setup\NDP\v4\Full' -ErrorAction SilentlyContinue).Release
Say ".NET Framework 4.x release: $net (WinSW needs 4.6.1 = 394254 or higher)"

Section 'Visual C++ 2015-2022 runtime (x64)'
$vc = Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64' -ErrorAction SilentlyContinue
if ($vc) { Say "registry: Installed=$($vc.Installed) Version=$($vc.Version)" } else { Say 'registry: NOT FOUND (php.exe will not start)' }
foreach ($dll in @('vcruntime140.dll', 'vcruntime140_1.dll', 'msvcp140.dll')) {
    Say "System32\$dll present: $(Test-Path (Join-Path $env:SystemRoot "System32\$dll"))"
}

Section 'Install folder'
foreach ($f in @('alice-service.exe', 'alice-service.xml', 'alice-service.xml.template', 'php\php.exe', 'php\php.ini', 'public\router.php', 'tools\rawprint.ps1')) {
    Say "$f : $(Test-Path (Join-Path $root $f))"
}
$port = ''
$xml = Join-Path $root 'alice-service.xml'
if (Test-Path $xml) {
    $x = Get-Content $xml -Raw
    if ($x -match 'name="ALICE_PORT" value="(\d+)"') { $port = $Matches[1] }
    if ($x -match '\{\{PORT\}\}') { Say 'alice-service.xml still contains {{PORT}} (installer did not fill it)' }
}
Say "port in alice-service.xml: $port"

Section 'Service'
$svc = Get-Service -Name Alice -ErrorAction SilentlyContinue
if ($svc) { Say "Alice: $($svc.Status) (start type $($svc.StartType))" } else { Say 'Alice: NOT INSTALLED' }
$exe = Join-Path $root 'alice-service.exe'
if (Test-Path $exe) {
    try { $st = & $exe status 2>&1 | Out-String; Say "alice-service.exe status: $($st.Trim())" } catch { Say "alice-service.exe status failed: $($_.Exception.Message)" }
}

Section 'Port'
if ($port) {
    $listen = netstat -ano -p tcp | Select-String -Pattern ":$port\s.*LISTENING"
    if ($listen) { Say "listening: $($listen.Line.Trim())" } else { Say "nothing listening on $port" }
    try {
        $r = Invoke-WebRequest -Uri "http://127.0.0.1:$port/api/status" -UseBasicParsing -TimeoutSec 3
        Say "panel answers: HTTP $($r.StatusCode) $($r.Content)"
    } catch { Say "panel does not answer: $($_.Exception.Message)" }
}

Section 'Bundled PHP starts?'
$php = Join-Path $root 'php\php.exe'
if (Test-Path $php) {
    try {
        $v = & $php -c (Join-Path $root 'php\php.ini') -r 'echo PHP_VERSION, " iconv=", (int) function_exists("iconv"), " intl=", (int) class_exists("IntlTimeZone");' 2>&1 | Out-String
        Say "php -r: $($v.Trim()) (exit $LASTEXITCODE)"
    } catch { Say "php.exe failed to start: $($_.Exception.Message)" }
} else { Say 'php\php.exe missing' }

Section 'Firewall rule "Alice"'
$fw = netsh advfirewall firewall show rule name=Alice 2>&1 | Out-String
Say ($fw.Trim() -split "`r?`n" | Select-Object -First 8 | Out-String).Trim()

Section 'Log tails'
$logs = Join-Path $root 'data\logs'
foreach ($name in @('alice-service.wrapper.log', 'alice-service.err.log', 'alice-service.out.log', 'php-error.log')) {
    $p = Join-Path $logs $name
    if (Test-Path $p) { Say "--- $name"; Get-Content $p -Tail 15 | ForEach-Object { Say "  $_" } } else { Say "--- $name : (none)" }
}
# newest Inno Setup log that belongs to Alice (other installers leave logs in %TEMP% too)
$setupLog = Get-ChildItem $env:TEMP -Filter 'Setup Log*' -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending |
    Where-Object { Select-String -Path $_.FullName -Pattern 'alice-service|Alice-\d' -Quiet } | Select-Object -First 1
if ($setupLog) {
    Say "--- $($setupLog.FullName) (installer lines)"
    Select-String -Path $setupLog.FullName -Pattern 'run:|exit code|could not|^.{25}port |runtime|service Alice|Exception' | ForEach-Object { Say "  $($_.Line)" }
} else { Say '--- no Alice installer log in %TEMP%' }

$lines | Set-Content -Path $out -Encoding UTF8
Say ''
Say "saved to $out"
exit 0
