<?php

namespace Drupal\localgov_elections\Form;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\localgov_elections\BoundaryProviderInterface;

/**
 * Provides an interface for Boundary Provider subforms.
 *
 * @todo Check if the method below are actually being used.
 */
interface BoundaryProviderSubformInterface extends PluginFormInterface {

  /**
   * Set the plugin.
   *
   * @param \Drupal\localgov_elections\BoundaryProviderInterface $plugin
   *   The boundary provider plugin.
   *
   * @return void
   *   Does not return anything.
   */
  public function setPlugin(BoundaryProviderInterface $plugin);

  /**
   * Get the plugin.
   *
   * @return \Drupal\localgov_elections\BoundaryProviderInterface
   *   The plugin instance.
   */
  public function getPlugin():BoundaryProviderInterface;

}
