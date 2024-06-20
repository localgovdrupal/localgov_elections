<?php

declare(strict_types=1);

namespace Drupal\localgov_elections;

use Drupal\Component\Plugin\PluginBase;

/**
 * Base class for boundary_provider plugins.
 */
abstract class BoundaryProviderPluginBase extends PluginBase implements BoundaryProviderInterface {

  /**
   * The boundary source configuration instance.
   *
   * @var BoundarySourceInterface
   */
  protected $configInstance;

  /**
   * An associative array containing the plugin's configuration.
   *
   * @var array
   */
  protected $config;

  /**
   * {@inheritDoc}
   */
  public function setConfigInstance(BoundarySourceInterface $config_instance) {
    $this->configInstance = $config_instance;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigInstance(): ?BoundarySourceInterface {
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
   */
  public function calculateDependencies() {
    // @todo should be done properly
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function getConfiguration() {
    return $this->config;
  }

  /**
   * Set the configuration.
   */
  public function setConfiguration(array $configuration) {
    $this->config = $configuration;
  }

}
