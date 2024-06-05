<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_reporting\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\localgov_elections_reporting\BoundaryProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Boundary Provider Add Form.
 */
class BounaryProviderAddForm extends FormBase {

  /**
   * The boundary provider plugin manager.
   *
   * @var \Drupal\localgov_elections_reporting\BoundaryProviderPluginManager
   */
  protected $pluginManager;

  /**
   * Constructs a new GeocoderProviderCreationForm.
   *
   * @param \Drupal\localgov_elections_reporting\BoundaryProviderPluginManager $plugin_manager
   *   The boundary provider plugin manager.
   */
  public function __construct(BoundaryProviderPluginManager $plugin_manager) {
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('plugin.manager.boundary_provider')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'localgov_elections_reporting_boundary_provider_add';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $providers = [];
    foreach ($this->pluginManager->getDefinitions() as $id => $definition) {
      $providers[$id] = $definition['label'];
    }

    $form['container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline']],
      '#open' => TRUE,
    ];

    $form['container']['header'] = [
      '#markup' => $this->t("<h3>Add a Provider</h3>"),
    ];

    if (empty($providers)) {
      $form['container']['no_providers'] = [
        '#markup' => $this->t("<p>You do not appear to have any enabled providers. Make sure to enable them first.</p>"),
      ];
      return $form;
    }

    $form['container']['provider'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Provider'),
      '#title_display' => 'invisible',
      '#options' => $providers,
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['container']['actions'] = [
      '#type' => 'actions',
    ];

    $form['container']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if (!$form_state->getValue('provider')) {
      $form_state->setErrorByName('provider', $this->t('You need to select a plugin provider before you can add one.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('provider')) {
      $form_state->setRedirect(
          'entity.boundary_source.add_form',
          ['plugin_id' => $form_state->getValue('provider')]
      );
    }
  }

}
