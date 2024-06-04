<?php

declare(strict_types=1);

namespace Drupal\Tests\localgov_elections_constituency_provider\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests autocompletion functionality.
 *
 * @group localgov_elections_constituency_provider
 */
final class AutocompleteTest extends WebDriverTestBase {

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
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;


  /**
   * Provider config entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $provider;


  /**
   * An election node.
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
      'type' => "election",
    ]);

    $this->election->save();
  }

  /**
   * Tests that that autocomplete field shows options correctly.
   */
  public function testAutocompleteFieldShowsOptions() {
    // Navigate to the homepage.
    $id = $this->election->id();
    $this->drupalGet("/node/$id/boundary-fetch");

    // Find the autocomplete input element by its ID or CSS selector.
    $assert_session = $this->assertSession();
    // Simulate typing into the autocomplete field.
    $autocomplete_field = $assert_session->waitForElement('css', '#edit-plugin-config-constituencies');
    $autocomplete_field->setValue('Edinburgh S');
    $this->getSession()->getDriver()->keyDown($autocomplete_field->getXpath(), ' ');
    $assert_session->waitOnAutocomplete();

    $suggestions = $this->getSession()->getPage()->findAll('css', '.ui-autocomplete li');
    $page = $this->getSession()->getPage();
    $results = $page->findAll('css', '.ui-autocomplete li');

    $this->assertCount(2, $results);
    foreach ($suggestions as $suggestion) {
      $this->assertStringContainsString("Edinburgh S", $suggestion->getText());
    }
  }

}
