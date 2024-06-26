<?php

declare(strict_types=1);

namespace Drupal\localgov_elections\Plugin\views\area;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views area plugin for election results heading.
 *
 * Sets dynamic heading depending on election type.
 *
 * @ViewsArea("localgov_elections_results_heading")
 */
class ResultsHeading extends AreaPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {

    $election_node_id = current($this->view->args);
    $election_node    = $this->entityTypeManager->getStorage('node')->load($election_node_id);

    if (!($election_node instanceof NodeInterface)) {
      return [];
    }

    if (!$election_node->hasField('localgov_election_type')) {
      return [];
    }

    $election_type = $election_node->localgov_election_type->first();
    $election_type_label = $election_type ? $election_type->view() : '';

    return [
      '#theme'         => 'localgov_elections_results_heading',
      '#election_type' => $election_type_label,
    ];
  }

  /**
   * Constructs a new plugin instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

}
