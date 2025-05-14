<?php

namespace Drupal\localgov_elections_ons_twenty_four_divisions\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
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
   * The plugin.
   *
   * @var \Drupal\localgov_elections\BoundaryProviderInterface
   */
  protected $plugin;

  /**
   * Value of the ARCGIS Services URL for the WD/LAD/CTY/CED lookup.
   */
  const URL_SERVICES_LU = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/WD24_LAD24_CTY24_CED24_EN_LU/FeatureServer/0/query';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('request_stack'),
      $container->get('messenger')
    );
  }

  /**
   * Constructs the ONS 2024.
   *
   * @param \GuzzleHttp\Client $http_client
   *   Guzzle HTTP client.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The current request.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(
    public Client $http_client,
    public RequestStack $request,
    public MessengerInterface $messenger,
  ) {}

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
    $opts = $this->getAreasToDownload();
    $form['options'] =
      [
        '#title' => $this->t('Areas to download'),
        '#type' => 'tableselect',
        '#header' => ['area' => $this->t('Area')],
        '#options' => &$opts,
        '#required' => TRUE,
      ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getAreasToDownload() {
    $lad = $this->plugin->getConfiguration()['lad'];
    $cty = $this->plugin->getConfiguration()['cty'];
    $url = self::URL_SERVICES_LU;
    $where = '';
    $params = [];
    $opts = [];
    if (!$lad && !$cty) {
      return $opts;
    }
    if ($lad && $cty) {
      $where = "CTY24CD = '$cty' AND LAD24CD = '$lad'";
    }
    elseif (!$lad && $cty) {
      $where = "CTY24CD = '$cty'";
    }
    else {
      $where = "LAD24CD = '$lad'";
    }
    $params = [
      'query' => [
        'where' => $where,
        'outFields' => 'CTY24CD,CTY24NM,CED24NM,CED24CD',
        'returnDistinctValues' => 'true',
        'returnGeometry' => 'false',
        'outSR' => '4326',
        'f' => 'json',
      ],
    ];
    try {
      $response = $this->http_client->get($url, $params);
      if ($response->getStatusCode() == 200) {
        $body = $response->getBody()->getContents();
        $decoded = json_decode($body, TRUE);
        foreach ($decoded['features'] as $item) {
          $item = $item['attributes'];
          $opts[$item['CED24CD']] = ['area' => str_replace(' ED', '', $item['CED24NM'])];
        }
      }
      return $opts;
    }
    catch (\Exception $exception) {
      $this->messenger->addError($this->t("Failed to get URL: @message",
          ["@message" => $exception->getMessage()]));
    }
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
