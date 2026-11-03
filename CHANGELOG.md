# Changelog

## 0.1.0 — unreleased

First version, implementing the `alice-print-server` change in `openspec/`.

- HTTP print API (`POST /api/print`: UTF-8 text transcoded to cp850/cp437/latin1, or raw bytes), job log, last-document copy, test page, reprint.
- Printer registry: installed printers via `Get-Printer` (no network scan), `local` printers through the Windows spooler in RAW mode without sharing (`tools/rawprint.ps1`), `unc` and `port` destinations via `copy /b`.
- Station routing by client IP: auto-registration, names, assignments, default printer, selector page, per-request `printer` override.
- Bilingual admin panel (English default, Spanish), optional admin PIN with 12-hour sessions; printing is never gated.
- Host adapters loaded from `adapters/*/adapter.php` with ESC/P helpers (`Escp\Builder`, `SpanishNumbers`); `adapters/example` ships, office adapters are git-ignored.
- Dry-run mode (automatic outside Windows) writes jobs to `data/spool/`.
- Desktop control window and tray icon (`tools/alice-tray.ps1`): Start/Stop/Restart, status light, log tail, development mode with `php -S`.
- Windows deployment: Inno Setup installer bundling PHP 8.3 and WinSW, service with auto-restart, firewall rule, port and computer-name pages, shortcuts, data-preserving upgrade and uninstall.
- Test suite: `php tests/run.php` (in-process, no sockets).

Windows verification (tasks 8.1–8.3 in `openspec/changes/alice-print-server/tasks.md`): pending.
