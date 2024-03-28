# Custom Boundary Providers

It's likely that many users may want to create their own boundary provider plugin. We have purposely not added any
besides the optional ONS 2023 Wards provider due to the various types of elections and the way areas are divided. It
is also quite challenging getting a hold of this data (depending on the format you need it in).

This is why we chose to go for a plugin approach. Developers can write their own boundary provider plugin and the
election reporting module will be able to detect these up and use them. This means we can abstract things in such as way
that it doesn't matter where the election is, what the boundaries are, etc. It also means developers are free to decide
if they want to load the data from an API or another method. All that matters is that the plugin provides the logic
required to do so.

## Getting Started

A good place to start is the ONS 2020 Wards Boundary Provider. It's a small submodule located in the
`modules/localgov_elections_ons_twenty_three_wards` directory. It's a relatively small module with only 4 files:

1. The README
2. The module info file
3. A @BoundaryProvider plugin file OnsTwentyThreeWards.php
4. A form for the plugin

So you can see it's relatively simple. The main work is in the @BoundaryProvider plugin OnsTwentyThreeWards.php file. It
contains an annotation and some required methods.

```php
/**
 * Plugin implementation of the boundary_provider.
 *
 * @BoundaryProvider(
 *   id = "ons_2023_wards",
 *   label = @Translation("ONS 2023 Wards"),
 *   description = @Translation("ONS 2023 Wards."),
 *   form = {
 *     "download" = "Drupal\localgov_elections_ons_twenty_three_wards\Form\OnsTwentyThreeWardsDownloadForm",
 *   }
 * )
 */
```

The annotation contains various elements, and all are required except the form section. This is entirely optional. This
form is used on the "add areas" form where the user selects what plugin the want to use to download areas. We use
it to display items that are decided on each download whereas the configuration form provided by the plugin is used for
details that are unlikely to over change. If you know each download will be exactly the same you can omit this second
form.

Given the annotation, and under the assumption the class is in a suitable namespace, Drupal should pick up your custom
provider.

## An Example Provider

```php
/**
 * Plugin implementation of the boundary_provider.
 *
 * @BoundaryProvider(
 *   id = "foo_provider",
 *   label = @Translation("Foo Provider"),
 *   description = @Translation("Foo provider."),
 *   form = {
 *      "download" = "Drupal\foo_provider\Form\FooProviderForm",
 *    }
 * )
 */
class FooProvider extends BoundaryProviderPluginBase
{


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration()
  {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigurable()
  {
    return TRUE;
  }


  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state)
  {
    $form['option'] = [
        '#type' => 'textfield',
        '#title' => "Configure me",
        "#required" => TRUE,
        "#default_value"=> $this->configuration['option'] ?? ""
    ];
    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function createBoundaries(BoundarySourceInterface $entity, array $form_values)
  {
    // Your logic to download the boundaries
  }

  /**
   * {@inheritdoc }
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
  {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {

  }

}
```

## An Example Plugin Download Form

```php
class FooProviderForm implements BoundaryProviderSubformInterface
{

  /**
   * The plugin.
   */
  protected $plugin;

  /**
   * {@inheritDoc}
   */
  public function setPlugin(BoundaryProviderInterface $plugin)
  {
    $this->plugin = $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin(): BoundaryProviderInterface
  {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state)
  {
    $form['option'] = [
        '#type' => 'textfield',
        '#title' => "Title",
        "#required" => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {
  }

}
```

