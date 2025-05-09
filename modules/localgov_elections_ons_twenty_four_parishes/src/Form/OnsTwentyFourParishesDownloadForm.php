<?php

namespace Drupal\localgov_elections_ons_twenty_four_parishes\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\localgov_elections\BoundaryProviderInterface;
use Drupal\localgov_elections\Form\BoundaryProviderSubformInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Value of the ARCGIS Services URL for the Parish lookup.  
 */
const URL_SERVICES_PAR = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/PAR_DEC_2024_EW_NC/FeatureServer/0/query?';

/**
 * Value of the query parameter "where" for LAD.  
 */
const URL_WHERE_LAD = 'LAD24CD%20%3D%20%27';

/**
 * The query parameter "outFields" no geometry format json.  
 */
const URL_FIELDS_PAR = 'outFields=PAR24CD,PAR24NM,LAD24CD,LAD24NM&f=json';

/**
 * Download form for ONS 2024 plugin.
 */
class OnsTwentyFourParishesDownloadForm implements BoundaryProviderSubformInterface, ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Guzzle HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  private Client $httpClient;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private RequestStack $request;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('http_client'), $container->get('request_stack'));
  }

  /**
   * Constructs the ONS 2024.
   *
   * @param \GuzzleHttp\Client $http_client
   *   Guzzle HTTP client.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The current request.
   */
  public function __construct(Client $http_client, RequestStack $request) {
    $this->httpClient = $http_client;
    $this->request = $request;
  }

  /**
   * The plugin.
   *
   * @var \Drupal\localgov_elections\BoundaryProviderInterface
   */
  protected $plugin;

  /**
   * {@inheritDoc}
   */
  public function setPlugin(BoundaryProviderInterface $plugin) {
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $opts = [];
    $form['options'] =
      [
        '#title' => $this->t('Areas to download'),
        '#type' => 'tableselect',
        '#header' => ['area' => $this->t('Area')],
        '#options' => &$opts,
        '#required' => TRUE,
      ];

    $lad = $this->plugin->getConfiguration()['lad'];
    $url = URL_SERVICES_PAR . 'where=' . URL_WHERE_LAD . $lad .'%27&' . URL_FIELDS_PAR;
    $response = $this->httpClient->get($url);
    if ($response->getStatusCode() == 200) {
      $body = $response->getBody()->getContents();
      $decoded = json_decode($body, TRUE);

      foreach ($decoded['features'] as $item) {
        $item = $item['attributes'];
        $opts[$item['PAR24CD']] = ['area' => $item['PAR24NM']];
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // @todo any validation needed?
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // @todo any submit handling needed?
  }

}
