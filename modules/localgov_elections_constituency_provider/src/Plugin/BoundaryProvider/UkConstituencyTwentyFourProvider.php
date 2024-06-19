<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_constituency_provider\Plugin\BoundaryProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\localgov_elections\BoundaryProviderPluginBase;
use Drupal\localgov_elections\BoundarySourceInterface;
use Drupal\node\NodeStorageInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the boundary_provider.
 *
 * @BoundaryProvider(
 *   id = "uk_constituency_provider_2024",
 *   label = @Translation("UK Parliamentary Constituency Boundaries 2024"),
 *   description = @Translation("UK ParliamentaryConstituency Boundaries 2024."),
 *   form = {
 *     "download" = "Drupal\localgov_elections_constituency_provider\Form\UkConstituencyTwentyFourProviderDownloadForm",
 *   }
 * )
 */
class UkConstituencyTwentyFourProvider extends BoundaryProviderPluginBase implements ContainerFactoryPluginInterface {


  use StringTranslationTrait;

  /**
   * Node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected NodeStorageInterface $nodeStorage;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $httpClient;

  /**
   * Templated constituency endpoint.
   *
   * @var string
   */
  protected string $boundaryEndpoint = "https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/Westminster_Parliamentary_Constituencies_July_2024_Boundaries_UK_BFC/FeatureServer/0/query?outFields=PCON24NM&outSR=4326&f=geojson&where={{ constituencies }}";

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
        $configuration,
        $plugin_id,
        $plugin_definition,
        $container->get('entity_type.manager')->getStorage('node'),
        $container->get('http_client'),
        $container->get('messenger')
    );
  }

  /**
   * Constructs Provider.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param array $plugin_definition
   *   Plugin implementation definition.
   * @param \Drupal\node\NodeStorageInterface $node_storage
   *   Node storage.
   * @param \GuzzleHttp\Client $http_client
   *   HTTP client.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    NodeStorageInterface $node_storage,
    Client $http_client,
    MessengerInterface $messenger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->nodeStorage = $node_storage;
    $this->httpClient = $http_client;
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
   * Fetch boundary information given an array of named constituencies.
   *
   * @param array $constituencies
   *   An array of constituencies that will be queried against the ONS.
   *
   * @return array
   *   An array of features fetched from the ONS which contain
   *   boundary information.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function fetchBoundaryInformation(array $constituencies) {
    // Build the rest of the endpoint. The where condition needs added.
    $features = [];
    // Create an array to hold the encoded parts.
    $encoded_parts = [];

    // Loop through the constituencies to build the encoded query parts.
    foreach ($constituencies as $constituency) {
      // Encode the individual parts.
      $part = urlencode("PCON24NM") . "%20%3D%20" . urlencode("'" . $constituency . "'");
      // Add the encoded part to the array.
      $encoded_parts[] = $part;
    }

    $encoded_query = implode("%20OR%20", $encoded_parts);
    $endpoint = $this->boundaryEndpoint;
    $endpoint = str_replace("{{ constituencies }}", $encoded_query, $endpoint);

    $response = $this->httpClient->get($endpoint);
    $body = $response->getBody()->getContents();
    $decoded = json_decode($body, TRUE);

    if (isset($decoded['features'])) {
      $features = $decoded['features'];
    }
    return $features;
  }

  /**
   * {@inheritdoc}
   */
  public function createBoundaries(BoundarySourceInterface $entity, array $form_values) {
    $boundaries = $this->fetchBoundaryInformation($form_values["plugin"]["config"]["constituencies"]);
    $election = $form_values['election'];
    $election_node = $this->nodeStorage->load($election);
    $n_areas = 0;

    foreach ($boundaries as $boundary) {
      /** @var \Drupal\paragraphs\Entity\Paragraph $area_paragraph */
      $area = $this->nodeStorage->create(
          [
            'type' => 'division_vote',
            'field_area_name' => $boundary["properties"]["PCON24NM"],
            'field_boundary_data' => json_encode($boundary),
            'field_election' => ['target_id' => $election],
            'title' => $election_node->getTitle() . ' - ' . $boundary["properties"]["PCON24NM"],
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

}
