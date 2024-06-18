<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_constituency_provider\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\localgov_elections_constituency_provider\CacheKey;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Autocomplete controller for UK Constituency 2024 provider.
 */
class UkConstituencyTwentyFourAutoComplete extends ControllerBase {

  /**
   * The ONS dataset endpoint.
   *
   * @var string
   */
  protected string $constituencyEndpoint = "https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/Westminster_Parliamentary_Constituencies_July_2024_Boundaries_UK_BFC/FeatureServer/0/query?where=1%3D1&outFields=PCON24NM&returnGeometry=false&outSR=4326&f=json";

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $httpClient;

  /**
   * The default cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cacheBackend;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructs the autocomplete route.
   *
   * @param \GuzzleHttp\Client $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Default cache backend.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   Logger channel factory.
   */
  public function __construct(
    Client $http_client,
    CacheBackendInterface $cache_backend,
    MessengerInterface $messenger,
    LoggerChannelFactoryInterface $logger_channel_factory,
  ) {
    $this->httpClient = $http_client;
    $this->cacheBackend = $cache_backend;
    $this->messenger = $messenger;
    $this->logger = $logger_channel_factory->get('localgov_elections_constituency_provider');
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('http_client'),
        $container->get('cache.default'),
        $container->get("messenger"),
        $container->get('logger.factory')
    );
  }

  /**
   * Fetches constituency names.
   *
   * Fetches constituency names from ONS or from local cache if available.
   *
   * @return array|null
   *   Will return the array of data or null if it cannot.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function fetchConstituencies() {
    if ($this->cacheBackend->get(CacheKey::CONSTITUENCY_NAMES_KEY) === FALSE) {
      $response = $this->httpClient->get($this->constituencyEndpoint);
      $body = $response->getBody()->getContents();
      $json_decoded = json_decode($body, TRUE);

      if (!empty($json_decoded['features']) && is_array($json_decoded['features'])) {
        $constituencies = array_map(function ($feature) {
          return $feature['attributes']['PCON24NM'] ?? NULL;
        },
            $json_decoded['features']
        );
        $this->cacheBackend->set(CacheKey::CONSTITUENCY_NAMES_KEY, $constituencies, Cache::PERMANENT);
      }
    }
    return $this->cacheBackend->get(CacheKey::CONSTITUENCY_NAMES_KEY)?->data;
  }

  /**
   * Builds the response.
   */
  public function build(Request $request): JsonResponse {
    $results = [];

    $query = $request->query->get('q');
    if (!$query) {
      return new JsonResponse($results);
    }

    $keyword = Xss::filter($query);
    if (empty($keyword)) {
      return new JsonResponse($results);
    }

    try {
      $constituencies = $this->fetchConstituencies();
    }
    catch (GuzzleException $exception) {
      $this->messenger->addError($this->t("Could not get autocomplete results. Query failed with: @message",
          ["@message" => $exception->getMessage()]));
      $this->logger->error($exception->getMessage());
      return new JsonResponse($results);
    }

    // Look through the results and see if the query matches any of what we have
    // We're going for a query IN approach here to allow partial matches.
    foreach ($constituencies as $item) {
      if (str_contains(strtolower($item), strtolower($query))) {
        if (strpos($item, ",")) {
          $item = '"' . $item . '"';
        }
        $results[] = ['value' => $item, 'label' => $item];
      }
    }

    return new JsonResponse($results);
  }

}
