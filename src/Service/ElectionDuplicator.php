<?php

namespace Drupal\localgov_elections\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Provides an election duplication service
 */
class ElectionDuplicator {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Duplicate an election node and all related entities.
   *
   * @param \Drupal\node\Entity\Node $election
   *   The original election node.
   *
   * @return \Drupal\node\Entity\Node
   *   The newly created election node.
   */
  public function duplicateElection(Node $election): Node {
    if ($election->bundle() !== 'localgov_election') {
      throw new \InvalidArgumentException('Node must be a localgov_election.');
    }

    // Clone the election node itself.
    $new_election = $election->createDuplicate();
    $new_election->setTitle($election->getTitle() . ' (Copy)');
    $new_election->setUnpublished();
    $new_election->save();

    // Load area votes linked to this election.
    $area_votes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'localgov_area_vote',
      'localgov_election' => $election->id(),
    ]);

    foreach ($area_votes as $area_vote) {
      $this->duplicateAreaVote($area_vote, $new_election->id());
    }

    return $new_election;
  }

  /**
   * Duplicate a localgov_area_vote node and its paragraphs.
   */
  protected function duplicateAreaVote(Node $area_vote, int $new_election_id): Node {
    $new_area_vote = $area_vote->createDuplicate();
    // Point to new election.
    $new_area_vote->set('localgov_election', $new_election_id);

    // Duplicate paragraphs.
    $paragraph_fields = [
      'localgov_election_candidates',
      'localgov_election_seats',
    ];

    foreach ($paragraph_fields as $field_name) {
      $new_paragraphs = [];
      foreach ($area_vote->get($field_name) as $item) {
        /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
        $paragraph = $item->entity;
        if ($paragraph) {
          $new_paragraphs[] = $this->duplicateParagraph($paragraph);
        }
      }
      $new_area_vote->set($field_name, $new_paragraphs);
    }

    $new_area_vote->save();

    return $new_area_vote;
  }

  /**
   * Duplicate a paragraph and any nested entities (eg candidate in uncontested seats).
   */
  protected function duplicateParagraph(Paragraph $paragraph): Paragraph {
    $new_paragraph = $paragraph->createDuplicate();

    // Handle nested paragraph references (example: seat → candidate).
    if ($paragraph->hasField('localgov_candidate_uncontested')) {
      $new_candidates = [];
      foreach ($paragraph->get('localgov_candidate_uncontested') as $item) {
        $candidate = $item->entity;
        if ($candidate) {
          $new_candidates[] = $this->duplicateParagraph($candidate);
        }
      }
      $new_paragraph->set('localgov_candidate_uncontested', $new_candidates);
    }

    $new_paragraph->save();
    return $new_paragraph;
  }

}
