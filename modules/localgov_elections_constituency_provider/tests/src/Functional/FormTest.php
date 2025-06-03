<?php

declare(strict_types=1);

namespace Drupal\Tests\localgov_elections_constituency_provider\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test description.
 *
 * @group localgov_elections_constituency_provider
 */
final class FormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['localgov_elections_constituency_provider'];

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  protected $strictConfigSchema = FALSE;
  // @codingStandardsIgnoreEnd

  /**
   * An admin user.
   *
   * @var \Drupal\user\Entity\User|null
   */
  protected $adminUser;

  /**
   * Provider config entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $provider;

  /**
   * Election node.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $election;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser(['administer boundary_source', 'can fetch boundaries']);
    $this->drupalLogin($this->adminUser);

    $storage = $this->container->get('entity_type.manager')->getStorage('boundary_source');
    $this->provider = $storage->create([
      'id' => "uk_2024",
      'label' => "UK 2024",
      'description' => '',
      'status' => TRUE,
      'plugin' => "uk_constituency_provider_2024",
    ]);
    $this->provider->save();

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->election = $storage->create([
      'title' => "UK Election 2024",
      'status' => TRUE,
      'type' => "localgov_election",
    ]);

    $this->election->save();
  }

  /**
   * Test our plugin appears on the listing page.
   */
  public function testPluginListing(): void {
    $this->drupalGet('/admin/structure/boundary-source');
    $this->assertSession()->pageTextContains("UK 2024");
    $this->assertSession()->pageTextContains("uk_constituency_provider_2024");
  }

  /**
   * Test that it appears on the fetch page.
   */
  public function testPluginDownloadForm(): void {
    $id = $this->election->id();
    $this->drupalGet("/node/$id/boundary-fetch");
    $this->assertSession()->pageTextContains("UK 2024");

    $this->assertSession()->fieldExists('edit-plugin-config-constituencies');
  }

  /**
   * Test the autocomplete endpoint.
   */
  public function testConstituencyLookupEndpoint(): void {
    // Send a GET request to the JSON endpoint.
    $this->drupalGet('/uk-constituency-twenty-four-auto-complete', ['query' => ['q' => 'Edinburgh S']]);

    $this->assertSession()->responseHeaderEquals('Content-Type', 'application/json');

    // Parse the JSON response.
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);

    $this->assertIsArray($response);

    $this->assertTrue(in_array(['value' => 'Edinburgh South West', 'label' => 'Edinburgh South West'], $response, TRUE));
  }

}
