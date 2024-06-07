<?php

namespace Drupal\localgov_elections_reporting\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an electionmenu block.
 *
 * @Block(
 *   id = "localgov_elections_reporting_electionmenu",
 *   admin_label = @Translation("Election Menu"),
 *   category = @Translation("Custom")
 * )
 */
class ElectionmenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The election node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new ElectionmenuBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
        $container->get('entity_type.manager')
    );
  }

  /**
   * Get links for the block.
   *
   * @return array
   *   An array of links.
   */
  private function getLinks(NodeInterface $node): array {
    $urls = [];
    $urls[] = [
      'attributes' => new Attribute(),
      'link' => Link::fromTextAndUrl($this->t('Summary'), Url::fromRoute('entity.node.canonical', ['node' => $this->node->id()])),
    ];

    // Allow editors to hide the map
    // It certainly won't work when we allow multiple winners / seats.
    if ($node->hasField('field_display_map')) {
      $display_map = $node->get('field_display_map')?->value;
    }
    else {
      $display_map = FALSE;
    }
    // Check that there is geo data to display.
    $results = \Drupal::entityQuery('node')
      ->condition('type', 'division_vote')
      ->condition('field_election', $node->id())
      ->exists('field_boundary_data')
      ->accessCheck(FALSE)
      ->execute();
    // If map to be displayed and there is geo data show the link.
    if ($display_map == "1" && $results) {
      $urls[] = [
        'attributes' => new Attribute(),
        'link' => Link::fromTextAndUrl($this->t('Electoral map'), Url::fromRoute('view.electoral_map.page_1', ['node' => $this->node->id()])),
      ];
    }

    // Should next 2 links should be displayed i.e. there are finalised votes.
    $results = \Drupal::entityQuery('node')
      ->condition('type', 'division_vote')
      ->condition('field_election', $node->id())
      ->condition('field_votes_finalised', TRUE)
      ->accessCheck(FALSE)
      ->execute();
    if ($results) {
      $urls[] = [
        'attributes' => new Attribute(),
        'link' => Link::fromTextAndUrl($this->t('Results timeline'), Url::fromRoute('view.election_results_timeline.page_1', ['node' => $this->node->id()])),
      ];
      $urls[] = [
        'attributes' => new Attribute(),
        'link' => Link::fromTextAndUrl($this->t('Share of the vote'), Url::fromRoute('view.election_results_vot.page_1', ['node' => $this->node->id()])),
      ];
    }

    // Work out if next link should be displayed i.e. there are PDFs uploaded.
    $results = \Drupal::entityQuery('node')
      ->condition('type', 'division_vote')
      ->condition('field_election', $node->id())
      ->exists('field_candidates_file')
      ->accessCheck(FALSE)
      ->execute();
    if ($results) {
      $urls[] = [
        'attributes' => new Attribute(),
        'link' => Link::fromTextAndUrl($this->t('Electoral candidates'), Url::fromRoute('view.electoral_candidates.page_1', ['node' => $this->node->id()])),
      ];
    }
    return $urls;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];
    $node = $this->routeMatch->getParameter('node');

    if (!($node instanceof NodeInterface)) {
      if (is_int(intval($node))) {
        $node = $this->entityTypeManager->getStorage('node')->load((intval($node)));
      }
    }
    if ($node instanceof NodeInterface) {
      if ($node->bundle() == 'division_vote') {
        $node_ref = $node->field_election?->first()->getValue()['target_id'];
        if ($node_ref) {
          $node = $this->entityTypeManager->getStorage('node')->load((intval($node_ref)));
        }
        // Should never reach this but return nothing if we do.
        else {
          return [];
        }
      }

      $this->node = $node;
      $build['#theme'] = 'election_menu';
      $build['#cache']['max-age'] = 0;
      $build['#attached']['library'][] = 'localgov_elections_reporting/election_menu';
      $build['#links'] = $this->getLinks($node);
    }

    return $build;
  }

}
