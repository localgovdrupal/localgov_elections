<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_configurable_provider\Plugin\BoundaryProvider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\localgov_elections\BoundaryProviderPluginBase;
use Drupal\localgov_elections\BoundarySourceInterface;
use Drupal\localgov_elections_configurable_provider\ApiHelper;
use Drupal\node\NodeInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configurable boundary provider plugin.
 *
 * Allows administrators to configure REST API endpoints, filter parameters,
 * and field mappings through the Drupal UI.
 *
 * @BoundaryProvider(
 *   id = "configurable_provider",
 *   label = @Translation("Configurable Provider"),
 *   description = @Translation("A configurable boundary provider for REST API endpoints."),
 *   form = {
 *     "download" = "Drupal\localgov_elections_configurable_provider\Form\ConfigurableDownloadForm",
 *   }
 * )
 */
class ConfigurableProvider extends BoundaryProviderPluginBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Maximum number of filter parameter slots.
   */
  const MAX_FILTERS = 3;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * Constructs a ConfigurableProvider instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\Client $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    $configuration,
    $plugin_id,
    $plugin_definition,
    protected Client $httpClient,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MessengerInterface $messenger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'filters' => [],
      'listing_api' => [
        'base_url' => '',
        'where' => '',
        'out_fields' => '',
        'code_field' => '',
        'name_field' => '',
        'result_path' => 'features',
        'attributes_path' => 'attributes',
        'additional_params' => '',
      ],
      'boundary_api' => [
        'base_url' => '',
        'where' => '',
        'out_fields' => '*',
        'code_field' => '',
        'name_field' => '',
        'result_path' => 'features',
        'attributes_path' => 'properties',
        'format' => 'geojson',
        'additional_params' => '',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigurable(): true {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $config = $this->configuration + $this->defaultConfiguration();

    // -- Filter parameters section --
    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filter parameters'),
      '#description' => $this->t('Optional filter values used to query the API. Each filter becomes a <code>{key}</code> token you can reference in the where clauses below.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    for ($i = 0; $i < self::MAX_FILTERS; $i++) {
      $filter = $config['filters'][$i] ?? [];
      $has_value = !empty($filter['key']);

      $form['filters'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Filter @num', ['@num' => $i + 1]),
        '#open' => ($i === 0) || $has_value,
      ];

      $form['filters'][$i]['key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Machine name'),
        '#default_value' => $filter['key'] ?? '',
        '#description' => $this->t('Used as a <code>{token}</code> in where clauses. Use lowercase letters and underscores only.'),
        '#pattern' => '[a-z][a-z0-9_]*',
        '#maxlength' => 64,
      ];

      $form['filters'][$i]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $filter['label'] ?? '',
        '#description' => $this->t('Human-readable label for this filter.'),
      ];

      $form['filters'][$i]['description'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Help text'),
        '#default_value' => $filter['description'] ?? '',
        '#description' => $this->t('Optional help text shown to administrators.'),
      ];

      $form['filters'][$i]['value'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Value'),
        '#default_value' => $filter['value'] ?? '',
        '#description' => $this->t('The actual filter value for this boundary source.'),
      ];
    }

    // -- Listing API section --
    $listing = $config['listing_api'] ?? [];
    $form['listing_api'] = [
      '#type' => 'details',
      '#title' => $this->t('Listing API'),
      '#description' => $this->t('The API call that fetches the list of available areas. Users will select which areas to download from this list.'),
      '#open' => TRUE,
    ];

    $form['listing_api']['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('API endpoint URL'),
      '#default_value' => $listing['base_url'] ?? '',
      '#required' => TRUE,
      '#maxlength' => 2048,
    ];

    $form['listing_api']['where'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Where clause'),
      '#default_value' => $listing['where'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t("Use <code>{filter_key}</code> tokens. Example: <code>PARENT_CODE = '{area_code}'</code>"),
      '#maxlength' => 2048,
    ];

    $form['listing_api']['out_fields'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Output fields'),
      '#default_value' => $listing['out_fields'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Comma-separated fields to return. Must include the code and name fields.'),
    ];

    $form['listing_api']['code_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Area code field'),
      '#default_value' => $listing['code_field'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('The attribute name containing the unique area code. Used as the selection key.'),
    ];

    $form['listing_api']['name_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Area name field'),
      '#default_value' => $listing['name_field'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('The attribute name containing the display name. Shown in the area selection list.'),
    ];

    $form['listing_api']['result_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Result path'),
      '#default_value' => $listing['result_path'] ?? 'features',
      '#description' => $this->t('Dot-notation path to the results array. Typically <code>features</code>.'),
    ];

    $form['listing_api']['attributes_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Attributes path'),
      '#default_value' => $listing['attributes_path'] ?? 'attributes',
      '#description' => $this->t('Path within each result item to the attributes. Use <code>attributes</code> for ArcGIS JSON, <code>properties</code> for GeoJSON, or leave empty for flat structure.'),
    ];

    $form['listing_api']['additional_params'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional parameters'),
      '#default_value' => $listing['additional_params'] ?? '',
      '#description' => $this->t('Optional extra query parameters, one per line in <code>key=value</code> format. For ArcGIS/ONS APIs, you typically need:<br><code>returnDistinctValues=true</code><br><code>outSR=4326</code><br><code>resultRecordCount=10000</code>'),
      '#rows' => 4,
      '#placeholder' => "returnDistinctValues=true\noutSR=4326\nresultRecordCount=10000",
    ];

    // -- Boundary API section --
    $boundary = $config['boundary_api'] ?? [];
    $form['boundary_api'] = [
      '#type' => 'details',
      '#title' => $this->t('Boundary API'),
      '#description' => $this->t('The API call that fetches full boundary geometry for selected areas.'),
      '#open' => TRUE,
    ];

    $form['boundary_api']['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('API endpoint URL'),
      '#default_value' => $boundary['base_url'] ?? '',
      '#required' => TRUE,
      '#maxlength' => 2048,
    ];

    $form['boundary_api']['where'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Where clause'),
      '#default_value' => $boundary['where'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t("Use <code>{filter_key}</code> tokens for filter values and <code>{selected_codes}</code> for the list of user-selected area codes. Examples: <code>PARENT_CODE = '{area_code}'</code> or <code>AREA_CODE IN ({selected_codes})</code>"),
      '#maxlength' => 2048,
    ];

    $form['boundary_api']['out_fields'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Output fields'),
      '#default_value' => $boundary['out_fields'] ?? '*',
      '#description' => $this->t('Comma-separated fields, or <code>*</code> for all. Must include geometry.'),
    ];

    $form['boundary_api']['code_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Area code field'),
      '#default_value' => $boundary['code_field'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Used to match returned features against selected areas.'),
    ];

    $form['boundary_api']['name_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Area name field'),
      '#default_value' => $boundary['name_field'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Used for the area vote node title and area name.'),
    ];

    $form['boundary_api']['result_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Result path'),
      '#default_value' => $boundary['result_path'] ?? 'features',
      '#description' => $this->t('Dot-notation path to the results array. Typically <code>features</code>.'),
    ];

    $form['boundary_api']['attributes_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Attributes path'),
      '#default_value' => $boundary['attributes_path'] ?? 'properties',
      '#description' => $this->t('Path to attributes within each result. Use <code>properties</code> for GeoJSON, <code>attributes</code> for ArcGIS JSON.'),
    ];

    $form['boundary_api']['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Response format'),
      '#options' => [
        'geojson' => $this->t('GeoJSON (f=geojson)'),
        'json' => $this->t('JSON with geometry (f=json)'),
      ],
      '#default_value' => $boundary['format'] ?? 'geojson',
      '#required' => TRUE,
      '#description' => $this->t('The format to request from the API. GeoJSON is preferred when available.'),
    ];

    $form['boundary_api']['additional_params'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional parameters'),
      '#default_value' => $boundary['additional_params'] ?? '',
      '#description' => $this->t('Optional extra query parameters, one per line in <code>key=value</code> format. For ArcGIS/ONS APIs, you typically need:<br><code>outSR=4326</code><br><code>resultRecordCount=10000</code>'),
      '#rows' => 3,
      '#placeholder' => "outSR=4326\nresultRecordCount=10000",
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $listing = $form_state->getValue('listing_api') ?? [];

    if (empty($listing['base_url'])) {
      return;
    }

    // Gather filter values for token replacement.
    $filters = $form_state->getValue('filters') ?? [];
    $tokens = [];
    foreach ($filters as $filter) {
      if (!empty($filter['key']) && !empty($filter['value'])) {
        $tokens[$filter['key']] = $filter['value'];
      }
    }

    $where = ApiHelper::replaceTokens($listing['where'] ?? '', $tokens);
    $result_path = $listing['result_path'] ?? 'features';
    $additional_params = ApiHelper::parseAdditionalParams($listing['additional_params'] ?? '');

    try {
      $response = ApiHelper::executeQuery(
        $this->httpClient,
        $listing['base_url'],
        $where,
        $listing['out_fields'] ?? '*',
        'json',
        FALSE,
        $additional_params
      );

      $results = ApiHelper::extractResults($response, $result_path);

      if (count($results) === 0) {
        $form_state->setErrorByName(
          'filters][0][value',
          $this->t('The filter values did not return any areas from the listing API. Please check they are correct.')
        );
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t(
        'Failed to validate against listing API: @message',
        ['@message' => $e->getMessage()]
      ));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function createBoundaries(BoundarySourceInterface $entity, array $form_values): void {
    $settings = $entity->getSettings();
    $boundary_config = $settings['boundary_api'] ?? [];

    if (empty($boundary_config['base_url'])) {
      $this->messenger->addError($this->t('Boundary API endpoint is not configured.'));
      return;
    }

    // Extract selected area codes from the download form tableselect.
    // Cast to strings because PHP converts numeric-looking array keys to ints.
    $selected_codes = array_map('strval', array_keys(array_filter(
      $form_values['plugin']['config']['options'] ?? [],
      function ($item) {
        return $item !== 0;
      }
    )));

    if (empty($selected_codes)) {
      $this->messenger->addError($this->t('No areas were selected.'));
      return;
    }

    // Build token replacements: filter values + selected_codes.
    $tokens = ApiHelper::buildTokenMap($settings['filters'] ?? []);
    $tokens['selected_codes'] = "'" . implode("','", $selected_codes) . "'";

    $where = ApiHelper::replaceTokens($boundary_config['where'] ?? '', $tokens);
    $format = $boundary_config['format'] ?? 'geojson';
    $result_path = $boundary_config['result_path'] ?? 'features';
    $attributes_path = $boundary_config['attributes_path'] ?? 'properties';
    $code_field = $boundary_config['code_field'] ?? '';
    $name_field = $boundary_config['name_field'] ?? '';
    $additional_params = ApiHelper::parseAdditionalParams($boundary_config['additional_params'] ?? '');

    try {
      $response = ApiHelper::executeQuery(
        $this->httpClient,
        $boundary_config['base_url'],
        $where,
        $boundary_config['out_fields'] ?? '*',
        $format,
        TRUE,
        $additional_params
      );
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t(
        'Failed to fetch boundaries: @message',
        ['@message' => $e->getMessage()]
      ));
      return;
    }

    $features = ApiHelper::extractResults($response, $result_path);

    // Filter to only the selected codes.
    $matched_features = [];
    foreach ($features as $feature) {
      $code = ApiHelper::getAttributeValue($feature, $code_field, $attributes_path);
      if (in_array((string) $code, $selected_codes, TRUE)) {
        $matched_features[] = $feature;
      }
    }

    // Load the election node.
    $election_nid = $form_values['localgov_election'] ?? NULL;
    if (!$election_nid) {
      $this->messenger->addError($this->t('No election node specified.'));
      return;
    }

    $election_node = $this->entityTypeManager->getStorage('node')->load($election_nid);

    if (!$election_node instanceof NodeInterface) {
      $this->messenger->addError($this->t('Node is not of content type Election'));
      return;
    }

    $created_count = 0;
    foreach ($matched_features as $feature) {
      $name = (string) ApiHelper::getAttributeValue($feature, $name_field, $attributes_path);

      // Normalize to GeoJSON Feature format for storage.
      $geojson_feature = ApiHelper::normalizeToGeoJson($feature, $format);

      $area = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'localgov_area_vote',
        'localgov_election_area_name' => $name,
        'localgov_election_boundary_data' => json_encode($geojson_feature),
        'localgov_election' => ['target_id' => $election_nid],
        'title' => $election_node->getTitle() . ' - ' . $name,
      ]);
      $area->save();
      $created_count++;
    }

    if ($created_count > 0) {
      $this->messenger->addMessage($this->t(
        'Created @count area vote records with boundary information.',
        ['@count' => $created_count]
      ));
    }
  }

}
