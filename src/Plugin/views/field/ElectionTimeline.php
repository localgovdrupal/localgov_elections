<?php

namespace Drupal\localgov_elections\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\node\NodeInterface;

/**
 * Timeline data for an area.
 *
 * @ViewsField("election_timeline")
 */
class ElectionTimeline extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   */
  public function query(): void {
    // No query alteration.
  }

  /**
   * Assembles data for an area's timeline.
   */
  public function render(ResultRow $values): array {
    $node = $values->_entity;
    if (!$node instanceof NodeInterface || $node->getType() !== 'localgov_area_vote') {
      return [];
    }

    $winner_ids = array_map(fn($item) => $item['target_id'], $node->get('localgov_election_winner')->getValue());

    $candidates = [];

    foreach ($node->get('localgov_election_candidates')->referencedEntities() as $candidate_paragraph) {
      $votes_field = $candidate_paragraph->get('localgov_election_votes')->getValue();
      $votes = (int) ($votes_field[0]['value'] ?? 0);
      $cand_id = $candidate_paragraph->id();

      $is_winner = in_array($cand_id, $winner_ids, TRUE);

      if ($is_winner && $votes === 0) {
        continue;
      }

      $party = $candidate_paragraph->get('localgov_election_party')->entity;

      $candidates[] = [
        'candidate' => $candidate_paragraph->get('localgov_election_candidate')->value ?? '',
        'forename' => $candidate_paragraph->get('localgov_election_forename')->value ?? '',
        'party' => $candidate_paragraph->get('localgov_election_party')->entity?->label() ?? '',
        'abbreviation' => $party?->get('localgov_election_abbreviation')->value ?? '',
        'votes' => $votes,
        'winner' => $is_winner,
        'id' => $cand_id,
      ];
    }

    // Find the lowest winning vote count.
    $min_winner_votes = PHP_INT_MAX;
    foreach ($candidates as $candidate) {
      if ($candidate['winner'] && $candidate['votes'] < $min_winner_votes) {
        $min_winner_votes = $candidate['votes'];
      }
    }

    // Do any non-winners have the same votes as the lowest winning vote count?
    $has_tie = FALSE;
    foreach ($candidates as $candidate) {
      if (!$candidate['winner'] && $candidate['votes'] === $min_winner_votes) {
        $has_tie = TRUE;
        break;
      }
    }

    // Mark winners with minimum votes as tie winners if there's a tie.
    foreach ($candidates as &$candidate) {
      $candidate['tie'] = $candidate['winner'] && $has_tie && $candidate['votes'] === $min_winner_votes;
    }

    if (empty($candidates)) {
      return [];
    }

    usort($candidates, function ($a, $b) {
      // Winners first.
      if ($a['winner'] && !$b['winner']) {
        return -1;
      }
      if (!$a['winner'] && $b['winner']) {
        return 1;
      }
      // Then sort by votes descending.
      return $b['votes'] <=> $a['votes'];
    });

    // Get number of seats (count of seat paragraphs).
    $seats = $node->get('localgov_election_seats')->count();

    // Format the finalised date as H:i.
    $time_formatted = '';
    $finalised_date = $node->get('localgov_election_finalised_date')->value;
    if ($finalised_date) {
      $date = new \DateTime($finalised_date);
      $time_formatted = $date->format('H:i');
    }

    // Return a render array.
    return [
      '#theme' => 'election_timeline_table_row',
      '#time' => $time_formatted,
      '#area' => $node->get('localgov_election_area_name')->value,
      '#area_url' => $node->toUrl()->toString(),
      '#seats' => $seats,
      '#candidates' => $candidates,
    ];
  }

}
