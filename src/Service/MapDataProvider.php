<?php

namespace Drupal\localgov_elections\Service;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\views\ViewExecutable;

/**
 * Provides electoral map data for JavaScript rendering.
 */
class MapDataProvider {

  /**
   * Prepare map data from a view result.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view executable.
   *
   * @return array
   *   Array with 'multi_winner' and 'single_winner' data.
   */
  public function prepareMapData(ViewExecutable $view): array {
    $multi_winner_data = [];
    $single_winner_data = [];

    foreach ($view->result as $row) {
      /** @var \Drupal\node\NodeInterface $area_vote */
      $area_vote = $row->_entity;
      $node_id = $area_vote->id();

      $winners = $area_vote->get('localgov_election_winner')->referencedEntities();
      $boundary_field = $area_vote->get('localgov_election_boundary_data');

      if ($boundary_field->isEmpty()) {
        continue;
      }

      $boundary_value = $boundary_field->first();
      if (!$boundary_value) {
        continue;
      }

      $boundary_data = $boundary_value->getValue()['value'] ?? '';
      if (empty($boundary_data)) {
        continue;
      }

      if (count($winners) > 1) {
        // Multi-winner constituency.
        $data = $this->prepareMultiWinnerData($boundary_data, $winners, $node_id);
        if (!empty($data)) {
          $multi_winner_data[$node_id] = $data;
        }
      }
      elseif (count($winners) === 1) {
        // Single-winner constituency.
        $data = $this->prepareSingleWinnerData($boundary_data, $winners[0], $node_id);
        if (!empty($data)) {
          $single_winner_data[$node_id] = $data;
        }
      }
    }

    return [
      'multi_winner' => $multi_winner_data,
      'single_winner' => $single_winner_data,
    ];
  }

  /**
   * Prepare data for a multi-winner constituency.
   *
   * @param string $boundary_data
   *   The GeoJSON boundary data.
   * @param \Drupal\paragraphs\ParagraphInterface[] $winners
   *   Array of winning candidate paragraphs.
   * @param int $node_id
   *   The area vote node ID.
   *
   * @return array
   *   Prepared data array or empty if insufficient winners.
   */
  protected function prepareMultiWinnerData(string $boundary_data, array $winners, int $node_id): array {
    $winner_data = [];

    foreach ($winners as $winner) {
      $party = $winner->get('localgov_election_party')->entity;
      $vote_count = $winner->get('localgov_election_votes')->value ?? 0;

      if ($party) {
        $winner_data[] = [
          'party_name' => $party->getName(),
          'color' => $party->get('localgov_election_party_colour')->color ?? '#cccccc',
          'vote_count' => $vote_count,
        ];
      }
    }

    // Need at least 2 winners to be multi-winner.
    if (count($winner_data) < 2) {
      return [];
    }

    return [
      'boundary' => $boundary_data,
      'winners' => $winner_data,
      'winner_count' => count($winner_data),
      'node_id' => $node_id,
    ];
  }

  /**
   * Prepare data for a single-winner constituency.
   *
   * @param string $boundary_data
   *   The GeoJSON boundary data.
   * @param \Drupal\paragraphs\ParagraphInterface $winner
   *   The winning candidate paragraph.
   * @param int $node_id
   *   The area vote node ID.
   *
   * @return array
   *   Prepared data array or empty if no party.
   */
  protected function prepareSingleWinnerData(string $boundary_data, ParagraphInterface $winner, int $node_id): array {
    $party = $winner->get('localgov_election_party')->entity;

    if (!$party) {
      return [];
    }

    return [
      'boundary' => $boundary_data,
      'party_color' => $party->get('localgov_election_party_colour')->color ?? '#cccccc',
      'party_name' => $party->getName(),
      'node_id' => $node_id,
    ];
  }

}
