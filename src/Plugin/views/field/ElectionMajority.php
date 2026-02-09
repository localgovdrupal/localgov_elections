<?php

namespace Drupal\localgov_elections\Plugin\views\field;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to flag the node type.
 *
 * @ViewsField("election_majority")
 */
class ElectionMajority extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   */
  public function query(): void {

  }

  /**
   * Render function for the election_majority field.
   *
   * Rounds down 50% of number of wards/areas in election, adds 1 to
   * calculate majority required.
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values): ?float {
    // Get ID of current election node (from URL argument)
    $node = \Drupal::routeMatch()->getParameter('node');
    $majority = NULL;
    if ($node instanceof NodeInterface) {
      // Arg must be NID of an Election content type.
      if ($node->getType() === 'localgov_election') {
        $election = $node->id();

        // Find all 'Election Area' nodes referencing this election.
        $query = \Drupal::entityQuery('node')
          ->condition('type', 'localgov_area_vote')
          ->condition('localgov_election', $election);
        // Has to include the not contesed.
        $query->accessCheck(FALSE);

        // Load all area_vote nodes for this election.
        $area_ids = $query->execute();
        $total_seats = 0;

        foreach ($area_ids as $area_id) {
          $area = Node::load($area_id);
          if ($area) {
            $total_seats += count($area->get('localgov_election_seats')->referencedEntities());
          }
        }

        if ($total_seats > 0) {
          $majority = floor($total_seats / 2) + 1;
        }

      }
    }
    return $majority;
  }

}
