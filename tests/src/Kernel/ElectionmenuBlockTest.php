<?php

declare(strict_types=1);

namespace Drupal\Tests\localgov_elections\Kernel;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests the ElectionmenuBlock plugin.
 *
 * @group localgov_elections
 */
class ElectionmenuBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'link',
    'datetime',
    'options',
    'taxonomy',
    'path',
    'workflows',
    'content_moderation',
    'geofield',
    'color_field',
    'menu_ui',
    'field_group',
    'field_formatter_class',
    'autocomplete_deluxe',
    'paragraphs',
    'paragraphs_table',
    'entity_reference_revisions',
    'file',
    'localgov_elections',
  ];

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The mocked route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE; // phpcs:ignore.

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'field', 'node', 'paragraphs']);
    $this->installConfig(['localgov_elections']);

    $this->blockManager = $this->container->get('plugin.manager.block');

    // Create a mock route match service.
    $this->routeMatch = $this->createMock(RouteMatchInterface::class);
  }

  /**
   * Create an election node.
   */
  protected function createElection(array $fields = []): Node {
    $election = Node::create(array_merge([
      'type' => 'localgov_election',
      'title' => 'Test Election',
    ], $fields));
    $election->save();
    return $election;
  }

  /**
   * Create an area vote node.
   */
  protected function createAreaVote(Node $election, array $fields = []): Node {
    $area = Node::create(array_merge([
      'type' => 'localgov_area_vote',
      'title' => 'Test Area',
      'localgov_election' => $election,
    ], $fields));
    $area->save();
    return $area;
  }

  /**
   * Create a block instance with mocked route match.
   *
   * @param mixed $node
   *   The node or node ID to pass to the block.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface
   *   The block instance.
   */
  protected function createBlock($node): BlockPluginInterface {
    // Set up the route match to return our node.
    $this->routeMatch->method('getParameter')
      ->with('node')
      ->willReturn($node);

    // Replace the route match service in the container.
    $this->container->set('current_route_match', $this->routeMatch);

    // Create the block instance.
    $block = $this->blockManager->createInstance('localgov_elections_electionmenu', []);

    return $block;
  }

  /**
   * Test basic election returns Results link.
   */
  public function testBasicElectionReturnsResultsLink(): void {
    $election = $this->createElection();
    $block = $this->createBlock($election);
    $build = $block->build();

    $this->assertNotEmpty($build['#links']);
    $this->assertCount(1, $build['#links']);
    $this->assertEquals('Results', $build['#links'][0]['link']->getText());
  }

  /**
   * Test electoral map link appears with boundary data.
   */
  public function testElectoralMapLinkAppearsWithBoundaryData(): void {
    $election = $this->createElection([
      'localgov_election_display_map' => TRUE,
    ]);

    // Create area vote with boundary data.
    $this->createAreaVote($election, [
      'localgov_election_boundary_data' => json_encode([
        'type' => 'Point',
        'coordinates' => [1.234567, 2.345678],
      ]),
    ]);

    $block = $this->createBlock($election);
    $build = $block->build();

    $this->assertCount(2, $build['#links']);
    $this->assertEquals('Results', $build['#links'][0]['link']->getText());
    $this->assertEquals('Electoral map', $build['#links'][1]['link']->getText());
  }

  /**
   * Test electoral map link hidden when display_map is FALSE.
   */
  public function testElectoralMapLinkHiddenWhenDisplayMapFalse(): void {
    $election = $this->createElection([
      'localgov_election_display_map' => FALSE,
    ]);

    // Create area vote with boundary data.
    $this->createAreaVote($election, [
      'localgov_election_boundary_data' => json_encode([
        'type' => 'Point',
        'coordinates' => [1.234567, 2.345678],
      ]),
    ]);

    $block = $this->createBlock($election);
    $build = $block->build();

    $this->assertCount(1, $build['#links']);
    $this->assertEquals('Results', $build['#links'][0]['link']->getText());
  }

  /**
   * Test electoral map link hidden without boundary data.
   */
  public function testElectoralMapLinkHiddenWithoutBoundaryData(): void {
    $election = $this->createElection([
      'localgov_election_display_map' => TRUE,
    ]);

    // Create area vote without boundary data.
    $this->createAreaVote($election);

    $block = $this->createBlock($election);
    $build = $block->build();

    $this->assertCount(1, $build['#links']);
    $this->assertEquals('Results', $build['#links'][0]['link']->getText());
  }

  /**
   * Test timeline and share links appear with finalized votes.
   */
  public function testTimelineAndShareLinksAppearWithFinalizedVotes(): void {
    $election = $this->createElection([
      'localgov_election_type' => 'County',
    ]);

    // Create area vote with finalized votes.
    $this->createAreaVote($election, [
      'localgov_election_votes_final' => TRUE,
    ]);

    $block = $this->createBlock($election);
    $build = $block->build();

    // Should have: Results, Results timeline, Share of the vote.
    $this->assertCount(3, $build['#links']);
    $this->assertEquals('Results', $build['#links'][0]['link']->getText());
    $this->assertEquals('Results timeline', $build['#links'][1]['link']->getText());
    $this->assertEquals('Share of the vote', $build['#links'][2]['link']->getText());
  }

  /**
   * Test timeline and share links hidden for NationalParliamentary elections.
   */
  public function testTimelineAndShareLinksHiddenForNationalParliamentary(): void {
    $election = $this->createElection([
      'localgov_election_type' => 'NationalParliamentary',
    ]);

    // Create area vote with finalized votes.
    $this->createAreaVote($election, [
      'localgov_election_votes_final' => TRUE,
    ]);

    $block = $this->createBlock($election);
    $build = $block->build();

    // Should only have: Results (timeline and share hidden).
    $this->assertCount(1, $build['#links']);
    $this->assertEquals('Results', $build['#links'][0]['link']->getText());
  }

  /**
   * Test electoral candidates link appears with PDFs.
   */
  public function testElectoralCandidatesLinkAppearsWithPdfs(): void {
    $election = $this->createElection();

    // Create a test file.
    $file = \Drupal::service('entity_type.manager')
      ->getStorage('file')
      ->create([
        'uri' => 'public://test.pdf',
        'status' => 1,
      ]);
    $file->save();

    // Create area vote with candidate file.
    $this->createAreaVote($election, [
      'localgov_election_cand_file' => [$file],
    ]);

    $block = $this->createBlock($election);
    $build = $block->build();

    // Should have: Results, Electoral candidates.
    $this->assertCount(2, $build['#links']);
    $this->assertEquals('Results', $build['#links'][0]['link']->getText());
    $this->assertEquals('Electoral candidates', $build['#links'][1]['link']->getText());
  }

  /**
   * Test complete menu with all links.
   */
  public function testCompleteMenuWithAllLinks(): void {
    $election = $this->createElection([
      'localgov_election_type' => 'County',
      'localgov_election_display_map' => TRUE,
    ]);

    // Create a test file.
    $file = \Drupal::service('entity_type.manager')
      ->getStorage('file')
      ->create([
        'uri' => 'public://test.pdf',
        'status' => 1,
      ]);
    $file->save();

    // Create area vote with all features.
    $this->createAreaVote($election, [
      'localgov_election_boundary_data' => json_encode([
        'type' => 'Point',
        'coordinates' => [1.234567, 2.345678],
      ]),
      'localgov_election_votes_final' => TRUE,
      'localgov_election_cand_file' => [$file],
    ]);

    $block = $this->createBlock($election);
    $build = $block->build();

    // Should have all 5 links.
    $this->assertCount(5, $build['#links']);
    $this->assertEquals('Results', $build['#links'][0]['link']->getText());
    $this->assertEquals('Electoral map', $build['#links'][1]['link']->getText());
    $this->assertEquals('Results timeline', $build['#links'][2]['link']->getText());
    $this->assertEquals('Share of the vote', $build['#links'][3]['link']->getText());
    $this->assertEquals('Electoral candidates', $build['#links'][4]['link']->getText());
  }

  /**
   * Test area vote node loads parent election.
   */
  public function testAreaVoteNodeLoadsParentElection(): void {
    $election = $this->createElection([
      'localgov_election_type' => 'County',
    ]);

    $area_vote = $this->createAreaVote($election, [
      'localgov_election_votes_final' => TRUE,
    ]);

    // Pass the area vote node to the block.
    $block = $this->createBlock($area_vote);
    $build = $block->build();

    // Should load parent election and show its menu.
    $this->assertNotEmpty($build['#links']);
    $this->assertCount(3, $build['#links']);
    $this->assertEquals('Results', $build['#links'][0]['link']->getText());
  }

  /**
   * Test area vote without parent election returns empty.
   */
  public function testAreaVoteWithoutParentReturnsEmpty(): void {
    // Create area vote without parent election reference.
    $area_vote = Node::create([
      'type' => 'localgov_area_vote',
      'title' => 'Orphan Area',
    ]);
    $area_vote->save();

    $block = $this->createBlock($area_vote);
    $build = $block->build();

    $this->assertEmpty($build);
  }

  /**
   * Test non-election node returns empty.
   */
  public function testNonElectionNodeReturnsEmpty(): void {
    // Create a page node.
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test Page',
    ]);
    $node->save();

    $block = $this->createBlock($node);
    $build = $block->build();

    $this->assertEmpty($build);
  }

  /**
   * Test node ID as integer loads correctly.
   */
  public function testNodeIdAsIntegerLoadsCorrectly(): void {
    $election = $this->createElection();

    // Pass node ID as string (simulating route parameter).
    $block = $this->createBlock((string) $election->id());
    $build = $block->build();

    $this->assertNotEmpty($build['#links']);
    $this->assertCount(1, $build['#links']);
    $this->assertEquals('Results', $build['#links'][0]['link']->getText());
  }

  /**
   * Test invalid node ID returns empty.
   */
  public function testInvalidNodeIdReturnsEmpty(): void {
    // Pass invalid node ID.
    $block = $this->createBlock('999999');
    $build = $block->build();

    $this->assertEmpty($build);
  }

  /**
   * Test null node parameter returns empty.
   */
  public function testNullNodeParameterReturnsEmpty(): void {
    $block = $this->createBlock(NULL);
    $build = $block->build();

    $this->assertEmpty($build);
  }

}
