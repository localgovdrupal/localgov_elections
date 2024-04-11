<?php

namespace Drupal\localgov_elections_reporting\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
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
   *
   * @var NodeInterface
   */
  protected NodeInterface $node;

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * Get links for the block.
   *
   * @return array
   */
  private function getLinks(NodeInterface $node): array {
    $urls = [];
    $urls[] = [
      'attributes' => new Attribute(),
      'link' => Link::fromTextAndUrl(t('Summary'), Url::fromRoute('entity.node.canonical', ['node' => $this->node->id()])),
    ];

    // Allow editors to hide the map
    // It certainly won't work when we allow multiple winners / seats
    if ($node->hasField('field_display_map')) {
      $display_map = $node->get('field_display_map')?->value;
    } else {
      $display_map = FALSE;
    }
    if ($display_map == "1") {
      $urls[] = [
          'attributes' => new Attribute(),
          'link' => Link::fromTextAndUrl(t('Electoral map'), Url::fromRoute('view.electoral_map.page_1', ['node' => $this->node->id()])),
      ];
    }

    $urls[] = [
      'attributes' => new Attribute(),
      'link' => Link::fromTextAndUrl(t('Results timeline'), Url::fromRoute('view.election_results_timeline.page_1', ['node' => $this->node->id()])),
    ];
    $urls[] = [
      'attributes' => new Attribute(),
      'link' => Link::fromTextAndUrl(t('Share of the vote'), Url::fromRoute('view.election_results_vot.page_1', ['node' => $this->node->id()])),
    ];
    $urls[] = [
      'attributes' => new Attribute(),
      'link' => Link::fromTextAndUrl(t('Electoral candidates'), Url::fromRoute('view.electoral_candidates.page_1', ['node' => $this->node->id()])),
    ];
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
        $node = Node::load(intval($node));
      }
    }
    if ($node instanceof NodeInterface) {
      if ($node->bundle() == 'division_vote') {
        $node_ref = $node->field_election?->first()->getValue()['target_id'];
        if ($node_ref) {
          $node = Node::load(intval($node_ref));
        }
        // should never reach this but return nothing if we do
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
