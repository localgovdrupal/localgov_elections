<?php

namespace Drupal\localgov_elections\Plugin\views\field;

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to flag the node type.
 *
 * @ViewsField("party_name")
 */
class PartyName extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   */
  public function query() {

  }

  /**
   * Render function for the party_name field.
   *
   * Displays a participating party in an electoral area (Ward).
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $election = $values->_entity;

    // Iterate through each candidate and store votes.
    $party_name = NULL;
    $results = [];

    // Find all 'Areas vote' (division_vote) nodes referencing this election.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'division_vote')
      ->condition('localgov_election', $election);
    $query->accessCheck(FALSE);
    $wards = $query->execute();

    // Add all candidate votes + spoils for each ward.
    foreach ($wards as $ward_id) {
      $ward = Node::load($ward_id);
      $candidates = $ward->get('localgov_election_candidates');

      foreach ($candidates->referencedEntities() as $candidate) {
        $party = Term::load($candidate->get('field_party')->target_id);
        $party_name = $party->getTitle->value;
        $results[] = ['name' => $party_name];
      }
    }

    // Return party names.
    if ($results) {
      $party_name = $results[0]['name'];
    }

    return $party_name;
  }

}
