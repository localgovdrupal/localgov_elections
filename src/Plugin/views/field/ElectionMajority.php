<?php

namespace Drupal\localgov_elections\Plugin\views\field;

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
  public function query() {

  }

  /**
   * Render function for the election_majority field.
   *
   * Rounds down 50% of number of wards/areas in election, adds 1 to
   * calculate majority required.
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    // Get ID of current election node (from URL argument)
    $node = \Drupal::routeMatch()->getParameter('node');
    $majority = NULL;
    if ($node instanceof NodeInterface) {
      // Arg must be NID of an Election content type.
      if ($node->getType() == 'election') {
        $election = $node->id();

        // Find all 'Election Area' nodes referencing this election.
        $query = \Drupal::entityQuery('node')
          ->condition('type', 'division_vote')
          ->condition('field_election', $election);
        // Has to include the not contesed.
        $query->accessCheck(FALSE);
        $num_rows = $query->count()->execute();
        $majority = (floor($num_rows / 2)) + 1;
      }
    }
    return $majority;
  }

}
