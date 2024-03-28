<?php

namespace Drupal\localgov_elections_reporting\Plugin\Block;

use Drupal\Core\Block\BlockBase;
// Do I need the following 3 libraries...? Probably
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

/**
 *
 * @Block(
 *   id = "party_seats_block",
 *   admin_label = @Translation("Election party seats results")
 * )
 *
 */
class PartySeatsBlock extends BlockBase {

  /**
   * {@inheritdoc)
   */
  public function build() {
    $markup = '';

    // Get ID of current election from URL
    $election = \Drupal::routeMatch()->getParameter('node');
    if ($election instanceof \Drupal\node\NodeInterface) {
      $nid = $election->id();
      $election_parties = [];

      //Find all 'Areas vote' (division_vote) nodes referencing this election
      $query = \Drupal::entityQuery('node')
          ->condition('type', 'division_vote')
          ->condition('field_election', $nid);
      $query->accessCheck(FALSE);
      $wards = $query->execute();

      // OR DO A BIG JOIN ON ABOVE QUERY AND THEN FIND DISTINCT VALUES FOR PARTIES IN ELECTION



      // ***************








      // Find parties
      foreach ($wards as $ward_id) {
        $ward = \Drupal\node\Entity\Node::load($ward_id);
        $candidates = $ward->get('field_candidates');
        $results = [];

        foreach ($candidates->referencedEntities() as $candidate) {
          $party = Term::load($candidate->get('field_party')->target_id);
          $party_abbr = $party->get('field_abbreviation')->value;

//          // Only add new parties if partiticpating in election
//          if (!(in_array($party_abbr, $election_parties))) {
//            //$party_name = $party->getTitle->value;
//            //$party_colour = $party->get('field_party_colour');
//            $election_parties[] = [
//              'abbr' => $party_abbr,
//              'count' => 0
//            ];
//          }
          // Store value
          $votes = $candidate->get('field_votes')->value;
          $results[] = ['abbr' => $party_abbr, 'votes' => $votes];
        }
        // Sort $results
        if ($results) {
          $votes = array_column($results, 'votes');
          $sorted = array_multisort($votes, SORT_DESC, $results);
          // Find party of 1st result from sorted array
          if ($sorted) {
            $winning_party_abbr = $results[0]['abbr'];
            $election_parties[$winning_party_abbr]['count'] += 1;
          }
        }
      }


      // wrapper to be displayed as highcharts table
      $markup .= '<div class="results-seats-chart">';
      foreach ($election_parties as $election_party) {
        $p_abbr = $election_party['abbr'];
        //$p_name = $election_party['name'];
        //$p_colour = $election_party['colour'];
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
