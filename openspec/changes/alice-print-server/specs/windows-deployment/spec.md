# Spec Delta

## Purpose

Lets a non-technical person install Alice on the PC that has the printer, have it run unattended from boot, and remove it cleanly, with nothing else to install.

## ADDED Requirements

### Requirement: Self-contained installer
The system SHALL ship as a single Inno Setup installer (`Alice-<version>-setup.exe`) that bundles the PHP runtime, the application, the service wrapper, and the raw-print helper, requiring no other software on the target PC.

#### Scenario: Clean PC
- **WHEN** the installer runs on a Windows 10/11 PC with nothing else installed
- **THEN** after it finishes the panel answers at `http://localhost/` from a browser

### Requirement: Runs as a Windows service
The installer SHALL register Alice as a Windows service that starts automatically at boot, runs without a logged-in user, restarts within 10 seconds if the process exits, and writes its output to a log file under the install directory.

#### Scenario: Reboot
- **WHEN** the PC restarts and nobody logs in
- **THEN** stations can still print within a minute of boot

#### Scenario: Process crash
- **WHEN** the PHP process exits unexpectedly
- **THEN** the service wrapper restarts it and the event is visible in the log

### Requirement: Port and firewall
The installer SHALL let the user choose the HTTP port (default 80), open that port for inbound TCP on the private and domain firewall profiles, and store the port in the configuration.

#### Scenario: Port 80 is taken
- **WHEN** another program already listens on the chosen port
- **THEN** the installer warns before finishing and offers to pick another port

### Requirement: Name resolution help
The installer SHALL offer an optional step to set the PC's computer name (default `alice`) so stations can reach it as `http://alice/`, and the panel SHALL display the server's current name and IP addresses to copy into other configurations.

#### Scenario: Rename accepted
- **WHEN** the user accepts the rename step
- **THEN** the installer sets the computer name, explains that a restart applies it, and does not force the restart

#### Scenario: Rename declined
- **WHEN** the user declines
- **THEN** the computer name is untouched and the panel still shows the IP addresses to use

### Requirement: Shortcuts
The installer SHALL create Start-menu entries "Alice panel" (opens the panel in the default browser) and "Alice logs" (opens the log folder), and an optional desktop shortcut to the panel.

#### Scenario: Open panel from Start menu
- **WHEN** the user clicks "Alice panel"
- **THEN** the default browser opens `http://localhost:<port>/`

### Requirement: Upgrade keeps data
Running a newer installer over an existing installation SHALL replace the application and runtime, keep `data/`, `adapters/`, and the configuration, and restart the service.

#### Scenario: Upgrade in place
- **WHEN** version 0.2.0 is installed over 0.1.0
- **THEN** the registered printers, stations, and office adapter are intact and the service is running the new version

### Requirement: Uninstall
The uninstaller SHALL stop and remove the service, remove the firewall rule and shortcuts, and ask whether to delete `data/` and `adapters/`, defaulting to keeping them.

#### Scenario: Keep data on uninstall
- **WHEN** the user uninstalls and keeps the default answer
- **THEN** the program files are gone, `data/` and `adapters/` remain, and no service or firewall rule named Alice exists

### Requirement: Raw-print helper
The deployment SHALL include a PowerShell helper that writes a file to a named local printer in raw mode through the Windows spooler, invoked with execution policy bypass so machine policy does not block it.

#### Scenario: Policy restricted
- **WHEN** the PC's PowerShell execution policy is `Restricted`
- **THEN** raw printing still works because the helper is run with `-ExecutionPolicy Bypass`

### Requirement: Build script
The repository SHALL include a build script that downloads the pinned PHP zip and service wrapper, verifies their SHA-256, assembles the payload, and compiles the installer, so a release can be produced on any Windows PC with Inno Setup.

#### Scenario: Checksum mismatch
- **WHEN** a downloaded dependency does not match its pinned hash
- **THEN** the build stops with an error naming the file and no installer is produced
