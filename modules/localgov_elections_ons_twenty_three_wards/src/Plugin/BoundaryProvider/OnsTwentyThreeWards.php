<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_ons_twenty_three_wards\Plugin\BoundaryProvider;

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
 *   id = "ons_2023_wards",
 *   label = @Translation("ONS 2023 Wards"),
 *   description = @Translation("ONS 2023 Wards."),
 *   form = {
 *     "download" = "Drupal\localgov_elections_ons_twenty_three_wards\Form\OnsTwentyThreeWardsDownloadForm",
 *   }
 * )
 */
class OnsTwentyThreeWards extends BoundaryProviderPluginBase implements ContainerFactoryPluginInterface {

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
   * Constructs an instance of the ONS 2023 plugin.
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

    $url = "https://geoportal.statistics.gov.uk/datasets/ons::local-authority-districts-april-2023-names-and-codes-in-the-united-kingdom/explore?showTable=true";

    $form['lad'] = [
      '#type' => 'textfield',
      '#title' => "Local Authority District Code (LAD23CD)",
      '#maxlength' => 1000,
      '#default_value' => $this->configuration['lad'] ?? "",
      // phpcs:ignore
      '#description' => $this->t("Local Authority District code. You can find this <a href='$url'>here</a>. Use the value from the LAD23CD column."),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * Fetches boundary information given a Local Authority District.
   *
   * Fetches boundary information given a Local Authority District code and the
   * IDs to fetch.
   *
   * @param string $lad
   *   The local authority district code.
   * @param array $ids
   *   An array of IDs to check against the GIS API.
   *
   * @return array
   *   The matched features. Potentially empty if no IDs match.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   Could potentially throw a GuzzleException.
   */
  protected function fetchBoundaryInformation($lad, $ids): array {
    // @todo Unsure if we should expose this and the other URLs.
    $gis_url = "https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/WD_MAY_2023_UK_BFE/FeatureServer/0/query?where=LAD23CD%20%3D%20%27$lad%27&outFields=LAD23CD,LAD23NM,WD23NM,WD23NMW,WD23CD&returnGeometry=true&outSR=4326&f=geojson";
    $response = $this->httpClient->get($gis_url);
    $body = $response->getBody()->getContents();

    $json_decoded = json_decode($body, TRUE);
    $num_to_match = count($ids);
    $num_matched = 0;
    $matched_features = [];
    if ($response->getStatusCode() == 200) {
      foreach ($json_decoded['features'] as $feature) {
        if (in_array($feature['properties']['WD23CD'], $ids, TRUE)) {
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
    $lad = $entity->getSettings()['lad'];
    $vals = array_keys(array_filter($form_values['plugin']['config']['options'], function ($item) {
      return $item !== 0;
    }));

    $boundaries = $this->fetchBoundaryInformation($lad, $vals);
    $election = $form_values['localgov_election'];
    $election_node = $this->nodeStorage->load($election);
    $n_areas = 0;
    foreach ($boundaries as $boundary) {
      /** @var \Drupal\paragraphs\Entity\Paragraph $area_paragraph */
      $area = $this->nodeStorage->create(
          [
            'type' => 'localgov_area_vote',
            'localgov_election_area_name' => $boundary['properties']['WD23NM'],
            'localgov_election_boundary_data' => json_encode($boundary),
            'localgov_election' => ['target_id' => $election],
            'title' => $election_node->getTitle() . ' - ' . $boundary['properties']['WD23NM'],
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
    // Should be not null since we enforce on form.
    $lad = $form_state->getValue('lad');
    // @todo Unsure if we should expose this and the other URL.
    $url = "https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/LAD_APR_2023_UK_NC/FeatureServer/0/query?where=LAD23CD%20%3D%20%27$lad%27&outFields=*&outSR=4326&f=json";

    $response = $this->httpClient->get($url);
    // @todo should we check that the response comes back as 200?
    $body = $response->getBody()->getContents();
    $json_decoded = json_decode($body, TRUE);
    $features = $json_decoded['features'];
    if (count($features) == 0) {
      $form_state->setErrorByName('lad', $this->t("The area code, @code, you inputted does not seem to come back as valid. Are you sure it's correct? Check that it is correct and try again.", ['@code' => $lad]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Nothing to do.
  }

}
