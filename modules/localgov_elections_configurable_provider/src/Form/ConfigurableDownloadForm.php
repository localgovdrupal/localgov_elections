<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_configurable_provider\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\localgov_elections\BoundaryProviderInterface;
use Drupal\localgov_elections\Form\BoundaryProviderSubformInterface;
use Drupal\localgov_elections_configurable_provider\ApiHelper;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Download form for the configurable boundary provider.
 *
 * Supports two selection modes:
 * - tableselect: Checkbox list of areas filtered by configured filters.
 * - autocomplete: Search field for selecting areas by name.
 */
class ConfigurableDownloadForm implements BoundaryProviderSubformInterface, ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The boundary provider plugin.
   *
   * @var \Drupal\localgov_elections\BoundaryProviderInterface
   */
  protected BoundaryProviderInterface $plugin;

  /**
   * Constructs the download form.
   *
   * @param \GuzzleHttp\Client $httpClient
   *   The HTTP client.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The request stack.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend.
   */
  public function __construct(
    protected Client $httpClient,
    protected RequestStack $request,
    protected MessengerInterface $messenger,
    protected CacheBackendInterface $cacheBackend,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('http_client'),
      $container->get('request_stack'),
      $container->get('messenger'),
      $container->get('cache.default')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setPlugin(BoundaryProviderInterface $plugin): void {
    $this->plugin = $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin(): BoundaryProviderInterface {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $config = $this->plugin->getConfiguration();
    $selection_mode = $config['selection_mode'] ?? 'tableselect';

    if ($selection_mode === 'autocomplete') {
      return $this->buildAutocompleteForm($form);
    }

    return $this->buildTableselectForm($form);
  }

  /**
   * Build the tableselect form variant.
   *
   * @param array $form
   *   The form array.
   *
   * @return array
   *   The form with tableselect element.
   */
  protected function buildTableselectForm(array $form): array {
    $opts = $this->getAvailableAreas();
    $form['options'] = [
      '#title' => $this->t('Areas to download'),
      '#type' => 'tableselect',
      '#header' => ['area' => $this->t('Area')],
      '#options' => $opts,
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * Build the autocomplete form variant.
   *
   * @param array $form
   *   The form array.
   *
   * @return array
   *   The form with autocomplete element.
   */
  protected function buildAutocompleteForm(array $form): array {
    $boundary_source = $this->plugin->getConfigInstance();
    $boundary_source_id = $boundary_source ? $boundary_source->id() : '';

    $form['areas'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Areas'),
      '#maxlength' => 2000,
      '#description' => $this->t('Type to search for areas. Use commas to select multiple areas.'),
      '#autocomplete_route_name' => 'localgov_elections_configurable_provider.autocomplete',
      '#autocomplete_route_parameters' => ['boundary_source' => $boundary_source_id],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * Fetch available areas from the configured listing API.
   *
   * @return array
   *   Array of options for the tableselect, keyed by area code.
   *   Each value is an array with an 'area' key containing the display name.
   *   Returns empty array if no areas are found or on error.
   */
  public function getAvailableAreas(): array {
    $config = $this->plugin->getConfiguration();
    $listing_config = $config['listing_api'] ?? [];
    $opts = [];

    if (empty($listing_config['base_url'])) {
      return $opts;
    }

    // Build token map from filters.
    $tokens = ApiHelper::buildTokenMap($config['filters'] ?? []);

    $where = ApiHelper::replaceTokens($listing_config['where'] ?? '', $tokens);
    $result_path = $listing_config['result_path'] ?? 'features';
    $attributes_path = $listing_config['attributes_path'] ?? 'attributes';
    $code_field = $listing_config['code_field'] ?? '';
    $name_field = $listing_config['name_field'] ?? '';
    $additional_params = ApiHelper::parseAdditionalParams($listing_config['additional_params'] ?? '');

    try {
      $response = ApiHelper::executeQuery(
        $this->httpClient,
        $listing_config['base_url'],
        $where,
        $listing_config['out_fields'] ?? '*',
        'json',
        FALSE,
        $additional_params
      );

      $results = ApiHelper::extractResults($response, $result_path);

      foreach ($results as $item) {
        $code = ApiHelper::getAttributeValue($item, $code_field, $attributes_path);
        $name = ApiHelper::getAttributeValue($item, $name_field, $attributes_path);
        if ($code !== NULL && $name !== NULL) {
          $opts[(string) $code] = ['area' => (string) $name];
        }
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t(
        'Failed to fetch areas: @message',
        ['@message' => $e->getMessage()]
      ));
    }

    return $opts;
  }

  /**
   * Get cache key for autocomplete data.
   *
   * @return string
   *   The cache key.
   */
  protected function getAutocompleteCacheKey(): string {
    $boundary_source = $this->plugin->getConfigInstance();
    $boundary_source_id = $boundary_source ? $boundary_source->id() : 'unknown';
    return 'localgov_elections_configurable_provider:autocomplete:' . $boundary_source_id;
  }

  /**
   * Get cached area names for validation.
   *
   * @return array|null
   *   Array of valid area names, or NULL if not cached.
   */
  protected function getCachedAreaNames(): ?array {
    $cache_key = $this->getAutocompleteCacheKey();
    $cached = $this->cacheBackend->get($cache_key);
    if ($cached === FALSE) {
      return NULL;
    }

    // Extract just the names from the cached data.
    return array_column($cached->data, 'name');
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->plugin->getConfiguration();
    $selection_mode = $config['selection_mode'] ?? 'tableselect';

    if ($selection_mode !== 'autocomplete') {
      return;
    }

    // Only validate on submit, not on AJAX events.
    $triggering_element = $form_state->getTriggeringElement();
    if (!$triggering_element || ($triggering_element['#type'] ?? '') !== 'submit') {
      return;
    }

    $areas_input = $form_state->getValue('areas');
    if (empty($areas_input)) {
      return;
    }

    // Parse the comma-separated values.
    $values = str_getcsv($areas_input);
    $values = array_map(fn($value): string => trim(trim($value), '"'), $values);
    $values = array_filter($values);

    // Validate against cached area names.
    $valid_names = $this->getCachedAreaNames();
    if ($valid_names === NULL) {
      // Cache not populated yet, skip validation.
      return;
    }

    foreach ($values as $val) {
      if (!in_array($val, $valid_names, TRUE)) {
        $form_state->setErrorByName('areas', $this->t(
          '"@value" does not appear to be a valid area name.',
          ['@value' => $val]
        ));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->plugin->getConfiguration();
    $selection_mode = $config['selection_mode'] ?? 'tableselect';

    if ($selection_mode !== 'autocomplete') {
      return;
    }

    // Convert comma-separated area names to an array.
    $areas_input = $form_state->getValue('areas');
    if (!empty($areas_input)) {
      $values = str_getcsv($areas_input);
      $values = array_map(fn($value): string => trim(trim($value), '"'), $values);
      $values = array_filter($values);
      $form_state->setValue('areas', $values);
    }
  }

}
