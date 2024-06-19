<?php

namespace Drupal\localgov_elections\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
// Do I need the following 3 libraries...? Probably.
use Drupal\taxonomy\Entity\Term;

/**
 * Provides a 'party seats' block.
 *
 * @Block(
 *   id = "party_seats_block",
 *   admin_label = @Translation("Election party seats results")
 * )
 */
class PartySeatsBlock extends BlockBase {

  /**
   * {@inheritDoc}
   */
  public function build() {
    $markup = '';

    // Get ID of current election from URL.
    // phpcs:ignore
    $election = \Drupal::routeMatch()->getParameter('node');
    if ($election instanceof NodeInterface) {
      $nid = $election->id();
      $election_parties = [];

      // Find all 'Areas vote' (localgov_area_vote) nodes referencing this election.
      // phpcs:ignore
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'localgov_area_vote')
        ->condition('localgov_election', $nid);
      $query->accessCheck(FALSE);
      $wards = $query->execute();

      // Find parties.
      foreach ($wards as $ward_id) {
        // phpcs:ignore
        $ward = Node::load($ward_id);
        $candidates = $ward->get('localgov_election_candidates');
        $results = [];

        foreach ($candidates->referencedEntities() as $candidate) {
          // phpcs:ignore
          $party = Term::load($candidate->get('field_party')->target_id);
          $party_abbr = $party->get('localgov_election_abbreviation')->value;

          $votes = $candidate->get('localgov_election_votes')->value;
          $results[] = ['abbr' => $party_abbr, 'votes' => $votes];
        }
        // Sort $results.
        if ($results) {
          $votes = array_column($results, 'votes');
          $sorted = array_multisort($votes, SORT_DESC, $results);
          // Find party of 1st result from sorted array.
          if ($sorted) {
            $winning_party_abbr = $results[0]['abbr'];
            $election_parties[$winning_party_abbr]['count'] += 1;
          }
        }
      }

      // Wrapper to be displayed as highcharts table.
      $markup .= '<div class="results-seats-chart">';
      foreach ($election_parties as $election_party) {
        $p_abbr = $election_party['abbr'];
        // $p_name = $election_party['name'];
        // $p_colour = $election_party['colour'];
        $p_count = $election_party['count'];

        if ($p_abbr) {
          $markup .= '<div class="label abbr">ABBR: ' . $p_abbr . '</div>';
        }
        if ($p_count) {
          $markup .= '<div class="label count">COUNT: ' . $p_count . '</div>';
        }
      }

      $markup .= '</div>';
    }

    return [
      '#type' => 'markup',
      '#markup' => $markup,
    ];
  }

}
