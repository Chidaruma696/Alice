[English](README.md) · [Español](README.es.md)

# Alice

LAN print server for ESC/P ticket printers. Runs as a Windows service on the PC that has the printer; every other PC in the office prints by calling an HTTP URL, and Alice routes each job to the printer assigned to that station. No printer sharing, no `hosts` edits on the cash registers, no Laragon.

Status: **0.1.0 in development** — code complete, Windows verification pending (see [`CHANGELOG.md`](CHANGELOG.md)).

## Quick start

**Office PC (Windows):** run `Alice-<version>-setup.exe`, open the panel at `http://localhost/`, click **Add** next to the ticket printer, click **Test page**. Details in [`docs/install.md`](docs/install.md).

**Developer (any OS):**

```sh
php -S 0.0.0.0:8080 -t public public/router.php   # dry-run on Linux/macOS; ALICE_DRY_RUN=1 on Windows
php tests/run.php                                   # test suite
```

## API in five lines

```sh
curl -X POST http://alice/api/print -H 'Content-Type: application/json' -d '{"text": "HELLO\n"}'
# → {"ok": true, "job": "20260101-120000-a1b2c3", "printer": "front-desk", "bytes": 8, "dry_run": false}
curl -X POST http://alice/api/print -d '{"raw": "<base64 ESC/P bytes>", "printer": "warehouse"}'
curl http://alice/api/jobs
```

Legacy host URLs (`/printer/imprime_nota.php?id_nota=42`) keep working through a small adapter you write once; see [`docs/adapters.md`](docs/adapters.md). Full reference: [`docs/api.md`](docs/api.md).

## How it works

- A **station** is a client IP. The first time it prints it appears in the panel; assign it a printer, or let the default printer serve it. A station with no printer gets a one-time selector page.
- A **printer** is `local` (installed on the server, no sharing needed — Alice writes RAW bytes through the Windows spooler), `unc` (`\\PC\Share`) or `port` (`LPT1`, `COM1`).
- Every job gets its own spool file and a log line; the last document per printer can be re-printed from the panel.
- Everything lives in human-readable JSON under `data/`.

## Layout

```
public/        front controller, router script, CSS/JS
src/           App, Router, Store, Printers, Stations, Jobs, Auth, I18n, Escp/, Drivers/, Adapters/, Controllers/
templates/     panel, selector, result, pin, layout
lang/          en.php, es.php
adapters/      example/ (shipped); your own adapters are git-ignored
tools/         rawprint.ps1 (RAW printing through winspool.drv)
installer/     alice.iss, build.ps1, deps.json, winsw.xml, php.ini
tests/         run.php + one file per capability
docs/          api.md, adapters.md, install.md
openspec/      the specification this code implements
```

## Name

Alice Margatroid, from Touhou Project by Team Shanghai Alice. This is an unofficial fan-named tool, not affiliated with Team Shanghai Alice.

## License

[MIT](LICENSE).
