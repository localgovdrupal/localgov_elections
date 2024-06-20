<?php

namespace Drupal\localgov_elections\Plugin\views\field;

use Drupal\node\Entity\Node;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to flag the node type.
 *
 * @ViewsField("election_turnout")
 */
class ElectionTurnout extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   */
  public function query() {

  }

  /**
   * Render function for the election_turnout field.
   *
   * Displays the difference between number of votes of first and second
   * results in an electoral area (Ward).
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $node = $values->_entity;
    $election = $node->id();

    // Find all 'Election Area' nodes referencing this election.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'localgov_area_vote')
      ->condition('localgov_election', $election);
    // OR ->condition('localgov_election', $node);
    // OR ->condition('localgov_election.entity:node.entity_id', $election);
    // Exclude contested.
    $query->accessCheck(FALSE);
    $wards = $query->execute();

    $turnout = 0;
    $electorate = 0;

    // Add all candidate votes + spoils for each ward.
    foreach ($wards as $ward_id) {
      $ward = Node::load($ward_id);

      if ($ward->get("localgov_election_no_contest")?->value == "1") {
        continue;
      }

      // Find spoils and add to turnout.
      $spoils = $ward->get('localgov_election_spoils')->value;
      $turnout += $spoils;

      // Iterate through each candidate and add votes to turnout.
      $candidates = $ward->get('localgov_election_candidates');

      /** @var \Drupal\paragraphs\Entity\Paragraph $candidate */
      foreach ($candidates->referencedEntities() as $candidate) {
        $votes = $candidate->get('localgov_election_votes')->value;
        $turnout += $votes;
      }

      // Find electorate and add to running total.
      $eligible_electorate = $ward->get('localgov_election_electorate')->value;
      if ($ward->localgov_election_votes_final?->value == 1) {
        $electorate += $eligible_electorate;
      }
    }

    // Needed to avoid division by 0.
    if ($electorate == 0) {
      return "0%";
    }

    $percentage = round(($turnout / $electorate * 100), 1);

    return '(' . $percentage . '%) ' . $turnout;
  }

}
