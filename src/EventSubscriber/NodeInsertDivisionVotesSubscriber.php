<?php

namespace Drupal\localgov_elections_reporting\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\localgov_elections_reporting\Event\NodeInsertDivisionVotes;

/**
 * Logs the creation of a new node.
 *
 * @todo do we even need this?
 */
class NodeInsertDivisionVotesSubscriber implements EventSubscriberInterface {

  /**
   * Log the creation of a new node.
   *
   * @param $event
   */
  public function onDivisionVotesNodeInsert(NodeInsertDivisionVotes $event) {
    $entity = $event->getEntity();
    \Drupal::logger('localgov_elections_reporting')->notice('New @type: @title. Created by: @owner and clocked by custom module',
        array(
            '@type' => $entity->getType(),
            '@title' => $entity->label(),
            '@owner' => $entity->getOwner()->getDisplayName()
        ));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[NodeInsertDivisionVotes::localgov_elections_reporting_NODE_INSERT][] = ['onDivisionsVoteNodeInsert'];
    return $events;
  }
}
