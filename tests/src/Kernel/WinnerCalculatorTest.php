<?php

declare(strict_types=1);

namespace Drupal\Tests\localgov_elections\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests the WinnerCalculator service.
 *
 * @group localgov_elections
 */
class WinnerCalculatorTest extends KernelTestBase {

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
   * The winner calculator service.
   *
   * @var \Drupal\localgov_elections\Service\WinnerCalculator
   */
  protected $winnerCalculator;

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

    $this->winnerCalculator = $this->container->get('localgov_elections.winner_calculator');
  }

  /**
   * Create an area vote node.
   */
  protected function createAreaVote(string $title, array $additional_fields = []): Node {
    $area = Node::create(array_merge([
      'type' => 'localgov_area_vote',
      'title' => $title,
    ], $additional_fields));
    $area->save();
    return $area;
  }

  /**
   * Create a candidate paragraph.
   */
  protected function createCandidate(?int $votes = NULL): Paragraph {
    $fields = [
      'type' => 'localgov_election_candidate',
    ];

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
   * Test area vote with no seats returns empty array.
   */
  public function testAreaVoteWithNoSeatsReturnsEmpty(): void {
    $area_vote = $this->createAreaVote('Test Area');

    $winners = $this->winnerCalculator->calculateWinners($area_vote);
    $this->assertEmpty($winners);
  }

  /**
   * Test single seat election with clear winner.
   */
  public function testSingleSeatElectionClearWinner(): void {
    $candidate1 = $this->createCandidate(100);
    $candidate2 = $this->createCandidate(200);
    $candidate3 = $this->createCandidate(150);
    $seat = $this->createSeat('Seat 1');

    $area_vote = $this->createAreaVote('Test Area', [
      'localgov_election_seats' => [$seat],
      'localgov_election_candidates' => [$candidate1, $candidate2, $candidate3],
      'localgov_election_no_contest' => FALSE,
    ]);

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(1, $winners);
    $this->assertEquals($candidate2->id(), $winners[0]->id());
  }

  /**
   * Test tie-breaking uses candidate field order.
   */
  public function testTieBreakingUsesCandidateFieldOrder(): void {
    $candidate1 = $this->createCandidate(100);
    $candidate2 = $this->createCandidate(100);
    $candidate3 = $this->createCandidate(100);
    $seat = $this->createSeat('Seat 1');

    $area_vote = $this->createAreaVote('Tied Area', [
      'localgov_election_seats' => [$seat],
      'localgov_election_candidates' => [$candidate1, $candidate2, $candidate3],
      'localgov_election_no_contest' => FALSE,
    ]);

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(1, $winners);
    $this->assertEquals($candidate1->id(), $winners[0]->id(),
      'First candidate should win tie due to field order');
  }

  /**
   * Test multi-seat election returns top N candidates.
   */
  public function testMultiSeatElectionReturnsTopCandidates(): void {
    $candidates = [];
    foreach ([300, 250, 200, 150, 100] as $votes) {
      $candidates[] = $this->createCandidate($votes);
    }

    $seats = [];
    for ($i = 0; $i < 3; $i++) {
      $seats[] = $this->createSeat('Seat ' . ($i + 1));
    }

    $area_vote = $this->createAreaVote('Multi-Seat Area', [
      'localgov_election_seats' => $seats,
      'localgov_election_candidates' => $candidates,
      'localgov_election_no_contest' => FALSE,
    ]);

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(3, $winners);
    $this->assertEquals($candidates[0]->id(), $winners[0]->id());
    $this->assertEquals($candidates[1]->id(), $winners[1]->id());
    $this->assertEquals($candidates[2]->id(), $winners[2]->id());
  }

  /**
   * Test multi-seat tie at boundary uses field order.
   */
  public function testMultiSeatTieAtBoundaryUsesFieldOrder(): void {
    $candidate1 = $this->createCandidate(300);
    $candidate2 = $this->createCandidate(200);
    $candidate3 = $this->createCandidate(150);
    $candidate4 = $this->createCandidate(150);

    $seats = [];
    for ($i = 0; $i < 3; $i++) {
      $seats[] = $this->createSeat('Seat ' . ($i + 1));
    }

    $area_vote = $this->createAreaVote('Tied Boundary Area', [
      'localgov_election_seats' => $seats,
      'localgov_election_candidates' => [$candidate1, $candidate2, $candidate3, $candidate4],
      'localgov_election_no_contest' => FALSE,
    ]);

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(3, $winners);
    $this->assertEquals($candidate1->id(), $winners[0]->id());
    $this->assertEquals($candidate2->id(), $winners[1]->id());
    $this->assertEquals($candidate3->id(), $winners[2]->id(),
      'Candidate 3 should win over candidate 4 due to field order');
  }

  /**
   * Test fewer candidates than seats returns all candidates.
   */
  public function testFewerCandidatesThanSeatsReturnsAllCandidates(): void {
    $candidate1 = $this->createCandidate(100);
    $candidate2 = $this->createCandidate(50);

    $seats = [];
    for ($i = 0; $i < 3; $i++) {
      $seats[] = $this->createSeat('Seat ' . ($i + 1));
    }

    $area_vote = $this->createAreaVote('More Seats Area', [
      'localgov_election_seats' => $seats,
      'localgov_election_candidates' => [$candidate1, $candidate2],
      'localgov_election_no_contest' => FALSE,
    ]);

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(2, $winners);
  }

  /**
   * Test uncontested seat returns uncontested candidate.
   */
  public function testUncontestedSeatReturnsUncontestedCandidate(): void {
    $uncontested_candidate = $this->createCandidate();
    $seat = $this->createSeat('Seat 1', FALSE, $uncontested_candidate);

    $area_vote = $this->createAreaVote('Uncontested Area', [
      'localgov_election_seats' => [$seat],
      'localgov_election_no_contest' => FALSE,
    ]);

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(1, $winners);
    $this->assertEquals($uncontested_candidate->id(), $winners[0]->id());
  }

  /**
   * Test mixed contested and uncontested seats.
   */
  public function testMixedContestedAndUncontestedSeats(): void {
    $candidate1 = $this->createCandidate(200);
    $candidate2 = $this->createCandidate(150);
    $uncontested_candidate = $this->createCandidate();

    $contested_seat = $this->createSeat('Seat 1');
    $uncontested_seat = $this->createSeat('Seat 2', FALSE, $uncontested_candidate);

    $area_vote = $this->createAreaVote('Mixed Area', [
      'localgov_election_seats' => [$contested_seat, $uncontested_seat],
      'localgov_election_candidates' => [$candidate1, $candidate2],
      'localgov_election_no_contest' => FALSE,
    ]);

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(2, $winners);
    $this->assertEquals($candidate1->id(), $winners[0]->id());
    $this->assertEquals($uncontested_candidate->id(), $winners[1]->id());
  }

  /**
   * Test entire area marked as uncontested.
   */
  public function testEntireAreaUncontested(): void {
    $uncontested_candidate = $this->createCandidate();
    $seat = $this->createSeat('Seat 1', FALSE, $uncontested_candidate);

    $area_vote = $this->createAreaVote('Fully Uncontested Area', [
      'localgov_election_seats' => [$seat],
      'localgov_election_no_contest' => TRUE,
    ]);

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(1, $winners);
    $this->assertEquals($uncontested_candidate->id(), $winners[0]->id());
  }

  /**
   * Test area with no candidates returns empty.
   */
  public function testAreaWithNoCandidatesReturnsEmpty(): void {
    $seat = $this->createSeat('Seat 1');

    $area_vote = $this->createAreaVote('No Candidates Area', [
      'localgov_election_seats' => [$seat],
      'localgov_election_no_contest' => FALSE,
    ]);

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertEmpty($winners);
  }

  /**
   * Test candidates with zero votes are handled correctly.
   */
  public function testCandidatesWithZeroVotes(): void {
    $candidate1 = $this->createCandidate(0);
    $candidate2 = $this->createCandidate(1);
    $seat = $this->createSeat('Seat 1');

    $area_vote = $this->createAreaVote('Zero Votes Area', [
      'localgov_election_seats' => [$seat],
      'localgov_election_candidates' => [$candidate1, $candidate2],
      'localgov_election_no_contest' => FALSE,
    ]);

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(1, $winners);
    $this->assertEquals($candidate2->id(), $winners[0]->id());
  }

  /**
   * Test multiple uncontested seats.
   */
  public function testMultipleUncontestedSeats(): void {
    $uncontested1 = $this->createCandidate();
    $uncontested2 = $this->createCandidate();

    $seat1 = $this->createSeat('Seat 1', FALSE, $uncontested1);
    $seat2 = $this->createSeat('Seat 2', FALSE, $uncontested2);

    $area_vote = $this->createAreaVote('Multiple Uncontested Area', [
      'localgov_election_seats' => [$seat1, $seat2],
      'localgov_election_no_contest' => FALSE,
    ]);

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(2, $winners);
    $this->assertEquals($uncontested1->id(), $winners[0]->id());
    $this->assertEquals($uncontested2->id(), $winners[1]->id());
  }

}
