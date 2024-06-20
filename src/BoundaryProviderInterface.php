<?php

declare(strict_types=1);

namespace Drupal\localgov_elections;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for boundary_provider plugins.
 */
interface BoundaryProviderInterface extends PluginInspectionInterface, ConfigurableInterface, DependentPluginInterface, PluginFormInterface {

  /**
   * Set the plugin configuration.
   *
   * @param BoundarySourceInterface $config_instance
   *   The boundary source configuration instance.
   */
  public function setConfigInstance(BoundarySourceInterface $config_instance);

  /**
   * Get the config instance.
   *
   * @return BoundarySourceInterface|null
   *   The boundary source configuration instance.
   */
  public function getConfigInstance(): ?BoundarySourceInterface;

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Create boundary information for an election.
   *
   * Creates boundary information given a source configuration instance
   * and the values of the submitted form.
   *
   * The module does not assume how the boundaries are created and assumes
   * that the form values and boundary source entity will provide enough
   * information to construct them.
   *
   * @param BoundarySourceInterface $entity
   *   The boundary source config entity.
   * @param array $form_values
   *   The form values which come from the boundary fetch submission.
   */
  public function createBoundaries(BoundarySourceInterface $entity, array $form_values);

}
