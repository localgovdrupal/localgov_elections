<?php

declare(strict_types=1);

namespace Drupal\Tests\localgov_elections\Functional;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\node\NodeStorageInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests alias functionality for Election nodes.
 *
 * @group localgov_elections
 */
final class ElectionAliasTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'localgov_base';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['localgov_elections'];


  /**
   * Node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected NodeStorageInterface $nodeStorage;


  /**
   * An election node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $election;

  /**
   * An area vote node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $areaVote;

  /**
   * The alias manager service.
   *
   * @var \Drupal\path_alias\AliasManager
   */
  protected $aliasManager;

  /**
   * An array of aliases an internal paths for election pages.
   *
   * @var array
   */
  protected array $aliasPaths = [];

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  protected $strictConfigSchema = FALSE;
  // @codingStandardsIgnoreEnd

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->nodeStorage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->aliasManager = $this->container->get('path_alias.manager');

    $this->election = $this->nodeStorage->create([
      'title' => "UK Election 2024",
      'status' => TRUE,
      'type' => "localgov_election",
    ]);

    $this->election->save();

    $file = File::create([
      'uri' => 'public://empty_test.pdf',
      'status' => 1,
    ]);
    $file->save();

    $this->area_vote = $this->nodeStorage->create([
      'title' => "An area",
      'status' => TRUE,
      'type' => "localgov_area_vote",
      'localgov_election' => $this->election->id(),
      'localgov_election_votes_final' => TRUE,
      'localgov_election_cand_file' => $file->id(),
      'localgov_election_boundary_data' => json_encode([
        'type' => 'Point',
        'coordinates' => [1.234567, 2.345678],
      ]),
    ]);
    $this->area_vote->save();

    // Map page.
    $map_page_url = Url::fromRoute('view.localgov_election_electoral_map.page_1',
        [
          'node' => $this->election->id(),
        ]
    );
    $map_path = $map_page_url->toString();
    $this->aliasPaths['map'] = ['internal' => $map_page_url->getInternalPath(), 'alias' => $map_path];

    // Results timeline.
    $results_page_url = Url::fromRoute('view.localgov_election_results_timeline.page_1',
        [
          'node' => $this->election->id(),
        ]
    );
    $results_path = $results_page_url->toString();
    $this->aliasPaths['results'] = ['internal' => $results_page_url->getInternalPath(), 'alias' => $results_path];

    // Vote share.
    $share_page_url = Url::fromRoute('view.localgov_election_results_vote.page_1',
        [
          'node' => $this->election->id(),
        ]
    );
    $share_path = $share_page_url->toString();
    $this->aliasPaths['share'] = ['internal' => $share_page_url->getInternalPath(), 'alias' => $share_path];

    // Electoral candidates.
    $candidate_page_url = Url::fromRoute('view.localgov_electoral_candidates.page_1',
        [
          'node' => $this->election->id(),
        ]
    );
    $candidate_path = $candidate_page_url->toString();
    $this->aliasPaths['candidate'] = ['internal' => $candidate_page_url->getInternalPath(), 'alias' => $candidate_path];

  }

  /**
   * Test that aliases are generated and are not the internal paths.
   */
  public function testElectionAliasesAreGenerated(): void {
    $this->assertNotEquals($this->aliasPaths['map']['alias'], $this->aliasPaths['map']['internal']);
    $this->assertNotEquals($this->aliasPaths['results']['alias'], $this->aliasPaths['results']['internal']);
    $this->assertNotEquals($this->aliasPaths['share']['alias'], $this->aliasPaths['share']['internal']);
    $this->assertNotEquals($this->aliasPaths['candidate']['alias'], $this->aliasPaths['candidate']['internal']);
  }

  /**
   * Test that electoral aliases match our expected patterns.
   */
  public function testElectionAliasPatterns(): void {
    $this->assertTrue($this->matchesPattern(
        $this->aliasPaths['map']['alias'],
        "#^/election/[^/]+/electoral-map$#"));
    $this->assertTrue($this->matchesPattern(
        $this->aliasPaths['results']['alias'],
        "#^/election/[^/]+/results$#"));
    $this->assertTrue($this->matchesPattern(
        $this->aliasPaths['share']['alias'],
        "#^/election/[^/]+/share$#"));
    $this->assertTrue($this->matchesPattern(
        $this->aliasPaths['candidate']['alias'],
        "#^/election/[^/]+/candidates$#"));
  }

  /**
   * Check if a string matches a pattern.
   */
  private function matchesPattern($string, $pattern): bool {

    return preg_match($pattern, $string) === 1;
  }

  /**
   * Test election sub-page aliases change after election alias changes.
   */
  public function testSubPageAliasesChangeAfterElectionAliasChange() {

    // Get the current paths to compare later.
    $election_path = $this->election->toUrl()->toString();
    $old_map_alias = $this->aliasPaths['map']['alias'];
    $old_results_alias = $this->aliasPaths['results']['alias'];
    $old_share_alias = $this->aliasPaths['share']['alias'];
    $old_candidate_alias = $this->aliasPaths['candidate']['alias'];

    // Change the election alias.
    $alias_query = $this->container->get('entity_type.manager')->getStorage('path_alias')->getQuery();
    $alias_query->condition('alias', $election_path);
    $alias_query->accessCheck(FALSE);
    $alias = $alias_query->execute();
    $alias = reset($alias);
    $alias = $this->container->get('entity_type.manager')->getStorage('path_alias')->load($alias);
    /** @var \Drupal\path_alias\Entity\PathAlias $alias */
    $alias->set('alias', '/election/new-election-name');
    $alias->save();

    // Get the newly generated aliases.
    $map_page_url = Url::fromRoute('view.localgov_election_electoral_map.page_1',
        [
          'node' => $this->election->id(),
        ]
    );

    $results_page_url = Url::fromRoute('view.localgov_election_results_timeline.page_1',
        [
          'node' => $this->election->id(),
        ]
    );

    $share_page_url = Url::fromRoute('view.localgov_election_results_vote.page_1',
        [
          'node' => $this->election->id(),
        ]
    );

    $candidate_page_url = Url::fromRoute('view.localgov_electoral_candidates.page_1',
        [
          'node' => $this->election->id(),
        ]
    );

    // Check they no longer match.
    $this->assertNotEquals($map_page_url, $old_map_alias);
    $this->assertNotEquals($results_page_url, $old_results_alias);
    $this->assertNotEquals($share_page_url, $old_share_alias);
    $this->assertNotEquals($candidate_page_url, $old_candidate_alias);
  }

}
