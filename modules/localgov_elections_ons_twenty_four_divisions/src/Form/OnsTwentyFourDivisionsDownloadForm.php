<?php

namespace Drupal\localgov_elections_ons_twenty_four_divisions\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\localgov_elections\BoundaryProviderInterface;
use Drupal\localgov_elections\Form\BoundaryProviderSubformInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Download form for ONS 2024 plugin.
 */
class OnsTwentyFourDivisionsDownloadForm implements BoundaryProviderSubformInterface, ContainerInjectionInterface {

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
          '#title' => $this->t("Areas to download"),
          '#type' => 'tableselect',
          '#header' => ['area' => $this->t('Area')],
          '#options' => &$opts,
          '#required' => TRUE,
        ];

    $lad = $this->plugin->getConfiguration()['lad'];
    $cty = $this->plugin->getConfiguration()['cty'];
    $url = "https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/WD24_LAD24_CTY24_CED24_EN_LU/FeatureServer/0/query?";
    if ($lad && $cty) {
      $url = $url . "where=CTY24CD%20%3D%20%27$cty%27%20AND%20LAD24CD%20%3D%20%27$lad%27&outFields=CTY24CD,CTY24NM,CED24NM,CED24CD&returnDistinctValues=true&returnGeometry=false&outSR=4326&f=json";
    }
    elseif (!$lad && $cty) {
      $url = $url . "where=CTY24CD%20%3D%20%27$cty%27&outFields=CTY24CD,CTY24NM,CED24NM,CED24CD&returnDistinctValues=true&returnGeometry=false&outSR=4326&f=json";
    }
    else {
      $url = $url . "where=LAD24CD%20%3D%20%27$lad%27&outFields=CTY24CD,CTY24NM,CED24NM,CED24CD&returnDistinctValues=true&returnGeometry=false&outSR=4326&f=json";
    }
    $response = $this->httpClient->get($url);
    $body = $response->getBody()->getContents();
    $decoded = json_decode($body, TRUE);

    foreach ($decoded['features'] as $item) {
      $item = $item['attributes'];
      $opts[$item['CED24CD']] = ['area' => $item['CED24NM']];
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
