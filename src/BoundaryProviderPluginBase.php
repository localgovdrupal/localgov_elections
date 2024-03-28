<?php declare(strict_types = 1);

namespace Drupal\localgov_elections_reporting;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for boundary_provider plugins.
 */
abstract class BoundaryProviderPluginBase extends PluginBase implements BoundaryProviderInterface {

  protected $configInstance;

  public function setConfigInstance(BoundarySourceInterface $config_instance)
  {
    $this->configInstance = $config_instance;
  }

  public function getConfigInstance(): ?BoundarySourceInterface
  {
    return $this->configInstance;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   */
  public function calculateDependencies()
  {
    // @todo should be done properly
    return [];
  }

  /**
   *
   *
   * @return array
   */
  public function getConfiguration()
  {
    return $this->config;
  }

  /**
   * Set the configuration.
   */
  public function setConfiguration(array $configuration)
  {
    $this->config = $configuration;
  }

}
