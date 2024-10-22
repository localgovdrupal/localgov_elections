<?php

declare(strict_types=1);

namespace Drupal\localgov_elections\Form;

use Drupal\Core\DependencyInjection\ClassResolver;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\localgov_elections\BoundaryProviderPluginManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a boundary fetch form.
 */
final class BoundaryFetchForm extends FormBase {
  /**
   * Boundary source plugin manager.
   *
   * @var \Drupal\localgov_elections\BoundaryProviderPluginManager
   */
  protected BoundaryProviderPluginManager $manager;

  /**
   * Class resolver service.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolver
   */
  protected ClassResolver $classResolver;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * All published boundary sources.
   *
   * @var array|\Drupal\Core\Entity\EntityInterface[]
   */
  protected array $entities;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $request;

  /**
   * Constructs the form object.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolver $classResolver
   *   The class resolver service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The current request.
   */
  public function __construct(ClassResolver $classResolver, EntityTypeManagerInterface $entityTypeManager, RequestStack $request) {

    $this->classResolver = $classResolver;
    $this->entityTypeManager = $entityTypeManager;
    $this->entities = $this->entityTypeManager->getStorage('boundary_source')
      ->loadByProperties(
            [
              'status' => 1,
            ]
        );
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(
        $container->get('class_resolver'),
        $container->get('entity_type.manager'),
        $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'localgov_elections_boundary_fetch';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    // It doesn't make sense to allow non-elections
    // using this form so deny any of the form.
    // @todo would it make sense to redirect somewhere? Or throw an error?
    if ($node->bundle() != 'localgov_election') {
      $this->messenger()->addError("Can only fetch boundary information for elections");
      return $form;
    }

    $opts = [];
    /** @var \Drupal\localgov_elections\Entity\BoundarySource $ent */
    foreach ($this->entities as $key => $ent) {
      $opts[$key] = $ent->label();
    }

    $form['#election'] = $node;

    if (count($opts) > 0) {

      $form['plugin_selection'] = [
        '#type' => 'radios',
        '#multiple' => FALSE,
        '#options' => $opts,
        '#title' => "Boundary Source",
        '#required' => TRUE,
        '#ajax' =>
              [
                'callback' => '::subformCallback',
                'disable-refocus' => FALSE,
                'event' => 'change',
                'method' => 'replace',
                'wrapper' => 'edit-output',
                'progress' => [
                  'type' => 'throbber',
                  'message' => $this->t('Updating...'),
                ],
              ],
      ];

      $form['actions'] = [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Fetch'),
        ],
      ];

      $form["plugin"] = ['#tree' => TRUE, 'config' => []];
      $form['plugin']['#prefix'] = '<div id="edit-output">';
      $form['plugin']['#suffix'] = '</div>';

      $plugin_opt = $form_state->getValue('plugin_selection');
      if (count($opts) == 1) {
        $keys = array_keys($opts);
        $form['plugin_selection']['#default_value'] = reset($keys);
        $plugin_opt = reset($keys);
      }
      if ($plugin_opt) {
        $subform = $this->getFormClassForPlugin($plugin_opt);
        if ($subform) {
          $form['plugin_configuration'] = [];
          $subform_state = SubformState::createForSubform($form['plugin_configuration'], $form, $form_state);
          $form['plugin']['config'] = $subform->buildConfigurationForm([], $subform_state);
          $form['plugin']['config']["#tree"] = TRUE;
        }
        else {
          $form['plugin']['config'] = [];
        }
      }
    }
    else {
      $form['no_plugins'] = ['#markup' => $this->t("You appear to have not yet added any boundary providers. You need to do this before trying to download any area boundaries.")];
    }
    return $form;
  }

  /**
   * Utility to get the form class of an entity.
   *
   * @param string $entity
   *   The plugin.
   *
   * @return mixed
   *   The form.
   */
  protected function getFormClassForPlugin($entity) {
    $plugin = $this->entities[$entity]->getPlugin();
    $definition = $plugin->getPluginDefinition();

    if (!$definition) {
      return NULL;
    }

    if (isset($definition['form']['download'])) {
      $definition = $definition['form']['download'];
    }
    else {
      return NULL;
    }

    /** @var BoundaryProviderSubformInterface $object */
    $object = $this->classResolver->getInstanceFromDefinition($definition);
    $object->setPlugin($plugin);
    return $object;
  }

  /**
   * Ajax callback.
   *
   * Ajax callback for plugin subform.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The plugin form.
   */
  public function subformCallback(&$form, FormStateInterface $form_state): array {
    return $form['plugin'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $id = $form_state->getValue('plugin_selection');
    if ($id && $plugin_form = $this->getFormClassForPlugin($id)) {
      $subform_state = SubformState::createForSubform($form['plugin']['config'], $form, $form_state);
      $plugin_form->validateConfigurationForm($form['plugin']['config'], $subform_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = $form_state->getValue('plugin_selection');
    if ($id && $plugin_form = $this->getFormClassForPlugin($id)) {
      $subform_state = SubformState::createForSubform($form['plugin']['config'], $form, $form_state);
      $plugin_form->submitConfigurationForm($form['plugin']['config'], $subform_state);
    }

    $election = $form['#election'];

    /** @var \Drupal\localgov_elections\BoundaryProviderInterface $plugin */
    $plugin = $this->entities[$id]->getPlugin();
    $form_state->setValue('localgov_election', $election->id());
    $plugin->createBoundaries($this->entities[$id], $form_state->getValues());
    $form_state->setRedirect('entity.node.canonical', ['node' => $form_state->getValue('localgov_election')]);
  }

}
