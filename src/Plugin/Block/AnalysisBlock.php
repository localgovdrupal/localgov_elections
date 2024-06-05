<?php

namespace Drupal\localgov_elections_reporting\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Provides an analysis block.
 *
 * @Block(
 *   id = "analysis_block",
 *   admin_label = @Translation("Ward results analysis block")
 * )
 */
class AnalysisBlock extends BlockBase {

  /**
   * {@inheritDoc}
   */
  public function build() {
    $markup = '';

    // Get ID of current node.
    // phpcs:ignore
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      // $nid = $node->id();
      // wrapper to be displayed as Grid or Flex table
      $markup .= '<div class="results-analysis-grid">';

      // Get Electorate.
      $electorate = $node->field_electorate->value;
      if (isset($electorate)) {
        $markup .= '<div class="label electorate">Electorate</div>';
        $markup .= '<div class="value electorate">' . $electorate . '</div>';
      }

      // Get spoils.
      $spoils = $node->field_spoils->value;
      if (isset($spoils)) {
        $markup .= '<div class="label spoils">Rejected ballot papers</div>';
        $markup .= '<div class="value spoils">' . $spoils . '</div>';
      }

      // Get results of each candidate and sum votes cast.
      $valid_total_votes = 0;

      // Iterate through each candidate and store votes.
      $first = 0;
      $second = 0;
      $results = [];
      $majority = NULL;
      $candidates = $node->get('field_candidates');

      foreach ($candidates->referencedEntities() as $candidate) {
        $votes = $candidate->get('field_votes')->value;
        $valid_total_votes += $votes;
        $results[] = $votes;
      }

      // Sort Vote results into descending order + reset key order.
      $sorted = rsort($results);

      // Work out diff between #1 and #2 for majority value.
      if ($sorted) {
        if (isset($results[0])) {
          $first = $results[0];
        }
        if (isset($results[1])) {
          // Find DIFF.
          $second = $results[1];
          $majority = $first - $second;
        }
        else {
          // Assume only 1 candidate standing.
          $majority = $first;
        }
      }

      // Total votes cast.
      if ($valid_total_votes > 0) {
        $markup .= '<div class="label votes">Votes cast</div>';
        $markup .= '<div class="value votes">' . $valid_total_votes . '</div>';
      }

      // Calculate percentage turnout.
      if ($valid_total_votes > 0 && is_numeric($electorate)) {
        $turnout = round((($valid_total_votes + $spoils) / $electorate) * 100, 1);

        $markup .= '<div class="label turnout">% turnout</div>';
        $markup .= '<div class="value turnout">' . $turnout . '</div>';
      }

      // Get the parent election so we can figure out if
      // we can display the majority.
      $display_majority = FALSE;
      if ($election_nodes = $node->get('field_election')->referencedEntities()) {
        if (isset($election_nodes[0])) {
          $election_node = $election_nodes[0];
          if ($election_node->hasField('field_display_majority_details')) {
            if ($election_node->get('field_display_majority_details')?->value == "1") {
              $display_majority = TRUE;
            }
          }
        }
      }

      // Display majority if we can.
      if ($display_majority && isset($majority)) {
        $markup .= '<div class="label majority">Majority</div>';
        $markup .= '<div class="value majority">' . $majority . '</div>';
      }

      // Retrieve results of previous election.
      $previous_year = $node->field_previous_year->value;
      $previous_winning_party = $node->field_previous_winner->entity;
      $previous_result = $node->field_previous_result->referencedEntity;
      if (isset($previous_winning_party)) {
        $previous_winner_abbr = $previous_winning_party->field_abbreviation->value;
      }

      // If previous year not manually set, look if previous 'division_vote'
      // been set.
      if (isset($previous_result)) {
        // phpcs:ignore
        $previous_division_vote = Node::load($previous_result->id());
        $previous_election = $previous_division_vote->field_election;

        // Find year from 'division_vote' entity.
        if (!isset($previous_year)) {
          $previous_date = $previous_election->field_date;
          if (isset($previous_date)) {
            $previous_year = date('Y', $previous_date);
          }
        }

        // Find winning party from 'division_vote' entity
        // Need to check all candidates and see who won!
        // @todo remove
        if (!isset($previous_winning_party)) {
          $previous_winner_abbr = "* TEST *";
        }
      }

      if (isset($previous_year)) {
        $markup .= '<div class="label previous">' . $previous_year . ' Result</div>';
        if (isset($previous_winning_party)) {
          $markup .= '<div class="value previous">' . $previous_winner_abbr . '</div>';
        }
        else {
          $markup .= '<div class="value previous"></div>';
        }
      }

      $markup .= '</div>';
    }

    return [
      '#type' => 'markup',
      '#markup' => $markup,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
