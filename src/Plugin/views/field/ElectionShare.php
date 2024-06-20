<?php

namespace Drupal\localgov_elections\Plugin\views\field;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to calculate percentage share of votes cast for a party.
 *
 * @ViewsField("election_share")
 */
class ElectionShare extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   */
  public function query() {

  }

  /**
   * Render function for the election_share field.
   *
   * Displays the percentage share of a political party.
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    // Get value of Area/Division vote (localgov_area_vote) from View -
    // unfortunately cannot use this Node directly due to way Views handles
    // aggregate functions from Custom fields.
    $entity = $values->_entity;
    // Get ID of Election.
    $election = $entity->get('localgov_election')->target_id;
    $party = $values->_relationship_entities['localgov_election_party'];
    $party_id = $party->id();
    $total_votes = 0;
    $party_votes = 0;
    $percentage = NULL;

    // FALLBACK METHOD TO GET ELECTION ID:
    // Get ID of current election node (from URL argument)
    // Only works if NID is 2nd arg. I know - flakey.
    // Would be better to grab the Views Contextual Filter
    // $current_path = \Drupal::service('path.current')->getPath();
    // $path_args = explode('/', $current_path);
    // $nid = $path_args[2];.
    $node = Node::load($election);
    if ($node instanceof NodeInterface) {
      // Arg must be NID of an Election content type.
      if ($node->getType() == 'localgov_election') {
        // $election = $node->id();
        // Find all 'Area vote' (localgov_area_vote) nodes referencing this election
        $query = \Drupal::entityQuery('node')
          ->condition('type', 'localgov_area_vote')
          ->condition('localgov_election', $election);
        // Exclude not contested.
        $query->accessCheck(FALSE);
        $wards = $query->execute();

        // Add all candidate votes + spoils for each ward.
        foreach ($wards as $ward_id) {
          $ward = Node::load($ward_id);

          // Iterate through each candidate and add votes to trunout.
          $candidates = $ward->get('localgov_election_candidates');

          foreach ($candidates->referencedEntities() as $candidate) {
            $votes = $candidate->get('localgov_election_votes')->value;
            $cand_party = $candidate->get('localgov_election_party')->target_id;
            $total_votes += $votes;
            if ($cand_party == $party_id) {
              $party_votes += $votes;
            }
          }
        }
        if ($total_votes > 0) {
          $percentage = round(($party_votes / $total_votes * 100), 1);
        }
      }
    }

    return $percentage;
  }

}
