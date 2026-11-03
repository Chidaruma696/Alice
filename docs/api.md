# Alice HTTP API

Everything is JSON under `/api/`. Responses always carry `"ok": true|false`; errors carry `"error": "<message>"`. The server is one PHP process: keep calls short.

Base URL examples below assume `http://alice/` (port 80). With another port: `http://alice:8080/`.

The print endpoint, the host-adapter URLs, the selector, and all `GET` endpoints are never protected. When an admin PIN is configured, every other method answers `401 {"ok": false, "error": "pin required", "pin_url": "/pin"}` until the browser holds a session (see [PIN](#pin)).

## Printing

### `POST /api/print`

Body (JSON):

| field      | type    | notes |
|------------|---------|-------|
| `text`     | string  | UTF-8 text. Transcoded to `encoding`, bare `LF` becomes `CR LF`, a form feed is appended unless `form_feed` is `false`. |
| `raw`      | string  | Base64 of the exact bytes to send. Nothing is added or changed. |
| `printer`  | string  | Registered printer key. One-off override; does not change the station's assignment. |
| `title`    | string  | Shown in the job log. |
| `encoding` | string  | For `text`: `cp850` (default), `cp437`, `latin1`. |
| `form_feed`| bool    | For `text`: default `true`. |

Exactly one of `text` or `raw` is required.

Destination resolution, in order:

1. `printer` in the body, if it names a registered printer (`404 unknown printer` otherwise);
2. the printer assigned to the calling station (client IP);
3. the default printer;
4. the only registered printer, if exactly one exists;
5. otherwise `409 {"ok": false, "error": "no printer assigned", "select_url": "/select"}`.

```sh
curl -s -X POST http://alice/api/print -H 'Content-Type: application/json' \
  -d '{"text": "HELLO\nÑandú\n", "title": "hello"}'
```
```json
{"ok": true, "job": "20260101-120000-a1b2c3", "printer": "front-desk", "bytes": 17, "dry_run": false}
```

Raw bytes:

```sh
printf '\033@\033R\014HELLO\r\n\f' | base64 | xargs -I{} \
  curl -s -X POST http://alice/api/print -H 'Content-Type: application/json' -d '{"raw": "{}"}'
```

Errors: `400` invalid body (`text or raw is required`, `send either text or raw, not both`, `raw is not valid base64`, bad `encoding`), `404` unknown printer, `409` nothing to print on, `502 {"ok": false, "error": "<printer error>", "job": "<id>"}` when the spooler, share, or port rejects the job (the job is logged as `failed`).

In dry-run mode (`"dry_run": true` in the response) the document is written to `data/spool/<job>.prn` and nothing goes to a printer.

### `GET /api/jobs?limit=50`

Newest first, up to 500. Each job: `id`, `time` (ISO 8601), `station`, `printer`, `title`, `bytes`, `outcome` (`sent`|`failed`), `error`, `dry_run`.

## Printers

### `GET /api/printers`

```json
{"ok": true, "default": "front-desk", "printers": [
  {"key": "front-desk", "name": "Front desk", "kind": "local", "destination": "EPSON LX-350",
   "created_at": "...", "last_job_at": "...", "last_error": null, "last_error_at": null}
]}
```

`kind` is `local` (installed printer name, no sharing needed), `unc` (`\\HOST\Share`) or `port` (`LPT1`–`LPT3`, `COM1`–`COM9`). `last_error` stays set until a later job succeeds.

### `GET /api/printers/installed[?refresh=1]`

Printers installed on the server PC (`Get-Printer`), each with `name`, `port`, `shared`, `driver`, and `registered` (the key if already registered). On non-Windows hosts or when the list cannot be read: `{"ok": false, "printers": [], "warning": "..."}`. Cached for 20 s unless `refresh=1`.

### `POST /api/printers` *(protected)*

```json
{"name": "Front desk"}                                        // local printer by installed name
{"name": "Back office", "destination": "\\\\OFFICE-PC\\Tickets"} // kind inferred: unc
{"name": "Old", "destination": "LPT1"}                        // kind inferred: port
{"name": "Caja 1", "kind": "local", "destination": "EPSON LX-350", "key": "caja-1"}
```

`201 {"ok": true, "printer": {...}}`. The key is derived from the name (`Caja 1 (Epson LX)` → `caja-1-epson-lx`, suffixed `-2`, `-3` on collision) unless given. `400` names the problem (`destination must be a UNC path like \\HOST\Share`, `no printer named 'X' is installed on this PC`, …); `409` when an explicit key exists. The first printer registered becomes the default.

### `PUT /api/printers/{key}` *(protected)*

Any of `name`, `destination`, `kind`. `200 {"ok": true, "printer": {...}}`.

### `DELETE /api/printers/{key}` *(protected)*

`200 {"ok": true, "removed": "front-desk", "affected_stations": ["172.18.1.50"]}`. Stations that were assigned to it fall back to the default; if it was the default, there is no default any more.

### `POST /api/printers/{key}/test` *(protected)*

Prints a test page naming the key, the server, and the time. `200 {"ok": true, "job": "...", "printer": "...", "dry_run": false}`.

### `POST /api/printers/{key}/reprint` *(protected)*

Sends the last document that went to that printer again, as a new job titled `reprint`. `404` when nothing was printed there yet.

## Stations

A station is a client IP (`::1` and `::ffff:a.b.c.d` are normalised to IPv4). Stations are added automatically the first time they print or open the selector.

### `GET /api/stations`

```json
{"ok": true, "default": "front-desk", "stations": [
  {"ip": "172.18.1.50", "name": "Cash register 1", "printer": "warehouse", "first_seen": "...", "last_seen": "..."}
]}
```

### `PUT /api/stations/{ip}` or `PUT /api/stations` with `"ip"` in the body *(protected)*

Fields: `name`, `printer` (key, or `null`/`""` to clear). Unknown printer → `404`. Creates the station if missing.

### `DELETE /api/stations/{ip}` *(protected)*

### `PUT /api/default-printer` *(protected)*

`{"printer": "front-desk"}` or `{"printer": null}`.

## Selector and pages

- `GET /` — the panel.
- `GET /select?return=/printer/x.php?id=1` — big buttons, one per printer. `POST /select` with form fields `printer` and `return` assigns the printer to the calling station and redirects to `return`. Only paths inside Alice (`/...`) are accepted as `return`; anything else goes to `/`.
- `GET /lang/en` or `/lang/es?return=...` — sets the language cookie.
- `GET /pin`, `POST /pin` (`pin`, `return`) — PIN prompt for browsers; `GET /logout`.

## Host-adapter URLs

Each loaded adapter registers its own paths, e.g. `GET /printer/sample.php?n=1` (shipped example). Flow: the station is registered, `?printer=<key>` overrides the destination for that request only, the handler builds the document, the job is printed through the same rules as `/api/print`, and an HTML result page says "Sent to <printer>" with **Print again** and **Change printer** buttons. If no printer can be resolved the browser is redirected to the selector with `return` set to the original URL, so the ticket prints as soon as a printer is chosen. See [adapters.md](adapters.md).

## Status

### `GET /api/status`

`version`, `host`, `ips`, `port`, `dry_run`, `windows`, `php`, `timezone`, `data_dir`, `spool_writable`, `pin_set`, `adapters` (each with `name`, `dir`, `loaded`, `ok`, `error`, `routes`).

## PIN

- `POST /api/pin` `{"pin": "1234"}` — log in; sets the `alice_session` cookie (12 hours). `401 wrong pin`.
- `PUT /api/pin` `{"pin": "1234"}` *(protected once a PIN exists)* — set or change the PIN (min. 4 characters); also logs the caller in.
- `DELETE /api/pin` *(protected)* — remove the PIN.
- `POST /api/logout`.

## Configuration

### `PUT /api/config` *(protected)*

`{"dry_run": true|false|null}` — force dry-run on/off, or `null` for automatic (on outside Windows, off on Windows). Applies to the next job. `{"port": 8080}` is stored for reference only; the service's port comes from the installer (`ALICE_PORT`).

## Files on disk (`data/`)

`config.json`, `printers.json`, `stations.json`, `jobs.jsonl` (last 500), `sessions.json`, `spool/<job>.prn` (last 200), `last/<printer-key>.prn`, `logs/`. All human-readable; edit them only with the service stopped.
