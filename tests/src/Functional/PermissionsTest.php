<?php

declare(strict_types=1);

namespace Drupal\Tests\localgov_elections\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test permissions for election module.
 *
 * @group localgov_elections
 */
final class PermissionsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'localgov_base';

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  protected $strictConfigSchema = FALSE;
  // @codingStandardsIgnoreEnd

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['localgov_elections', 'localgov_elections_demo_content'];

  /**
   * Test the visibility of the add area local task.
   */
  public function testAddAreaLocalTaskVisibility(): void {

    $this->drupalPlaceBlock('local_tasks_block', [
      'region' => 'content',
    ]);

    // Anonymous user.
    $this->drupalGet('/election/general-election-july-2024');
    $this->assertSession()->linkNotExists('Add areas');

    // A role who cannot access the button.
    $user = $this->drupalCreateUser();
    $user->addRole('localgov_editor');
    $this->drupalLogin($user);
    $this->drupalGet('/election/general-election-july-2024');
    $this->assertSession()->linkNotExists('Add areas');
    $this->drupalLogout();

    // Election officer.
    $election_officer = $this->drupalCreateUser(['can fetch boundaries']);
    $election_officer->addRole('localgov_elections_officer');
    $this->drupalLogin($election_officer);
    $this->drupalGet('/election/general-election-july-2024');
    $this->assertSession()->linkExists('Add areas');
  }

}
