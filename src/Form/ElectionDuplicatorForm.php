<?php

declare(strict_types=1);

namespace Drupal\localgov_elections\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\localgov_elections\Service\ElectionDuplicator;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to duplicate an election with a new title.
 */
final class ElectionDuplicatorForm extends FormBase {

  /**
   * The election duplicator service.
   *
   * @var \Drupal\localgov_elections\Service\ElectionDuplicator
   */
  protected ElectionDuplicator $duplicator;

  /**
   * Constructs the form object.
   *
   * @param \Drupal\localgov_elections\Service\ElectionDuplicator $duplicator
   *   The election duplicator service.
   */
  public function __construct(ElectionDuplicator $duplicator) {
    $this->duplicator = $duplicator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('localgov_elections.election_duplicator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'localgov_elections_election_duplicate';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node || $node->bundle() !== 'localgov_election') {
      $this->messenger()->addError($this->t('Only election nodes can be duplicated.'));
      return $form;
    }

    $form['#election'] = $node;

    $form['original_title'] = [
      '#type' => 'item',
      '#title' => $this->t('Original election'),
      '#markup' => $node->label(),
    ];

    $form['new_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New election title'),
      '#required' => TRUE,
      '#default_value' => $node->label() . ' (Copy)',
      '#maxlength' => 255,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Duplicate'),
      ],
      'cancel' => [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => $node->toUrl(),
        '#attributes' => ['class' => ['button']],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $election = $form['#election'];
    $new_title = $form_state->getValue('new_title');

    try {
      $new_election = $this->duplicator->duplicateElection($election, $new_title);
      $this->messenger()->addStatus($this->t('Election "@original" has been duplicated as "@new".', [
        '@original' => $election->label(),
        '@new' => $new_title,
      ]));
      $form_state->setRedirect('entity.node.edit_form', ['node' => $new_election->id()]);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Duplication failed: @message', ['@message' => $e->getMessage()]));
      $form_state->setRedirect('entity.node.canonical', ['node' => $election->id()]);
    }
  }

}
