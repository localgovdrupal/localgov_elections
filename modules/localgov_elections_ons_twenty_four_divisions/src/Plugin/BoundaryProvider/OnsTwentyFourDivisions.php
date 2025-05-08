<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_ons_twenty_four_divisions\Plugin\BoundaryProvider;

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

/**
 * Plugin implementation of the boundary_provider.
 *
 * @BoundaryProvider(
 *   id = "ons_2024_divisions",
 *   label = @Translation("ONS 2024 Divisions"),
 *   description = @Translation("ONS 2024 Divisions."),
 *   form = {
 *     "download" = "Drupal\localgov_elections_ons_twenty_four_divisions\Form\OnsTwentyFourDivisionsDownloadForm",
 *   }
 * )
 */
class OnsTwentyFourDivisions extends BoundaryProviderPluginBase implements ContainerFactoryPluginInterface {

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
   * Constructs an instance of the ONS 2024 Divisions plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\Client $http_client
   *   Http client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Client $http_client,
    EntityTypeManagerInterface $entityTypeManager,
    MessengerInterface $messenger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->paragraphStorage = $entityTypeManager->getStorage('paragraph');
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

    $lad_url = "https://geoportal.statistics.gov.uk/datasets/ons::local-authority-districts-april-2023-names-and-codes-in-the-uk/explore";

    $cty_url = "https://geoportal.statistics.gov.uk/datasets/ons::counties-december-2024-names-and-codes-in-en/explore";

    $form['cty'] = [
      '#type' => 'textfield',
      '#title' => "Local Authority County Code (CTY24CD)",
      '#maxlength' => 1000,
      '#default_value' => $this->configuration['cty'] ?? "",
      '#description' => $this->t("County code. You can find this <a href='@url'>here</a>. Use the value from the CTY24CD column.", ['@url' => $cty_url]),
      '#required' => TRUE,
    ];

    $form['lad'] = [
      '#type' => 'textfield',
      '#title' => "Local Authority District Code (LAD23CD)",
      '#maxlength' => 1000,
      '#default_value' => $this->configuration['lad'] ?? "",
      '#description' => $this->t("Local Authority District code. You can find this <a href='@url'>here</a>. Use the value from the LAD23CD column.", ['@url' => $lad_url]),
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * Fetches boundary information from County Division IDs.
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
  protected function fetchBoundaryInformation($ids): array {
    $list = "('" . implode("','", $ids) . "')";
    $gis_url = "https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/County_Electoral_Division_May_2023_Boundaries_EN_BFE/FeatureServer/0/query?where=CED23CD%20IN%20$list&outFields=*&returnDistinctValues=true&returnGeometry=true&outSR=4326&f=geojson";
    $response = $this->httpClient->get($gis_url);
    $body = $response->getBody()->getContents();

    $json_decoded = json_decode($body, TRUE);
    $num_to_match = count($ids);
    $num_matched = 0;
    $matched_features = [];
    if ($response->getStatusCode() == 200) {
      foreach ($json_decoded['features'] as $feature) {
        if (in_array($feature['properties']['CED23CD'], $ids, TRUE)) {
          $matched_features[] = $feature;
          $num_matched++;
        }
        if ($num_matched >= $num_to_match) {
          break;
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
            'localgov_election_area_name' => $boundary['properties']['CED23NM'],
            'localgov_election_boundary_data' => json_encode($boundary),
            'localgov_election' => ['target_id' => $election],
            'title' => $election_node->getTitle() . ' - ' . $boundary['properties']['CED23NM'],
          ]
      );
      $area->save();
      $n_areas += 1;
    }

    if ($n_areas > 0) {
      $this->messenger->addMessage($this->t("Created @n_area area votes records with boundary information", ['@n_area' => $n_areas]));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $lad = $form_state->getValue('lad');
    $cty = $form_state->getValue('cty');
    $url = "https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/WD24_LAD24_CTY24_CED24_EN_LU/FeatureServer/0/query?";
    if ($lad && $cty) {
      $url = $url . "where=CTY24CD%20%3D%20%27$cty%27%20AND%20LAD24CD%20%3D%20%27$lad%27&outFields=*&returnDistinctValues=true&f=json";
    }
    elseif (!$lad && $cty) {
      $url = $url . "where=CTY24CD%20%3D%20%27$cty%27&outFields=*&returnDistinctValues=true&f=json";
    }
    else {
      $url = $url . "where=LAD24CD%20%3D%20%27$lad%27&outFields=CTY24CD,CTY24NM,CED24NM,CED24CD&returnDistinctValues=true&returnGeometry=false&outSR=4326&f=json";
    }

    $response = $this->httpClient->get($url);
    $body = $response->getBody()->getContents();
    $json_decoded = json_decode($body, TRUE);
    $features = $json_decoded['features'];
    if (count($features) == 0) {
      $form_state->setErrorByName('cty', $this->t("The area codes, @code, you inputted do not seem to come back as valid. Are you sure they are correct? Check that they are correct and try again.", ['@code' => $cty . ',' . $lad]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Nothing to do.
  }

}
