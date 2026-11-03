# Spec Delta

## Purpose

Gives the office a Laragon/XAMPP-style desktop control window and tray icon on the server PC to start, stop, and watch Alice without opening Services or a terminal. Configuration stays in the web panel; this is only the on/off switch and the status light.

## ADDED Requirements

### Requirement: Control window
The system SHALL provide a desktop control window ("Alice") on the server PC showing whether the service is running, the HTTP port, the URLs stations can use, and buttons to start, stop, and restart the service, open the web panel, and open the logs, data, and adapters folders.

#### Scenario: Service stopped
- **WHEN** the operator opens the control window while the Alice service is stopped
- **THEN** the status shows "Stopped" in red, Start is enabled, and Stop/Restart are disabled

#### Scenario: Start from the window
- **WHEN** the operator clicks Start
- **THEN** the service is started (elevating with a UAC prompt when needed) and within a few seconds the status shows "Running" in green and the panel URL answers

### Requirement: Tray icon
The control window SHALL minimise to a notification-area icon whose colour reflects the service state, with a menu offering open window, open panel, start, stop, restart, and exit; closing the window keeps the icon.

#### Scenario: Close to tray
- **WHEN** the operator closes the control window
- **THEN** the window hides, the tray icon stays, and double-clicking the icon shows the window again

### Requirement: Live status
The control window SHALL refresh the service state every few seconds, show whether the panel answers over HTTP (version and dry-run flag), and show the tail of the service log so a crash or restart is visible without opening files.

#### Scenario: Process crash
- **WHEN** the PHP process exits and the service wrapper restarts it
- **THEN** the log area shows the restart lines and the status returns to "Running"

### Requirement: Development mode
When no installed service is found (a checkout run from source), the control window SHALL start and stop PHP's built-in server itself on the chosen port, with a dry-run switch, so the same window serves developers and the office.

#### Scenario: Checkout without the service
- **WHEN** the window is launched from `tools/` in a git checkout
- **THEN** it shows "development mode", Start launches `php -S` on the port, and Stop ends that process

### Requirement: Installed with the service
The installer SHALL create a Start-menu entry "Alice" that opens the control window and SHALL offer (default on) to launch it minimised to the tray at user login; the window SHALL be bilingual following the Windows display language, English by default.

#### Scenario: Login
- **WHEN** the operator logs in after installation with the login option enabled
- **THEN** the Alice tray icon appears without a window, green if the service is already running
