<?php

namespace Drupal\localgov_elections;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;

/**
 * Provides a collection of boundary source plugins.
 */
class BoundaryPluginCollection extends DefaultSingleLazyPluginCollection {

  /**
   * The ID this plugin collection belongs to.
   *
   * @var string
   */
  protected $boundarySourceId;

  /**
   * The boundary source entity.
   *
   * @var BoundarySourceInterface
   */
  protected $boundarySource;

  /**
   * Constructs a new BoundaryPluginCollection.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $manager
   *   The manager to be used for instantiating plugins.
   * @param string $instance_id
   *   The ID of the plugin instance.
   * @param array $configuration
   *   An array of configuration.
   * @param BoundarySourceInterface $boundary_source
   *   The unique ID of the boundary source entity using this plugin.
   */
  public function __construct(PluginManagerInterface $manager, $instance_id, array $configuration, BoundarySourceInterface $boundary_source) {
    $this->boundarySource = $boundary_source;
    parent::__construct($manager, $instance_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  protected function initializePlugin($instance_id) {
    if (!$instance_id) {
      throw new PluginException("The boundary source '{$this->boundarySourceId}' did not specify a plugin.");
    }

    try {
      parent::initializePlugin($instance_id);
      $plugin_instance = $this->pluginInstances[$instance_id];
      if ($plugin_instance instanceof BoundaryProviderInterface) {
        $plugin_instance->setConfigInstance($this->boundarySource);
      }
    }
    catch (PluginException $e) {
      $module = $this->configuration['provider'];
      // Ignore sources belonging to uninstalled modules, but re-throw
      // valid exceptions when the module is installed and the plugin is
      // misconfigured.
      if (!$module || \Drupal::moduleHandler()->moduleExists($module)) {
        throw $e;
      }
    }
  }

}
