<?php

namespace Drupal\localgov_elections\Service;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages path aliases for election nodes and their sub-pages.
 */
class ElectionAliasManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The path alias storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $pathAliasStorage;

  /**
   * The path validator service.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Election sub-page routes and their alias suffixes.
   *
   * @var array
   */
  protected const SUB_PAGES = [
    'view.localgov_election_electoral_map.page_map' => '/electoral-map',
    'view.localgov_election_results_timeline.page_timeline' => '/results',
    'view.localgov_election_results_vote.page_vote_share' => '/share',
    'view.localgov_electoral_candidates.page_candidates' => '/candidates',
  ];

  /**
   * Constructs an ElectionAliasManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    PathValidatorInterface $path_validator,
    LoggerInterface $logger,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->pathAliasStorage = $entity_type_manager->getStorage('path_alias');
    $this->pathValidator = $path_validator;
    $this->logger = $logger;
  }

  /**
   * Generate aliases for all election sub-pages.
   *
   * @param \Drupal\node\NodeInterface $election
   *   The election node.
   */
  public function generateElectionAliases(NodeInterface $election): void {
    if ($election->getType() !== 'localgov_election') {
      return;
    }

    $base_alias = $this->getEntityAlias($election);
    if (!$base_alias) {
      return;
    }

    foreach (self::SUB_PAGES as $route_name => $suffix) {
      $this->generateSubPageAlias($election, $route_name, $base_alias . $suffix);
    }
  }

  /**
   * Generate an alias for a single sub-page.
   *
   * @param \Drupal\node\NodeInterface $election
   *   The election node.
   * @param string $route_name
   *   The route name.
   * @param string $alias
   *   The desired alias.
   */
  protected function generateSubPageAlias(NodeInterface $election, string $route_name, string $alias): void {
    $url = Url::fromRoute($route_name, ['node' => $election->id()]);
    $path = $url->toString();

    if (!$this->aliasExists($path)) {
      $this->createAlias($path, $alias);
    }
  }

  /**
   * Delete all election sub-page aliases.
   *
   * @param \Drupal\node\NodeInterface $election
   *   The election node.
   */
  public function deleteElectionAliases(NodeInterface $election): void {
    foreach (self::SUB_PAGES as $route_name => $suffix) {
      $url = Url::fromRoute($route_name, ['node' => $election->id()]);
      $path = $url->toString();
      $this->deleteAliasByPath($path);
    }
  }

  /**
   * Delete an alias by its alias path.
   *
   * @param string $path
   *   The internal path or alias path.
   */
  public function deleteAliasByPath(string $path): void {
    $alias_entities = $this->pathAliasStorage->loadByProperties(['path' => $path]);

    if (!$alias_entities) {
      // Try loading by alias field instead.
      $alias_entities = $this->pathAliasStorage->loadByProperties(['alias' => $path]);
    }

    if ($alias_entities) {
      try {
        $alias = reset($alias_entities);
        $alias->delete();
      }
      catch (EntityStorageException $e) {
        $this->logger->error('Failed to delete alias: @message', [
          '@message' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Check if an alias exists.
   *
   * @param string $path
   *   The internal path.
   *
   * @return bool
   *   True if alias exists, false otherwise.
   */
  public function aliasExists(string $path): bool {
    $url_object = $this->pathValidator->getUrlIfValid($path);

    if (!$url_object) {
      return FALSE;
    }

    // If the path has an alias, the URL object will have it.
    return $url_object->toString() !== $path;
  }

  /**
   * Get the alias for an entity.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The entity.
   *
   * @return string|null
   *   The alias or NULL if none exists.
   */
  public function getEntityAlias(NodeInterface $entity): ?string {
    try {
      $url = $entity->toUrl('canonical');
      $internal_path = $url->getInternalPath();

      $alias_entities = $this->pathAliasStorage->loadByProperties([
        'path' => '/' . $internal_path,
      ]);

      if ($alias_entities) {
        $alias = reset($alias_entities);
        return $alias->get('alias')->value;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get entity alias: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Create a new path alias.
   *
   * @param string $path
   *   The internal path.
   * @param string $alias
   *   The alias.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function createAlias(string $path, string $alias): bool {
    try {
      $path_alias = $this->pathAliasStorage->create([
        'path' => $path,
        'alias' => $alias,
        'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      ]);
      $path_alias->save();
      return TRUE;
    }
    catch (EntityStorageException $e) {
      $this->logger->error('Failed to create alias: @message', [
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

}
