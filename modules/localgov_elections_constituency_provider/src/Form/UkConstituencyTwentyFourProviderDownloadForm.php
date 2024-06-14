<?php

namespace Drupal\localgov_elections_constituency_provider\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\localgov_elections_constituency_provider\CacheKey;
use Drupal\localgov_elections_reporting\BoundaryProviderInterface;
use Drupal\localgov_elections_reporting\Form\BoundaryProviderSubformInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Download form for UK constituency 2024 boundaries.
 */
class UkConstituencyTwentyFourProviderDownloadForm implements BoundaryProviderSubformInterface, ContainerInjectionInterface {

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
   * Default cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  private CacheBackendInterface $cacheBackend;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('http_client'),
        $container->get('request_stack'),
        $container->get('cache.default')
    );
  }

  /**
   * Constructs the ONS 2023.
   *
   * @param \GuzzleHttp\Client $http_client
   *   Guzzle HTTP client.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The current request.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   */
  public function __construct(Client $http_client, RequestStack $request, CacheBackendInterface $cache_backend) {
    $this->httpClient = $http_client;
    $this->request = $request;
    $this->cacheBackend = $cache_backend;
  }

  /**
   * The plugin.
   *
   * @var \Drupal\localgov_elections_reporting\BoundaryProviderInterface
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
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    // Add our autocomplete field.
    $form['constituencies'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Constituencies'),
      '#maxlength' => '1000',
      '#description' => $this->t("Use commas to lookup and select multiple constituencies."),
      '#autocomplete_route_name' => 'localgov_elections_constituency_provider.uk_constituency_auto_complete',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Make sure we only get triggered on submit and not ajax events too.
    if ($form_state->getTriggeringElement()['#type'] == 'submit') {
      $values = str_getcsv($form_state->getValue('constituencies'));
      $values = array_map(fn($value): string => trim(trim($value), '"'), $values);

      // Fetch the cached constituency names and make
      // sure we're not using a random one we've never
      // seen before. It needs to be in the dataset.
      $constituencies = $this->cacheBackend->get(CacheKey::CONSTITUENCY_NAMES_KEY)?->data;
      foreach ($values as $val) {
        if (!in_array($val, $constituencies)) {
          $form_state->setErrorByName('constituencies', "$val does not seem to be a valid choice.");
        }
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Map the constituencies into an array instead.
    $constituencies = $form_state->getValue('constituencies');
    $values = str_getcsv($constituencies);
    $values = array_map(fn($value): string => trim(trim($value), '"'), $values);
    $form_state->setValue('constituencies', $values);
  }

}
