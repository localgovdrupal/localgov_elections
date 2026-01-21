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
    $form['#validate'][] = [$this, 'validateSeatsAndCandidates'];
    $form['#validate'][] = [$this, 'validateAreaVoteForm'];
    $form['#validate'][] = [$this, 'validateContestedCandidates'];

    // Configure individual field behaviors.
    $this->configureHoldGainField($form, $form_state);
    $this->configureFinalisedDateField($form, $form_state);
    $this->configureSeatSummaries($form, $form_state);
    $this->configureUncontestedStates($form, $form_state);

    // Attach form library.
    $form['#attached']['library'][] = 'localgov_elections/localgov_elections_form';
  }

  /**
   * Validate area vote form seats and candidates.
   */
  public function validateSeatsAndCandidates(array &$form, FormStateInterface $form_state): void {
    $seats_values = $form_state->getValue('localgov_election_seats');

    if (!is_array($seats_values)) {
      return;
    }

    foreach ($seats_values as $delta => $seat_data) {
      if (!is_numeric($delta) || !is_array($seat_data)) {
        continue;
      }

      // New seats have subform, existing seats don't.
      if (!isset($seat_data['subform']['localgov_candidate_uncontested'])) {
        continue;
      }

      $candidates = $seat_data['subform']['localgov_candidate_uncontested'];

      if (!is_array($candidates)) {
        continue;
      }

      foreach ($candidates as $cand_delta => $candidate_data) {
        if (!is_numeric($cand_delta) || !is_array($candidate_data)) {
          continue;
        }

        if (!isset($candidate_data['subform'])) {
          continue;
        }

        $subform = $candidate_data['subform'];
        $has_name = !empty($subform['localgov_election_candidate'][0]['value'] ?? NULL);
        $has_party = !empty($subform['localgov_election_party']['target_id'] ?? NULL);

        if ($has_name && !$has_party) {
          $form_state->setError($form['localgov_election_seats'], $this->t('Uncontested candidate name requires a party to be specified.'));
          return;
        }
        if ($has_party && !$has_name) {
          $form_state->setError($form['localgov_election_seats'], $this->t('Uncontested candidate party requires a candidate name.'));
          return;
        }
      }
    }
  }

  /**
   * Validate area vote form.
   */
  public function validateAreaVoteForm(array &$form, FormStateInterface $form_state): void {
    $uncontested = $form_state->getValue('localgov_election_no_contest')['value'];
    $candidates = $form_state->getValue('localgov_election_candidates');
    $candidate_keys = array_filter(array_keys($candidates), function ($key) {
      return is_int($key);
    });

    // If an area is uncontested, no candidates should be associated with the
    // area node - they will be associated with each seat instead.
    if ($uncontested && count($candidate_keys) > 0) {
      $form_state->setErrorByName('localgov_election_candidates', $this->t("If the seat is <b>not</b> contested there should only be uncontested candidates assigned to seats."));
    }

    if ($uncontested) {
      $storage = $form_state->get('field_storage');
      if (isset($storage['#parents']['#fields']['localgov_election_candidates'])) {
        $candidates = $storage['#parents']['#fields']['localgov_election_candidates'];
      }
      if (count($candidate_keys) > 0) {
        $candidates = $candidates['paragraphs'];
        foreach ($candidates['paragraphs'] as $entry) {
          /** @var \Drupal\paragraphs\Entity\Paragraph $entry */
          if (isset($entry['entity'])) {
            $entry = $entry['entity'];
            if (!$entry->get('localgov_election_votes')->isEmpty()) {
              $form_state->setErrorByName('localgov_election_candidates', $this->t("If the seat is not contested there should be no votes registered with a candidate. The vote field should be empty."));
            }
          }
        }
      }
    }

    // Should not be able to finalise votes without at least one candidate.
    $finalised = $form_state->getValue('localgov_election_votes_final')['value'];
    if ($finalised && !$uncontested && count($candidate_keys) < 1) {
      $form_state->setErrorByName('localgov_election_candidates', $this->t("You cannot finalise the votes with no candidates"));
    }
  }

  /**
   * Validate candidates.
   */
  public function validateContestedCandidates(array &$form, FormStateInterface $form_state): void {
    $candidates_values = $form_state->getValue('localgov_election_candidates');

    if (!is_array($candidates_values)) {
      return;
    }

    foreach ($candidates_values as $delta => $candidate_data) {
      if (!is_numeric($delta) || !is_array($candidate_data)) {
        continue;
      }

      // Skip if no subform exists.
      if (!isset($candidate_data['subform'])) {
        continue;
      }

      $subform = $candidate_data['subform'];

      $forename = $subform['localgov_election_forename'][0]['value'] ?? '';
      $surname = $subform['localgov_election_candidate'][0]['value'] ?? '';
      $party = $subform['localgov_election_party']['target_id'] ?? '';

      // All three fields are required for contested candidates.
      if (empty($forename)) {
        $form_state->setError($form['localgov_election_candidates'], $this->t('Candidates require forename to be specified.'));
        return;
      }

      if (empty($surname)) {
        $form_state->setError($form['localgov_election_candidates'], $this->t('Candidates require surname to be specified.'));
        return;
      }

      if (empty($party)) {
        $form_state->setError($form['localgov_election_candidates'], $this->t('Candidates require party to be specified.'));
        return;
      }
    }
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
