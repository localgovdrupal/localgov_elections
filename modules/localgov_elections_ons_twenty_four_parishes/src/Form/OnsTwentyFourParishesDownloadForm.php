<?php

namespace Drupal\localgov_elections_ons_twenty_four_parishes\Form;

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
class OnsTwentyFourParishesDownloadForm implements BoundaryProviderSubformInterface, ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The plugin.
   *
   * @var \Drupal\localgov_elections\BoundaryProviderInterface
   */
  protected $plugin;

  /**
   * Value of the ARCGIS Services URL for the Parish lookup.
   */
  const URL_SERVICES_PAR = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/PAR_DEC_2024_EW_NC/FeatureServer/0/query';

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
    protected Client $http_client,
    protected RequestStack $request,
    protected MessengerInterface $messenger,
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
        '#options' => $opts,
        '#required' => TRUE,
      ];
    return $form;
  }

  /**
   * Fetch Parishes.
   *
   * Fetches Parish Codes and Names from ONS API.
   *
   * @return array
   *   Will return the array of data.
   *   This will be empty if no Parishes returned.
   */
  public function getAreasToDownload(): array {
    $lad = $this->plugin->getConfiguration()['lad'];
    $url = self::URL_SERVICES_PAR;
    $params = [
      'query' => [
        'where' => "LAD24CD = '$lad'",
        'outFields' => 'PAR24CD,PAR24NM,LAD24CD,LAD24NM',
        'f' => 'json',
      ],
    ];
    $opts = [];
    try {
      $response = $this->http_client->get($url, $params);
      if ($response->getStatusCode() == 200) {
        $body = $response->getBody()->getContents();
        $decoded = json_decode($body, TRUE);

        foreach ($decoded['features'] as $item) {
          $item = $item['attributes'];
          $opts[$item['PAR24CD']] = ['area' => $item['PAR24NM']];
        }
      }
      return $opts;
    }
    catch (\Exception $exception) {
      $this->messenger->addError($this->t("Failed to get URL: @message",
          ["@message" => $exception->getMessage()]));
      return $opts;
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
