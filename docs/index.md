# Documentation

## Contents

- [Setup](#setup)
  - [Boundary sources](#boundary-sources)
  - [Parties](#parties)
- [Creating an election](#creating-an-election)
  - [Adding electoral areas](#adding-electoral-areas)
  - [Seats](#seats)
  - [Adding candidates](#adding-candidates)
  - [Partial elections](#partial-elections)
- [Recording results](#recording-results)
  - [Ties](#ties)

## Setup

### Boundary sources

Boundary sources fetch electoral area boundaries from external APIs. Skip this if you don't need the map display or prefer to create areas manually.

1. Go to **Structure > Boundary Sources** (`/admin/structure/boundary-source`)
2. Select a provider from the dropdown and click **Add**
3. Complete the configuration fields and click **Save**

#### Available providers

**Configurable Provider** - Configure any REST API endpoint through the UI. Supports ArcGIS and other JSON/GeoJSON APIs. See the [sub-module README](../modules/localgov_elections_configurable_provider/README.md) for configuration details.

**ONS providers** - Pre-configured for Office of National Statistics boundary datasets:
- ONS Wards 2024
- ONS Divisions 2024
- ONS Parishes 2024
- Parliamentary Constituencies

Each ONS provider requires a local authority code. Check the provider's README for the specific field names and lookup values.

#### Custom providers

To create a custom boundary provider plugin, see [providers.md](providers.md).

### Parties

The **UK Parties** sub-module populates the party taxonomy with standard UK political parties, colours, and abbreviations. Enable it to skip manual setup.

To add parties manually:

1. Go to **Structure > Taxonomy > Party** (`/admin/structure/taxonomy/manage/party/overview`)
2. Click **Add term**
3. Enter Name, Abbreviation, Party colour (RGB), and Text colour (RGB)

Ensure party and text colours have sufficient contrast for accessibility.

## Creating an election

1. Go to **Content > Add content > Election** (`/node/add/localgov_election`)
2. Enter the election title, date, and type
3. Set the default number of seats per area (defaults to 1)
4. Set **Display map** to off if not using boundary data
5. Set **Display majority details** to off if reporting on a subset of seats (e.g. Westminster constituencies within a council area)
6. Click **Save**

### Adding electoral areas

#### From a boundary source

1. After saving the election, click the **Add areas** tab
2. Select a boundary source (if more than one is configured)
3. Select the areas to include
4. Click **Fetch**

For partial elections, select all areas including those not being contested.

#### Manually

1. Go to **Content > Add content > Area vote** (`/node/add/localgov_area_vote`)
2. Enter the Title and Area name
3. In the **References** section, select the parent election
4. Click **Save**

### Seats

The default number of seats per area is set on the election form. This applies to all areas unless overridden individually in the **Seats** section of an area vote edit form.

The number of seats determines how many winners are selected when votes are finalised. In a 3-seat ward, the top 3 vote-getters win.

### Adding candidates

1. View the election node to see the list of electoral areas
2. Click **[edit]** next to an area
3. In the **Details** tab, enter the number of eligible voters
4. In the **Candidates and Votes** tab, click **Add Candidate**
5. Enter forename, surname, and party for each candidate
6. Optionally upload a PDF candidate list

### Partial elections

For elections where only some seats are contested:

1. Edit each non-contested area
2. Enable the **Seat not contested** toggle
3. Add the incumbent as the uncontested candidate

This ensures majority calculations remain accurate.

## Recording results

1. View the election node
2. Click **[edit]** next to the area with results
3. For each candidate, click **Edit** and enter their vote count
4. Enable **Votes finalised** to confirm the result is declared
5. In the **Overall results** tab, enter spoilt ballots and whether it was a Hold or Gain
6. Click **Save**

Winners are calculated automatically when votes are finalised.

### Ties

When candidates have equal votes, the winner is determined by their order in the candidates list. The candidate appearing first wins.

To control tie-break order, drag candidates into the desired priority order before finalising votes. This matches the real-world practice where ties are typically resolved by lot, allowing you to record the actual outcome.
