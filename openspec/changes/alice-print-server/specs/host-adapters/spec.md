# Spec Delta

## Purpose

Keeps existing host systems printing by opening a URL, by letting a small adapter turn a host-specific request into a document that Alice prints, without the host's code or data model ever entering Alice's repository.

## ADDED Requirements

### Requirement: Adapter contract
An adapter SHALL be a directory under `adapters/<name>/` containing `adapter.php` that returns an object exposing `routes()` (a map of URL path to handler) where each handler receives the request parameters and returns either a document (`text` or `raw` bytes plus a title) or an error message.

#### Scenario: Adapter route is served
- **WHEN** an adapter declares route `printer/imprime_nota.php` and a client requests `/printer/imprime_nota.php?id_nota=42`
- **THEN** the handler runs with `id_nota=42`, its document is printed through the print-jobs flow for the calling station, and a result page is shown

#### Scenario: Handler returns an error
- **WHEN** a handler returns an error (for example "sale 42 not found")
- **THEN** nothing is printed, the result page shows the message, and a job is logged with outcome `failed`

### Requirement: Adapters live outside the repository
The repository SHALL ship only the adapter contract, an `example` adapter that prints a fixed sample ticket, and ignore every other `adapters/*` directory in version control, so office-specific database credentials, table names, and company text never get committed.

#### Scenario: Office adapter is not tracked
- **WHEN** an operator creates `adapters/office/` with real credentials
- **THEN** `git status` shows nothing to commit for that directory

### Requirement: Adapter configuration
Each adapter SHALL read its own settings (database host, user, password, database name, charset, company header lines) from `adapters/<name>/config.php`, and the panel SHALL show which adapters are loaded and whether each one's connectivity check passes.

#### Scenario: Database unreachable
- **WHEN** the adapter's connectivity check fails
- **THEN** the panel shows the adapter as "not connected" with the error, while printing through the API keeps working

### Requirement: Result page for URL printing
After printing from a host-adapter URL the system SHALL show a page stating success or failure, the printer used, and buttons to print again and to choose another printer, in the panel's language rules.

#### Scenario: Successful print from the sales system
- **WHEN** a cash register opens a host-adapter URL and the job is sent
- **THEN** the page reads "Sent to <printer name>" and offers "Print again" and "Change printer"

### Requirement: Legacy sale-ticket adapter ships as documentation
The repository SHALL document, in the adapter guide, how to port a ticket that is built with ESC/P control codes from a database query, including character-set handling (`ESC R` and `cp850`), without including any office's actual queries.

#### Scenario: Porting guide is followed
- **WHEN** a developer follows `docs/adapters.md` to create an adapter
- **THEN** the example adapter's structure, the handler signature, and the config file layout they are told to copy match what the running server accepts
