<?php

declare(strict_types=1);

namespace Drupal\Tests\localgov_elections\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\NodeInterface;

/**
 * Tests results heading text on the summary page.
 *
 * The results heading text differs based on election types.
 *
 * @group localgov_elections
 */
class ElectionSummaryTest extends BrowserTestBase {

  /**
   * Tests result heading text.
   */
  public function testResultHeadingOnSummaryPage() {

    $election_page_url = $this->electionPage->toUrl();

    foreach (static::EXPECTED_RESULT_HEADINGS as $election_type => $expected_result_heading_text) {
      $this->electionPage->set('localgov_election_type', $election_type)->save();

      $this->drupalGet($election_page_url);
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->pageTextContains($expected_result_heading_text);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Sets up an Election page and attaches an Area to it.  No election type is
   * set at this stage.
   */
  protected function setUp(): void {

    parent::setUp();

    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    $this->electionPage = $node_storage->create([
      'title' => "UK Election 2024",
      'status' => TRUE,
      'type' => "localgov_election",
    ]);
    $this->electionPage->save();

    $this->area = $node_storage->create([
      'title'                           => "An area",
      'status'                          => TRUE,
      'type'                            => "localgov_area_vote",
      'localgov_election'               => $this->electionPage->id(),
      'localgov_election_votes_final'   => TRUE,
      'localgov_election_boundary_data' => json_encode([
        'type'        => 'Point',
        'coordinates' => [1.234567, 2.345678],
      ]),
    ]);
    $this->area->save();
  }

  /**
   * Mapping between election types and their corresponding result headings.
   *
   * @see field.storage.node.localgov_election_type.yml
   */
  const EXPECTED_RESULT_HEADINGS = [
    ''                      => 'Results',
    'Parish'                => 'Parish results',
    'DistrictAndBorough'    => 'District and Borough results',
    'County'                => 'County results',
    'Mayoral'               => 'Mayoral results',
    'NationalParliamentary' => 'Constituency results',
    'EuropeanParliamentary' => 'European Parliamentary results',
  ];

  /**
   * Drupal theme used during test runs.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to activate.
   *
   * @var array
   */
  protected static $modules = [
    'localgov_elections',
    'localgov_elections_uk_parties',
  ];

  /**
   * An election node.
   *
   * @var Drupal\node\NodeInterface
   */
  protected NodeInterface $electionPage;

  /**
   * An Area node.
   *
   * @var Drupal\node\NodeInterface
   */
  protected NodeInterface $area;

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  protected $strictConfigSchema = FALSE;
  // @codingStandardsIgnoreEnd

}
