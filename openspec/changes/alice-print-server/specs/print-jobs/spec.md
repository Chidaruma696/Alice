# Spec Delta

## Purpose

Lets any system on the LAN print a document on a ticket printer by sending it to Alice over HTTP, and keeps a record of what was printed and whether it succeeded.

## ADDED Requirements

### Requirement: Print endpoint accepts text and raw documents
The system SHALL expose `POST /api/print` accepting a JSON body with either `text` (a UTF-8 string) or `raw` (base64-encoded bytes), an optional `printer` key, an optional `title`, and an optional `encoding` for text (`cp437`, `cp850`, `latin1`; default `cp850`).

#### Scenario: Text document is printed
- **WHEN** a client posts `{"text": "HELLO\n"}` and the client's station resolves to a printer
- **THEN** the system transcodes the text to the requested encoding, sends it to that printer, and responds `200` with `{"ok": true, "job": "<id>", "printer": "<key>"}`

#### Scenario: Raw document is printed unchanged
- **WHEN** a client posts `{"raw": "<base64>"}`
- **THEN** the decoded bytes are sent to the printer byte-for-byte, with no transcoding or trailing form feed added

#### Scenario: Body has neither text nor raw
- **WHEN** a client posts a body without `text` and without `raw`, or with both
- **THEN** the system responds `400` with `{"ok": false, "error": "<message>"}` and nothing is printed

### Requirement: Destination resolution order
The system SHALL choose the destination printer in this order: the `printer` key in the request if it names a registered printer; otherwise the printer assigned to the calling station; otherwise the default printer; otherwise the only registered printer if exactly one exists.

#### Scenario: Request names a printer
- **WHEN** the body contains `"printer": "front-desk"` and `front-desk` is registered
- **THEN** the job goes to `front-desk` regardless of the station's assignment

#### Scenario: Request names an unknown printer
- **WHEN** the body contains a `printer` key that is not registered
- **THEN** the system responds `404` with `{"ok": false, "error": "unknown printer"}` and nothing is printed

#### Scenario: No destination can be resolved
- **WHEN** no printer key is given, the station has no assignment, there is no default, and more than one printer is registered
- **THEN** the system responds `409` with `{"ok": false, "error": "no printer assigned", "select_url": "<url of the selector page>"}`

### Requirement: Job record
The system SHALL record every print attempt with an id, timestamp, station IP, printer key, title, byte count, outcome (`sent` or `failed`), and the error message when failed, keeping at least the most recent 500 jobs.

#### Scenario: Successful job appears in the log
- **WHEN** a job is sent to the printer without error
- **THEN** `GET /api/jobs` lists it with outcome `sent`, newest first

#### Scenario: Failed job keeps the error
- **WHEN** the printer rejects the job (spooler error, unreachable share, missing port)
- **THEN** the response is `502` with `{"ok": false, "error": "<printer error>"}` and the job is logged with outcome `failed` and that message

### Requirement: Last document is kept for inspection
The system SHALL keep a copy of the most recent document sent to each printer so an operator can re-print or inspect exactly what was sent.

#### Scenario: Reprint last document
- **WHEN** an operator requests `POST /api/printers/{key}/reprint`
- **THEN** the last document sent to that printer is sent again as a new job with title `reprint`

### Requirement: Test page
The system SHALL provide `POST /api/printers/{key}/test` that prints a short page identifying the printer key, the server name, and the current time.

#### Scenario: Test page from the panel
- **WHEN** an operator triggers a test for printer `front-desk`
- **THEN** a test page is sent to `front-desk` and the job is logged with title `test`

### Requirement: Dry-run mode
The system SHALL support a dry-run mode, enabled by configuration or automatically on non-Windows hosts, in which jobs are written to a spool folder as files instead of being sent to a printer, while all responses and logs behave as if the print succeeded.

#### Scenario: Dry-run on a developer machine
- **WHEN** Alice runs on Linux and a client posts a document
- **THEN** the document is written to `spool/<job-id>.prn`, the response is `200` with `"dry_run": true`, and the job is logged with outcome `sent`

### Requirement: Concurrent jobs do not interfere
The system SHALL give every job its own spool file and its own print call so that two stations printing at the same time never mix or overwrite each other's documents.

#### Scenario: Two stations print simultaneously
- **WHEN** two clients post documents within the same second
- **THEN** both documents are sent intact and both appear as separate jobs in the log
