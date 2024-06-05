<?php declare(strict_types=1);

namespace Drupal\localgov_elections_reporting\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\localgov_elections_reporting\BoundaryProviderInterface;
use Drupal\localgov_elections_reporting\BoundaryProviderPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Boundary Source form.
 */
class BoundarySourceForm extends EntityForm
{

  /**
   * The plugin form factory service.
   *
   * @var PluginFormFactoryInterface
   */
  private PluginFormFactoryInterface $formFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
        $container->get('plugin_form.factory'),
        $container->get('entity_type.manager')
    );
  }

  /**
   * Constructs the boundary source form.
   *
   * @param PluginFormFactoryInterface $formFactory
   *   The plugin form factory service.
   * @param EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(PluginFormFactoryInterface $formFactory, EntityTypeManagerInterface $entityTypeManager)
  {
    $this->formFactory = $formFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array
  {

    $form = parent::form($form, $form_state);

    $form['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#maxlength' => 255,
        '#default_value' => $this->entity->label(),
        '#required' => TRUE,
    ];

    // @todo don't know if a description field is actually useful. Should it be removed?
    $form['description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#default_value' => $this->entity->get('description'),
    ];

    $form['id'] = [
        '#type' => 'machine_name',
        '#default_value' => $this->entity->id(),
        '#machine_name' => [
            'exists' => [$this, 'exist'],
        ],
        '#disabled' => !$this->entity->isNew(),
    ];

    $form['status'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#default_value' => $this->entity->status(),
    ];

    // Add the plugin subform.
    $form['#tree'] = TRUE;
    $form['settings'] = [];
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $form['settings'] = $this->getPluginForm($this->entity->getPlugin())->buildConfigurationForm($form['settings'], $subform_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int
  {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
        match ($result) {
          \SAVED_NEW => $this->t('Created %label.', $message_args),
          \SAVED_UPDATED => $this->t('Updated %label.', $message_args),
        }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  /**
   * Utility to get the form.
   *
   * @param BoundaryProviderInterface $item
   *   The boundary provider item.
   * @return \Drupal\Core\Plugin\PluginFormInterface|BoundaryProviderInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getPluginForm(BoundaryProviderInterface $item)
  {
    if ($item instanceof PluginWithFormsInterface) {
      return $this->formFactory->createInstance($item, 'configure');
    }
    return $item;
  }

  /**
   * Helper function, checks whether an EventSource configuration entity exists.
   */
  public function exist($id)
  {
    $entity = $this->entityTypeManager->getStorage('boundary_source')->getQuery()
        ->accessCheck(FALSE)
        ->condition('id', $id)
        ->execute();
    return (bool)$entity;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $plugin = $this->getEntity()->getPlugin();
    if (!is_subclass_of($plugin, BoundaryProviderPluginBase::class)){
      $form_state->setErrorByName('', "Plugin not subclass of BoundaryProviderPluginBase");
      return;
    }
    $this->getPluginForm($plugin)->validateConfigurationForm($form['settings'], SubformState::createForSubform($form['settings'], $form, $form_state));
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    parent::submitForm($form, $form_state);
    $this->getPluginForm($this->entity->getPlugin())->submitConfigurationForm($form['settings'], SubformState::createForSubform($form['settings'], $form, $form_state));
  }


}
