<?php

namespace Drupal\localgov_elections\Service;

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
   *   Array of area data keyed by node ID.
   */
  public function prepareMapData(ViewExecutable $view): array {
    $map_data = [];

    foreach ($view->result as $row) {
      /** @var \Drupal\node\NodeInterface $area_vote */
      $area_vote = $row->_entity;
      $node_id = $area_vote->id();

      $winners = $area_vote->get('localgov_election_winner')->referencedEntities();

      // Skip if no winners.
      if (empty($winners)) {
        continue;
      }

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

      $area_data = $this->prepareAreaData($boundary_data, $winners, $node_id);
      if (!empty($area_data)) {
        $map_data[$node_id] = $area_data;
      }
    }

    return $map_data;
  }

  /**
   * Prepare data for an electoral area.
   *
   * @param string $boundary_data
   *   The GeoJSON boundary data.
   * @param \Drupal\paragraphs\ParagraphInterface[] $winners
   *   Array of winning candidate paragraphs.
   * @param int $node_id
   *   The area vote node ID.
   *
   * @return array
   *   Prepared data array with standardized structure.
   */
  protected function prepareAreaData(string $boundary_data, array $winners, int $node_id): array {
    $winner_data = [];

    foreach ($winners as $winner) {
      $party = $winner->get('localgov_election_party')->entity;

      if ($party) {
        $winner_data[] = [
          'party_name' => $party->getName(),
          'color' => $party->get('localgov_election_party_colour')->color ?? '#cccccc',
          'vote_count' => $winner->get('localgov_election_votes')->value ?? 0,
        ];
      }
    }

    if (empty($winner_data)) {
      return [];
    }

    return [
      'boundary' => $boundary_data,
      'winners' => $winner_data,
      'node_id' => $node_id,
    ];
  }

}
