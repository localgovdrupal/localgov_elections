<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_configurable_provider\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\localgov_elections\BoundaryProviderInterface;
use Drupal\localgov_elections\Form\BoundaryProviderSubformInterface;
use Drupal\localgov_elections_configurable_provider\ApiHelper;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Download form for the configurable boundary provider.
 *
 * Fetches available areas from the configured listing API and presents
 * them as a tableselect for user selection.
 */
class ConfigurableDownloadForm implements BoundaryProviderSubformInterface, ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The boundary provider plugin.
   *
   * @var \Drupal\localgov_elections\BoundaryProviderInterface
   */
  protected BoundaryProviderInterface $plugin;

  /**
   * Constructs the download form.
   *
   * @param \GuzzleHttp\Client $httpClient
   *   The HTTP client.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The request stack.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    protected Client $httpClient,
    protected RequestStack $request,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('http_client'),
      $container->get('request_stack'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setPlugin(BoundaryProviderInterface $plugin): void {
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $opts = $this->getAreasToDownload();
    $form['options'] = [
      '#title' => $this->t('Areas to download'),
      '#type' => 'tableselect',
      '#header' => ['area' => $this->t('Area')],
      '#options' => $opts,
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * Fetch available areas from the configured listing API.
   *
   * @return array
   *   Array of options for the tableselect, keyed by area code.
   *   Each value is an array with an 'area' key containing the display name.
   *   Returns empty array if no areas are found or on error.
   */
  public function getAreasToDownload(): array {
    $config = $this->plugin->getConfiguration();
    $listing_config = $config['listing_api'] ?? [];
    $opts = [];

    if (empty($listing_config['base_url'])) {
      return $opts;
    }

    // Build token map from filters.
    $tokens = ApiHelper::buildTokenMap($config['filters'] ?? []);

    $where = ApiHelper::replaceTokens($listing_config['where'] ?? '', $tokens);
    $result_path = $listing_config['result_path'] ?? 'features';
    $attributes_path = $listing_config['attributes_path'] ?? 'attributes';
    $code_field = $listing_config['code_field'] ?? '';
    $name_field = $listing_config['name_field'] ?? '';
    $additional_params = ApiHelper::parseAdditionalParams($listing_config['additional_params'] ?? '');

    try {
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

      foreach ($results as $item) {
        $code = ApiHelper::getAttributeValue($item, $code_field, $attributes_path);
        $name = ApiHelper::getAttributeValue($item, $name_field, $attributes_path);
        if ($code !== NULL && $name !== NULL) {
          $opts[(string) $code] = ['area' => (string) $name];
        }
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t(
        'Failed to fetch areas: @message',
        ['@message' => $e->getMessage()]
      ));
    }

    return $opts;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // No additional validation needed.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // No additional submit handling needed.
  }

}
