# Design

## Context

See proposal.md for motivation. What exists today is a ~1,300-line PHP 5.5/8 folder (`printer/`) that: builds ESC/P tickets from the sales database, writes each job to a `.prn` file, and hands it to Windows' `PRINT /D:\\PC\Share` command; keeps `impresoras.json` and `pcs.json` on the server; and discovers printers with `Get-Printer` + `net view`. The parts worth keeping are the ideas (server-side station mapping, one spool file per job, "last document" copies, selector page); the code is rewritten in English for PHP 8.

Constraints:
- Target is a Windows 10/11 PC with a USB ESC/P printer; stations are other Windows PCs on the same /24 opening URLs from a browser-based sales system.
- No Linux test bed for the Windows-only parts (spooler, service, installer); everything else must be testable on Linux.
- Project rules: code, identifiers and docs in English; UI bilingual with English default; no company names, credentials, or table names in the repository; no framework-heavy stack.

## Goals / Non-Goals

**Goals:**
- One PHP 8 application with no Composer dependencies at runtime, runnable with `php -S` from a checkout on Linux (dry-run) and as a service on Windows.
- Printing to an unshared local printer by name.
- Everything configurable from the panel; files on disk are the persistence, readable by a human.
- An installer a cashier could run.

**Non-Goals:**
- Driver-level rendering (PDF, graphics, cut commands for thermal printers). Alice moves bytes; adapters or clients produce them.
- Multi-server or cloud; one Alice per office.
- User accounts; a single optional PIN is enough for a LAN tool.
- Migrating the old module's JSON files.

## Decisions

**D1. PHP 8.3 with the built-in web server, no framework.**
The office already runs PHP; the user builds installers with Inno Setup and wants a "homemade Laragon". PHP's built-in server (`php -S 0.0.0.0:PORT -t public`) handles the load of a few stations printing tickets once the slow network scan is gone (print calls take well under a second). Alternatives: Caddy + php-cgi (two processes to supervise, no benefit at this scale); a Go/Rust binary (would mean rewriting the ESC/P adapters the office relies on, and PHP is the user's choice here).
Router: a single `public/index.php` front controller dispatching on method + path to handlers in `src/`. No `.htaccess` needed.

**D2. Raw printing through the Windows spooler, not `PRINT`.**
`PRINT /D:` only accepts ports and shares, which is why the old module forced sharing. `tools/rawprint.ps1` uses `Add-Type` with P/Invoke to `winspool.drv` (`OpenPrinter`, `StartDocPrinter` with datatype `RAW`, `WritePrinter`, `EndDocPrinter`, `ClosePrinter`), taking printer name and file path as arguments and exiting non-zero with the Win32 error text on failure. PHP calls it with `powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -File`. `unc` and `port` destinations keep using `copy /b` (`PRINT` is equivalent but noisier). The helper is pure script, so antivirus false positives are unlikely; it is version-pinned inside the install.

**D3. Storage as JSON files under `data/`, with a lock.**
`printers.json`, `stations.json`, `config.json`, `jobs.jsonl` (append-only, truncated to the last 500 lines on rotation), `spool/` for job files and `last/<printer-key>.prn`. Writes go through one `Store` class that takes an exclusive `flock` on `data/.lock`, reads, applies a closure, writes atomically (temp file + rename). Alternative SQLite: adds the `pdo_sqlite` extension to ship and makes hand-editing harder, for no gain at this size.

**D4. Station = client IP.**
Same model as the old module; it is what makes "nothing to configure on the cash registers" work. Stations should get DHCP reservations on the office router; the panel says so. Alternative (cookie per browser) would break when a cashier clears the browser or when the sales system opens the URL in a new profile.

**D5. Adapters as plain PHP plugins loaded at startup.**
`adapters/*/adapter.php` returns an object implementing `AdapterInterface { name(); routes(); check(); }`. Routes are registered under their declared paths (`printer/imprime_nota.php` style paths are allowed so the host system's URLs stay identical). The repo ships `adapters/example/`; `.gitignore` has `adapters/*` with `!adapters/example/`. Shared helpers for ESC/P (init, charset `ESC R 12`, double-width, number-to-words in Spanish) live in `src/Escp/` so adapters stay small and the office-specific adapter only holds queries and layout.

**D6. Text encoding.**
API `text` is UTF-8 and is transcoded with `iconv` (bundled in PHP for Windows) to `cp850` by default, since the office printer is set to the Latin-American table (`ESC R 12`) and the legacy data is latin1. `raw` is never touched.

**D7. i18n.**
`lang/en.php` and `lang/es.php` return arrays; `t('key')` looks up the current language chosen from a `lang` cookie, else `Accept-Language`, else `en`. Templates are PHP files under `templates/` with `htmlspecialchars` everywhere; no JS framework, a little vanilla JS for the inline station forms.

**D8. Admin PIN.**
Optional `admin_pin` (hashed with `password_hash`) in `config.json`, set from the panel or the installer. When set, mutating routes require a session cookie issued after a correct PIN; the cookie is an HMAC of a random session id stored in `data/sessions.json` with a 12-hour expiry. The print API and host-adapter URLs are never gated (stations must print without ceremony).

**D9. Service wrapper: WinSW.**
WinSW is a single MIT-licensed executable; its XML describes the command (`php.exe -S 0.0.0.0:%PORT% -t public`), working directory, auto-restart with a 10 s delay, and log rotation. The installer runs `alice-service.exe install` and `start` in `[Run]`, and `stop` + `uninstall` in `[UninstallRun]`. Alternatives: NSSM (public-domain, GUI-oriented, fine but less declarative); a hand-written service in C# (more to maintain).

**D10. Installer layout and build.**
`installer/alice.iss` with `PrivilegesRequired=admin`, a custom page for the port and the optional computer-name step, firewall via `netsh advfirewall firewall add rule name="Alice" dir=in action=allow protocol=TCP localport={port} profile=private,domain`, computer rename via `Rename-Computer -NewName alice -Force` without `-Restart`. `installer/build.ps1` downloads the pinned `php-8.3.x-nts-Win32-vs16-x64.zip` and `WinSW-x64.exe`, checks SHA-256 against `installer/deps.json`, stages `payload/`, and runs `ISCC`. Data and adapters are installed under `{app}\data` and `{app}\adapters` only if absent (`Flags: onlyifdoesntexist`), which is what makes upgrades keep them.

**D11. Testing without PHPUnit.**
`tests/run.php` is a tiny runner (assert helpers, one file per capability, exit code). It boots the app in dry-run mode with a temporary `data/` and hits the router in-process (no sockets), so the suite runs in a second on Linux and on Windows. Windows-only behaviour is isolated behind a `Printer\Driver` interface with `WindowsSpoolerDriver`, `FileCopyDriver` (unc/port) and `DryRunDriver`; tests use the last.

**D12. Project layout.**
```
public/index.php        front controller
src/                    App, Router, Store, I18n, Printers, Stations, Jobs, Escp/, Drivers/, Adapters/
templates/              panel.php, selector.php, result.php, pin.php, layout.php
lang/en.php lang/es.php
adapters/example/       sample adapter + config
tools/rawprint.ps1
installer/alice.iss installer/build.ps1 installer/deps.json installer/winsw.xml
tests/run.php tests/*.php
docs/api.md docs/adapters.md docs/install.md
README.md README.es.md LICENSE CHANGELOG.md
```

## Risks / Trade-offs

- [PHP built-in server is single-threaded] → every request must be fast; the spooler call is local and returns in milliseconds; adapters that query a slow remote database could block other stations for that long. Mitigation: 5-second database timeouts in the example adapter and a note in the adapter guide; if it ever matters, swapping to Caddy+php-cgi changes only `winsw.xml`.
- [PowerShell helper blocked by AppLocker or AV] → fall back is `copy /b` to a share, and the panel shows the exact error; documented in `docs/install.md`.
- [Port 80 busy (IIS, Skype, another Laragon)] → installer checks the port and lets the user change it; the sales system's URL then needs the port.
- [Client IP changes (DHCP)] → the station shows up as new and falls back to the default printer, which is the right thing for a one-printer office; panel recommends reservations.
- [Cannot test Windows parts from Linux] → the user tests the installer on the server PC; tasks mark what needs that.
- [Computer rename needs a restart] → optional, explained, never forced.

## Migration Plan

1. Install Alice on the Laragon PC on a different port (e.g. 8080) while the old module is still running.
2. Register the USB printer from the panel, print a test page.
3. Create `adapters/office/` from the example and the old `printer/` queries; verify `http://localhost:8080/printer/imprime_nota.php?id_nota=N` prints the same ticket as the old URL.
4. Stop Laragon, reinstall or reconfigure Alice on port 80, point stations at the server by name (router DNS entry or computer rename) or keep the existing `hosts` lines.
5. Rollback: start Laragon again; Alice on another port does not interfere.

## Open Questions

- Pinned PHP 8.3 patch version and WinSW release at build time (any current one works; pinned in `deps.json` when the first build is made on Windows).
- Whether the office wants the desktop shortcut created by default (installer checkbox, default on).
