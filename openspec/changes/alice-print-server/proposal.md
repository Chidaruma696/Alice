# Proposal

## Why

Offices with a web-based sales system and one or more ESC/P ticket printers (Epson LX class) still print through an ad-hoc PHP folder dropped into Laragon on whichever PC has the printer attached. That setup depends on Windows printer sharing, on a hand-edited `hosts` file in every cash register, on a desktop app (Laragon) that shows licence prompts and stops the web server when it is closed, and on code that is welded to one company's database. Alice replaces it with a proper LAN print server: a named product, installed as a Windows service by its own installer, that any system can call over HTTP and that routes each job to the printer assigned to the calling station.

## What Changes

- New product **Alice**, a LAN print server for raw/ESC/P ticket printers, written in PHP 8 and served by PHP's built-in web server running as a Windows service.
- HTTP print API: a client sends a document (plain text or raw bytes) and Alice prints it on the printer assigned to that client station, recording the job and its outcome.
- Printer registry: printers installed on the server PC are listed and usable **without** being shared; printers on other PCs can be added by UNC path; local ports (LPT/COM) remain supported. The `net view` network scan is removed.
- Station routing: each calling PC (by IP) is mapped to a printer; a default printer covers unmapped stations; a station with no printer is asked once through a selector page; `?printer=` overrides per request.
- Admin panel: one bilingual (English default, Spanish) web page to manage printers, stations, test prints, and recent jobs; optional admin PIN.
- Host adapters: the legacy URLs of the sales system (`imprime_nota.php?id_nota=N`, `imprime_edo_cuenta_cliente.php?...`) keep working through a pluggable adapter that reads the host database and builds the ESC/P ticket. The repository ships the adapter contract and an example; the real adapter for a given office lives outside the repository.
- Windows deployment: an Inno Setup installer that bundles PHP 8, registers the service (auto-start, restart on failure), opens the firewall port, creates a Start-menu shortcut to the panel, and optionally renames the PC so stations resolve it by name without `hosts` edits.
- Dry-run mode: on non-Windows hosts (development) and when enabled, jobs are written to a spool folder instead of a printer, so the whole system is testable without hardware.
- **BREAKING** for the old module: `impresoras.json`/`pcs.json` are replaced by Alice's own data files; nothing is migrated automatically (the office has one printer and a handful of stations, re-entered by hand in minutes).

## Capabilities

### New Capabilities
- `print-jobs`: accepting a document over HTTP, resolving its destination, sending it to the printer, and recording the result.
- `printer-registry`: discovering, adding, testing, and removing printers the server can print to.
- `station-routing`: mapping calling stations (PCs) to printers, defaults, overrides, and the selector page.
- `admin-panel`: the bilingual web interface for operators and its access control.
- `host-adapters`: compatibility endpoints for host systems that print by URL, implemented through pluggable adapters kept outside the repository.
- `windows-deployment`: installation and operation as a Windows service via the Inno Setup installer, including firewall, shortcuts, name resolution, upgrade, and uninstall.

### Modified Capabilities
<!-- none: greenfield project -->

## Impact

- New repository `Alice` (private): PHP 8 application, PowerShell raw-print helper, Inno Setup script, build script, tests.
- External dependencies bundled at build time: PHP 8.3 Windows NTS zip (php.net), WinSW service wrapper (MIT), Inno Setup 6/7 to compile the installer. None at runtime beyond Windows itself.
- The office's current `printer/` folder under Laragon is retired once Alice is installed on the same PC; the sales system's URLs do not change, only the host name/IP they point to.
- Development on Linux needs `php` (8.2+) to run the test suite and the dry-run server; the installer and the spooler helper can only be verified on Windows.
