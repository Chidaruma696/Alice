# Tasks

## 1. Skeleton and tooling

- [ ] 1.1 Create the project layout from design D12 (`public/`, `src/`, `templates/`, `lang/`, `adapters/example/`, `tools/`, `installer/`, `tests/`, `docs/`), `.gitignore` (ignores `data/`, `adapters/*` except `example`, `installer/payload/`, `installer/dist/`), `LICENSE` (MIT), and verify `git status` shows only intended files.
- [ ] 1.2 Write `tests/run.php` (assert helpers, per-file discovery, non-zero exit on failure) and a smoke test that boots the app in dry-run mode with a temp `data/`; verify `php tests/run.php` passes.
- [ ] 1.3 Implement `src/App.php` (config load, dry-run detection on non-Windows, data dir bootstrap) and `src/Router.php` (method + path dispatch, JSON and HTML responses, 404); verify with a test that `GET /api/status` returns version, host name, and `dry_run: true` on Linux.
- [ ] 1.4 Implement `src/Store.php` (locked read-modify-write of JSON files, atomic rename, `jobs.jsonl` append and rotation at 500 lines); verify with a test that two interleaved updates both persist and rotation keeps the newest 500.

## 2. Printer registry

- [ ] 2.1 Implement `src/Printers.php`: registered printer model, key derivation with suffixing, destination validation for `local`/`unc`/`port`, CRUD persisted in `printers.json`; verify with tests covering key collisions and invalid destinations.
- [ ] 2.2 Implement `GET /api/printers`, `POST`, `PUT /{key}`, `DELETE /{key}` (delete reports affected stations); verify with router tests.
- [ ] 2.3 Implement `src/Drivers/`: `DriverInterface`, `DryRunDriver` (writes `spool/<job>.prn`), `FileCopyDriver` (`copy /b` for unc/port), `WindowsSpoolerDriver` (invokes `tools/rawprint.ps1` with Bypass policy, captures stderr as the error); verify the dry-run and copy drivers with tests, and document that the spooler driver is verified in task 8.2.
- [ ] 2.4 Write `tools/rawprint.ps1` (P/Invoke to winspool.drv, RAW datatype, exit codes and messages per design D2); verify the script parses under `pwsh -NoProfile -Command "[scriptblock]::Create((Get-Content -Raw tools/rawprint.ps1))"` where available, otherwise by review, with real verification in task 8.2.
- [ ] 2.5 Implement installed-printer discovery (`GET /api/printers/installed`): `Get-Printer` JSON on Windows, empty list with warning elsewhere; verify with a test that the non-Windows branch returns the warning and that the Windows parser handles the sample JSON fixture in `tests/fixtures/get-printer.json`.
- [ ] 2.6 Track per-printer last job time and last error in `printers.json` and expose them in `GET /api/printers`; verify with a test that a failed job marks the printer and a later success clears it.

## 3. Station routing

- [ ] 3.1 Implement `src/Stations.php`: IP normalisation, auto-registration with last-seen, names, assignments, default printer, persisted in `stations.json`; verify with tests for `::1`, `::ffff:` forms and first-contact registration.
- [ ] 3.2 Implement `GET/PUT /api/stations`, `DELETE /api/stations/{ip}`, `PUT /api/default-printer`; verify with router tests.
- [ ] 3.3 Implement destination resolution (request key → station → default → single printer → 409 with `select_url`) in `src/Jobs.php`; verify with a table-driven test covering every branch and the unknown-printer 404.
- [ ] 3.4 Implement the selector page (`GET /select?return=...`, `POST /select`) with return-URL restriction to Alice paths; verify with tests that choosing assigns the station and that an external return URL falls back to `/`.

## 4. Print jobs

- [ ] 4.1 Implement `POST /api/print` (text with `iconv` transcoding to `cp850`/`cp437`/`latin1`, raw base64, validation 400, per-job spool file, driver call, 502 on failure); verify with tests for text, raw byte-for-byte, both/neither given, and a driver that throws.
- [ ] 4.2 Implement job logging (`jobs.jsonl`, `GET /api/jobs` newest first) and the `last/<key>.prn` copy; verify with tests that outcomes and errors are recorded and `last` is overwritten per printer.
- [ ] 4.3 Implement `POST /api/printers/{key}/test` (test page text) and `POST /api/printers/{key}/reprint`; verify with tests that the test page contains the key and host name and that reprint sends the same bytes under title `reprint`.
- [ ] 4.4 Write `docs/api.md` with every endpoint, request and response examples, and the resolution order; verify each example in the doc is exercised by a test or a curl line that runs against `php -S` in dry-run mode.

## 5. ESC/P helpers and adapters

- [ ] 5.1 Implement `src/Escp/Builder.php` (init, charset, line spacing, page length, align, double width, bold, form feed, fixed-width column helper) and `src/Escp/SpanishNumbers.php` (amount to words with `NN/100 M.N.`); verify with tests including the 4,393.70 → "CUATRO MIL TRESCIENTOS NOVENTA Y TRES PESOS 70/100 M.N." case.
- [ ] 5.2 Implement adapter loading (`src/Adapters/Loader.php`, `AdapterInterface` with `name()`, `routes()`, `check()`), route registration under declared paths, the result page (`templates/result.php`) with "Print again" and "Change printer"; verify with tests using the example adapter that `/printer/sample.php?n=1` prints and that a handler error yields a failed job and the message.
- [ ] 5.3 Write `adapters/example/` (adapter printing a fixed sample ticket via the ESC/P builder, `config.php` with the documented keys, `check()` returning ok) and the `.gitignore` rules; verify `git check-ignore adapters/office/x` ignores and `adapters/example/adapter.php` is tracked.
- [ ] 5.4 Write `docs/adapters.md` (contract, handler signature, config layout, porting a legacy ESC/P ticket, charset notes, database timeout advice); verify that following it against the example adapter matches what the loader accepts (reviewed against the tests in 5.2).

## 6. Admin panel and i18n

- [ ] 6.1 Implement `src/I18n.php` (`lang` cookie → `Accept-Language` → `en`), `lang/en.php`, `lang/es.php`, language switch route; verify with tests for `es-MX`, unknown languages, and the cookie override.
- [ ] 6.2 Implement `templates/layout.php` and `templates/panel.php` rendering printers (installed with "Add", registered with test/reprint/remove), manual printer form, stations with inline rename/assign/remove, default printer, last 50 jobs, status block, adapter status; verify with tests that the rendered HTML contains each section and escapes a printer name containing `<`.
- [ ] 6.3 Implement the optional admin PIN (hash in `config.json`, PIN prompt template, session cookie with 12-hour expiry, gating of mutating routes only); verify with tests that a change without session returns 401 when a PIN is set, that print and adapter URLs are never gated, and that no PIN means no prompt.
- [ ] 6.4 Add the small vanilla JS for inline saves and confirm dialogs; verify by running `php -S 127.0.0.1:8080 -t public` in dry-run mode and exercising add printer, assign station, test print, and language switch in a browser (record the checks in `docs/install.md` under "Smoke test").

## 7. Windows deployment

- [ ] 7.1 Write `installer/winsw.xml` (command, working dir, restart 10 s, log rotation) and `installer/deps.json` with pinned PHP zip and WinSW URLs and SHA-256; verify the XML is well-formed with `xmllint --noout`.
- [ ] 7.2 Write `installer/build.ps1` (download, verify hashes, stage payload, run ISCC, output `installer/dist/Alice-<version>-setup.exe`); verify the hash-mismatch path exits non-zero by review and by a dry run with a wrong hash on Windows (task 8.1).
- [ ] 7.3 Write `installer/alice.iss` (admin privileges, port page with port-in-use check, optional computer-name step, firewall rule, service install/start, shortcuts "Alice panel"/"Alice logs"/desktop optional, `onlyifdoesntexist` for `data/` and `adapters/`, uninstall with keep-data prompt); verify by review against the windows-deployment spec, with real verification in task 8.1.
- [ ] 7.4 Write `docs/install.md` (requirements, install steps, port choice, name resolution options: computer rename or router DNS static entry, hosts fallback, upgrade, uninstall, troubleshooting for spooler errors and port conflicts) and `README.md` + `README.es.md` (what Alice is, quick start, API in five lines, Touhou attribution, licence); verify the documented commands match the scripts.

## 8. Windows verification (on the server PC, by the user)

- [ ] 8.1 Build the installer with `installer/build.ps1` on a Windows PC with Inno Setup, install on a test port, confirm the service starts at boot, the firewall rule exists, shortcuts open the panel, and uninstall keeps `data/`; record results in `CHANGELOG.md` for 0.1.0.
- [ ] 8.2 Register the USB printer as `local` without sharing it, print the test page and a sample adapter ticket from another PC on the LAN, confirm bytes match the dry-run spool file; confirm `unc` destination works against a shared printer if one exists.
- [ ] 8.3 Create `adapters/office/` from the example, point a cash register's sales system at Alice, print a real sale ticket, and compare it with the old module's `tmp/ultimo_nota.prn`; adjust the adapter until identical.

## Workflow follow-up

- Archive the change after 8.1–8.3 pass and tag `v0.1.0`.
