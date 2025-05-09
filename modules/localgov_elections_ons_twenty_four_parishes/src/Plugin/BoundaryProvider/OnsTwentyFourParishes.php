<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_ons_twenty_four_parishes\Plugin\BoundaryProvider;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\localgov_elections\BoundaryProviderPluginBase;
use Drupal\localgov_elections\BoundarySourceInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

use const Drupal\localgov_elections_ons_twenty_four_divisions\Form\URL_FIELDS_CED;

/**
 * Value of the URL for the Local Authority Districts (LAD) lookup.
 */
const URL_LAD = 'https://geoportal.statistics.gov.uk/datasets/ons::local-authority-districts-april-2023-names-and-codes-in-the-uk/explore';

/**
 * Value of the ARCGIS Services URL for Parishes and Non Civil Parished Area Boundaries.
 */
const URL_SERVICES_PARNCP = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/Parishes_and_Non_Civil_Parished_Areas_December_2024_Boundaries_EW_BFC/FeatureServer/0/query?';

/**
 * Value of the ARCGIS Services URL for Parishes List.
 */
const URL_SERVICES_PAR = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/PAR_DEC_2024_EW_NC/FeatureServer/0/query?';

/**
 * Value of the query parameter "where" for PAR.
 */
const URL_WHERE_PAR = 'PARNCP24CD%20IN%20';

/**
 * Value of the query parameter "where" for LAD.
 */
const URL_WHERE_LAD = 'LAD24CD%20%3D%20%27';

/**
 * The query parameter "outFields" with Geometry output as geojson.
 */
const URL_FIELDS_GEOJSON = 'outFields=*&returnDistinctValues=true&returnGeometry=true&outSR=4326&f=geojson';

/**
 * The query parameter "outFields" with no Geometry output as json.
 */
const URL_FIELDS_JSON = 'outFields=PAR24CD,PAR24NM,LAD24CD,LAD24NM&f=json';

/**
 * Plugin implementation of the boundary_provider.
 *
 * @BoundaryProvider(
 *   id = "localgov_elections_ons_2024_parishes",
 *   label = @Translation("ONS 2024 Parishes"),
 *   description = @Translation("ONS 2024 Parishes."),
 *   form = {
 *     "download" = "Drupal\localgov_elections_ons_twenty_four_parishes\Form\OnsTwentyFourParishesDownloadForm",
 *   }
 * )
 */
class OnsTwentyFourParishes extends BoundaryProviderPluginBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Guzzle HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $httpClient;

  /**
   * Node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * Paragraph storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $paragraphStorage;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private MessengerInterface $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
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
   * Constructs an instance of the ONS 2024 Parishes plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\Client $http_client
   *   Http client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Client $http_client,
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->paragraphStorage = $entity_type_manager->getStorage('paragraph');
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigurable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $lad_url = URL_LAD;

    $form['lad'] = [
      '#type' => 'textfield',
      '#title' => t('Local Authority District Code (LAD23CD)'),
      '#maxlength' => 1000,
      '#default_value' => $this->configuration['lad'] ?? "",
      '#description' => $this->t('Local Authority District code. You can find this <a href="@url">here</a>. Use the value from the LAD23CD column.', ['@url' => $lad_url]),
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * Fetches boundary information from Parish IDs.
   *
   * @param array $ids
   *   An array of IDs to check against the GIS API.
   *
   * @return array
   *   The matched features. Potentially empty if no IDs match.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   Could potentially throw a GuzzleException.
   */
  protected function fetchBoundaryInformation(array $ids): array {
    $list = "('" . implode("','", $ids) . "')";
    $gis_url = URL_SERVICES_PARNCP . 'where=' . URL_WHERE_PAR . $list . '&' . URL_FIELDS_GEOJSON;
    $matched_features = [];
    $response = $this->httpClient->get($gis_url);
    if ($response->getStatusCode() == 200) {
      $body = $response->getBody()->getContents();

      $json_decoded = json_decode($body, TRUE);
      $num_to_match = count($ids);
      $num_matched = 0;
      if ($response->getStatusCode() == 200) {
        foreach ($json_decoded['features'] as $feature) {
          if (in_array($feature['properties']['PARNCP24CD'], $ids, TRUE)) {
            $matched_features[] = $feature;
            $num_matched++;
          }
          if ($num_matched >= $num_to_match) {
            break;
          }
        }
      }
    }

    return $matched_features;
  }

  /**
   * {@inheritdoc}
   */
  public function createBoundaries(BoundarySourceInterface $entity, array $form_values) {
    $vals = array_keys(array_filter($form_values['plugin']['config']['options'], function ($item) {
      return $item !== 0;
    }));

    $boundaries = $this->fetchBoundaryInformation($vals);
    $election = $form_values['localgov_election'];
    $election_node = $this->nodeStorage->load($election);
    $n_areas = 0;
    foreach ($boundaries as $boundary) {
      /** @var \Drupal\paragraphs\Entity\Paragraph $area_paragraph */
      $area = $this->nodeStorage->create(
        [
          'type' => 'localgov_area_vote',
          'localgov_election_area_name' => $boundary['properties']['PARNCP24NM'],
          'localgov_election_boundary_data' => json_encode($boundary),
          'localgov_election' => ['target_id' => $election],
          'title' => $election_node->getTitle() . ' - ' . $boundary['properties']['PARNCP24NM'],
        ]
      );
      $area->save();
      $n_areas += 1;
    }

    if ($n_areas > 0) {
      $this->messenger->addMessage($this->t('Created @n_area area votes records with boundary information', ['@n_area' => $n_areas]));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $lad = $form_state->getValue('lad');
    $url = URL_SERVICES_PAR . 'where=' . URL_WHERE_LAD . $lad . '%27&' . URL_FIELDS_JSON;

    $response = $this->httpClient->get($url);
    if ($response->getStatusCode() == 200) {
      $body = $response->getBody()->getContents();
      $json_decoded = json_decode($body, TRUE);
      $features = $json_decoded['features'];
      if (count($features) == 0) {
        $form_state->setErrorByName('lad', $this->t('The area codes, @code, you inputted do not seem to come back as valid. Are you sure they are correct? Check that they are correct and try again.', ['@code' => $lad]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Nothing to do.
  }

}
