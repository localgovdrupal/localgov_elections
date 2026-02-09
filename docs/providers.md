# Custom Boundary Providers

## Before Writing a Custom Provider

The **Configurable Provider** sub-module can connect to most REST APIs that return JSON or GeoJSON without writing code. It supports ArcGIS FeatureServer endpoints, configurable field mappings, and both tableselect and autocomplete selection modes.

See the [Configurable Provider README](../modules/localgov_elections_configurable_provider/README.md) and example configurations in that module's `examples/` directory.

Only create a custom plugin if you need functionality the Configurable Provider doesn't support, such as complex authentication, data transformation, or non-REST data sources.

## Plugin Basics

* Must extend `Drupal\localgov_elections\BoundaryProviderPluginBase`
* Must use the `@BoundaryProvider` annotation
* Must implement these methods:

### buildConfigurationForm
Builds the form shown when creating/editing a boundary source.

### validateConfigurationForm / submitConfigurationForm
Handle validation and saving of configuration values.

### createBoundaries
Accepts a `BoundarySourceInterface` entity and form values array. Creates `localgov_area_vote` nodes with boundary data.

## Getting Started

A good place to start is the Configurable Provider sub-module in `modules/localgov_elections_configurable_provider`. It contains:

1. The module info file
2. The `@BoundaryProvider` plugin file `ConfigurableProvider.php`
3. A download form `ConfigurableDownloadForm.php`

### Annotation

```php
/**
 * @BoundaryProvider(
 *   id = "my_provider",
 *   label = @Translation("My Provider"),
 *   description = @Translation("Description of my provider."),
 *   form = {
 *     "download" = "Drupal\my_module\Form\MyDownloadForm",
 *   }
 * )
 */
```

The `form.download` key is optional. Use it when users need to make selections at download time (e.g. choosing which areas to fetch). Omit it if every download is identical.

### Download Form

If you need a download form, implement `BoundaryProviderSubformInterface`:

* `setPlugin()` / `getPlugin()` - Store and retrieve the plugin instance
* `buildConfigurationForm()` - Build the form shown on the "Add areas" page
* `validateConfigurationForm()` / `submitConfigurationForm()` - Handle validation and submission

The form values are passed to `createBoundaries()` when the user submits the download.
