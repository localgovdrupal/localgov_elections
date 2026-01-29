# LocalGov Elections

Election reporting for UK local government elections. Provides content types, services, and views for managing and displaying election results.

## Installation

```bash
composer require localgovdrupal/localgov_elections
```

The Charts library is included via CDN by default. For local installation, see the [Charts module documentation](https://www.drupal.org/docs/contributed-modules/charts/50x-getting-started#s-using-composer-and-wikimediacomposer-merge-plugin).

## Submodules

**Boundary Providers** - fetch electoral area boundaries from external APIs:

- **Configurable Provider** - configure any REST API endpoint through the UI. Includes example configurations for UK wards, divisions, parishes, constituencies, and Irish local electoral areas.

*Deprecated providers (use Configurable Provider instead):*
- ONS Wards 2024, ONS Divisions 2024, ONS Parishes 2024, Parliamentary Constituencies

**Supporting Modules**:

- **UK Parties** - populates party taxonomy with UK political parties, colours, and abbreviations
- **Demo Content** - example election data for testing, including:
  - *General Election July 2024* - single-seat parliamentary constituencies
  - *Local Government Elections 2025* - multi-seat council wards

## Content Structure

- **Election** (`localgov_election`) - the parent election with date, type, and aggregated results
- **Area Vote** (`localgov_area_vote`) - individual electoral areas with seats, candidates, and vote counts

Winners are calculated automatically when votes are marked as final.

## Documentation

See [docs/index.md](docs/index.md) for usage instructions.

## Known Issues

**Node Revisions**: Revisions do not work reliably with Area Vote nodes due to the paragraph structure. We prevent revisions on candidate and seat paragraphs as a workaround. See [#94](https://github.com/localgovdrupal/localgov_elections/issues/94).

## Support

- Slack: **#feature_elections** in LocalGov Drupal
- Issues: https://github.com/localgovdrupal/localgov_elections/issues

## Maintainers

- Dan Champion https://www.drupal.org/u/danchamp
- Duncan Davidson https://www.drupal.org/u/ded
- Finn Lewis https://www.drupal.org/u/finn-lewis

Based on work by Rob Carr https://github.com/rgcarr.
