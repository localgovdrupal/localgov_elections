<?php

declare(strict_types=1);

namespace Drupal\localgov_elections\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines boundary_provider annotation object.
 *
 * @Annotation
 */
class BoundaryProvider extends Plugin {

  /**
   * Constructor.
   */
  public function __construct(
    /**
     * The plugin ID.
     */
    public readonly string $id,
    /**
     * The human-readable name of the plugin.
     *
     * @ingroup plugin_translatable
     */
    public readonly string $title,
    /**
     * The description of the plugin.
     *
     * @ingroup plugin_translatable
     */
    public readonly string $description,
    /**
     * An array of plugin provided forms.
     *
     * Returns an array of plugin provided forms. We only support the download
     * form at the moment.
     *
     * @returns array
     */
    public array $form = [],
  ) {
  }

}
