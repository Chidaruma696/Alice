; Alice — Inno Setup script. Compiled by installer\build.ps1, which stages
; installer\payload\ first. Do not run ISCC on this file without the payload.

#ifndef AppVersion
  #define AppVersion "0.0.0"
#endif
#define AppName "Alice"
#define ServiceName "Alice"

[Setup]
AppId={{7D5C3B1E-5A8E-4C2B-9A41-0C3F2A1B9E77}
AppName={#AppName}
AppVersion={#AppVersion}
AppVerName={#AppName} {#AppVersion}
AppPublisher=Alice contributors
AppPublisherURL=https://github.com/
DefaultDirName={autopf}\Alice
DefaultGroupName=Alice
DisableProgramGroupPage=yes
LicenseFile=payload\app\LICENSE
PrivilegesRequired=admin
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
OutputDir=dist
OutputBaseFilename=Alice-{#AppVersion}-setup
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
UninstallDisplayName={#AppName} print server
UninstallDisplayIcon={app}\alice.ico
SetupIconFile=alice.ico
CloseApplications=no
SetupLogging=yes

[Languages]
Name: "en"; MessagesFile: "compiler:Default.isl"
Name: "es"; MessagesFile: "compiler:Languages\Spanish.isl"

[CustomMessages]
en.PortPageCaption=HTTP port
en.PortPageDescription=Stations print by opening http://<this PC>:<port>/
en.PortPageSub=Port 80 lets stations use http://alice/ with no port in the address. Change it only if another program already uses port 80.
en.PortLabel=Port:
en.PortInvalid=The port must be a number between 1 and 65535.
en.PortInUse=Another program is already listening on port %1. Alice will not start until that port is free.%n%nDo you want to choose a different port?
en.RenamePageCaption=Computer name
en.RenamePageDescription=Let stations reach this PC by name
en.RenameCheck=Rename this computer so stations can use http://%1/
en.RenameLabel=Computer name:
en.RenameNote=The new name applies after the next restart. The installer will not restart the PC.
en.RenameInvalid=The computer name must have 1 to 15 letters, digits or hyphens.
en.OpenPanel=Open the Alice panel
en.OpenControl=Open the Alice control window
en.StartupTask=Show the Alice icon in the notification area at login
en.ControlShortcutComment=Start, stop and watch the Alice print server
en.PanelShortcut=Alice panel
en.LogsShortcut=Alice logs
en.UninstallKeepData=Keep the data folder (printers, stations, jobs) and the adapters folder?%n%nChoose No to delete them.
en.FirewallMsg=Configuring the firewall...
en.ServiceMsg=Installing the Alice service...
en.StartMsg=Starting the Alice service...
en.RuntimeMsg=Installing the Microsoft Visual C++ runtime (needed by PHP)...
en.RuntimeFailed=The Microsoft Visual C++ runtime could not be installed (code %1). PHP will not start without it. Install "Microsoft Visual C++ 2015-2022 Redistributable (x64)" from Microsoft and then start Alice from its control window.
en.ServiceInstallFailed=The Alice service could not be installed (code %1). Open the Alice control window and press Start to try again; details are in the setup log:%n%2
en.ServiceStartFailed=The Alice service was installed but did not start (code %1). Check the files in %2 and press Start in the Alice control window.
en.ServiceNotRunning=The Alice service was installed but is not running. Check the files in %1 and press Start in the Alice control window.
es.PortPageCaption=Puerto HTTP
es.PortPageDescription=Las estaciones imprimen abriendo http://<esta PC>:<puerto>/
es.PortPageSub=El puerto 80 permite a las estaciones usar http://alice/ sin puerto en la dirección. Cámbialo solo si otro programa ya usa el puerto 80.
es.PortLabel=Puerto:
es.PortInvalid=El puerto debe ser un número entre 1 y 65535.
es.PortInUse=Otro programa ya está escuchando en el puerto %1. Alice no arrancará hasta que ese puerto quede libre.%n%n¿Quieres elegir otro puerto?
es.RenamePageCaption=Nombre del equipo
es.RenamePageDescription=Permite que las estaciones lleguen a esta PC por nombre
es.RenameCheck=Renombrar este equipo para que las estaciones usen http://%1/
es.RenameLabel=Nombre del equipo:
es.RenameNote=El nuevo nombre aplica después del próximo reinicio. El instalador no reiniciará la PC.
es.RenameInvalid=El nombre del equipo debe tener de 1 a 15 letras, dígitos o guiones.
es.OpenPanel=Abrir el panel de Alice
es.OpenControl=Abrir la ventana de control de Alice
es.StartupTask=Mostrar el icono de Alice en el área de notificación al iniciar sesión
es.ControlShortcutComment=Iniciar, detener y vigilar el servidor de impresión Alice
es.PanelShortcut=Panel de Alice
es.LogsShortcut=Registros de Alice
es.UninstallKeepData=¿Conservar la carpeta de datos (impresoras, estaciones, trabajos) y la carpeta de adaptadores?%n%nElige No para borrarlas.
es.FirewallMsg=Configurando el firewall...
es.ServiceMsg=Instalando el servicio de Alice...
es.StartMsg=Iniciando el servicio de Alice...
es.RuntimeMsg=Instalando el runtime de Microsoft Visual C++ (lo necesita PHP)...
es.RuntimeFailed=No se pudo instalar el runtime de Microsoft Visual C++ (código %1). PHP no arrancará sin él. Instala "Microsoft Visual C++ 2015-2022 Redistributable (x64)" desde Microsoft y luego inicia Alice desde su ventana de control.
es.ServiceInstallFailed=No se pudo instalar el servicio de Alice (código %1). Abre la ventana de control de Alice y pulsa Iniciar para reintentar; los detalles están en el registro del instalador:%n%2
es.ServiceStartFailed=El servicio de Alice se instaló pero no arrancó (código %1). Revisa los archivos en %2 y pulsa Iniciar en la ventana de control de Alice.
es.ServiceNotRunning=El servicio de Alice se instaló pero no está en ejecución. Revisa los archivos en %1 y pulsa Iniciar en la ventana de control de Alice.

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"
Name: "startup"; Description: "{cm:StartupTask}"; GroupDescription: "{cm:AdditionalIcons}"

[Dirs]
Name: "{app}\data"
Name: "{app}\data\spool"
Name: "{app}\data\last"
Name: "{app}\data\logs"
Name: "{app}\adapters"

[Files]
Source: "payload\php\*"; DestDir: "{app}\php"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "payload\app\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "payload\alice-service.exe"; DestDir: "{app}"; Flags: ignoreversion
Source: "alice.ico"; DestDir: "{app}"; Flags: ignoreversion
Source: "payload\alice-service.xml.template"; DestDir: "{app}"; Flags: ignoreversion
; Microsoft Visual C++ 2015-2022 runtime: php.exe needs it and a clean Windows does not have it.
Source: "payload\VC_redist.x64.exe"; DestDir: "{tmp}"; Check: NeedsVcRuntime
; The example adapter is updated on upgrade. Any other adapter staged by build.ps1 (an office
; adapter present in the build checkout) is installed too: its code is refreshed on upgrade,
; its config.php is only written when the server has none, so local edits survive.
Source: "payload\adapters\example\*"; DestDir: "{app}\adapters\example"; Flags: ignoreversion recursesubdirs createallsubdirs
#define AdaptersDir "payload\adapters"
#define FindHandle
#define FindResult
#define AdapterName
#sub AdapterEntry
  #define AdapterName FindGetFileName(FindHandle)
  #if AdapterName != "example" && AdapterName != "." && AdapterName != ".."
    #if FileExists(AdaptersDir + "\" + AdapterName + "\adapter.php")
      #pragma message "bundling adapter " + AdapterName
Source: "{#AdaptersDir}\{#AdapterName}\*"; DestDir: "{app}\adapters\{#AdapterName}"; Excludes: "config.php"; Flags: ignoreversion recursesubdirs createallsubdirs
      #if FileExists(AdaptersDir + "\" + AdapterName + "\config.php")
Source: "{#AdaptersDir}\{#AdapterName}\config.php"; DestDir: "{app}\adapters\{#AdapterName}"; Flags: onlyifdoesntexist
      #endif
    #endif
  #endif
#endsub
#for {FindHandle = FindResult = FindFirst(AdaptersDir + "\*", faDirectory); FindResult; FindResult = FindNext(FindHandle)} AdapterEntry
#if FindHandle
  #expr FindClose(FindHandle)
#endif

[Icons]
Name: "{group}\Alice"; Filename: "{sys}\WindowsPowerShell\v1.0\powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File ""{app}\tools\alice-tray.ps1"""; WorkingDir: "{app}"; IconFilename: "{app}\alice.ico"; Comment: "{cm:ControlShortcutComment}"
Name: "{group}\{cm:PanelShortcut}"; Filename: "http://localhost:{code:GetPort}/"
Name: "{userstartup}\Alice"; Filename: "{sys}\WindowsPowerShell\v1.0\powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File ""{app}\tools\alice-tray.ps1"" -Minimized"; WorkingDir: "{app}"; IconFilename: "{app}\alice.ico"; Tasks: startup
Name: "{group}\{cm:LogsShortcut}"; Filename: "{app}\data\logs"
Name: "{group}\{cm:UninstallProgram,{#AppName}}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\Alice"; Filename: "{sys}\WindowsPowerShell\v1.0\powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File ""{app}\tools\alice-tray.ps1"""; WorkingDir: "{app}"; IconFilename: "{app}\alice.ico"; Tasks: desktopicon

[Run]
; Runtime, firewall rule and service are set up in [Code] (CurStepChanged/ssPostInstall)
; so that each step's exit code is checked and reported; [Run] entries ignore exit codes.
Filename: "powershell"; Parameters: "-NoProfile -NonInteractive -ExecutionPolicy Bypass -Command ""Rename-Computer -NewName '{code:GetComputerName}' -Force"""; Flags: runhidden; Check: WantsRename
Filename: "{sys}\WindowsPowerShell\v1.0\powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File ""{app}\tools\alice-tray.ps1"""; WorkingDir: "{app}"; Description: "{cm:OpenControl}"; Flags: postinstall nowait skipifsilent runasoriginaluser

[UninstallRun]
Filename: "{app}\alice-service.exe"; Parameters: "stop"; Flags: runhidden; RunOnceId: "AliceStop"
Filename: "{app}\alice-service.exe"; Parameters: "uninstall"; Flags: runhidden; RunOnceId: "AliceUninstall"
Filename: "netsh"; Parameters: "advfirewall firewall delete rule name=""{#ServiceName}"""; Flags: runhidden; RunOnceId: "AliceFirewall"

[UninstallDelete]
Type: files; Name: "{userstartup}\Alice.lnk"
Type: files; Name: "{app}\alice-service.xml"
Type: files; Name: "{app}\alice-service.wrapper.log"
Type: files; Name: "{app}\alice-service.err.log"
Type: files; Name: "{app}\alice-service.out.log"
Type: files; Name: "{app}\data\installed-cache.json"

[Code]
var
  PortPage: TInputQueryWizardPage;
  RenamePage: TWizardPage;
  RenameCheck: TNewCheckBox;
  RenameEdit: TNewEdit;
  ChosenPort: String;

function GetPort(Param: String): String;
begin
  Result := ChosenPort;
end;

function GetComputerName(Param: String): String;
begin
  Result := Trim(RenameEdit.Text);
end;

function WantsRename: Boolean;
begin
  Result := RenameCheck.Checked and (Trim(RenameEdit.Text) <> '');
end;

function ServiceExists: Boolean;
var
  Code: Integer;
begin
  Result := Exec('sc.exe', 'query {#ServiceName}', '', SW_HIDE, ewWaitUntilTerminated, Code) and (Code = 0);
end;

function ServiceRunning: Boolean;
var
  Code: Integer;
begin
  Result := Exec(ExpandConstant('{cmd}'), '/c sc query {#ServiceName} | findstr /c:"RUNNING" >nul', '', SW_HIDE, ewWaitUntilTerminated, Code) and (Code = 0);
end;

{ The PHP build (vs16) needs the Visual C++ 2015-2022 x64 runtime, i.e. 14.20 or newer. }
function NeedsVcRuntime: Boolean;
var
  Installed, Minor: Cardinal;
begin
  Result := True;
  if RegQueryDWordValue(HKLM64, 'SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64', 'Installed', Installed)
    and RegQueryDWordValue(HKLM64, 'SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64', 'Minor', Minor) then
    Result := not ((Installed = 1) and (Minor >= 20));
  Log(Format('Visual C++ runtime needed: %d', [Integer(Result)]));
end;

{ /PORT=8080 on the command line pre-fills the port page and is used by silent installs. }
function InitializeSetup: Boolean;
begin
  ChosenPort := Trim(ExpandConstant('{param:PORT|}'));
  if (ChosenPort <> '') and ((StrToIntDef(ChosenPort, 0) < 1) or (StrToIntDef(ChosenPort, 0) > 65535)) then
  begin
    Log('ignoring invalid /PORT=' + ChosenPort);
    ChosenPort := '';
  end;
  Result := True;
end;

{ Previous port from the service XML of an existing installation, else 80. }
function PreviousPort: String;
var
  Xml: AnsiString;
  P, E: Integer;
  S: String;
begin
  Result := '80';
  if LoadStringFromFile(ExpandConstant('{app}\alice-service.xml'), Xml) then
  begin
    S := String(Xml);
    P := Pos('name="ALICE_PORT" value="', S);
    if P > 0 then
    begin
      S := Copy(S, P + Length('name="ALICE_PORT" value="'), 10);
      E := Pos('"', S);
      if E > 1 then Result := Copy(S, 1, E - 1);
    end;
  end;
end;

function PortInUse(Port: String): Boolean;
var
  Code: Integer;
begin
  { findstr exits 0 when a LISTENING socket on that port exists }
  Result := Exec(ExpandConstant('{cmd}'), '/c netstat -ano -p tcp | findstr /r /c:":' + Port + ' .*LISTENING" >nul', '', SW_HIDE, ewWaitUntilTerminated, Code) and (Code = 0);
end;

procedure InitializeWizard;
var
  Note: TNewStaticText;
begin
  PortPage := CreateInputQueryPage(wpSelectDir, CustomMessage('PortPageCaption'), CustomMessage('PortPageDescription'), CustomMessage('PortPageSub'));
  PortPage.Add(CustomMessage('PortLabel'), False);
  PortPage.Values[0] := '80';

  RenamePage := CreateCustomPage(PortPage.ID, CustomMessage('RenamePageCaption'), CustomMessage('RenamePageDescription'));
  RenameCheck := TNewCheckBox.Create(RenamePage);
  RenameCheck.Parent := RenamePage.Surface;
  RenameCheck.Left := 0;
  RenameCheck.Top := 8;
  RenameCheck.Width := RenamePage.SurfaceWidth;
  RenameCheck.Caption := FmtMessage(CustomMessage('RenameCheck'), ['alice']);
  RenameCheck.Checked := False;

  Note := TNewStaticText.Create(RenamePage);
  Note.Parent := RenamePage.Surface;
  Note.Left := 0;
  Note.Top := RenameCheck.Top + RenameCheck.Height + 16;
  Note.Caption := CustomMessage('RenameLabel');

  RenameEdit := TNewEdit.Create(RenamePage);
  RenameEdit.Parent := RenamePage.Surface;
  RenameEdit.Left := 0;
  RenameEdit.Top := Note.Top + Note.Height + 4;
  RenameEdit.Width := 200;
  RenameEdit.Text := 'alice';

  Note := TNewStaticText.Create(RenamePage);
  Note.Parent := RenamePage.Surface;
  Note.Left := 0;
  Note.Top := RenameEdit.Top + RenameEdit.Height + 16;
  Note.Width := RenamePage.SurfaceWidth;
  Note.WordWrap := True;
  Note.Caption := CustomMessage('RenameNote');
end;

procedure CurPageChanged(CurPageID: Integer);
begin
  if CurPageID = PortPage.ID then
  begin
    if ChosenPort <> '' then
      PortPage.Values[0] := ChosenPort
    else
      PortPage.Values[0] := PreviousPort;
  end;
end;

function NextButtonClick(CurPageID: Integer): Boolean;
var
  Port: Integer;
  Name: String;
  I: Integer;
begin
  Result := True;
  if CurPageID = PortPage.ID then
  begin
    Port := StrToIntDef(Trim(PortPage.Values[0]), 0);
    if (Port < 1) or (Port > 65535) then
    begin
      MsgBox(CustomMessage('PortInvalid'), mbError, MB_OK);
      Result := False;
      exit;
    end;
    ChosenPort := IntToStr(Port);
    { On an upgrade the running Alice service owns the port; that is not a conflict. }
    if (not ServiceExists) and PortInUse(ChosenPort) then
    begin
      if MsgBox(FmtMessage(CustomMessage('PortInUse'), [ChosenPort]), mbConfirmation, MB_YESNO) = IDYES then
        Result := False;
    end;
  end
  else if (CurPageID = RenamePage.ID) and RenameCheck.Checked then
  begin
    Name := Trim(RenameEdit.Text);
    if (Length(Name) < 1) or (Length(Name) > 15) then
      Result := False;
    for I := 1 to Length(Name) do
      if not ((Name[I] >= 'a') and (Name[I] <= 'z') or (Name[I] >= 'A') and (Name[I] <= 'Z') or (Name[I] >= '0') and (Name[I] <= '9') or (Name[I] = '-')) then
        Result := False;
    if not Result then
      MsgBox(CustomMessage('RenameInvalid'), mbError, MB_OK);
  end;
end;

procedure StopExistingService;
var
  Code: Integer;
  Exe: String;
begin
  Exe := ExpandConstant('{app}\alice-service.exe');
  if FileExists(Exe) and ServiceExists then
  begin
    Exec(Exe, 'stop', '', SW_HIDE, ewWaitUntilTerminated, Code);
    Exec(Exe, 'uninstall', '', SW_HIDE, ewWaitUntilTerminated, Code);
    Sleep(1500);
  end;
end;

procedure WriteServiceXml;
var
  Xml: AnsiString;
  S: String;
begin
  if LoadStringFromFile(ExpandConstant('{app}\alice-service.xml.template'), Xml) then
  begin
    S := String(Xml);
    StringChangeEx(S, '{{PORT}}', ChosenPort, True);
    SaveStringToFile(ExpandConstant('{app}\alice-service.xml'), AnsiString(S), False);
  end;
end;

{ Runs a command hidden, logs the exit code, returns True when it ran and exited with 0. }
function RunStep(const Exe, Params, StatusMsg: String; var Code: Integer): Boolean;
begin
  WizardForm.StatusLabel.Caption := StatusMsg;
  Log(Format('run: "%s" %s', [Exe, Params]));
  Result := Exec(Exe, Params, ExpandConstant('{app}'), SW_HIDE, ewWaitUntilTerminated, Code);
  if not Result then
  begin
    Log('  could not start: ' + SysErrorMessage(Code));
    exit;
  end;
  Log(Format('  exit code %d', [Code]));
  Result := (Code = 0);
end;

{ Visual C++ runtime, firewall rule, service registration and start, in this order,
  each checked. [Run] entries would silently ignore a failing step, and Inno Setup
  processes [Run] BEFORE CurStepChanged(ssPostInstall) (verified with 6.7.3): the
  first release registered the service from [Run] before alice-service.xml existed,
  so the service was never installed. }
procedure PostInstall;
var
  Code: Integer;
  Exe, Logs: String;
begin
  WriteServiceXml;
  Exe := ExpandConstant('{app}\alice-service.exe');
  Logs := ExpandConstant('{app}\data\logs');

  if NeedsVcRuntime then
  begin
    { 1638: a newer version is already installed; 3010: installed, reboot pending (not required for php) }
    if not RunStep(ExpandConstant('{tmp}\VC_redist.x64.exe'), '/install /quiet /norestart', CustomMessage('RuntimeMsg'), Code)
      and (Code <> 1638) and (Code <> 3010) then
      SuppressibleMsgBox(FmtMessage(CustomMessage('RuntimeFailed'), [IntToStr(Code)]), mbError, MB_OK, IDOK);
  end;

  RunStep('netsh', 'advfirewall firewall delete rule name="{#ServiceName}"', CustomMessage('FirewallMsg'), Code);
  if not RunStep('netsh', 'advfirewall firewall add rule name="{#ServiceName}" dir=in action=allow protocol=TCP localport=' + ChosenPort + ' profile=private,domain', CustomMessage('FirewallMsg'), Code) then
    Log('firewall rule not created; stations on the LAN may be blocked');

  if not RunStep(Exe, 'install', CustomMessage('ServiceMsg'), Code) then
  begin
    SuppressibleMsgBox(FmtMessage(CustomMessage('ServiceInstallFailed'), [IntToStr(Code), ExpandConstant('{log}')]), mbError, MB_OK, IDOK);
    exit;
  end;
  if not RunStep(Exe, 'start', CustomMessage('StartMsg'), Code) then
  begin
    SuppressibleMsgBox(FmtMessage(CustomMessage('ServiceStartFailed'), [IntToStr(Code), Logs]), mbError, MB_OK, IDOK);
    exit;
  end;
  Sleep(2500);
  if ServiceRunning then
    Log('service {#ServiceName} is running on port ' + ChosenPort)
  else
  begin
    Log('service {#ServiceName} is not running after start');
    SuppressibleMsgBox(FmtMessage(CustomMessage('ServiceNotRunning'), [Logs]), mbError, MB_OK, IDOK);
  end;
end;

procedure CurStepChanged(CurStep: TSetupStep);
begin
  if CurStep = ssInstall then
  begin
    { silent install (no port page): keep the port of an existing installation, else 80 }
    if ChosenPort = '' then
      ChosenPort := PreviousPort;
    Log('port ' + ChosenPort);
    StopExistingService;
  end
  else if CurStep = ssPostInstall then
    PostInstall;
end;

procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if CurUninstallStep = usPostUninstall then
  begin
    if MsgBox(CustomMessage('UninstallKeepData'), mbConfirmation, MB_YESNO or MB_DEFBUTTON1) = IDNO then
    begin
      DelTree(ExpandConstant('{app}\data'), True, True, True);
      DelTree(ExpandConstant('{app}\adapters'), True, True, True);
      RemoveDir(ExpandConstant('{app}'));
    end;
  end;
end;
