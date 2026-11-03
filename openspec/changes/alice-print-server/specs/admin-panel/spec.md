# Spec Delta

## Purpose

Gives the person in charge of the office a single web page to see printers, stations, and recent jobs, and to fix things without touching files or other PCs.

## ADDED Requirements

### Requirement: Single-page panel
The system SHALL serve the panel at `/` with sections for printers (installed and registered), stations with their assigned printer, the default printer, the last 50 jobs, and server status (version, host name, IP addresses, dry-run flag, spool folder writable).

#### Scenario: Operator opens the panel
- **WHEN** an operator opens `http://<server>/` in a browser
- **THEN** every section renders from the current data and no action requires another page except the selector

### Requirement: Register a printer in two clicks
The panel SHALL show each installed-but-unregistered printer with an "Add" button that registers it with its installed name as display name and destination kind `local`.

#### Scenario: Add an installed printer
- **WHEN** the operator clicks "Add" next to an installed printer
- **THEN** the printer appears in the registered list with a test button and the installed list marks it as registered

### Requirement: Manual printer entry
The panel SHALL provide a form to register a printer by display name and destination (UNC path or port) for printers not installed on the server.

#### Scenario: Add a shared printer on another PC
- **WHEN** the operator submits name "Back office" and destination `\\OFFICE-PC\Tickets`
- **THEN** the printer is registered with kind `unc` and listed with a test button

### Requirement: Station management inline
The panel SHALL let the operator rename a station, pick its printer from a drop-down, and remove it, saving on change.

#### Scenario: Assign a printer to a station
- **WHEN** the operator picks `warehouse` in the drop-down of station `172.18.1.50`
- **THEN** the assignment is saved and the next job from that IP goes to `warehouse`

### Requirement: Bilingual interface
The panel and the selector SHALL be available in English and Spanish, choosing the language from the browser's `Accept-Language` header with English as fallback, and SHALL offer a visible language switch that persists for that browser.

#### Scenario: Spanish browser
- **WHEN** the browser prefers `es-MX`
- **THEN** the panel renders in Spanish

#### Scenario: Unknown language
- **WHEN** the browser prefers a language other than English or Spanish
- **THEN** the panel renders in English

### Requirement: Optional admin PIN
If an admin PIN is configured, the system SHALL require it before any change made from the panel or the management API, remembering a successful entry for that browser for 12 hours; read-only views and the selector SHALL stay open.

#### Scenario: Change without PIN
- **WHEN** a PIN is configured and a browser without a valid session submits a change
- **THEN** the system responds `401` and the panel shows the PIN prompt

#### Scenario: No PIN configured
- **WHEN** no PIN is configured
- **THEN** all panel actions work without prompting
