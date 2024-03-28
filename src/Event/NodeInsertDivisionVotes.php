<?php

namespace Drupal\localgov_elections_reporting\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

/**
 * Wraps a node insertion event for event listeners.
 *
 * @todo do we need this?
 */
class NodeInsertDivisionVotes extends Event {

  const localgov_elections_reporting_NODE_INSERT = 'localgov_elections_reporting.node.insert';

  /**
   * Node entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Constructs a node insertion event object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public function __construct(EntityInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * Get the inserted entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function getEntity() {
    return $this->entity;
  }
}
