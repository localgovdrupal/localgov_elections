<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_configurable_provider\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for importing boundary source configurations.
 */
class BoundarySourceImportForm extends FormBase {

  /**
   * Constructs the import form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'localgov_elections_boundary_source_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $step = $form_state->get('step') ?? 1;

    if ($step === 1) {
      return $this->buildStep1($form, $form_state);
    }

    return $this->buildStep2($form, $form_state);
  }

  /**
   * Build step 1: YAML input.
   */
  protected function buildStep1(array $form, FormStateInterface $form_state): array {
    $form['upload'] = [
      '#type' => 'file',
      '#title' => $this->t('Upload configuration file'),
      '#description' => $this->t('Upload an exported .yml file.'),
    ];

    $form['or'] = [
      '#type' => 'markup',
      '#markup' => '<p><strong>' . $this->t('— or —') . '</strong></p>',
    ];

    $form['yaml'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Paste configuration YAML'),
      '#description' => $this->t('Paste the exported boundary source YAML configuration here.'),
      '#rows' => 20,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['next'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next'),
      '#submit' => ['::submitStep1'],
      '#validate' => ['::validateStep1'],
    ];

    return $form;
  }

  /**
   * Build step 2: Set label and filter values.
   */
  protected function buildStep2(array $form, FormStateInterface $form_state): array {
    $parsed = $form_state->get('parsed_config');

    $form['info'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Importing configuration based on: <strong>@label</strong>', [
        '@label' => $parsed['label'] ?? 'Unknown',
      ]) . '</p>',
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $parsed['label'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('The name for this boundary source.'),
    ];

    // Generate a unique machine name suggestion.
    $suggested_id = $this->generateUniqueMachineName($parsed['label'] ?? 'imported');

    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name'),
      '#default_value' => $suggested_id,
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => [$this, 'boundarySourceExists'],
        'source' => ['label'],
      ],
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $parsed['description'] ?? '',
    ];

    // Filter values section.
    $filters = $parsed['settings']['filters'] ?? [];
    if (!empty($filters)) {
      $form['filters'] = [
        '#type' => 'details',
        '#title' => $this->t('Filter values'),
        '#description' => $this->t('Enter the filter values for your local authority.'),
        '#open' => TRUE,
        '#tree' => TRUE,
      ];

      foreach ($filters as $i => $filter) {
        if (empty($filter['key'])) {
          continue;
        }
        $form['filters'][$i] = [
          '#type' => 'textfield',
          '#title' => $filter['label'] ?? $filter['key'],
          '#description' => $filter['description'] ?? '',
          '#default_value' => $filter['value'] ?? '',
          '#required' => ($i === 0),
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#submit' => ['::submitBack'],
      '#limit_validation_errors' => [],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];

    return $form;
  }

  /**
   * Validate step 1: Parse and validate YAML.
   */
  public function validateStep1(array &$form, FormStateInterface $form_state): void {
    $yaml = '';

    // Check for uploaded file first.
    $files = $this->getRequest()->files->get('files', []);
    if (!empty($files['upload'])) {
      /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
      $file = $files['upload'];
      if ($file->isValid()) {
        $yaml = file_get_contents($file->getRealPath());
      }
      else {
        $form_state->setErrorByName('upload', $this->t('File upload failed.'));
        return;
      }
    }

    // Fall back to textarea if no file uploaded.
    if (empty($yaml)) {
      $yaml = $form_state->getValue('yaml');
    }

    if (empty($yaml)) {
      $form_state->setErrorByName('yaml', $this->t('Please upload a file or paste YAML configuration.'));
      return;
    }

    try {
      $parsed = Yaml::decode($yaml);
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('yaml', $this->t('Invalid YAML: @message', [
        '@message' => $e->getMessage(),
      ]));
      return;
    }

    if (!is_array($parsed)) {
      $form_state->setErrorByName('yaml', $this->t('Invalid configuration format.'));
      return;
    }

    // Validate required structure.
    if (empty($parsed['settings'])) {
      $form_state->setErrorByName('yaml', $this->t('Missing settings in configuration.'));
      return;
    }

    $settings = $parsed['settings'];
    $required_keys = ['listing_api', 'boundary_api'];
    foreach ($required_keys as $key) {
      if (empty($settings[$key])) {
        $form_state->setErrorByName('yaml', $this->t('Missing required setting: @key', [
          '@key' => $key,
        ]));
        return;
      }
    }

    $form_state->set('parsed_config', $parsed);
  }

  /**
   * Submit step 1: Move to step 2.
   */
  public function submitStep1(array &$form, FormStateInterface $form_state): void {
    $form_state->set('step', 2);
    $form_state->setRebuild();
  }

  /**
   * Submit back: Return to step 1.
   */
  public function submitBack(array &$form, FormStateInterface $form_state): void {
    $form_state->set('step', 1);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $parsed = $form_state->get('parsed_config');
    $settings = $parsed['settings'];

    // Update filter values from form input.
    $filter_values = $form_state->getValue('filters') ?? [];
    if (!empty($settings['filters'])) {
      foreach ($settings['filters'] as $i => $filter) {
        if (isset($filter_values[$i])) {
          $settings['filters'][$i]['value'] = $filter_values[$i];
        }
      }
    }

    // Create the new boundary source entity.
    $storage = $this->entityTypeManager->getStorage('boundary_source');
    $entity = $storage->create([
      'id' => $form_state->getValue('id'),
      'label' => $form_state->getValue('label'),
      'description' => $form_state->getValue('description'),
      'plugin' => 'configurable_provider',
      'settings' => $settings,
      'status' => TRUE,
    ]);

    $entity->save();

    $this->messenger()->addStatus($this->t('Imported boundary source: @label', [
      '@label' => $entity->label(),
    ]));

    $form_state->setRedirect('entity.boundary_source.collection');
  }

  /**
   * Check if a boundary source with the given ID exists.
   *
   * @param string $id
   *   The machine name to check.
   *
   * @return bool
   *   TRUE if it exists, FALSE otherwise.
   */
  public function boundarySourceExists(string $id): bool {
    $entity = $this->entityTypeManager->getStorage('boundary_source')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

  /**
   * Generate a unique machine name based on a label.
   *
   * @param string $label
   *   The label to base the machine name on.
   *
   * @return string
   *   A unique machine name.
   */
  protected function generateUniqueMachineName(string $label): string {
    // Convert label to machine name format.
    $base_id = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $label));
    $base_id = trim($base_id, '_');

    // Limit length to leave room for suffix.
    if (strlen($base_id) > 50) {
      $base_id = substr($base_id, 0, 50);
    }

    // If base ID doesn't exist, use it.
    if (!$this->boundarySourceExists($base_id)) {
      return $base_id;
    }

    // Otherwise append a number until we find a unique one.
    $counter = 2;
    while ($this->boundarySourceExists($base_id . '_' . $counter)) {
      $counter++;
    }

    return $base_id . '_' . $counter;
  }

}
