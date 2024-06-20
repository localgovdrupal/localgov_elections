<?php

declare(strict_types=1);

namespace Drupal\localgov_elections;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\localgov_elections\Annotation\BoundaryProvider;

/**
 * BoundaryProvider plugin manager.
 */
final class BoundaryProviderPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/BoundaryProvider', $namespaces, $module_handler, BoundaryProviderInterface::class, BoundaryProvider::class);
    $this->alterInfo('boundary_provider_info');
    $this->setCacheBackend($cache_backend, 'boundary_provider_plugins');
  }

}
