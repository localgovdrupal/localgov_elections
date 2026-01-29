# Changelog

## [3.x Unreleased]

### Added
- **Multi-seat electoral areas**: Support for wards/divisions with multiple seats. Each seat can be marked as contested or uncontested independently.
- **Configurable Provider sub-module**: Configure any REST API endpoint through the UI for fetching boundary data. Includes example configurations for UK wards, Westminster constituencies, and Irish local electoral areas.
- **Autocomplete mode for boundary sources**: Alternative to tableselect for sources with many areas (e.g. UK parliamentary constituencies).
- **Votes finalised date field**: Records when results were declared, used for timeline display.
- **Election duplicator**: Duplicate an election with all its areas and candidates via the election's Operations menu.
- **Tie-break resolution**: When candidates have equal votes, the winner is determined by candidate order.

### Changed
- Renamed "Areas vote" content type label to "Electoral area" in admin UI.
- Refactored Electoral map to support multi-seat area rendering.
- Refactored winner calculation, majority calculation, map data, and alias management into dedicated services.
- **Demo content**: Two example elections - General Election July 2024 (single-seat) and Local Government Elections 2025 (multi-seat).

### Deprecated
- **Boundary provider sub-modules**: ONS Wards 2023, ONS Wards 2024, ONS Divisions 2024, ONS Parishes 2024, and Parliamentary Constituencies are deprecated. Use the Configurable Provider instead.

### Upgrade notes
Run `drush updb` after updating. The update hooks will:
- Create the new seat paragraph type and fields
- Add a default seat to existing electoral areas
- Recalculate winners for finalised areas
- Copy node timestamps to the new finalised date field for timeline display

## [2.0.0-beta1] - 2024-06-xx

### Added
- Drupal 11 support.

### Removed
- Social Post sub-module (use core social sharing or contrib alternatives).

## [1.2.1] - 2024-xx-xx

### Fixed
- PHP 8.1 compatibility issues.

## [1.0.0] - 2023-xx-xx

Initial stable release with:
- Election and Area Vote content types
- Boundary provider plugin system
- ONS boundary provider sub-modules
- Electoral map display
- Results timeline
- Vote share charts
- UK Parties sub-module
