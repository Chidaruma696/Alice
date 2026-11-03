# Installing Alice on Windows

## Requirements

- Windows 10 or 11 (64-bit), administrator rights for the installer.
- The ticket printer installed on this PC (USB, LPT, or a network share from another PC). It does **not** need to be shared.
- Stations on the same LAN that open URLs from a browser or any HTTP client.

Nothing else: the installer bundles PHP, the Microsoft Visual C++ runtime PHP needs (installed silently only if the PC does not have it), the service wrapper, and the raw-print helper. A clean Windows needs no download.

Silent install (deployment scripts): `Alice-<version>-setup.exe /VERYSILENT /NORESTART /PORT=80 /LOG="C:\alice-setup.log"`. Without `/PORT` a silent install keeps the port of an existing installation, else 80.

## Install

1. Run `Alice-<version>-setup.exe` (English or Spanish).
2. **HTTP port**: keep `80` so stations use `http://alice/` with no port. If another program already listens on 80 (IIS, Skype, another web server) the installer warns and lets you pick e.g. `8080`; stations then use `http://alice:8080/`.
3. **Computer name** (optional): tick it to rename this PC to `alice` (or any name). Stations can then reach it as `http://alice/`. The name applies after the next restart; the installer never restarts the PC.
4. Finish. The service **Alice** is installed, started, set to start at boot, and a firewall rule "Alice" opens the port on private and domain networks. "Alice panel" in the Start menu (and optionally the desktop) opens `http://localhost:<port>/`.

## Control window (tray)

"Alice" in the Start menu (and the icon in the notification area after login) opens a small Laragon-style window: a green/red light with the service state, the port and the URLs stations use, **Start / Stop / Restart** (each asks for administrator confirmation, the service needs it), **Open panel**, and the **Logs / Data / Adapters** folders, plus the tail of the service log so a crash or restart is visible. Closing the window keeps the tray icon; the service itself keeps running regardless of the window. Right-click the icon for the same actions.

The same window runs from a git checkout without the service (`powershell -ExecutionPolicy Bypass -File tools\alice-tray.ps1 -Port 8080`): it then shows "development mode", starts `php -S` itself, and has a dry-run switch.

## First configuration

In the panel:

1. **Printers installed on this PC** lists what Windows knows. Click **Add** next to the ticket printer. It is registered as kind `local` and becomes the default printer.
2. Click **Test page**. If the printer prints, you are done with the server.
3. Open the panel from a cash register (`http://alice/` or `http://<server IP>/`): the station appears under **Stations** the first time it prints. Give it a name; leave its printer empty to use the default, or pick one.
4. Optional: set an **Admin PIN** so only the person in charge can change things. Printing never asks for the PIN.

5. **Office adapter**: if the installer was built with it (see *Building the installer*) it is already in `adapters\office\`; otherwise copy your `adapters\office\` folder (the one that serves the sales system's URLs, see [adapters.md](adapters.md)) into `C:\Program Files\Alice\adapters\office\` and restart the service from the control window. The panel's adapter list must show it with a green check; from then on `http://alice/printer/imprime_nota.php?id_nota=N` prints on the printer of the station that opens it.

Give the stations fixed IPs (DHCP reservations on the router). Alice identifies a station by IP; if an IP changes, the station shows up as new and uses the default printer until assigned again.

## Name resolution for stations

Stations need to reach the server. Pick one:

- **Computer rename** (installer option or *Settings → System → About → Rename*): Windows name resolution on a LAN lets other Windows PCs open `http://alice/` with no further setup.
- **Router DNS entry**: most office routers allow a static host → IP entry; add `alice` → the server's IP (shown in the panel's status block).
- **`hosts` file** on each station (`C:\Windows\System32\drivers\etc\hosts`): `172.18.1.7 alice`. Works, but it is what Alice was built to avoid; use it only if the router cannot.
- **Plain IP**: point the sales system at `http://172.18.1.7/`.

## Where things are

| path | what |
|------|------|
| `C:\Program Files\Alice\` | application, `php\`, `alice-service.exe` + `alice-service.xml` |
| `...\data\` | `printers.json`, `stations.json`, `config.json`, `jobs.jsonl`, `spool\`, `last\`, `logs\` |
| `...\adapters\` | `example\` (shipped) and your `office\` adapter |
| `...\data\logs\` | `alice-service.out.log` / `.err.log` (PHP server output), `alice-service.wrapper.log` (service events, restarts), `php-error.log` |

## Upgrade

Run the newer installer over the existing installation. It stops the service, replaces the application and the PHP runtime, keeps `data\` and `adapters\` (only `adapters\example` is refreshed), keeps the port, and restarts the service.

## Uninstall

*Settings → Apps → Alice* or the Start-menu entry. The uninstaller stops and removes the service, the firewall rule and the shortcuts, then asks whether to keep `data\` and `adapters\` (default: keep).

## Dry-run mode

The panel's status block has a **Switch to …** button that toggles dry-run: jobs are written to `data\spool\<job>.prn` instead of the printer, useful to inspect what an adapter produces. On Linux dry-run is always on.

## Running without the installer (development)

```sh
php -S 0.0.0.0:8080 -t public public/router.php          # Linux: dry-run automatically
ALICE_DRY_RUN=1 php -S 127.0.0.1:8080 -t public public/router.php   # Windows, forced dry-run
php tests/run.php                                          # test suite (~1 s)
```

Environment variables: `ALICE_DATA_DIR` (data folder), `ALICE_DRY_RUN` (`1`/`0`), `ALICE_PORT` (shown in the panel).

### Smoke test (checked on 0.1.0)

With the server above in dry-run and a browser on `http://127.0.0.1:8080/`:

- [x] Add a printer by hand (`Front desk`, destination `LPT1`) → appears as registered, kind `port`, default.
- [x] **Test page** → flash "Sent (job …)", job listed as `sent · dry run`, file in `data/spool/`.
- [x] Open `/printer/sample.php?n=1` → result page "Sent to Front desk (dry run …)", **Print again** reprints, **Change printer** opens the selector.
- [x] Station row for `127.0.0.1` appears; rename it and pick a printer → saved on change.
- [x] Language switch (top right) → Spanish, persists on reload; `Accept-Language: es` renders Spanish by default.
- [x] Set a PIN → mutating buttons redirect to `/pin`; after unlocking they work; `POST /api/print` works without it.

## Building the installer

On a Windows PC with [Inno Setup 6](https://jrsoftware.org/isinfo.php):

```powershell
powershell -ExecutionPolicy Bypass -File installer\build.ps1
```

The icon is drawn by `installer\make-icon.ps1` (an original doll motif in Alice Margatroid's colours; fan art found online is copyrighted by its artist even when it is free to view). To use an image you have the rights to, save it as `installer\alice.png` (square works best) and the build makes `alice.ico` from it.

Every `adapters\<name>\` folder of the checkout that has an `adapter.php` goes into the installer: `example` always, and your office adapter when you build on the PC that has it. An installer built that way carries the adapter's `config.php` (database credentials), so keep it private. On upgrade the adapter code is replaced; the server's `config.php` is kept if it already exists.

The script downloads the PHP zip, WinSW (.NET Framework build) and the Microsoft Visual C++ runtime installer pinned in `installer\deps.json`, verifies their SHA-256 (a mismatch aborts with the file name and no installer is produced), stages `installer\payload\`, checks that the bundled PHP loads the required extensions, and compiles `installer\dist\Alice-<version>-setup.exe`. Add `-SkipCompile` to stage only.

## Troubleshooting

Start with the diagnostic script (no administrator rights needed); it prints and saves `alice-diagnose.txt` on the Desktop with the Windows and runtime versions, service and port state, whether the bundled PHP starts, and the log tails:

```powershell
powershell -ExecutionPolicy Bypass -File "C:\Program Files\Alice\tools\diagnose.ps1"
```

**"Alice does not know this URL" (404) when the sales system prints** — the URL the sales system opens (for example `/printer/imprime_nota.php?id_nota=42`) is not a route of any adapter on this server. The 404 page lists the routes that do exist; the service log records every miss with the client IP. Only `adapters\example` ships; the office adapter that serves the real URL is written once from the old print module (see [adapters.md](adapters.md)) and dropped into `adapters\office\`. Restart the service after adding it.

**"The printer name is invalid" / "OpenPrinter … error 1801"** — the registered `local` destination does not match the installed name exactly. Refresh the installed list in the panel and add the printer from there.

**"Access is denied" on a local printer** — the service runs as *Local System*, which can print to local printers. If the printer was installed per-user (some USB installers do that), reinstall it for all users, or register it by share (`\\PC\Share`) as a fallback.

**Raw print helper blocked (AppLocker / antivirus)** — the panel shows the exact PowerShell error. Share the printer and register it again with destination `\\localhost\ShareName` (kind `unc`), which uses `copy /b` instead of the helper.

**UNC destination fails: "The network path was not found"** — the share name is wrong, or the other PC is off. Test with `copy /b C:\Windows\win.ini \\PC\Share` from the server.

**Port already in use** — `netstat -ano | findstr :80` shows the PID; stop that program or reinstall Alice on another port. Change the port in `alice-service.xml` (two places) and run `alice-service.exe restart` as administrator, then fix the firewall rule to match.

**"Service not installed" in the control window** — a post-install step failed. The installer shows which one and its exit code; the full setup log is in `%TEMP%\Setup Log <date> #<n>.txt` (or the file given with `/LOG=`). Press **Install and start** in the control window to register the service again under elevation (it needs `alice-service.xml` next to `alice-service.exe`, written by the installer). If PHP itself does not start, check that *Microsoft Visual C++ 2015-2022 Redistributable (x64)* appears in *Settings → Apps*; the installer adds it when missing.

**Service does not start** — read `data\logs\alice-service.err.log` and `alice-service.wrapper.log`. Run `php\php.exe -c php\php.ini -S 127.0.0.1:8080 -t public public\router.php` from the install folder to see errors directly.

**Stations cannot reach the server** — from a station, `ping alice` / `ping <IP>`. Check that the PC is on a *Private* network profile (the firewall rule is for private and domain), and that the router does not isolate clients (common on guest Wi-Fi).

**Wrong accents on the ticket** — the printer's character table and the document encoding disagree. See *Character sets* in [adapters.md](adapters.md).
