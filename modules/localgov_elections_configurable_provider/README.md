# LocalGov Elections - Configurable Provider

A boundary provider plugin that allows administrators to configure REST API endpoints through the Drupal UI, without requiring custom code for each data source.

## Installation

```bash
drush en localgov_elections_configurable_provider
```

## Usage

1. Go to **Admin > Structure > Boundary Sources**
2. Click **Add boundary source**
3. Select **Configurable Provider** as the plugin
4. Configure the filter parameters, listing API, and boundary API settings
5. Save

The boundary source can then be used on any election's **Add Areas** tab.

## Configuration

### Filter Parameters

Define up to 3 filter values that become `{token}` placeholders in your API queries. At minimum, filter 1 is required.

Example: A filter with key `area_code` and value `E07000228` can be referenced as `{area_code}` in where clauses.

### Listing API

Fetches the list of available electoral areas for user selection. Configure:

- **API endpoint URL** - The query endpoint
- **Where clause** - Filter expression using `{token}` placeholders
- **Output fields** - Comma-separated field list
- **Area code field** - Attribute containing the unique identifier
- **Area name field** - Attribute containing the display name
- **Result path** - Path to results array (default: `features`)
- **Attributes path** - Path to attributes within each result (`attributes` for ArcGIS, `properties` for GeoJSON)

### Boundary API

Fetches full geometry for selected areas. Same fields as listing API, plus:

- **Response format** - GeoJSON or JSON (ArcGIS format)

The where clause can use `{selected_codes}` to reference user-selected area codes.

### Additional Parameters

Both API sections include an **Additional parameters** field for custom query parameters in `key=value` format (one per line). These override the defaults.

## ArcGIS Defaults

The module includes default query parameters suited for ArcGIS REST APIs:

- `outSR=4326` - Output spatial reference (WGS 84)
- `returnDistinctValues=true`
- `resultRecordCount=10000`
- `f=json` or `f=geojson` - Response format

For non-ArcGIS APIs, these parameters are typically ignored. Use the **Additional parameters** field to override if needed.

## Export / Import

Configurations can be shared between sites:

- **Export**: Click the **Export** operation on any Configurable Provider boundary source. Filter values are cleared from the export.
- **Import**: Click **Import configuration** on the boundary sources list. Upload a `.yml` file or paste YAML directly.

See `examples/ons_2025_wards.yml` for a working configuration template.

## Example

The `examples/` directory contains a ready-to-import configuration for ONS 2025 ward boundaries. After importing, set your local authority code in Filter 1.
