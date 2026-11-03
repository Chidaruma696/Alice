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
UninstallDisplayIcon={app}\alice-service.exe
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
en.PanelShortcut=Alice panel
en.LogsShortcut=Alice logs
en.UninstallKeepData=Keep the data folder (printers, stations, jobs) and the adapters folder?%n%nChoose No to delete them.
en.FirewallMsg=Configuring the firewall...
en.ServiceMsg=Installing the Alice service...
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
es.PanelShortcut=Panel de Alice
es.LogsShortcut=Registros de Alice
es.UninstallKeepData=¿Conservar la carpeta de datos (impresoras, estaciones, trabajos) y la carpeta de adaptadores?%n%nElige No para borrarlas.
es.FirewallMsg=Configurando el firewall...
es.ServiceMsg=Instalando el servicio de Alice...

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"

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
Source: "payload\alice-service.xml.template"; DestDir: "{app}"; Flags: ignoreversion
; The example adapter is updated on upgrade; every other adapters\* directory is never touched.
Source: "payload\adapters\example\*"; DestDir: "{app}\adapters\example"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{group}\{cm:PanelShortcut}"; Filename: "http://localhost:{code:GetPort}/"
Name: "{group}\{cm:LogsShortcut}"; Filename: "{app}\data\logs"
Name: "{group}\{cm:UninstallProgram,{#AppName}}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\{cm:PanelShortcut}"; Filename: "http://localhost:{code:GetPort}/"; Tasks: desktopicon

[Run]
Filename: "netsh"; Parameters: "advfirewall firewall delete rule name=""{#ServiceName}"""; Flags: runhidden; StatusMsg: "{cm:FirewallMsg}"
Filename: "netsh"; Parameters: "advfirewall firewall add rule name=""{#ServiceName}"" dir=in action=allow protocol=TCP localport={code:GetPort} profile=private,domain"; Flags: runhidden; StatusMsg: "{cm:FirewallMsg}"
Filename: "{app}\alice-service.exe"; Parameters: "install"; Flags: runhidden; StatusMsg: "{cm:ServiceMsg}"
Filename: "{app}\alice-service.exe"; Parameters: "start"; Flags: runhidden; StatusMsg: "{cm:ServiceMsg}"
Filename: "powershell"; Parameters: "-NoProfile -NonInteractive -ExecutionPolicy Bypass -Command ""Rename-Computer -NewName '{code:GetComputerName}' -Force"""; Flags: runhidden; Check: WantsRename
Filename: "http://localhost:{code:GetPort}/"; Description: "{cm:OpenPanel}"; Flags: shellexec postinstall nowait skipifsilent

[UninstallRun]
Filename: "{app}\alice-service.exe"; Parameters: "stop"; Flags: runhidden; RunOnceId: "AliceStop"
Filename: "{app}\alice-service.exe"; Parameters: "uninstall"; Flags: runhidden; RunOnceId: "AliceUninstall"
Filename: "netsh"; Parameters: "advfirewall firewall delete rule name=""{#ServiceName}"""; Flags: runhidden; RunOnceId: "AliceFirewall"

[UninstallDelete]
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
    PortPage.Values[0] := PreviousPort;
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

procedure CurStepChanged(CurStep: TSetupStep);
begin
  if CurStep = ssInstall then
    StopExistingService
  else if CurStep = ssPostInstall then
    WriteServiceXml;
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
