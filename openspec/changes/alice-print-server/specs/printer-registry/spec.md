# Spec Delta

## Purpose

Lets an operator see which printers the server PC can reach and register the ones Alice may print to, without requiring Windows printer sharing for local printers.

## ADDED Requirements

### Requirement: Registered printer
A registered printer SHALL have a unique kebab-case key, a display name, and a destination of one of three kinds: `local` (the name of a printer installed on the server PC), `unc` (`\\HOST\Share`), or `port` (`LPT1`-`LPT3`, `COM1`-`COM9`).

#### Scenario: Key is derived from the name
- **WHEN** an operator registers a printer named "Caja 1 (Epson LX)" without giving a key
- **THEN** the key `caja-1-epson-lx` is assigned, and a numeric suffix is appended if that key already exists

#### Scenario: Destination is validated
- **WHEN** an operator submits a destination that is not a valid UNC path, port name, or installed local printer
- **THEN** the registration is rejected with a message naming the problem

### Requirement: Installed printers are discoverable
The system SHALL list the printers installed on the server PC (name, port, whether shared, whether already registered) through `GET /api/printers/installed`, using the operating system's printer list and no network scan.

#### Scenario: Unshared local printer is listed as usable
- **WHEN** a USB printer is installed on the server but not shared
- **THEN** it appears in the installed list as usable and can be registered with destination kind `local`

#### Scenario: Discovery unavailable
- **WHEN** the host is not Windows or the printer list cannot be read
- **THEN** the response contains an empty list and a `warning` explaining that printers must be added by hand

### Requirement: Register, edit, and remove printers
The system SHALL provide `POST /api/printers`, `PUT /api/printers/{key}`, and `DELETE /api/printers/{key}` to manage registered printers, persisting them on the server.

#### Scenario: Removing a printer in use
- **WHEN** an operator deletes a printer that stations are assigned to
- **THEN** the printer is removed and those stations fall back to the default printer, and the response lists the affected stations

### Requirement: Printing to a local printer needs no share
The system SHALL print to a `local` destination through the operating system's print spooler in raw mode, by printer name, whether or not the printer is shared.

#### Scenario: Raw bytes reach an unshared printer
- **WHEN** a job targets a `local` printer that is not shared
- **THEN** the bytes are delivered to that printer's spooler as a raw document and the job outcome is `sent`

### Requirement: Printer status
The system SHALL report, for each registered printer, whether it was reachable at the last job and the time of that job.

#### Scenario: Printer offline
- **WHEN** the last job to a printer failed
- **THEN** the printer list marks it with the failure time and the error message until a later job succeeds
