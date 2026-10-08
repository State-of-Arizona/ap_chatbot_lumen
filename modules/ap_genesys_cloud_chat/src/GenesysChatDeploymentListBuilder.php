<?php

namespace Drupal\ap_genesys_cloud_chat;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists Genesys deployment profiles.
 */
class GenesysChatDeploymentListBuilder extends ConfigEntityListBuilder {

  /**
   * The deployment usage finder.
   *
   * @var \Drupal\ap_genesys_cloud_chat\DeploymentUsage
   */
  protected $deploymentUsage;

  /**
   * Constructs a GenesysChatDeploymentListBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   * @param \Drupal\ap_genesys_cloud_chat\DeploymentUsage $deployment_usage
   *   The deployment usage finder.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, DeploymentUsage $deployment_usage) {
    parent::__construct($entity_type, $storage);
    $this->deploymentUsage = $deployment_usage;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('ap_genesys_cloud_chat.deployment_usage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['deployment_id'] = $this->t('Deployment ID');
    $header['environment_name'] = $this->t('Environment');
    $header['blocks'] = $this->t('Used by blocks');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentInterface $entity */
    $block_labels = array_map(function ($block) {
      return $block->label();
    }, $this->deploymentUsage->getBlocks($entity->id()));

    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['deployment_id'] = $entity->getDeploymentId();
    $row['environment_name'] = $entity->getEnvironmentName();
    $row['blocks'] = $block_labels ? implode(', ', $block_labels) : $this->t('None');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('No chat deployments yet. Add one with the details your chat vendor provided, then select it in a Chat Icon Block placement.');
    // The "Used by blocks" column changes whenever a block placement does.
    $build['table']['#cache']['tags'][] = 'config:block_list';
    return $build;
  }

}
