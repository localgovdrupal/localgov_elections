<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_reporting_social_post\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Election Social Integration settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'localgov_elections_reporting_social_post_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['localgov_elections_reporting_social_post.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['message_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Example'),
      '#description' => $this->t("This field supports tokens."),
      '#default_value' => $this->config('localgov_elections_reporting_social_post.settings')->get('message_template'),
    ];

    $form['token_tree'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['user', 'node'],
      '#show_restricted' => TRUE,
      '#weight' => 100,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('localgov_elections_reporting_social_post.settings')
      ->set('message_template', $form_state->getValue('message_template'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
