<?php

namespace Drupal\localgov_elections\Plugin\views\field;

use Drupal\Core\Render\Markup;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to flag the node type.
 *
 * @ViewsField("ward_party")
 */
class WardParty extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   */
  public function query() {

  }

  /**
   * Render function for the ward_party field.
   *
   * Displays the winning party in an electoral area (Ward).
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $node = $values->_entity;

    // Iterate through each candidate and store votes.
    $party_name = NULL;
    $abbr = NULL;
    $results = [];
    $candidates = $node->get('localgov_election_candidates');

    foreach ($candidates->referencedEntities() as $candidate) {
      $votes = $candidate->get('localgov_election_votes')->value;
      $party = Term::load($candidate->get('localgov_election_party')->target_id);

      $party_abbr = $party->get('localgov_election_abbreviation')->value;

      $results[] = ['abbr' => $party_abbr, 'votes' => $votes, 'name' => $party->name?->value];
    }

    // Sort results into descending order by votes.
    if ($results) {
      $votes = array_column($results, 'votes');
      $sorted = array_multisort($votes, SORT_DESC, $results);
      // Find party of 1st result from sorted array.
      if ($sorted) {
        $party_name = $results[0]['name'];
        $abbr = $results[0]['abbr'];
        if ($abbr) {
          $abbr = strtolower($abbr);
        }
      }
    }

    return Markup::create("<div class='party " . $abbr . "'>" . $party_name . "</div>");
  }

}
