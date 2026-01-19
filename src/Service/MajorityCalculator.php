<?php

namespace Drupal\localgov_elections\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Calculates number of seats required for an electoral majority.
 */
class MajorityCalculator {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs a MajorityCalculator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * Calculate the number of seats needed for a majority.
   *
   * @param \Drupal\node\NodeInterface $election
   *   The election node.
   *
   * @return int|null
   *   The number of seats, or NULL if not an election.
   */
  public function calculateMajority(NodeInterface $election): ?int {
    if ($election->getType() !== 'localgov_election') {
      return NULL;
    }

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'localgov_area_vote')
      ->condition('localgov_election', $election->id())
      ->accessCheck(FALSE)
      ->count();

    $num_areas = $query->execute();

    return (int) (floor($num_areas / 2) + 1);
  }

}
