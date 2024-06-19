<?php

namespace Drupal\localgov_elections\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to flag the node type.
 *
 * @ViewsField("ward_majority")
 */
class WardMajority extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   */
  public function query() {

  }

  /**
   * Render function for the ward_majority field.
   *
   * Displays the difference between number of votes of first and second
   * results in an electoral area (Ward).
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $node = $values->_entity;

    // Iterate through each candidate and store votes.
    $first = 0;
    $second = 0;
    $results = [];
    $majority = NULL;
    $candidates = $node->get('localgov_election_candidates');

    foreach ($candidates->referencedEntities() as $candidate) {
      $votes = $candidate->get('localgov_election_votes')->value;
      $results[] = $votes;
    }

    // Sort Vote results into descending order resetting array key order.
    $sorted = rsort($results);

    // Work out diff between #1 and #2 for majority value.
    if ($sorted) {
      if (isset($results[0])) {
        $first = $results[0];
      }
      if (isset($results[1])) {
        // Find DIFF.
        $second = $results[1];
        $majority = $first - $second;
      }
      else {
        // Assume only 1 candidate standing.
        $majority = $first;
      }
    }
    return $majority;
  }

}
