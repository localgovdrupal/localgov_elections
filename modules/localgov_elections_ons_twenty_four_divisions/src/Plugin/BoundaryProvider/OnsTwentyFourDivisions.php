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
use Drupal\node\NodeInterface;
use Exception;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the boundary_provider.
 *
 * @BoundaryProvider(
 *   id = "localgov_elections_ons_2024_divisions",
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
   * Value of the ARCGIS Services URL for CED Boundaries.
   */
  const URL_SERVICES_CED = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/County_Electoral_Division_May_2023_Boundaries_EN_BFE/FeatureServer/0/query?';

  /**
   * Value of the ARCGIS Services URL for the WD/LAD/CTY/CED lookup.
   */
  const URL_SERVICES_LU = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/WD24_LAD24_CTY24_CED24_EN_LU/FeatureServer/0/query?';

  /**
   * Value of the URL for the LAD lookup.
   */
  const URL_LAD = 'https://geoportal.statistics.gov.uk/datasets/ons::local-authority-districts-april-2023-names-and-codes-in-the-uk/explore';

  /**
   * Value of the URL for the CTY lookup.
   */
  const URL_CTY = 'https://geoportal.statistics.gov.uk/datasets/ons::counties-december-2024-names-and-codes-in-en/explore';

  /**
   * Value of the query parameter "where" for CTY.
   */
  const URL_WHERE_CTY = 'CTY24CD%20%3D%20%27';

  /**
   * Value of the query parameter "where" for LAD.
   */
  const URL_WHERE_LAD = 'LAD24CD%20%3D%20%27';

  /**
   * Value of the query parameter "where" for CED.
   */
  const URL_WHERE_CED = 'CED23CD%20IN%20';

  /**
   * The query parameter "outFields" with Geometry output as geojson.
   */
  const URL_FIELDS_GEOJSON = 'outFields=*&returnDistinctValues=true&returnGeometry=true&outSR=4326&f=geojson';

  /**
   * The query parameter "outFields" no Geomotry format as json.
   */
  const URL_FIELDS_JSON = 'outFields=*&returnDistinctValues=true&f=json';

  /**
   * Guzzle HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  // protected Client $httpClient;

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
  // private MessengerInterface $messenger;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(
    $configuration,
    $plugin_id,
    $plugin_definition,
    public Client $http_client,
    public entityTypeManagerInterface $entity_type_manager,
    public MessengerInterface $messenger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->paragraphStorage = $entity_type_manager->getStorage('paragraph');
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

    $form['cty'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Local Authority County Code (CTY24CD)'),
      '#maxlength' => 1000,
      '#default_value' => $this->configuration['cty'] ?? "",
      '#description' => $this->t('County code. You can find this <a href="@url">here</a>. Use the value from the CTY24CD column.', ['@url' => self::URL_CTY]),
      '#required' => TRUE,
    ];

    $form['lad'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Local Authority District Code (LAD23CD)'),
      '#maxlength' => 1000,
      '#default_value' => $this->configuration['lad'] ?? "",
      '#description' => $this->t('Local Authority District code. You can find this <a href="@url">here</a>. Use the value from the LAD23CD column.', ['@url' => self::URL_LAD]),
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
  protected function fetchBoundaryInformation(array $ids): array {
    $gis_url = self::URL_SERVICES_CED;
    $list = implode("','", $ids);
    $params = [
      'query' => [
        'where' => "CED23CD IN ('$list')",
        'outFields' => '*',
        'returnDistinctValues' => 'true',
        'returnGeometry' => 'true',
        'outSR' => '4326',
        'f' => 'geojson',
      ]
    ];
    $matched_features = [];
    try {
      $response = $this->http_client->get($gis_url, $params);
      if ($response->getStatusCode() == 200) {
        $body = $response->getBody()->getContents();

        $json_decoded = json_decode($body, TRUE);
        $num_to_match = count($ids);
        $num_matched = 0;
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
    catch(Exception $exception) {
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
    $election_node = $this->nodeStorage->load($election);
    if ($election_node instanceof NodeInterface) {
      $n_areas = 0;
      foreach ($boundaries as $boundary) {
        /** @var \Drupal\paragraphs\Entity\Paragraph $area_paragraph */
        $name = str_replace(' ED', '', $boundary['properties']['CED23NM']);
        $area = $this->nodeStorage->create(
          [
            'type' => 'localgov_area_vote',
            'localgov_election_area_name' => $name,
            'localgov_election_boundary_data' => json_encode($boundary),
            'localgov_election' => ['target_id' => $election],
            'title' => $election_node->getTitle() . ' - ' . $name,
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
    $cty = $form_state->getValue('cty');
    $url = self::URL_SERVICES_LU;
    $params = [];
    $where = '';
    if (!$lad && !$cty) $form_state->setErrorByName('cty', $this->t('The area codes are empty. Please try again.'));
    if ($lad && $cty) {
      $where = "CTY24CD = '" . $cty . "' AND LAD24CD = '" . $lad . "'";
    }
    elseif (!$lad && $cty) {
      $where = "CTY24CD = '" . $cty . "'";
    }
    else {
      $where = "LAD24CD = '" . $lad . "'";
    }
    $params = [
      'query' => [
        'where' => $where,
        'outFields' => '*',
        'returnDistinctValues' => 'true',
        'f' => 'json',
      ]
    ];
    try {
      $response = $this->http_client->get($url, $params);
      if ($response->getStatusCode() == 200) {
        $body = $response->getBody()->getContents();
        $json_decoded = json_decode($body, TRUE);
        $features = $json_decoded['features'] ?? [];
        if (count($features) == 0) {
          $form_state->setErrorByName('cty', $this->t('The area codes, @code, you inputted do not seem to come back as valid. Are you sure they are correct? Check that they are correct and try again.', ['@code' => $cty . ',' . $lad]));
        }
      }
    }
    catch(Exception $exception) {
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
