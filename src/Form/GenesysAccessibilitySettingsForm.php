<?php

namespace Drupal\ap_genesys_cloud\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures how strictly this site enforces the colour-contrast check.
 */
class GenesysAccessibilitySettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a GenesysAccessibilitySettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config_manager, EntityTypeManagerInterface $entity_type_manager) {
    // ConfigFormBase takes the typed config manager since Drupal 10.2, and
    // requires it in Drupal 11. Drupal 9's constructor only takes the
    // config factory and ignores the extra argument.
    parent::__construct($config_factory, $typed_config_manager);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ap_genesys_cloud.accessibility'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ap_genesys_cloud_accessibility_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ap_genesys_cloud.accessibility');

    // Profiles where Genesys Cloud's own launcher stands in for this
    // module's icon/popup aren't affected by anything on this page.
    // Chat deployments come from the chat submodule, which is normally
    // installed alongside this module but is checked for rather than
    // assumed, since this form is shared by every feature.
    $launcher_links = [];
    $deployments = $this->entityTypeManager->hasDefinition('ap_genesys_deployment')
      ? $this->entityTypeManager->getStorage('ap_genesys_deployment')->loadMultiple()
      : [];
    foreach ($deployments as $deployment) {
      if ($deployment->genesysManagesLauncher()) {
        $launcher_links[] = Link::fromTextAndUrl($deployment->label(), $deployment->toUrl('branding-form'))->toRenderable();
      }
    }
    if ($launcher_links) {
      $form['genesys_manages_launcher_notice'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'message' => [
          '#markup' => $this->t('Genesys Cloud is managing the chat launcher for these chat deployments (see the Branding tab of each one), so the settings below do not apply to anything visitors see for them. This module is not rendering its own icon or popup there. If the launcher shown by Genesys Cloud has a contrast conflict, that needs to be reviewed and corrected in Genesys Cloud Admin directly; it cannot be fixed from the module.'),
        ],
        'deployments' => [
          '#theme' => 'item_list',
          '#items' => $launcher_links,
        ],
      ];
    }

    $form['contrast_enforcement'] = [
      '#type' => 'radios',
      '#title' => $this->t('Color contrast enforcement'),
      '#description' => $this->t('Applies to every chat deployment. Controls what happens on each chat deployment Branding tab when Button & Header Color and Icon & Text Color do not have enough contrast against each other.'),
      '#options' => [
        'none' => $this->t('Off (do not check colour contrast at all)'),
        'warning' => $this->t('Warn (save anyway, but flag the issue)'),
        'block' => $this->t('Block (refuse to save until the target ratio is met)'),
      ],
      '#default_value' => $config->get('contrast_enforcement') ?: 'warning',
    ];

    $form['wcag_level'] = [
      '#type' => 'radios',
      '#title' => $this->t('Target WCAG conformance level'),
      // WCAG 2.1 and 2.2 use identical colour-contrast numbers, so a single choice covers both versions
      // Level A isn't offered: WCAG has no minimum colour-contrast requirement at that level at
      // all, so there would be nothing for the enforcement mode above to check.
      '#description' => $this->t('AA requires 4.5:1 contrast; AAA requires 7:1.'),
      '#options' => [
        'AA' => $this->t('2.1/2.2 AA'),
        'AAA' => $this->t('2.1/2.2 AAA'),
      ],
      '#default_value' => $config->get('wcag_level') ?: 'AA',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('ap_genesys_cloud.accessibility')
      ->set('contrast_enforcement', $form_state->getValue('contrast_enforcement'))
      ->set('wcag_level', $form_state->getValue('wcag_level'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
