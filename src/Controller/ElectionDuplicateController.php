<?php

namespace Drupal\localgov_elections\Controller;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\localgov_elections\Service\ElectionDuplicator;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Duplicate an election node and redirect to the new copy.
 */
class ElectionDuplicateController extends ControllerBase {

  /**
   * The election duplicator service.
   *
   * @var \Drupal\localgov_elections\Service\ElectionDuplicator
   */
  protected ElectionDuplicator $duplicator;

  /**
   * Constructor.
   */
  public function __construct(ElectionDuplicator $duplicator) {
    $this->duplicator = $duplicator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('localgov_elections.election_duplicator')
    );
  }

  /**
   * Access check for duplicating elections.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Election node.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Access check result.
   */
  public function access(NodeInterface $node, AccountInterface $account) {
    if ($node->bundle() !== 'localgov_election') {
      return AccessResult::forbidden();
    }

    return AccessResult::allowedIfHasPermission($account, 'create localgov_election content');
  }

  /**
   * Handle the duplication request.
   */
  public function duplicate(NodeInterface $node): RedirectResponse {
    if ($node->bundle() !== 'localgov_election') {
      $this->messenger()->addError($this->t('Only election nodes can be duplicated.'));
      return new RedirectResponse($node->toUrl()->toString());
    }

    try {
      $new_node = $this->duplicator->duplicateElection($node);
      $this->messenger()->addStatus($this->t('Election "@title" has been duplicated.', [
        '@title' => $node->label(),
      ]));
      return $this->redirect('entity.node.canonical', ['node' => $new_node->id()]);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Duplication failed: @message', ['@message' => $e->getMessage()]));
      return new RedirectResponse($node->toUrl()->toString());
    }
  }

}
