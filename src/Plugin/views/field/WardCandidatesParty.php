<?php

namespace Drupal\localgov_elections\Plugin\views\field;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to list results in each ward.
 *
 * @ViewsField("ward_candidates_party")
 */
class WardCandidatesParty extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   */
  public function query() {

  }

  /**
   * Render function for the ward_candidates field.
   *
   * Displays multiple rows, listing each candidate (including winner) and votes
   * for a ward in a specific election.
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $area_vote = $values->_entity;
    $hold_gain = $area_vote->get('localgov_election_hold_or_gain')->value;
    $markup = '<div class="ward-candidate-results">';

    $winner = Paragraph::load($area_vote->get('localgov_election_winner')->target_id);
    if (isset($winner)) {
      $party_term = Term::load($winner->get('localgov_election_party')->target_id);
      $party = $party_term ? $party_term->getName() : '_';
      $party_abbr = $party_term?->get('localgov_election_abbreviation')?->value
      ? strtolower($party_term->get('localgov_election_abbreviation')->value) : "";
      $votes = $winner->get('localgov_election_votes')->value;
      $markup .= '<div class="winner result-row">';

      $markup .= '<div class="key-result">';
      $markup .= '<span class="winning-party ' . $party_abbr . '">' . $party . '</span> ';
      if (isset($hold_gain)) {
        $markup .= '<span class="hold-gain ' . strtolower($hold_gain) . '">' . $hold_gain . '</span>';
      }
      // End of key-result DIV.
      $markup .= '</div>';
      // End of winner DIV.
      $markup .= '</div>';
    }

    // Iterate through each candidate and store name, party and votes.
    $results = [];
    $candidates = $area_vote->get('localgov_election_candidates');

    foreach ($candidates->referencedEntities() as $candidate) {
      $surname = $candidate->get('localgov_election_candidate')->value;
      $forenames = $candidate->get('localgov_election_forenames')->value;
      $party_term = Term::load($candidate->get('localgov_election_party')->target_id);
      $party = $party_term ? $party_term->getName() : '_';
      $votes = $candidate->get('localgov_election_votes')->value;
      $results[] = [
        'surname' => $surname,
        'forenames' => $forenames,
        'party' => $party,
        'votes' => $votes,
      ];
    }

    // Sort results into descending order by votes and
    // ignore 1st result (winner)
    if ($results) {
      $votes = array_column($results, 'votes');
      $sorted = array_multisort($votes, SORT_DESC, $results);

      // Generate markup.
      if ($sorted) {
        // Remove 1st result (winner)
        foreach ($results as $result) {
          $party = $result['party'];
          $votes = $result['votes'];
          $markup .= '<div class="loser result-row">';
          $markup .= '<div class="party">' . $party . '</div>';
          // End of loser DIV.
          $markup .= '</div>';
        }
      }
    }

    return [
      '#markup' => $markup,
    ];
  }

}
