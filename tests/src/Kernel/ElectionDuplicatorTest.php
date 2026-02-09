<?php

declare(strict_types=1);

namespace Drupal\Tests\localgov_elections\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

/**
 * Tests the ElectionDuplicator service.
 *
 * @group localgov_elections
 */
class ElectionDuplicatorTest extends KernelTestBase {

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
   * The election duplicator service.
   *
   * @var \Drupal\localgov_elections\Service\ElectionDuplicator
   */
  protected $electionDuplicator;

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

    $this->electionDuplicator = $this->container->get('localgov_elections.election_duplicator');
  }

  /**
   * Create an election node.
   */
  protected function createElection(string $title = 'General Election 2024', bool $published = TRUE): Node {
    $election = Node::create([
      'type' => 'localgov_election',
      'title' => $title,
      'status' => $published,
    ]);
    $election->save();
    return $election;
  }

  /**
   * Create an area vote node.
   */
  protected function createAreaVote(Node $election, string $title, array $additional_fields = []): Node {
    $area = Node::create(array_merge([
      'type' => 'localgov_area_vote',
      'title' => $title,
      'localgov_election' => $election,
    ], $additional_fields));
    $area->save();
    return $area;
  }

  /**
   * Create a party term.
   */
  protected function createParty(string $name = 'Test Party'): Term {
    $party = Term::create([
      'vid' => 'localgov_party',
      'name' => $name,
    ]);
    $party->save();
    return $party;
  }

  /**
   * Create a candidate paragraph.
   */
  protected function createCandidate(string $forename, string $surname, $party = NULL, ?int $votes = NULL): Paragraph {
    $fields = [
      'type' => 'localgov_election_candidate',
      'localgov_election_forename' => $forename,
      'localgov_election_candidate' => $surname,
    ];

    if ($party) {
      $fields['localgov_election_party'] = $party;
    }

    if ($votes !== NULL) {
      $fields['localgov_election_votes'] = $votes;
    }

    $candidate = Paragraph::create($fields);
    $candidate->save();
    return $candidate;
  }

  /**
   * Create a seat paragraph.
   */
  protected function createSeat(string $name, bool $contested = TRUE, $uncontested_candidate = NULL): Paragraph {
    $fields = [
      'type' => 'localgov_area_seat',
      'localgov_seat' => $name,
      'localgov_seat_not_contested' => !$contested,
    ];

    if ($uncontested_candidate) {
      $fields['localgov_candidate_uncontested'] = $uncontested_candidate;
    }

    $seat = Paragraph::create($fields);
    $seat->save();
    return $seat;
  }

  /**
   * Load area votes for an election.
   */
  protected function loadAreaVotesForElection(Node $election): array {
    return $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'localgov_area_vote',
        'localgov_election' => $election->id(),
      ]);
  }

  /**
   * Test duplicate election with no area votes.
   */
  public function testDuplicateElectionWithNoAreaVotes(): void {
    $election = $this->createElection();

    $new_election = $this->electionDuplicator->duplicateElection($election, 'General Election 2029');

    $this->assertEquals('General Election 2029', $new_election->getTitle());
    $this->assertFalse($new_election->isPublished(), 'Duplicated election should be unpublished');
    $this->assertNotEquals($election->id(), $new_election->id());
    $this->assertTrue($election->isPublished(), 'Original election should remain published');
  }

  /**
   * Test duplicate election with single area vote.
   */
  public function testDuplicateElectionWithSingleAreaVote(): void {
    $election = $this->createElection();
    $area = $this->createAreaVote($election, 'Scarfolk Central', ['status' => TRUE]);

    $new_election = $this->electionDuplicator->duplicateElection($election, 'General Election 2029');

    $new_areas = $this->loadAreaVotesForElection($new_election);

    $this->assertCount(1, $new_areas);
    $new_area = reset($new_areas);
    $this->assertEquals('Scarfolk Central', $new_area->getTitle());
    $this->assertFalse($new_area->isPublished(), 'Duplicated area should be unpublished');
    $this->assertNotEquals($area->id(), $new_area->id(), 'Area should be duplicated, not referenced');
  }

  /**
   * Test duplicate election with multiple area votes.
   */
  public function testDuplicateElectionWithMultipleAreaVotes(): void {
    $election = $this->createElection();

    for ($i = 1; $i <= 3; $i++) {
      $this->createAreaVote($election, "Scarfolk Ward $i");
    }

    $new_election = $this->electionDuplicator->duplicateElection($election, 'General Election 2029');
    $new_areas = $this->loadAreaVotesForElection($new_election);

    $this->assertCount(3, $new_areas);
  }

  /**
   * Test candidate paragraphs are duplicated.
   */
  public function testCandidateParagraphsDuplicated(): void {
    $election = $this->createElection();
    $party = $this->createParty();
    $candidate = $this->createCandidate('John', 'Smith', $party, 100);
    $seat = $this->createSeat('Seat 1');

    $this->createAreaVote($election, 'Scarfolk Central', [
      'localgov_election_candidates' => [$candidate],
      'localgov_election_seats' => [$seat],
    ]);

    $new_election = $this->electionDuplicator->duplicateElection($election, 'General Election 2029');
    $new_areas = $this->loadAreaVotesForElection($new_election);
    $new_area = reset($new_areas);
    $new_candidates = $new_area->get('localgov_election_candidates')->referencedEntities();

    $this->assertCount(1, $new_candidates);
    $new_candidate = $new_candidates[0];
    $this->assertNotEquals($candidate->id(), $new_candidate->id(), 'Candidate should be duplicated');
    $this->assertEquals('John', $new_candidate->get('localgov_election_forename')->value);
    $this->assertEquals('Smith', $new_candidate->get('localgov_election_candidate')->value);
    $this->assertEquals(100, $new_candidate->get('localgov_election_votes')->value);
  }

  /**
   * Test seat paragraphs are duplicated.
   */
  public function testSeatParagraphsDuplicated(): void {
    $election = $this->createElection();

    $seats = [];
    for ($i = 1; $i <= 3; $i++) {
      $seats[] = $this->createSeat("Seat $i");
    }

    $this->createAreaVote($election, 'Scarfolk Central', [
      'localgov_election_seats' => $seats,
    ]);

    $new_election = $this->electionDuplicator->duplicateElection($election, 'General Election 2029');
    $new_areas = $this->loadAreaVotesForElection($new_election);
    $new_area = reset($new_areas);
    $new_seats = $new_area->get('localgov_election_seats')->referencedEntities();

    $this->assertCount(3, $new_seats);
    $this->assertNotEquals($seats[0]->id(), $new_seats[0]->id(), 'Seats should be duplicated');
    $this->assertEquals('Seat 1', $new_seats[0]->get('localgov_seat')->value);
  }

  /**
   * Test nested paragraphs are duplicated (uncontested candidates).
   */
  public function testNestedParagraphsDuplicated(): void {
    $election = $this->createElection();
    $party = $this->createParty();
    $uncontested_candidate = $this->createCandidate('Jane', 'Doe', $party);
    $seat = $this->createSeat('Seat 1', FALSE, $uncontested_candidate);

    $this->createAreaVote($election, 'Scarfolk Central', [
      'localgov_election_seats' => [$seat],
    ]);

    $new_election = $this->electionDuplicator->duplicateElection($election, 'General Election 2029');
    $new_areas = $this->loadAreaVotesForElection($new_election);
    $new_area = reset($new_areas);
    $new_seats = $new_area->get('localgov_election_seats')->referencedEntities();
    $new_seat = $new_seats[0];
    $new_uncontested = $new_seat->get('localgov_candidate_uncontested')->entity;

    $this->assertNotNull($new_uncontested);
    $this->assertNotEquals($uncontested_candidate->id(), $new_uncontested->id(), 'Nested candidate should be duplicated');
    $this->assertEquals('Jane', $new_uncontested->get('localgov_election_forename')->value);
    $this->assertEquals('Doe', $new_uncontested->get('localgov_election_candidate')->value);
  }

  /**
   * Test winner field is cleared in duplicate.
   */
  public function testWinnerFieldCleared(): void {
    $election = $this->createElection();
    $candidate = $this->createCandidate('John', 'Smith');
    $seat = $this->createSeat('Seat 1');

    $this->createAreaVote($election, 'Scarfolk Central', [
      'localgov_election_candidates' => [$candidate],
      'localgov_election_seats' => [$seat],
      'localgov_election_winner' => [$candidate],
    ]);

    $new_election = $this->electionDuplicator->duplicateElection($election, 'General Election 2029');
    $new_areas = $this->loadAreaVotesForElection($new_election);
    $new_area = reset($new_areas);

    $this->assertTrue($new_area->get('localgov_election_winner')->isEmpty(), 'Winner field should be cleared');
  }

  /**
   * Test area vote title is updated with election title.
   */
  public function testAreaVoteTitleUpdated(): void {
    $election = $this->createElection();
    $this->createAreaVote($election, 'Scarfolk Central - General Election 2024');

    $new_election = $this->electionDuplicator->duplicateElection($election, 'General Election 2029');
    $new_areas = $this->loadAreaVotesForElection($new_election);
    $new_area = reset($new_areas);

    $this->assertEquals('Scarfolk Central - General Election 2029', $new_area->getTitle());
  }

  /**
   * Test duplicated entities are unpublished.
   */
  public function testDuplicatedEntitiesUnpublished(): void {
    $election = $this->createElection('General Election 2024', TRUE);
    $this->createAreaVote($election, 'Scarfolk Central', ['status' => TRUE]);

    $new_election = $this->electionDuplicator->duplicateElection($election, 'General Election 2029');
    $new_areas = $this->loadAreaVotesForElection($new_election);
    $new_area = reset($new_areas);

    $this->assertFalse($new_election->isPublished(), 'Duplicated election should be unpublished');
    $this->assertFalse($new_area->isPublished(), 'Duplicated area should be unpublished');
  }

  /**
   * Test invalid argument exception for non-election node.
   */
  public function testInvalidArgumentExceptionForNonElectionNode(): void {
    $election = $this->createElection();
    $area = $this->createAreaVote($election, 'Test Area');

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Node must be a localgov_election.');

    $this->electionDuplicator->duplicateElection($area, 'New Title');
  }

  /**
   * Test area vote with no candidates handles gracefully.
   */
  public function testAreaVoteWithNoCandidates(): void {
    $election = $this->createElection();
    $seat = $this->createSeat('Seat 1');

    $this->createAreaVote($election, 'Scarfolk Central', [
      'localgov_election_seats' => [$seat],
    ]);

    $new_election = $this->electionDuplicator->duplicateElection($election, 'General Election 2029');
    $new_areas = $this->loadAreaVotesForElection($new_election);

    $this->assertCount(1, $new_areas);
    $new_area = reset($new_areas);
    $this->assertTrue($new_area->get('localgov_election_candidates')->isEmpty());
  }

}
