<?php

namespace Drupal\localgov_elections\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Helper service for configuring area vote forms.
 */
class AreaVoteFormHelper {

  use StringTranslationTrait;

  /**
   * Configure all aspects of the area vote form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function configureForm(array &$form, FormStateInterface $form_state): void {
    // Add validation.
    $form['#validate'][] = '_localgov_elections_area_vote_form_validation';

    // Configure individual field behaviors.
    $this->configureHoldGainField($form, $form_state);
    $this->configureFinalisedDateField($form, $form_state);
    $this->configureSeatSummaries($form, $form_state);
    $this->configureUncontestedStates($form, $form_state);

    // Attach form library.
    $form['#attached']['library'][] = 'localgov_elections/localgov_elections_form';
  }

  /**
   * Configure the hold/gain field visibility and help text.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function configureHoldGainField(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['localgov_election_hold_or_gain'])) {
      return;
    }

    // Add help text.
    $form['localgov_election_hold_or_gain']['widget']['#description'] = $this->t('Only applicable for single-seat areas. Leave as N/A for multi-seat areas or when outcome is not yet determined.');

    $node = $form_state->getFormObject()->getEntity();

    // Hide for multi-seat areas.
    if ($node->hasField('localgov_election_seats')) {
      $seat_count = $node->get('localgov_election_seats')->count();

      if ($seat_count > 1) {
        $form['localgov_election_hold_or_gain']['#access'] = FALSE;
        $form['localgov_election_hold_or_gain']['widget']['#access'] = FALSE;
      }
    }
  }

  /**
   * Configure the finalised date field visibility and requirements.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function configureFinalisedDateField(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['localgov_election_finalised_date'])) {
      return;
    }

    $form['localgov_election_finalised_date']['#states'] = [
      'visible' => [
        ':input[name="localgov_election_votes_final[value]"]' => ['checked' => TRUE],
      ],
      'required' => [
        ':input[name="localgov_election_votes_final[value]"]' => ['checked' => TRUE],
      ],
    ];
  }

  /**
   * Configure seat paragraph summaries to show uncontested status.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function configureSeatSummaries(array &$form, FormStateInterface $form_state): void {
    if (empty($form['localgov_election_seats']['widget'])) {
      return;
    }

    $node = $form_state->getFormObject()->getEntity();

    if (!$node instanceof NodeInterface) {
      return;
    }

    foreach ($node->get('localgov_election_seats')->referencedEntities() as $delta => $paragraph) {
      if ($paragraph->bundle() === 'localgov_area_seat' && $paragraph->get('localgov_seat_not_contested')->value) {
        if (isset($form['localgov_election_seats']['widget'][$delta]['top']['summary']['fields_info']['#summary']['content'][0])) {
          $summary = &$form['localgov_election_seats']['widget'][$delta]['top']['summary']['fields_info']['#summary']['content'];
          $summary[0] .= ', ' . $this->t('Not contested');
        }
      }
    }
  }

  /**
   * Configure states for uncontested candidate fields in seat paragraphs.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function configureUncontestedStates(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['localgov_election_seats']['widget'])) {
      return;
    }

    foreach ($form['localgov_election_seats']['widget'] as $delta => &$seat_element) {
      if (is_numeric($delta) && isset($seat_element['subform']['localgov_candidate_uncontested'])) {
        $seat_element['subform']['localgov_candidate_uncontested']['#states'] = [
          'visible' => [
            ':input[name="localgov_election_seats[' . $delta . '][subform][localgov_seat_not_contested][value]"]' => ['checked' => TRUE],
          ],
        ];
      }
    }
  }

}
