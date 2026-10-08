<?php

namespace Drupal\ap_genesys_cloud_chat;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Finds the Chat Icon Block placements that use a deployment profile.
 *
 * Core's Block config entity doesn't implement onDependencyRemoval(), so
 * deleting a profile a block depends on would silently delete that block
 * placement along with it. The deployment delete form and list builder
 * use this to show (and guard against) that before it happens.
 */
class DeploymentUsage {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a DeploymentUsage.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Gets the block placements that reference a deployment profile.
   *
   * @param string $deployment_id
   *   The deployment profile's machine name.
   *
   * @return \Drupal\block\BlockInterface[]
   *   The referencing block config entities, keyed by block ID.
   */
  public function getBlocks($deployment_id) {
    if (!$this->entityTypeManager->hasDefinition('block')) {
      return [];
    }
    $blocks = $this->entityTypeManager->getStorage('block')->loadByProperties([
      'plugin' => 'ap_genesys_cloud_block',
    ]);
    return array_filter($blocks, function ($block) use ($deployment_id) {
      return ($block->get('settings')['deployment'] ?? '') === $deployment_id;
    });
  }

}
