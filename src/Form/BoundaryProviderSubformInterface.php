<?php

namespace Drupal\localgov_elections_reporting\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\localgov_elections_reporting\BoundaryProviderInterface;

/**
 * Provides an interface for Boundary Provider subforms.
 *
 * @todo Check if the method below are actually being used.
 */
interface BoundaryProviderSubformInterface extends PluginFormInterface
{

  /**
   * Set the plugin.
   *
   * @param BoundaryProviderInterface $plugin
   *   The boundary provider plugin.
   * @return void
   */
  public function setPlugin(BoundaryProviderInterface $plugin);

  /**
   * Get the plugin.
   *
   * @return BoundaryProviderInterface
   */
  public function getPlugin():BoundaryProviderInterface;
}