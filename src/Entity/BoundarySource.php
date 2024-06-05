<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_reporting\Entity;

use Drupal\Component\Plugin\LazyPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\localgov_elections_reporting\BoundaryPluginCollection;
use Drupal\localgov_elections_reporting\BoundarySourceInterface;

/**
 * Defines the boundary source entity type.
 *
 * @ConfigEntityType(
 *   id = "boundary_source",
 *   label = @Translation("Boundary Source"),
 *   label_collection = @Translation("Boundary Sources"),
 *   label_singular = @Translation("boundary source"),
 *   label_plural = @Translation("boundary sources"),
 *   label_count = @PluralTranslation(
 *     singular = "@count boundary source",
 *     plural = "@count boundary sources",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\localgov_elections_reporting\BoundarySourceListBuilder",
 *     "form" = {
 *       "default" = "Drupal\localgov_elections_reporting\Form\BoundarySourceForm",
 *       "add" = "Drupal\localgov_elections_reporting\Form\BoundarySourceForm",
 *       "edit" = "Drupal\localgov_elections_reporting\Form\BoundarySourceForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "boundary_source",
 *   admin_permission = "administer boundary_source",
 *   links = {
 *     "collection" = "/admin/structure/boundary-source",
 *     "edit-form" = "/admin/structure/boundary-source/{boundary_source}",
 *     "delete-form" = "/admin/structure/boundary-source/{boundary_source}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "plugin",
 *     "settings",
 *   },
 * )
 */
class BoundarySource extends ConfigEntityBase implements BoundarySourceInterface, EntityWithPluginCollectionInterface {

  /**
   * The ID of the entity.
   *
   * @var string
   */
  protected string $id;

  /**
   * The label of the entity.
   *
   * @var string
   */
  protected string $label;

  /**
   * Lazy plugin collection.
   *
   * @var \Drupal\Component\Plugin\LazyPluginCollection
   */
  protected $pluginCollection;

  /**
   * The entity description.
   */
  protected string $description;

  /**
   * The plugin.
   *
   * @var string
   */
  protected string $plugin;

  /**
   * The settings of the entity.
   *
   * @var array
   */
  protected array $settings = [];

  /**
   * Encapsulates the creation of the LazyPluginCollection.
   *
   * @return \Drupal\Component\Plugin\LazyPluginCollection
   *   The plugin collection.
   */
  protected function getPluginCollection(): LazyPluginCollection {
    if (!$this->pluginCollection) {
      $this->pluginCollection = new BoundaryPluginCollection(
          \Drupal::service('plugin.manager.boundary_provider'),
          $this->plugin,
          $this->settings,
          $this);
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    return ['configuration' => $this->getPluginCollection()];
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    return $this->getPluginCollection()->get($this->plugin);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->settings;
  }

}
