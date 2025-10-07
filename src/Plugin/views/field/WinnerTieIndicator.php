<?php

namespace Drupal\localgov_elections\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to indicate if a winner won on a tie.
 *
 * @ViewsField("winner_tie_indicator")
 */
class WinnerTieIndicator extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   */
  public function query(): void {

  }

  /**
   * Render function for the winner tie indicator.
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values): string {
    // Get the winner paragraph from the current row.
    $winner = $this->getEntity($values);

    if (!$winner || !$winner->hasField('localgov_election_votes')) {
      return '';
    }

    $winner_votes = (int) $winner->get('localgov_election_votes')->value;

    $area_node = $values->_entity;

    if (!$area_node || !$area_node->hasField('localgov_election_candidates')) {
      return '';
    }

    // Check if there's a tie.
    $candidates = $area_node->get('localgov_election_candidates')->referencedEntities();
    $winners = $area_node->get('localgov_election_winner')->referencedEntities();

    $winner_ids = array_map(fn($winner_paragraph) => $winner_paragraph->id(), $winners);

    foreach ($candidates as $candidate) {
      if (!in_array($candidate->id(), $winner_ids, TRUE)) {
        $candidate_votes = (int) $candidate->get('localgov_election_votes')->value;

        if ($candidate_votes === $winner_votes) {
          return ' (tie winner)';
        }
      }
    }

    return '';
  }

}
