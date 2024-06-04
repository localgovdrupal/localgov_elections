# LocalGov Elections Reporting UK Parliamentary Constituency Boundary Provider

The parliamentary constituency boundary provider makes it possible to automatically fetch consitutuency boundaries for
election areas in a UK general election.

See the [documentation](../../docs/index.md) for details on how to use this.

## Data Sources

The data for UK Parliamentary Constituency Boundary Provider plugin is provided by the Office for National Statistics (ONS). The ONS provides various
types of geo-data. The dataset we use for the plugin is
titled "[Westminster Parliamentary Constituencies (July 2024) Boundaries UK BFE](https://hub.arcgis.com/datasets/d8069770c4304befb17e40d9a32b4716/about)".

## Boundary Fetching Process

The boundary fetching process used by the UK Parliamentary Constituency Boundary Provider plugin is fairly simple and can be described in a few steps:

1. Enable the plugin and create a plugin instance from the "boundary sources" menu item under the structure menu.
2. Create an election.
3. From the election edit screen, click "add areas".
4. Select the boundary provider you created.
5. The plugin presents a subform which shows a single text field. Start typing the name of a constituency e.g. Edinburgh South
6. The field should start to auto complete.
7. You can enter multiple constituencies by using commas.
8. Once happy, press fetch.
