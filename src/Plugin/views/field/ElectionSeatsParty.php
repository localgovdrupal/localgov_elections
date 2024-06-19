<?php

namespace Drupal\localgov_elections\Plugin\views\field;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to flag the node type.
 *
 * @ViewsField("election_seats_party")
 */
class ElectionSeatsParty extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   */
  public function query() {

  }

  /**
   * Render function for the election_seats_party.
   *
   * Displays the difference between number of votes of first and second
   * results in an electoral area (Ward).
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $party = $values->_entity;
    $party_tid = $party->id();
    $seats = 0;
    $party_standing = FALSE;

    // Get ID of current election node (from URL argument)
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      // Arg must be NID of an Election content type.
      if ($node->getType() == 'localgov_election') {
        $election = $node->id();
        // Find all 'Area vote' (localgov_area_vote) nodes referencing this election.
        $query = \Drupal::entityQuery('node')
          ->condition('type', 'localgov_area_vote')
          ->condition('localgov_election', $election);
        $query->accessCheck(FALSE);
        $wards = $query->execute();
        // Go through each ward/area/division.
        // If a party has a candidate in the ward set $party_standing to TRUE
        // If a party won the seat increment the $seats counter;.
        foreach ($wards as $ward_id) {
          $ward = Node::load($ward_id);
          // Iterate through each candidate to see if party standing -
          // only if not already flagged.
          if ($party_standing == FALSE) {
            $candidates = $ward->get('localgov_election_candidates');

            foreach ($candidates->referencedEntities() as $candidate) {
              $cand_party = $candidate->get('field_party')->target_id;
              if ($party_tid == $cand_party) {
                $party_standing = TRUE;
              }
            }
          }

          // Find party of Ward/Area/Division winning candidate.
          $winning_cand_id = $ward->get('field_winning_candidate')->target_id;
          if (isset($winning_cand_id)) {
            $winning_cand = Paragraph::load($winning_cand_id);
            if (isset($winning_cand)) {
              $winning_party = $winning_cand->get('field_party')->target_id;
              if ($party_tid == $winning_party) {
                $seats++;
              }
            }
          }
        }
      }// End of node being an Election node type
    } // End of being a node
    if ($party_standing) {
      return $seats;
    }
    else {
      return NULL;
    }
  }

}
