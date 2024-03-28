<?php


namespace Drupal\localgov_elections_reporting\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for building the boundary-source instance add form.
 */
class BoundarySourceAddController extends ControllerBase
{
  /**
   * Constructor for the controller.
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager)
  {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Controller callback.
   */
  public function __invoke($plugin_id)
  {
    $entity = $this->entityTypeManager->getStorage('boundary_source')->create(['plugin' => $plugin_id]);
    return $this->entityFormBuilder()->getForm($entity);
  }

}