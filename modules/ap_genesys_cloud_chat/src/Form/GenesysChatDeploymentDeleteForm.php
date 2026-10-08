<?php

namespace Drupal\ap_genesys_cloud_chat\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ap_genesys_cloud_chat\DeploymentUsage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delete form for a Genesys deployment profile.
 *
 * Refuses to delete a profile while any Chat Icon Block placement still
 * uses it. Each such block declares a config dependency on its profile,
 * and core's Block entity doesn't implement onDependencyRemoval(), so
 * deleting the profile would otherwise silently delete those block
 * placements too.
 */
class GenesysChatDeploymentDeleteForm extends EntityDeleteForm {

  /**
   * The deployment usage finder.
   *
   * @var \Drupal\ap_genesys_cloud_chat\DeploymentUsage
   */
  protected $deploymentUsage;

  /**
   * Constructs a GenesysChatDeploymentDeleteForm.
   *
   * @param \Drupal\ap_genesys_cloud_chat\DeploymentUsage $deployment_usage
   *   The deployment usage finder.
   */
  public function __construct(DeploymentUsage $deployment_usage) {
    $this->deploymentUsage = $deployment_usage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ap_genesys_cloud_chat.deployment_usage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $blocks = $this->deploymentUsage->getBlocks($this->entity->id());
    if ($blocks) {
      $items = [];
      foreach ($blocks as $block) {
        $items[] = $this->t('@label (@theme theme)', [
          '@label' => $block->label(),
          '@theme' => $block->getTheme(),
        ]);
      }
      $form['in_use'] = [
        '#theme' => 'item_list',
        '#title' => $this->t('This deployment cannot be deleted while these Chat Icon Block placements use it. Select a different deployment in each block, or remove the blocks, first:'),
        '#items' => $items,
        '#weight' => -10,
      ];
      $form['description']['#access'] = FALSE;
      $form['entity_updates']['#access'] = FALSE;
      $form['entity_deletes']['#access'] = FALSE;
      $form['actions']['submit']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // Re-checked here as well, in case a block started using this
    // deployment after the confirmation page was loaded.
    if ($this->deploymentUsage->getBlocks($this->entity->id())) {
      $form_state->setErrorByName('', $this->t('This deployment is in use by one or more Chat Icon Block placements and cannot be deleted.'));
    }
  }

}
