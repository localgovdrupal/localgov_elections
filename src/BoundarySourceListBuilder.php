<?php

declare(strict_types=1);

namespace Drupal\localgov_elections;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\localgov_elections\Form\BounaryProviderAddForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of boundary sources.
 */
final class BoundarySourceListBuilder extends ConfigEntityListBuilder {

  /**
   * Form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, FormBuilderInterface $form_builder) {
    parent::__construct($entity_type, $storage);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
        $entity_type,
        $container->get('entity_type.manager')->getStorage($entity_type->id()),
        $container->get('form_builder')
    );
  }

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
    /** @var \Drupal\localgov_elections\BoundarySourceInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    $row['plugin'] = $entity->getPlugin()->getPluginDefinition()['id'];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritDoc}
   */
  public function render() {
    $build['provider_entity_create_form'] = $this->formBuilder->getForm(BounaryProviderAddForm::class);
    $build['provider_header']['#markup'] = '<br><h3>' . $this->t('Available Boundary Providers') . '</h3>';
    $build['provider_entity_list'] = parent::render();
    return $build;
  }

}
