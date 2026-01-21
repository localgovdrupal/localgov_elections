<?php

declare(strict_types=1);

namespace Drupal\Tests\localgov_elections\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests the MajorityCalculator service.
 *
 * @group localgov_elections
 */
class MajorityCalculatorTest extends KernelTestBase {

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
   * The majority calculator service.
   *
   * @var \Drupal\localgov_elections\Service\MajorityCalculator
   */
  protected $majorityCalculator;

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

    $this->majorityCalculator = $this->container->get('localgov_elections.majority_calculator');
  }

  /**
   * Create an election node.
   */
  protected function createElection(string $title = 'Test Election'): Node {
    $election = Node::create([
      'type' => 'localgov_election',
      'title' => $title,
    ]);
    $election->save();
    return $election;
  }

  /**
   * Create multiple area votes for an election.
   */
  protected function createAreas(Node $election, int $count): void {
    for ($i = 1; $i <= $count; $i++) {
      $area = Node::create([
        'type' => 'localgov_area_vote',
        'title' => "Area $i",
        'localgov_election' => $election,
      ]);
      $area->save();
    }
  }

  /**
   * Test election with no areas returns 1.
   */
  public function testElectionWithNoAreasReturnsOne(): void {
    $election = $this->createElection();

    $majority = $this->majorityCalculator->calculateMajority($election);
    $this->assertEquals(1, $majority);
  }

  /**
   * Test election with one area requires one seat for majority.
   */
  public function testElectionWithOneArea(): void {
    $election = $this->createElection();
    $this->createAreas($election, 1);

    $majority = $this->majorityCalculator->calculateMajority($election);
    $this->assertEquals(1, $majority, 'With 1 area, majority is 1');
  }

  /**
   * Test election with two areas requires two seats for majority.
   */
  public function testElectionWithTwoAreas(): void {
    $election = $this->createElection();
    $this->createAreas($election, 2);

    $majority = $this->majorityCalculator->calculateMajority($election);
    $this->assertEquals(2, $majority, 'With 2 areas, majority is 2');
  }

  /**
   * Test election with three areas requires two seats for majority.
   */
  public function testElectionWithThreeAreas(): void {
    $election = $this->createElection();
    $this->createAreas($election, 3);

    $majority = $this->majorityCalculator->calculateMajority($election);
    $this->assertEquals(2, $majority, 'With 3 areas, majority is 2 (floor(3/2) + 1)');
  }

  /**
   * Test election with even number of areas.
   */
  public function testElectionWithEvenNumberOfAreas(): void {
    $election = $this->createElection();
    $this->createAreas($election, 50);

    $majority = $this->majorityCalculator->calculateMajority($election);
    $this->assertEquals(26, $majority, 'With 50 areas, majority is 26 (floor(50/2) + 1)');
  }

  /**
   * Test election with odd number of areas.
   */
  public function testElectionWithOddNumberOfAreas(): void {
    $election = $this->createElection();
    $this->createAreas($election, 51);

    $majority = $this->majorityCalculator->calculateMajority($election);
    $this->assertEquals(26, $majority, 'With 51 areas, majority is 26 (floor(51/2) + 1)');
  }

}
