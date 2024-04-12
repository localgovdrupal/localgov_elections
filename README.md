# LocalGov Elections Reporting

This module provides submodules, content types, views and configuration that allow the reporting of election results for
the LocalGov Drupal distribution.

## Features

- First past the post single seat per electoral area elections
- 'All-out' and not 'all-out' (e.g. halves or thirds) elections 
- Table, graph and map based views of the results
- Extensible boundary source provider for electoral areas and geo data
- Social media posting of results (currently Twitter / X)

## How to use

See the [Documentation](docs/index.md) for more details

## Installing

### Before installing

As the libraries for Highcharts are not coming from Packagist they need to be defined in the root package. To do this
add the following to the sites main composer.json in the `"repositories": [... ]` section.

```
        {
            "type": "package",
            "package": {
                "name": "highcharts/highcharts",
                "version": "8.2.2",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts"
                },
                "dist": {
                    "url": "https://code.highcharts.com/8.2.2/highcharts.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/more",
                "version": "8.2.2",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_more"
                },
                "dist": {
                    "url": "https://code.highcharts.com/8.2.2/highcharts-more.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/exporting",
                "version": "8.2.2",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_exporting"
                },
                "dist": {
                    "url": "https://code.highcharts.com/8.2.2/modules/exporting.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/export-data",
                "version": "8.2.2",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_export-data"
                },
                "dist": {
                    "url": "https://code.highcharts.com/8.2.2/modules/export-data.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/accessibility",
                "version": "8.2.2",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_accessibility"
                },
                "dist": {
                    "url": "https://code.highcharts.com/8.2.2/modules/accessibility.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
                "package": {
                "name": "highcharts/3d",
                "version": "8.2.2",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_3d"
                },
                "dist": {
                    "url": "https://code.highcharts.com/8.2.2/highcharts-3d.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        }
```

### Installation

You can install this module with the following composer command.

```
composer require localgovdrupal/localgov_elections_reporting
```

#### Submodules

You may also wish to use the submodules provided as part of the Localgov Election Reporting module. They are:

1. LocalGov Elections Reporting ONS Wards 2023 - Boundary source provider for Office of National Statistics 2023 Wards
2. LocalGov Elections Reporting Social Post Integration - Post results to social media (Twitter/X)

Further details for these modules are in their own module READMEs and the [Documentation](docs/index.md).

## Issues

If you run into issues using this module, please report them
at https://github.com/localgovdrupal/localgov_elections_reporting/issues

## Maintainers

This project is currently maintained by:

- Chris Wales https://github.com/chriswales95
- Duncan Davidson https://github.com/dedavidson

It is based on work originally done by Rob Carr https://github.com/rgcarr.
