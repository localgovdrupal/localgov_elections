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
use Drupal\node\NodeInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Value of the URL for the LAD lookup.
   */
  const URL_LAD = 'https://geoportal.statistics.gov.uk/datasets/ons::local-authority-districts-december-2024-names-and-codes-in-the-uk/explore';

  /**
   * Value of the ARCGIS Services URL for PAR & NC PAR Boundaries.
   */
  const URL_SERVICES_PARNCP = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/Parishes_and_Non_Civil_Parished_Areas_December_2024_Boundaries_EW_BFC/FeatureServer/0/query';

  /**
   * Value of the ARCGIS Services URL for Parishes List.
   */
  const URL_SERVICES_PAR = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/PAR_DEC_2024_EW_NC/FeatureServer/0/query';

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
   * @param \GuzzleHttp\Client $httpClient
   *   Http client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(
    $configuration,
    $plugin_id,
    $plugin_definition,
    protected Client $httpClient,
    protected entityTypeManagerInterface $entityTypeManager,
    protected MessengerInterface $messenger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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

    $lad_url = self::URL_LAD;

    $form['lad'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Local Authority District Code (LAD24CD)'),
      '#maxlength' => 1000,
      '#default_value' => $this->configuration['lad'] ?? "",
      '#description' => $this->t('Local Authority District code. You can find this <a href="@url">here</a>. Use the value from the LAD24CD column.', ['@url' => $lad_url]),
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
    $gis_url = self::URL_SERVICES_PARNCP;
    $list = implode("','", $ids);
    $params = [
      'query' => [
        'where' => "PARNCP24CD IN ('$list')",
        'outFields' => '*',
        'returnDistinctValues' => 'true',
        'returnGeometry' => 'true',
        'outSR' => '4326',
        'f' => 'geojson',
      ],
    ];
    $matched_features = [];
    try {
      $response = $this->httpClient->get($gis_url, $params);
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
    catch (\Exception $exception) {
      $this->messenger->addError($this->t("Failed to get URL: @message",
          ["@message" => $exception->getMessage()]));
      return $matched_features;
    }
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
    $election_node = $this->entityTypeManager->getStorage('node')->load($election);
    if ($election_node instanceof NodeInterface) {
      $n_areas = 0;
      foreach ($boundaries as $boundary) {
        /** @var \Drupal\paragraphs\Entity\Paragraph $area_paragraph */
        $area = $this->entityTypeManager->getStorage('node')->create(
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
    else {
      $this->messenger->addError($this->t('Node is not an Election Content Type Node'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $lad = $form_state->getValue('lad');
    $url = self::URL_SERVICES_PAR;
    $params = [
      'query' => [
        'where' => "LAD24CD = '$lad'",
        'outFields' => 'PAR24CD,PAR24NM,LAD24CD,LAD24NM',
        'f' => 'json',
      ],
    ];
    try {
      $response = $this->httpClient->get($url, $params);
      if ($response->getStatusCode() == 200) {
        $body = $response->getBody()->getContents();
        $json_decoded = json_decode($body, TRUE);
        $features = $json_decoded['features'];
        if (count($features) == 0) {
          $form_state->setErrorByName('lad', $this->t('The area codes, @code, you inputted do not seem to come back as valid. Are you sure they are correct? Check that they are correct and try again.', ['@code' => $lad]));
        }
      }
    }
    catch (\Exception $exception) {
      $this->messenger->addError($this->t("Failed to get URL: @message",
          ["@message" => $exception->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Nothing to do.
  }

}
