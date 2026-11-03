# Spec Delta

## Purpose

Decides which printer each calling PC (station) prints on, so cash registers never choose a printer themselves and an operator configures everything from the server.

## ADDED Requirements

### Requirement: Stations are identified by IP
The system SHALL identify a station by the client IP of the request, normalising IPv6 loopback and IPv4-mapped addresses to their IPv4 form.

#### Scenario: Loopback is normalised
- **WHEN** a request arrives from `::1` or `::ffff:127.0.0.1`
- **THEN** the station IP is recorded as `127.0.0.1`

### Requirement: Stations register themselves on first contact
The system SHALL add an unknown station to the station list the first time it prints or opens the selector, recording its IP, the time last seen, and an empty display name.

#### Scenario: New cash register prints for the first time
- **WHEN** a request from an IP not in the list hits a print or host-adapter endpoint
- **THEN** the station appears in the station list with no assigned printer and the destination falls back as the print-jobs capability specifies

### Requirement: Station assignment and default printer
The system SHALL let an operator assign a printer to each station, name the station, remove it, and set one default printer for stations without an assignment, through `GET/PUT /api/stations` and `PUT /api/default-printer`.

#### Scenario: Default covers unassigned stations
- **WHEN** the default printer is `front-desk` and a station with no assignment prints
- **THEN** the job goes to `front-desk`

#### Scenario: Assignment beats default
- **WHEN** a station is assigned `warehouse` and the default is `front-desk`
- **THEN** that station's jobs go to `warehouse`

### Requirement: Selector page for unassigned stations
The system SHALL serve a selector page that lists the registered printers as large buttons; choosing one assigns that printer to the calling station and redirects back to the URL that triggered the selector.

#### Scenario: Station picks a printer once
- **WHEN** a station with no printer opens a host-adapter URL and picks `front-desk` on the selector
- **THEN** the station is assigned `front-desk`, the original document is printed, and later requests from that station print without asking

#### Scenario: Return URL is restricted
- **WHEN** the selector is opened with a return URL outside Alice's own paths
- **THEN** the system ignores it and returns to the panel home instead

### Requirement: Per-request override
The system SHALL honour a `printer` query parameter on host-adapter URLs and the `printer` key on the print API as a one-off override that does not change the station's assignment.

#### Scenario: Override does not stick
- **WHEN** a station assigned `front-desk` prints once with `?printer=warehouse`
- **THEN** that job goes to `warehouse` and the next job without the parameter goes to `front-desk`
