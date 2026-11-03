<#
.SYNOPSIS
  Alice control window and tray icon (Laragon-style on/off switch).

.DESCRIPTION
  Shows whether the Alice service is running, the port and URLs, the tail of
  the service log, and offers Start / Stop / Restart, Open panel, and the
  logs / data / adapters folders. Minimises to the notification area.

  Installed:   reads the port from alice-service.xml and drives alice-service.exe
               (elevating with a UAC prompt, the service needs admin).
  Development: when no alice-service.exe exists next to tools\ (a git checkout),
               it starts and stops PHP's built-in server itself, with a dry-run switch.

  Launch:  powershell -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File tools\alice-tray.ps1 [-Minimized] [-Port 8080]
#>
[CmdletBinding()]
param(
    [switch] $Minimized,
    [int] $Port = 0,
    [switch] $SelfTest   # start, probe, stop, print the outcome and exit (no window)
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
[System.Windows.Forms.Application]::EnableVisualStyles()

# --- paths and mode ------------------------------------------------------------
$root = Split-Path -Parent $PSScriptRoot
$serviceExe = Join-Path $root 'alice-service.exe'
$serviceXml = Join-Path $root 'alice-service.xml'
$dataDir = if ($env:ALICE_DATA_DIR) { $env:ALICE_DATA_DIR } else { Join-Path $root 'data' }
$logsDir = Join-Path $dataDir 'logs'
$adaptersDir = Join-Path $root 'adapters'
$devMode = -not (Test-Path $serviceExe)
# mutable state in a hashtable: event-handler scriptblocks mutate it by reference, whatever scope they run in
$G = @{ devProcess = $null; exiting = $false; lastState = '' }

foreach ($d in @($dataDir, $logsDir)) { if (-not (Test-Path $d)) { New-Item -ItemType Directory -Force $d | Out-Null } }

if ($Port -le 0) {
    $Port = 80
    if (Test-Path $serviceXml) {
        $xml = Get-Content $serviceXml -Raw
        if ($xml -match 'name="ALICE_PORT" value="(\d+)"') { $Port = [int] $Matches[1] }
    } elseif ($devMode) {
        $Port = 8080
    }
}

# --- strings (Windows display language: Spanish, else English) ---------------------
$spanish = ([System.Globalization.CultureInfo]::CurrentUICulture.TwoLetterISOLanguageName -eq 'es')
$S = if ($spanish) { @{
    title = 'Alice'; running = 'En ejecución'; stopped = 'Detenido'; starting = 'Iniciando…'; stopping = 'Deteniendo…'
    notInstalled = 'Servicio no instalado'; devMode = 'modo desarrollo (php -S)'; service = 'servicio de Windows "Alice"'
    port = 'Puerto'; urls = 'Las estaciones imprimen en'; panel = 'Panel que responde'; yes = 'sí'; no = 'no'
    start = 'Iniciar'; stop = 'Detener'; restart = 'Reiniciar'; openPanel = 'Abrir panel'; logs = 'Registros'; data = 'Datos'; adapters = 'Adaptadores'
    dryRun = 'Simulación (no imprime, guarda en spool)'; log = 'Registro del servidor'; show = 'Mostrar ventana'; exit = 'Salir'
    exitRunning = 'Salir cierra solo esta ventana; el servicio sigue corriendo.'; devExit = 'Salir detendrá el servidor de desarrollo. ¿Continuar?'
    phpMissing = 'No se encontró php.exe (ni en PATH ni en php\). Instala PHP o usa el instalador de Alice.'
    version = 'versión'; dry = 'simulación'; live = 'real'
} } else { @{
    title = 'Alice'; running = 'Running'; stopped = 'Stopped'; starting = 'Starting…'; stopping = 'Stopping…'
    notInstalled = 'Service not installed'; devMode = 'development mode (php -S)'; service = 'Windows service "Alice"'
    port = 'Port'; urls = 'Stations print at'; panel = 'Panel answering'; yes = 'yes'; no = 'no'
    start = 'Start'; stop = 'Stop'; restart = 'Restart'; openPanel = 'Open panel'; logs = 'Logs'; data = 'Data'; adapters = 'Adapters'
    dryRun = 'Dry run (nothing printed, jobs go to spool)'; log = 'Server log'; show = 'Show window'; exit = 'Exit'
    exitRunning = 'Exit closes only this window; the service keeps running.'; devExit = 'Exit will stop the development server. Continue?'
    phpMissing = 'php.exe not found (not on PATH nor in php\). Install PHP or use the Alice installer.'
    version = 'version'; dry = 'dry run'; live = 'live'
} }

# --- helpers ---------------------------------------------------------------------------
function Get-PhpExe {
    $bundled = Join-Path $root 'php\php.exe'
    if (Test-Path $bundled) { return $bundled }
    $cmd = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    return $null
}

function Get-AliceState {
    if ($devMode) {
        if ($G.devProcess -and -not $G.devProcess.HasExited) { return 'running' }
        return 'stopped'
    }
    $svc = Get-Service -Name 'Alice' -ErrorAction SilentlyContinue
    if (-not $svc) { return 'missing' }
    switch ($svc.Status) {
        'Running' { 'running' }
        'StartPending' { 'starting' }
        'StopPending' { 'stopping' }
        default { 'stopped' }
    }
}

function Invoke-ServiceVerb([string] $verb) {
    try {
        Start-Process -FilePath $serviceExe -ArgumentList $verb -Verb RunAs -WindowStyle Hidden -Wait
    } catch {
        # UAC cancelled: nothing to do
    }
}

function Start-Alice {
    if ($devMode) {
        if ($G.devProcess -and -not $G.devProcess.HasExited) { return }
        $php = Get-PhpExe
        if (-not $php) { [System.Windows.Forms.MessageBox]::Show($S.phpMissing, 'Alice', 'OK', 'Error') | Out-Null; return }
        $env:ALICE_DRY_RUN = if ($chkDry.Checked) { '1' } else { '0' }
        $env:ALICE_PORT = "$Port"
        $env:ALICE_DATA_DIR = $dataDir
        $phpArgs = @('-S', "0.0.0.0:$Port", '-t', (Join-Path $root 'public'), (Join-Path $root 'public\router.php'))
        $ini = Join-Path $root 'php\php.ini'
        if (Test-Path $ini) { $phpArgs = @('-c', $ini) + $phpArgs }
        $G.devProcess = Start-Process -FilePath $php -ArgumentList $phpArgs -WorkingDirectory $root -WindowStyle Hidden -PassThru `
            -RedirectStandardOutput (Join-Path $logsDir 'dev-server.out.log') -RedirectStandardError (Join-Path $logsDir 'dev-server.err.log')
    } else {
        Invoke-ServiceVerb 'start'
    }
}

function Stop-Alice {
    if ($devMode) {
        if ($G.devProcess -and -not $G.devProcess.HasExited) {
            # kill the process tree: a launcher shim (scoop, etc.) may sit between us and php.exe
            Start-Process -FilePath 'taskkill.exe' -ArgumentList @('/PID', $G.devProcess.Id, '/T', '/F') -WindowStyle Hidden -Wait
        }
        $G.devProcess = $null
    } else {
        Invoke-ServiceVerb 'stop'
    }
}

function Restart-Alice {
    if ($devMode) { Stop-Alice; Start-Sleep -Milliseconds 500; Start-Alice } else { Invoke-ServiceVerb 'restart' }
}

function Get-PanelStatus {
    try {
        $req = [System.Net.WebRequest]::Create("http://127.0.0.1:$Port/api/status")
        $req.Timeout = 1000
        $res = $req.GetResponse()
        $reader = New-Object System.IO.StreamReader($res.GetResponseStream())
        $json = $reader.ReadToEnd()
        $reader.Close(); $res.Close()
        return ($json | ConvertFrom-Json)
    } catch { return $null }
}

function Get-LogTail {
    $candidates = if ($devMode) {
        @((Join-Path $logsDir 'dev-server.err.log'), (Join-Path $logsDir 'dev-server.out.log'))
    } else {
        @((Join-Path $logsDir 'alice-service.err.log'), (Join-Path $logsDir 'alice-service.wrapper.log'), (Join-Path $logsDir 'alice-service.out.log'))
    }
    $lines = @()
    foreach ($f in $candidates) {
        if (Test-Path $f) {
            try { $lines += Get-Content -LiteralPath $f -Tail 40 -ErrorAction SilentlyContinue } catch { }
        }
    }
    return ($lines | Where-Object { $_ -and $_.Trim() -ne '' } | Select-Object -Last 40) -join "`r`n"
}

# Alice's icon (installer\alice.ico in a checkout, alice.ico when installed) with a status dot in the corner
$iconFile = @((Join-Path $root 'alice.ico'), (Join-Path $root 'installer\alice.ico')) | Where-Object { Test-Path $_ } | Select-Object -First 1
$appIcon = if ($iconFile) { New-Object System.Drawing.Icon $iconFile, 32, 32 } else { $null }
function New-StatusIcon([System.Drawing.Color] $color) {
    $bmp = New-Object System.Drawing.Bitmap 32, 32
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = 'AntiAlias'
    if ($appIcon) {
        $g.DrawIcon($appIcon, (New-Object System.Drawing.Rectangle 0, 0, 32, 32))
    } else {
        $g.TextRenderingHint = 'AntiAlias'
        $g.FillEllipse((New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromArgb(72, 110, 200))), 1, 1, 30, 30)
        $font = New-Object System.Drawing.Font('Segoe UI', 17, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel)
        $size = $g.MeasureString('A', $font)
        $g.DrawString('A', $font, [System.Drawing.Brushes]::White, (32 - $size.Width) / 2, (32 - $size.Height) / 2)
    }
    $g.FillEllipse([System.Drawing.Brushes]::White, 18, 18, 14, 14)
    $g.FillEllipse((New-Object System.Drawing.SolidBrush $color), 20, 20, 10, 10)
    $g.Dispose()
    return [System.Drawing.Icon]::FromHandle($bmp.GetHicon())
}

$iconGreen = New-StatusIcon ([System.Drawing.Color]::FromArgb(31, 138, 76))
$iconRed = New-StatusIcon ([System.Drawing.Color]::FromArgb(192, 57, 43))
$iconGrey = New-StatusIcon ([System.Drawing.Color]::FromArgb(120, 120, 120))
$iconAmber = New-StatusIcon ([System.Drawing.Color]::FromArgb(183, 121, 31))

function Open-Url([string] $url) { Start-Process $url | Out-Null }
function Open-Folder([string] $path) { if (-not (Test-Path $path)) { New-Item -ItemType Directory -Force $path | Out-Null }; Start-Process 'explorer.exe' $path | Out-Null }

# --- window --------------------------------------------------------------------------------
$form = New-Object System.Windows.Forms.Form
$form.Text = $S.title
$form.Size = New-Object System.Drawing.Size(560, 520)
$form.MinimumSize = New-Object System.Drawing.Size(480, 420)
$form.StartPosition = 'CenterScreen'
$form.Icon = if ($appIcon) { New-Object System.Drawing.Icon $iconFile } else { $iconGrey }
$form.Font = New-Object System.Drawing.Font('Segoe UI', 9.5)

$lblDot = New-Object System.Windows.Forms.Label
$lblDot.Text = [string][char]0x25CF
$lblDot.Font = New-Object System.Drawing.Font('Segoe UI', 26)
$lblDot.ForeColor = [System.Drawing.Color]::Gray
$lblDot.Location = New-Object System.Drawing.Point(16, 10)
$lblDot.AutoSize = $true

$lblState = New-Object System.Windows.Forms.Label
$lblState.Font = New-Object System.Drawing.Font('Segoe UI', 16, [System.Drawing.FontStyle]::Bold)
$lblState.Location = New-Object System.Drawing.Point(62, 18)
$lblState.AutoSize = $true
$lblState.Text = '…'

$lblMode = New-Object System.Windows.Forms.Label
$lblMode.ForeColor = [System.Drawing.Color]::DimGray
$lblMode.Location = New-Object System.Drawing.Point(64, 50)
$lblMode.AutoSize = $true
$lblMode.Text = if ($devMode) { $S.devMode } else { $S.service }

$lblInfo = New-Object System.Windows.Forms.Label
$lblInfo.Location = New-Object System.Drawing.Point(18, 80)
$lblInfo.Size = New-Object System.Drawing.Size(520, 70)
$lblInfo.Anchor = 'Top, Left, Right'

$btnStart = New-Object System.Windows.Forms.Button; $btnStart.Text = $S.start
$btnStop = New-Object System.Windows.Forms.Button; $btnStop.Text = $S.stop
$btnRestart = New-Object System.Windows.Forms.Button; $btnRestart.Text = $S.restart
$btnPanel = New-Object System.Windows.Forms.Button; $btnPanel.Text = $S.openPanel
$btnLogs = New-Object System.Windows.Forms.Button; $btnLogs.Text = $S.logs
$btnData = New-Object System.Windows.Forms.Button; $btnData.Text = $S.data
$btnAdapters = New-Object System.Windows.Forms.Button; $btnAdapters.Text = $S.adapters
$x = 18
foreach ($b in @($btnStart, $btnStop, $btnRestart)) {
    $b.Location = New-Object System.Drawing.Point($x, 158); $b.Size = New-Object System.Drawing.Size(96, 32); $x += 102
}
$btnPanel.Location = New-Object System.Drawing.Point(($x + 10), 158); $btnPanel.Size = New-Object System.Drawing.Size(120, 32)
$btnPanel.Font = New-Object System.Drawing.Font('Segoe UI', 9.5, [System.Drawing.FontStyle]::Bold)
$x = 18
foreach ($b in @($btnLogs, $btnData, $btnAdapters)) {
    $b.Location = New-Object System.Drawing.Point($x, 198); $b.Size = New-Object System.Drawing.Size(96, 28); $x += 102
}

$chkDry = New-Object System.Windows.Forms.CheckBox
$chkDry.Text = $S.dryRun
$chkDry.Location = New-Object System.Drawing.Point(20, 232)
$chkDry.AutoSize = $true
$chkDry.Checked = $true
$chkDry.Visible = $devMode

$lblLog = New-Object System.Windows.Forms.Label
$lblLog.Text = $S.log
$lblLog.ForeColor = [System.Drawing.Color]::DimGray
$lblLog.Location = New-Object System.Drawing.Point(18, 258)
$lblLog.AutoSize = $true

$txtLog = New-Object System.Windows.Forms.TextBox
$txtLog.Multiline = $true
$txtLog.ReadOnly = $true
$txtLog.ScrollBars = 'Vertical'
$txtLog.WordWrap = $false
$txtLog.Font = New-Object System.Drawing.Font('Consolas', 8.5)
$txtLog.BackColor = [System.Drawing.Color]::FromArgb(30, 34, 43)
$txtLog.ForeColor = [System.Drawing.Color]::FromArgb(220, 224, 232)
$txtLog.Location = New-Object System.Drawing.Point(18, 278)
$txtLog.Size = New-Object System.Drawing.Size(508, 190)
$txtLog.Anchor = 'Top, Bottom, Left, Right'

$form.Controls.AddRange(@($lblDot, $lblState, $lblMode, $lblInfo, $btnStart, $btnStop, $btnRestart, $btnPanel, $btnLogs, $btnData, $btnAdapters, $chkDry, $lblLog, $txtLog))

if ($SelfTest) {
    Write-Host "mode: $(if ($devMode) { 'development' } else { 'service' }), port $Port, state before: $(Get-AliceState)"
    Start-Alice
    Start-Sleep -Seconds 3
    $panel = Get-PanelStatus
    Write-Host "state after start: $(Get-AliceState), panel answers: $([bool]$panel)$(if ($panel) { " (version $($panel.version), dry_run $($panel.dry_run))" })"
    Stop-Alice
    Start-Sleep -Seconds 2
    $listening = (netstat -ano -p tcp | Select-String ":$Port .*LISTENING") -ne $null
    Write-Host "state after stop: $(Get-AliceState), port still listening: $listening"
    exit $(if ($panel -and -not $listening) { 0 } else { 1 })
}

# --- tray ------------------------------------------------------------------------------------
$tray = New-Object System.Windows.Forms.NotifyIcon
$tray.Icon = $iconGrey
$tray.Text = 'Alice'
$tray.Visible = $true
$menu = New-Object System.Windows.Forms.ContextMenuStrip
$miShow = $menu.Items.Add($S.show)
$miPanel = $menu.Items.Add($S.openPanel)
$menu.Items.Add('-') | Out-Null
$miStart = $menu.Items.Add($S.start)
$miStop = $menu.Items.Add($S.stop)
$miRestart = $menu.Items.Add($S.restart)
$menu.Items.Add('-') | Out-Null
$miExit = $menu.Items.Add($S.exit)
$tray.ContextMenuStrip = $menu

function Show-Window {
    $form.Show()
    $form.WindowState = 'Normal'
    $form.Activate()
}

# --- refresh -----------------------------------------------------------------------------------
$hostName = $env:COMPUTERNAME
function Get-LanIps {
    try {
        return @(Get-NetIPAddress -AddressFamily IPv4 -ErrorAction Stop | Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' -and $_.PrefixOrigin -ne 'WellKnown' } | Sort-Object { if ($_.IPAddress -like '192.168.*' -or $_.IPAddress -like '10.*' -or $_.IPAddress -like '172.*') { 0 } else { 1 } } | Select-Object -ExpandProperty IPAddress)
    } catch { return @() }
}
$lanIps = Get-LanIps
$portSuffix = if ($Port -eq 80) { '' } else { ":$Port" }

function Update-View {
    $state = Get-AliceState
    $panel = $null
    if ($state -eq 'running') { $panel = Get-PanelStatus }
    switch ($state) {
        'running' { $lblState.Text = $S.running; $color = [System.Drawing.Color]::FromArgb(31, 138, 76); $icon = $iconGreen }
        'starting' { $lblState.Text = $S.starting; $color = [System.Drawing.Color]::FromArgb(183, 121, 31); $icon = $iconAmber }
        'stopping' { $lblState.Text = $S.stopping; $color = [System.Drawing.Color]::FromArgb(183, 121, 31); $icon = $iconAmber }
        'missing' { $lblState.Text = $S.notInstalled; $color = [System.Drawing.Color]::Gray; $icon = $iconGrey }
        default { $lblState.Text = $S.stopped; $color = [System.Drawing.Color]::FromArgb(192, 57, 43); $icon = $iconRed }
    }
    if ($state -eq 'running' -and -not $panel) { $color = [System.Drawing.Color]::FromArgb(183, 121, 31); $icon = $iconAmber }
    $lblDot.ForeColor = $color
    if ($G.lastState -ne "$state/$([bool]$panel)") {
        $tray.Icon = $icon
        $G.lastState = "$state/$([bool]$panel)"
    }
    $tray.Text = "Alice - $($lblState.Text) ($($S.port) $Port)"

    $urls = @("http://$hostName$portSuffix/") + ($lanIps | ForEach-Object { "http://$_$portSuffix/" })
    $panelText = if ($panel) { "$($S.yes) - $($S.version) $($panel.version), " + $(if ($panel.dry_run) { $S.dry } else { $S.live }) } else { $S.no }
    $lblInfo.Text = "$($S.port): $Port`r`n$($S.urls): " + ($urls -join '  ') + "`r`n$($S.panel): $panelText"

    $canStart = $state -in @('stopped', 'missing') -and -not ($state -eq 'missing' -and -not $devMode)
    $btnStart.Enabled = $canStart; $miStart.Enabled = $canStart
    $btnStop.Enabled = ($state -eq 'running'); $miStop.Enabled = ($state -eq 'running')
    $btnRestart.Enabled = ($state -eq 'running'); $miRestart.Enabled = ($state -eq 'running')
    $btnPanel.Enabled = [bool]$panel; $miPanel.Enabled = [bool]$panel
    $chkDry.Enabled = ($state -ne 'running')

    $tail = Get-LogTail
    if ($txtLog.Text -ne $tail) {
        $txtLog.Text = $tail
        $txtLog.SelectionStart = $txtLog.Text.Length
        $txtLog.ScrollToCaret()
    }
}

$timer = New-Object System.Windows.Forms.Timer
$timer.Interval = 3000
$timer.Add_Tick({ try { Update-View } catch { } })

# --- events --------------------------------------------------------------------------------------
$panelUrl = "http://localhost$portSuffix/"
$btnStart.Add_Click({ Start-Alice; Start-Sleep -Milliseconds 800; Update-View })
$btnStop.Add_Click({ Stop-Alice; Start-Sleep -Milliseconds 500; Update-View })
$btnRestart.Add_Click({ Restart-Alice; Start-Sleep -Milliseconds 800; Update-View })
$btnPanel.Add_Click({ Open-Url $panelUrl })
$btnLogs.Add_Click({ Open-Folder $logsDir })
$btnData.Add_Click({ Open-Folder $dataDir })
$btnAdapters.Add_Click({ Open-Folder $adaptersDir })
$miShow.Add_Click({ Show-Window })
$miPanel.Add_Click({ Open-Url $panelUrl })
$miStart.Add_Click({ Start-Alice; Start-Sleep -Milliseconds 800; Update-View })
$miStop.Add_Click({ Stop-Alice; Start-Sleep -Milliseconds 500; Update-View })
$miRestart.Add_Click({ Restart-Alice; Start-Sleep -Milliseconds 800; Update-View })
$tray.Add_DoubleClick({ Show-Window })
$miExit.Add_Click({
    if ($devMode -and (Get-AliceState) -eq 'running') {
        if ([System.Windows.Forms.MessageBox]::Show($S.devExit, 'Alice', 'YesNo', 'Question') -ne 'Yes') { return }
        Stop-Alice
    }
    $G.exiting = $true
    $form.Close()
})
$form.Add_FormClosing({
    param($sender, $e)
    if (-not $G.exiting) {
        $e.Cancel = $true
        $form.Hide()
        if (-not $devMode) { $tray.ShowBalloonTip(1500, 'Alice', $S.exitRunning, 'Info') }
    }
})
$form.Add_Resize({ if ($form.WindowState -eq 'Minimized') { $form.Hide() } })

# --- run ------------------------------------------------------------------------------------------
Update-View
$timer.Start()
if (-not $Minimized) { Show-Window }
[System.Windows.Forms.Application]::Run()
$timer.Stop()
$tray.Visible = $false
$tray.Dispose()
