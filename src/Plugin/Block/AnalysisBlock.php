<?php

namespace Drupal\localgov_elections\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an analysis block.
 *
 * @Block(
 *   id = "analysis_block",
 *   admin_label = @Translation("Ward results analysis block")
 * )
 */
class AnalysisBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs an AnalysisBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    RouteMatchInterface $route_match
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\Core\Routing\RouteMatchInterface $route_match */
    $route_match = $container->get('current_route_match');

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $route_match
    );
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node');

    if (!$node instanceof NodeInterface) {
      return [];
    }

    $data = $this->prepareAnalysisData($node);

    return [
      '#theme' => 'analysis_block',
      '#data' => $data,
    ];
  }

  /**
   * Prepare analysis data from the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The area vote node.
   *
   * @return array
   *   Prepared data for the template.
   */
  protected function prepareAnalysisData(NodeInterface $node): array {
    $data = [];

    // Electorate.
    $electorate = $node->get('localgov_election_electorate')->value;
    if (isset($electorate)) {
      $data['electorate'] = $electorate;
    }

    // Spoils (rejected ballots).
    $spoils = $node->get('localgov_election_spoils')->value ?? 0;
    if (isset($spoils)) {
      $data['spoils'] = $spoils;
    }

    // Calculate votes and majority.
    $vote_data = $this->calculateVoteStatistics($node, $spoils);
    $data = array_merge($data, $vote_data);

    // Turnout percentage.
    if (!empty($data['total_votes']) && is_numeric($electorate)) {
      $data['turnout'] = round((($data['total_votes']) / $electorate) * 100, 1);
    }

    // Check if we should display majority.
    $data['show_majority'] = $this->shouldDisplayMajority($node);

    // Hold or Gain.
    $hold_or_gain = $node->get('localgov_election_hold_or_gain')->value;
    if (!empty($hold_or_gain) && $hold_or_gain !== 'na') {
      $data['hold_or_gain'] = $hold_or_gain;
    }

    // Previous election data.
    $previous_data = $this->getPreviousElectionData($node);
    $data = array_merge($data, $previous_data);

    return $data;
  }

  /**
   * Calculate vote statistics.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The area vote node.
   * @param int $spoils
   *   Number of spoiled ballots.
   *
   * @return array
   *   Array with valid_votes, total_votes, and majority.
   */
  protected function calculateVoteStatistics(NodeInterface $node, int $spoils): array {
    $valid_votes = 0;
    $results = [];

    $candidates = $node->get('localgov_election_candidates');
    foreach ($candidates->referencedEntities() as $candidate) {
      $votes = $candidate->get('localgov_election_votes')->value ?? 0;
      $valid_votes += $votes;
      $results[] = $votes;
    }

    // Sort descending to find first and second place.
    rsort($results);

    $majority = NULL;
    if (!empty($results)) {
      $first = $results[0] ?? 0;
      $second = $results[1] ?? 0;
      $majority = $first - $second;
    }

    return [
      'valid_votes' => $valid_votes,
      'total_votes' => $valid_votes + $spoils,
      'majority' => $majority,
    ];
  }

  /**
   * Determine if majority should be displayed.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The area vote node.
   *
   * @return bool
   *   TRUE if majority should be shown.
   */
  protected function shouldDisplayMajority(NodeInterface $node): bool {
    // Don't show for multi-seat areas.
    if ($node->hasField('localgov_election_seats')) {
      $seat_count = $node->get('localgov_election_seats')->count();
      if ($seat_count > 1) {
        return FALSE;
      }
    }

    // Check parent election setting.
    $election_nodes = $node->get('localgov_election')->referencedEntities();
    if (empty($election_nodes)) {
      return FALSE;
    }

    $election_node = $election_nodes[0];
    if (!$election_node->hasField('localgov_election_majority')) {
      return FALSE;
    }

    return $election_node->get('localgov_election_majority')->value == "1";
  }

  /**
   * Get previous election data.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The area vote node.
   *
   * @return array
   *   Array with previous_year and previous_winner_abbr if available.
   */
  protected function getPreviousElectionData(NodeInterface $node): array {
    $data = [];

    $previous_year = $node->get('localgov_election_previous_year')->value;
    $previous_winning_party = $node->get('localgov_election_prev_winner')->entity;

    if ($previous_winning_party) {
      $data['previous_winner_abbr'] = $previous_winning_party->get('localgov_election_abbreviation')->value;
    }

    // Try to get year from linked previous result if not manually set.
    if (!isset($previous_year)) {
      $previous_result = $node->get('localgov_election_prev_result')->entity;
      if ($previous_result) {
        $previous_election = $previous_result->get('localgov_election')->entity;
        if ($previous_election) {
          $previous_date = $previous_election->get('localgov_election_date')->value;
          if ($previous_date) {
            $previous_year = date('Y', $previous_date);
          }
        }
      }
    }

    if (isset($previous_year)) {
      $data['previous_year'] = $previous_year;
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
