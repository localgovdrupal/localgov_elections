<?php declare(strict_types = 1);

namespace Drupal\localgov_elections_reporting;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of boundary sources.
 */
final class BoundarySourceListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['status'] = $this->t('Status');
    $header['plugin'] = $this->t('Plugin');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\localgov_elections_reporting\BoundarySourceInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    $row['plugin'] = $entity->getPlugin()->getPluginDefinition()['id'];

    return $row + parent::buildRow($entity);
  }

}
