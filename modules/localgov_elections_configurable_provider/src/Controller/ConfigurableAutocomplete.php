<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_configurable_provider\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\localgov_elections_configurable_provider\ApiHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Generic autocomplete controller for configurable boundary providers.
 */
class ConfigurableAutocomplete extends ControllerBase {

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
   * Constructs the autocomplete controller.
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
    $this->logger = $logger_channel_factory->get('localgov_elections_configurable_provider');
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('http_client'),
      $container->get('cache.default'),
      $container->get('messenger'),
      $container->get('logger.factory')
    );
  }

  /**
   * Get cache key for a boundary source.
   *
   * @param string $boundary_source_id
   *   The boundary source entity ID.
   *
   * @return string
   *   The cache key.
   */
  protected function getCacheKey(string $boundary_source_id): string {
    return 'localgov_elections_configurable_provider:autocomplete:' . $boundary_source_id;
  }

  /**
   * Fetches area names from the listing API.
   *
   * @param string $boundary_source_id
   *   The boundary source entity ID.
   *
   * @return array|null
   *   Array of area data with 'code' and 'name' keys, or NULL on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function fetchAreas(string $boundary_source_id): ?array {
    $cache_key = $this->getCacheKey($boundary_source_id);

    if ($this->cacheBackend->get($cache_key) !== FALSE) {
      return $this->cacheBackend->get($cache_key)->data;
    }

    // Load the boundary source entity.
    $boundary_source = $this->entityTypeManager()
      ->getStorage('boundary_source')
      ->load($boundary_source_id);

    if (!$boundary_source) {
      return NULL;
    }

    $settings = $boundary_source->getSettings();
    $listing_config = $settings['listing_api'] ?? [];

    if (empty($listing_config['base_url'])) {
      return NULL;
    }

    // Build token map from filters.
    $tokens = ApiHelper::buildTokenMap($settings['filters'] ?? []);

    $where = ApiHelper::replaceTokens($listing_config['where'] ?? '1=1', $tokens);
    $result_path = $listing_config['result_path'] ?? 'features';
    $attributes_path = $listing_config['attributes_path'] ?? 'attributes';
    $code_field = $listing_config['code_field'] ?? '';
    $name_field = $listing_config['name_field'] ?? '';
    $additional_params = ApiHelper::parseAdditionalParams($listing_config['additional_params'] ?? '');

    $response = ApiHelper::executeQuery(
      $this->httpClient,
      $listing_config['base_url'],
      $where,
      $listing_config['out_fields'] ?? '*',
      'json',
      FALSE,
      $additional_params
    );

    $results = ApiHelper::extractResults($response, $result_path);

    $areas = [];
    foreach ($results as $item) {
      $code = ApiHelper::getAttributeValue($item, $code_field, $attributes_path);
      $name = ApiHelper::getAttributeValue($item, $name_field, $attributes_path);
      if ($code !== NULL && $name !== NULL) {
        $areas[] = [
          'code' => (string) $code,
          'name' => (string) $name,
        ];
      }
    }

    $this->cacheBackend->set($cache_key, $areas, Cache::PERMANENT);

    return $areas;
  }

  /**
   * Builds the autocomplete response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param string $boundary_source
   *   The boundary source entity ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response with matching areas.
   */
  public function build(Request $request, string $boundary_source): JsonResponse {
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
      $areas = $this->fetchAreas($boundary_source);
    }
    catch (GuzzleException $exception) {
      $this->messenger->addError($this->t(
        'Could not get autocomplete results. Query failed with: @message',
        ['@message' => $exception->getMessage()]
      ));
      $this->logger->error($exception->getMessage());
      return new JsonResponse($results);
    }

    if ($areas === NULL) {
      return new JsonResponse($results);
    }

    // Filter areas by the search query (case-insensitive partial match).
    // Return plain strings so Drupal's autocomplete.js built-in deduplication
    // works (it compares already-selected values against the suggestions array
    // using Array.includes, which only matches strings, not objects).
    foreach ($areas as $area) {
      if (str_contains(strtolower($area['name']), strtolower($keyword))) {
        $display = $area['name'];
        // Quote names containing commas.
        if (str_contains($display, ',')) {
          $display = '"' . $display . '"';
        }
        $results[] = $display;
      }
    }

    return new JsonResponse($results);
  }

}
