<?php

declare(strict_types=1);

namespace Drupal\localgov_elections\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines boundary_provider annotation object.
 *
 * @see plugin_api
 *
 * @Annotation
 */
class BoundaryProvider extends Plugin {

  /**
   * The plugin ID.
   *
   * @phpstan-ignore-next-line This is allowed in Drupal, but PHPStan complains even though this is valid
   */
  public readonly string $id;

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   *
   * @phpstan-ignore-next-line This is allowed in Drupal, but PHPStan complains even though this is valid
   */
  public readonly string $title;

  /**
   * The description of the plugin.
   *
   * @ingroup plugin_translatable
   *
   * @phpstan-ignore-next-line This is allowed in Drupal, but PHPStan complains even though this is valid
   */
  public readonly string $description;

  /**
   * An array of plugin provided forms.
   *
   * Returns an array of plugin provided forms. We only support the download
   * form at the moment.
   *
   * @returns array
   *
   * @phpstan-ignore-next-line This is allowed in Drupal, but PHPStan complains even though this is valid
   */
  public array $form = [];

}
