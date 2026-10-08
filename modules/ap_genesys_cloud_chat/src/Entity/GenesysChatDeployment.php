<?php

namespace Drupal\ap_genesys_cloud_chat\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ap_genesys_cloud_chat\Form\GenesysChatDeploymentBrandingForm;
use Drupal\ap_genesys_cloud_chat\Form\GenesysChatDeploymentDeleteForm;
use Drupal\ap_genesys_cloud_chat\Form\GenesysChatDeploymentForm;
use Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentInterface;
use Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentListBuilder;

/**
 * Defines the Genesys deployment profile config entity.
 *
 * Each profile holds one Genesys Cloud web messaging deployment and how
 * its chat icon/popup look. Every Chat Icon Block placement selects one.
 *
 * Declared both as a PHP attribute (read by Drupal 10.2+, where the
 * annotation is deprecated) and as an annotation (read by Drupal 9, which
 * ignores the attribute). Keep the two in sync; drop the annotation when
 * Drupal 9 support ends.
 *
 * @ConfigEntityType(
 *   id = "ap_genesys_deployment",
 *   label = @Translation("Chat deployment"),
 *   label_collection = @Translation("Chat deployments"),
 *   label_singular = @Translation("chat deployment"),
 *   label_plural = @Translation("chat deployments"),
 *   label_count = @PluralTranslation(
 *     singular = "@count chat deployment",
 *     plural = "@count chat deployments",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ap_genesys_cloud_chat\Form\GenesysChatDeploymentForm",
 *       "edit" = "Drupal\ap_genesys_cloud_chat\Form\GenesysChatDeploymentForm",
 *       "branding" = "Drupal\ap_genesys_cloud_chat\Form\GenesysChatDeploymentBrandingForm",
 *       "delete" = "Drupal\ap_genesys_cloud_chat\Form\GenesysChatDeploymentDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "deployment",
 *   admin_permission = "administer ap genesys cloud settings",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "collection" = "/admin/config/services/ap-genesys-cloud/chat",
 *     "add-form" = "/admin/config/services/ap-genesys-cloud/chat/add",
 *     "edit-form" = "/admin/config/services/ap-genesys-cloud/chat/manage/{ap_genesys_deployment}",
 *     "branding-form" = "/admin/config/services/ap-genesys-cloud/chat/manage/{ap_genesys_deployment}/branding",
 *     "delete-form" = "/admin/config/services/ap-genesys-cloud/chat/manage/{ap_genesys_deployment}/delete",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "environment_name",
 *     "deployment_id",
 *     "bootstrap_url",
 *     "custom_fields",
 *     "chat_icon",
 *     "icon_alttext",
 *     "brand_color",
 *     "text_color",
 *     "use_theme_colors",
 *     "custom_css_class",
 *     "genesys_manages_launcher",
 *   },
 * )
 */
#[ConfigEntityType(
  id: 'ap_genesys_deployment',
  label: new TranslatableMarkup('Chat deployment'),
  label_collection: new TranslatableMarkup('Chat deployments'),
  label_singular: new TranslatableMarkup('chat deployment'),
  label_plural: new TranslatableMarkup('chat deployments'),
  config_prefix: 'deployment',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'list_builder' => GenesysChatDeploymentListBuilder::class,
    'form' => [
      'add' => GenesysChatDeploymentForm::class,
      'edit' => GenesysChatDeploymentForm::class,
      'branding' => GenesysChatDeploymentBrandingForm::class,
      'delete' => GenesysChatDeploymentDeleteForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/config/services/ap-genesys-cloud/chat',
    'add-form' => '/admin/config/services/ap-genesys-cloud/chat/add',
    'edit-form' => '/admin/config/services/ap-genesys-cloud/chat/manage/{ap_genesys_deployment}',
    'branding-form' => '/admin/config/services/ap-genesys-cloud/chat/manage/{ap_genesys_deployment}/branding',
    'delete-form' => '/admin/config/services/ap-genesys-cloud/chat/manage/{ap_genesys_deployment}/delete',
  ],
  admin_permission: 'administer ap genesys cloud settings',
  label_count: [
    'singular' => '@count chat deployment',
    'plural' => '@count chat deployments',
  ],
  config_export: [
    'id',
    'label',
    'environment_name',
    'deployment_id',
    'bootstrap_url',
    'custom_fields',
    'chat_icon',
    'icon_alttext',
    'brand_color',
    'text_color',
    'use_theme_colors',
    'custom_css_class',
    'genesys_manages_launcher',
  ],
)]
class GenesysChatDeployment extends ConfigEntityBase implements GenesysChatDeploymentInterface {

  /**
   * The machine name.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Genesys environment name.
   *
   * @var string
   */
  protected $environment_name = '';

  /**
   * The Genesys deployment ID.
   *
   * @var string
   */
  protected $deployment_id = '';

  /**
   * The Genesys bootstrap script URL.
   *
   * @var string
   */
  protected $bootstrap_url = '';

  /**
   * The lead-capture form fields.
   *
   * @var array
   */
  protected $custom_fields = [];

  /**
   * The uploaded chat icon file ID, as a single-item list (or empty).
   *
   * @var int[]
   */
  protected $chat_icon = [];

  /**
   * The chat icon alt text.
   *
   * @var string
   */
  protected $icon_alttext = '';

  /**
   * The button and header colour (hex).
   *
   * @var string
   */
  protected $brand_color = self::DEFAULT_BRAND_COLOR;

  /**
   * The icon and text colour (hex).
   *
   * @var string
   */
  protected $text_color = self::DEFAULT_TEXT_COLOR;

  /**
   * Whether the theme supplies colours instead of brand/text colour.
   *
   * @var bool
   */
  protected $use_theme_colors = FALSE;

  /**
   * An additional CSS class for the chat icon and popup.
   *
   * @var string
   */
  protected $custom_css_class = '';

  /**
   * Whether Genesys Cloud's own launcher replaces this module's icon/popup.
   *
   * @var bool
   */
  protected $genesys_manages_launcher = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentName() {
    return (string) $this->environment_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeploymentId() {
    return (string) $this->deployment_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getBootstrapUrl() {
    return (string) $this->bootstrap_url;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomFields() {
    return is_array($this->custom_fields) ? $this->custom_fields : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getChatIcon() {
    return is_array($this->chat_icon) ? array_values(array_map('intval', $this->chat_icon)) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getIconAltText() {
    return (string) $this->icon_alttext;
  }

  /**
   * {@inheritdoc}
   */
  public function getBrandColor() {
    return (string) $this->brand_color;
  }

  /**
   * {@inheritdoc}
   */
  public function getTextColor() {
    return (string) $this->text_color;
  }

  /**
   * {@inheritdoc}
   */
  public function useThemeColors() {
    return (bool) $this->use_theme_colors;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomCssClass() {
    return (string) $this->custom_css_class;
  }

  /**
   * {@inheritdoc}
   */
  public function genesysManagesLauncher() {
    return (bool) $this->genesys_manages_launcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getFileUsageType() {
    return $this->getConfigDependencyName();
  }

  /**
   * {@inheritdoc}
   *
   * Releases each deleted profile's chat icon, and deletes the file once
   * nothing else uses it. Permanent files are never garbage-collected by
   * file_cron(), so this has to happen here. Uninstalling the module
   * deletes its config entities, so this also covers uninstall.
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    if (!\Drupal::hasService('file.usage')) {
      return;
    }
    $file_storage = \Drupal::entityTypeManager()->getStorage('file');
    $file_usage = \Drupal::service('file.usage');
    /** @var \Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentInterface $entity */
    foreach ($entities as $entity) {
      foreach ($entity->getChatIcon() as $fid) {
        if ($file = $file_storage->load($fid)) {
          $file_usage->delete($file, 'ap_genesys_cloud_chat', $entity->getFileUsageType(), self::FILE_USAGE_ID);
          if (!$file_usage->listUsage($file)) {
            $file->delete();
          }
        }
      }
    }
  }

}
