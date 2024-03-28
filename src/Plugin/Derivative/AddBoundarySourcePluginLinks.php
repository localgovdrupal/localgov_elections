<?php

namespace Drupal\localgov_elections_reporting\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines dynamic local tasks.
 */
class AddBoundarySourcePluginLinks extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $pluginManager;

  /**
   * Constructs a new ThemeLocalTask.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $plugin_manager
   *   The theme handler.
   */
  public function __construct(PluginManagerInterface $plugin_manager) {
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
        $container->get('plugin.manager.boundary_provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->pluginManager->getDefinitions() as $pluginId => $pluginDefinition) {
      $key = 'entity.boundary_source.add_form.' . $pluginId;
      $this->derivatives[$key] = $base_plugin_definition;
      $this->derivatives[$key]['title'] = $pluginDefinition['label'];
      $this->derivatives[$key]['route_name'] = 'entity.boundary_source.add_form';
      $this->derivatives[$key]['route_parameters']['plugin_id'] = $pluginId;
    }
    return $this->derivatives;
  }

}
