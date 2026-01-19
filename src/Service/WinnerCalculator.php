<?php

namespace Drupal\localgov_elections\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Calculates election winners for area vote nodes.
 */
class WinnerCalculator {

  /**
   * The paragraph storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $paragraphStorage;

  /**
   * Constructs a WinnerCalculator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->paragraphStorage = $entity_type_manager->getStorage('paragraph');
  }

  /**
   * Calculate winners for an area vote node.
   *
   * @param \Drupal\node\NodeInterface $area_vote
   *   The area vote node.
   *
   * @return \Drupal\paragraphs\ParagraphInterface[]
   *   Array of winner paragraph entities in order.
   */
  public function calculateWinners(NodeInterface $area_vote): array {
    if ($area_vote->getType() !== 'localgov_area_vote') {
      return [];
    }

    $uncontested_area = $area_vote->get('localgov_election_no_contest')?->value;
    $available_seats = $this->countAvailableSeats($area_vote);

    // If no seats are available, no winners.
    if ($available_seats === 0) {
      return [];
    }

    $winners = [];

    // Calculate contested winners if there are any.
    if (!$uncontested_area) {
      $contested_winners = $this->calculateContestedWinners(
        $area_vote,
        $available_seats
      );
      $winners = array_merge($winners, $contested_winners);
    }

    // Add uncontested winners.
    $uncontested_winners = $this->getUncontestedWinners($area_vote);
    $winners = array_merge($winners, $uncontested_winners);

    return $winners;
  }

  /**
   * Count available seats, excluding uncontested seats.
   *
   * @param \Drupal\node\NodeInterface $area_vote
   *   The area vote node.
   *
   * @return int
   *   Number of available seats.
   */
  protected function countAvailableSeats(NodeInterface $area_vote): int {
    $seats = $area_vote->get('localgov_election_seats')->referencedEntities();

    if (empty($seats)) {
      return 0;
    }

    $available_seats = 0;
    foreach ($seats as $seat) {
      if (!$seat->get('localgov_seat_not_contested')->value) {
        $available_seats++;
      }
    }

    return $available_seats;
  }

  /**
   * Calculate winners from contested candidates.
   *
   * @param \Drupal\node\NodeInterface $area_vote
   *   The area vote node.
   * @param int $available_seats
   *   Number of seats to fill.
   *
   * @return \Drupal\paragraphs\ParagraphInterface[]
   *   Array of winner paragraphs.
   */
  protected function calculateContestedWinners(NodeInterface $area_vote, int $available_seats): array {
    $candidates_field = $area_vote->get('localgov_election_candidates');

    if ($candidates_field->isEmpty()) {
      return [];
    }

    // Build candidate order map for tie-breaking.
    $candidate_order_map = $this->buildCandidateOrderMap($candidates_field);

    // Collect candidates with their votes and tie weight.
    $candidates = $this->collectCandidateData(
      $candidates_field->referencedEntities(),
      $candidate_order_map
    );

    if (empty($candidates)) {
      return [];
    }

    // Sort by votes DESC, then tie weight ASC.
    $this->sortCandidates($candidates);

    // Pick top N to fill available seats.
    $top_candidates = array_slice($candidates, 0, $available_seats);

    // Load winner paragraph entities in resolved order.
    return $this->loadWinnerParagraphs($top_candidates);
  }

  /**
   * Build a map of candidate IDs to their original field delta.
   *
   * Used for tie-breaking - earlier candidates win ties.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $candidates_field
   *   The candidates field.
   *
   * @return array
   *   Map of candidate ID => delta position.
   */
  protected function buildCandidateOrderMap($candidates_field): array {
    $map = [];

    foreach ($candidates_field as $delta => $item) {
      if (!empty($item->target_id)) {
        $map[(int) $item->target_id] = (int) $delta;
      }
    }

    return $map;
  }

  /**
   * Collect candidate data for sorting.
   *
   * @param \Drupal\paragraphs\ParagraphInterface[] $candidate_entities
   *   Array of candidate paragraph entities.
   * @param array $candidate_order_map
   *   Map of candidate ID to delta.
   *
   * @return array
   *   Array of candidate data with cand_id, votes, and tie_weight.
   */
  protected function collectCandidateData(array $candidate_entities, array $candidate_order_map): array {
    $candidates = [];

    foreach ($candidate_entities as $candidate) {
      $cand_id = (int) $candidate->id();
      $votes = (int) $candidate->get('localgov_election_votes')->value;
      $tie_weight = $candidate_order_map[$cand_id] ?? PHP_INT_MAX;

      $candidates[] = [
        'cand_id'    => $cand_id,
        'votes'      => $votes,
        'tie_weight' => $tie_weight,
      ];
    }

    return $candidates;
  }

  /**
   * Sort candidates by votes descending, then tie weight ascending.
   *
   * @param array $candidates
   *   Array of candidate data to sort (passed by reference).
   */
  protected function sortCandidates(array &$candidates): void {
    $votes_col = array_column($candidates, 'votes');
    $tie_col = array_column($candidates, 'tie_weight');
    array_multisort($votes_col, SORT_DESC, $tie_col, SORT_ASC, $candidates);
  }

  /**
   * Load paragraph entities for winning candidates.
   *
   * @param array $winners
   *   Array of winner data with cand_id keys.
   *
   * @return \Drupal\paragraphs\ParagraphInterface[]
   *   Array of loaded paragraph entities.
   */
  protected function loadWinnerParagraphs(array $winners): array {
    $winner_paragraphs = [];

    foreach ($winners as $winner) {
      $paragraph = $this->paragraphStorage->load($winner['cand_id']);

      if ($paragraph instanceof ParagraphInterface) {
        $winner_paragraphs[] = $paragraph;
      }
    }

    return $winner_paragraphs;
  }

  /**
   * Get uncontested winners from seats marked as uncontested.
   *
   * @param \Drupal\node\NodeInterface $area_vote
   *   The area vote node.
   *
   * @return \Drupal\paragraphs\ParagraphInterface[]
   *   Array of uncontested winner paragraphs.
   */
  protected function getUncontestedWinners(NodeInterface $area_vote): array {
    $uncontested_winners = [];

    $seats = $area_vote->get('localgov_election_seats')->referencedEntities();

    foreach ($seats as $seat) {
      if ($seat->get('localgov_seat_not_contested')->value) {
        $uncontested_paragraph = $seat->get('localgov_candidate_uncontested')->entity;

        if ($uncontested_paragraph instanceof ParagraphInterface) {
          $uncontested_winners[] = $uncontested_paragraph;
        }
      }
    }

    return $uncontested_winners;
  }

}
