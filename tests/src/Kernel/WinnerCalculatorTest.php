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
   * Test area vote with no seats returns empty array.
   */
  public function testAreaVoteWithNoSeatsReturnsEmpty(): void {
    $area_vote = Node::create([
      'type' => 'localgov_area_vote',
      'title' => 'Test Area',
    ]);
    $area_vote->save();

    $winners = $this->winnerCalculator->calculateWinners($area_vote);
    $this->assertEmpty($winners);
  }

  /**
   * Test single seat election with clear winner.
   */
  public function testSingleSeatElectionClearWinner(): void {
    $candidate1 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 100,
    ]);
    $candidate1->save();

    $candidate2 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 200,
    ]);
    $candidate2->save();

    $candidate3 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 150,
    ]);
    $candidate3->save();

    $seat = Paragraph::create([
      'type' => 'localgov_area_seat',
      'localgov_seat' => 'Seat 1',
      'localgov_seat_not_contested' => FALSE,
    ]);
    $seat->save();

    $area_vote = Node::create([
      'type' => 'localgov_area_vote',
      'title' => 'Test Area',
      'localgov_election_seats' => [$seat],
      'localgov_election_candidates' => [
        $candidate1,
        $candidate2,
        $candidate3,
      ],
      'localgov_election_no_contest' => FALSE,
    ]);
    $area_vote->save();

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(1, $winners);
    $this->assertEquals($candidate2->id(), $winners[0]->id());
  }

  /**
   * Test tie-breaking uses candidate field order.
   */
  public function testTieBreakingUsesCandidateFieldOrder(): void {
    $candidate1 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 100,
    ]);
    $candidate1->save();

    $candidate2 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 100,
    ]);
    $candidate2->save();

    $candidate3 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 100,
    ]);
    $candidate3->save();

    $seat = Paragraph::create([
      'type' => 'localgov_area_seat',
      'localgov_seat' => 'Seat 1',
      'localgov_seat_not_contested' => FALSE,
    ]);
    $seat->save();

    $area_vote = Node::create([
      'type' => 'localgov_area_vote',
      'title' => 'Tied Area',
      'localgov_election_seats' => [$seat],
      'localgov_election_candidates' => [
        $candidate1,
        $candidate2,
        $candidate3,
      ],
      'localgov_election_no_contest' => FALSE,
    ]);
    $area_vote->save();

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
    $vote_counts = [300, 250, 200, 150, 100];

    foreach ($vote_counts as $votes) {
      $candidate = Paragraph::create([
        'type' => 'localgov_election_candidate',
        'localgov_election_votes' => $votes,
      ]);
      $candidate->save();
      $candidates[] = $candidate;
    }

    $seats = [];
    for ($i = 0; $i < 3; $i++) {
      $seat = Paragraph::create([
        'type' => 'localgov_area_seat',
        'localgov_seat' => 'Seat ' . ($i + 1),
        'localgov_seat_not_contested' => FALSE,
      ]);
      $seat->save();
      $seats[] = $seat;
    }

    $area_vote = Node::create([
      'type' => 'localgov_area_vote',
      'title' => 'Multi-Seat Area',
      'localgov_election_seats' => $seats,
      'localgov_election_candidates' => $candidates,
      'localgov_election_no_contest' => FALSE,
    ]);
    $area_vote->save();

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
    $candidate1 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 300,
    ]);
    $candidate1->save();

    $candidate2 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 200,
    ]);
    $candidate2->save();

    // These two tied for the last seat.
    $candidate3 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 150,
    ]);
    $candidate3->save();

    $candidate4 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 150,
    ]);
    $candidate4->save();

    $seats = [];
    for ($i = 0; $i < 3; $i++) {
      $seat = Paragraph::create([
        'type' => 'localgov_area_seat',
        'localgov_seat' => 'Seat ' . ($i + 1),
        'localgov_seat_not_contested' => FALSE,
      ]);
      $seat->save();
      $seats[] = $seat;
    }

    $area_vote = Node::create([
      'type' => 'localgov_area_vote',
      'title' => 'Tied Boundary Area',
      'localgov_election_seats' => $seats,
      'localgov_election_candidates' => [
        $candidate1,
        $candidate2,
        $candidate3,
        $candidate4,
      ],
      'localgov_election_no_contest' => FALSE,
    ]);
    $area_vote->save();

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
    $candidate1 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 100,
    ]);
    $candidate1->save();

    $candidate2 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 50,
    ]);
    $candidate2->save();

    $seats = [];
    for ($i = 0; $i < 3; $i++) {
      $seat = Paragraph::create([
        'type' => 'localgov_area_seat',
        'localgov_seat' => 'Seat ' . ($i + 1),
        'localgov_seat_not_contested' => FALSE,
      ]);
      $seat->save();
      $seats[] = $seat;
    }

    $area_vote = Node::create([
      'type' => 'localgov_area_vote',
      'title' => 'More Seats Area',
      'localgov_election_seats' => $seats,
      'localgov_election_candidates' => [$candidate1, $candidate2],
      'localgov_election_no_contest' => FALSE,
    ]);
    $area_vote->save();

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(2, $winners);
  }

  /**
   * Test uncontested seat returns uncontested candidate.
   */
  public function testUncontestedSeatReturnsUncontestedCandidate(): void {
    $uncontested_candidate = Paragraph::create([
      'type' => 'localgov_election_candidate',
    ]);
    $uncontested_candidate->save();

    $seat = Paragraph::create([
      'type' => 'localgov_area_seat',
      'localgov_seat' => 'Seat 1',
      'localgov_seat_not_contested' => TRUE,
      'localgov_candidate_uncontested' => $uncontested_candidate,
    ]);
    $seat->save();

    $area_vote = Node::create([
      'type' => 'localgov_area_vote',
      'title' => 'Uncontested Area',
      'localgov_election_seats' => [$seat],
      'localgov_election_no_contest' => FALSE,
    ]);
    $area_vote->save();

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(1, $winners);
    $this->assertEquals($uncontested_candidate->id(), $winners[0]->id());
  }

  /**
   * Test mixed contested and uncontested seats.
   */
  public function testMixedContestedAndUncontestedSeats(): void {
    $candidate1 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 200,
    ]);
    $candidate1->save();

    $candidate2 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 150,
    ]);
    $candidate2->save();

    $uncontested_candidate = Paragraph::create([
      'type' => 'localgov_election_candidate',
    ]);
    $uncontested_candidate->save();

    $contested_seat = Paragraph::create([
      'type' => 'localgov_area_seat',
      'localgov_seat' => 'Seat 1',
      'localgov_seat_not_contested' => FALSE,
    ]);
    $contested_seat->save();

    $uncontested_seat = Paragraph::create([
      'type' => 'localgov_area_seat',
      'localgov_seat' => 'Seat 2',
      'localgov_seat_not_contested' => TRUE,
      'localgov_candidate_uncontested' => $uncontested_candidate,
    ]);
    $uncontested_seat->save();

    $area_vote = Node::create([
      'type' => 'localgov_area_vote',
      'title' => 'Mixed Area',
      'localgov_election_seats' => [$contested_seat, $uncontested_seat],
      'localgov_election_candidates' => [$candidate1, $candidate2],
      'localgov_election_no_contest' => FALSE,
    ]);
    $area_vote->save();

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(2, $winners);
    $this->assertEquals($candidate1->id(), $winners[0]->id());
    $this->assertEquals($uncontested_candidate->id(), $winners[1]->id());
  }

  /**
   * Test entire area marked as uncontested.
   */
  public function testEntireAreaUncontested(): void {
    $uncontested_candidate = Paragraph::create([
      'type' => 'localgov_election_candidate',
    ]);
    $uncontested_candidate->save();

    $seat = Paragraph::create([
      'type' => 'localgov_area_seat',
      'localgov_seat' => 'Seat 1',
      'localgov_seat_not_contested' => TRUE,
      'localgov_candidate_uncontested' => $uncontested_candidate,
    ]);
    $seat->save();

    $area_vote = Node::create([
      'type' => 'localgov_area_vote',
      'title' => 'Fully Uncontested Area',
      'localgov_election_seats' => [$seat],
      'localgov_election_no_contest' => TRUE,
    ]);
    $area_vote->save();

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(1, $winners);
    $this->assertEquals($uncontested_candidate->id(), $winners[0]->id());
  }

  /**
   * Test area with no candidates returns empty.
   */
  public function testAreaWithNoCandidatesReturnsEmpty(): void {
    $seat = Paragraph::create([
      'type' => 'localgov_area_seat',
      'localgov_seat' => 'Seat 1',
      'localgov_seat_not_contested' => FALSE,
    ]);
    $seat->save();

    $area_vote = Node::create([
      'type' => 'localgov_area_vote',
      'title' => 'No Candidates Area',
      'localgov_election_seats' => [$seat],
      'localgov_election_no_contest' => FALSE,
    ]);
    $area_vote->save();

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertEmpty($winners);
  }

  /**
   * Test candidates with zero votes are handled correctly.
   */
  public function testCandidatesWithZeroVotes(): void {
    $candidate1 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 0,
    ]);
    $candidate1->save();

    $candidate2 = Paragraph::create([
      'type' => 'localgov_election_candidate',
      'localgov_election_votes' => 1,
    ]);
    $candidate2->save();

    $seat = Paragraph::create([
      'type' => 'localgov_area_seat',
      'localgov_seat' => 'Seat 1',
      'localgov_seat_not_contested' => FALSE,
    ]);
    $seat->save();

    $area_vote = Node::create([
      'type' => 'localgov_area_vote',
      'title' => 'Zero Votes Area',
      'localgov_election_seats' => [$seat],
      'localgov_election_candidates' => [$candidate1, $candidate2],
      'localgov_election_no_contest' => FALSE,
    ]);
    $area_vote->save();

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(1, $winners);
    $this->assertEquals($candidate2->id(), $winners[0]->id());
  }

  /**
   * Test multiple uncontested seats.
   */
  public function testMultipleUncontestedSeats(): void {
    $uncontested1 = Paragraph::create([
      'type' => 'localgov_election_candidate',
    ]);
    $uncontested1->save();

    $uncontested2 = Paragraph::create([
      'type' => 'localgov_election_candidate',
    ]);
    $uncontested2->save();

    $seat1 = Paragraph::create([
      'type' => 'localgov_area_seat',
      'localgov_seat' => 'Seat 1',
      'localgov_seat_not_contested' => TRUE,
      'localgov_candidate_uncontested' => $uncontested1,
    ]);
    $seat1->save();

    $seat2 = Paragraph::create([
      'type' => 'localgov_area_seat',
      'localgov_seat' => 'Seat 2',
      'localgov_seat_not_contested' => TRUE,
      'localgov_candidate_uncontested' => $uncontested2,
    ]);
    $seat2->save();

    $area_vote = Node::create([
      'type' => 'localgov_area_vote',
      'title' => 'Multiple Uncontested Area',
      'localgov_election_seats' => [$seat1, $seat2],
      'localgov_election_no_contest' => FALSE,
    ]);
    $area_vote->save();

    $winners = $this->winnerCalculator->calculateWinners($area_vote);

    $this->assertCount(2, $winners);
    $this->assertEquals($uncontested1->id(), $winners[0]->id());
    $this->assertEquals($uncontested2->id(), $winners[1]->id());
  }

}
