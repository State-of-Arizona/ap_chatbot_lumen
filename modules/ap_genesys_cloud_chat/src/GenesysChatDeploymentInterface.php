<?php

namespace Drupal\ap_genesys_cloud_chat;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for a Genesys deployment profile.
 *
 * A deployment profile holds one Genesys Cloud web messaging deployment
 * (environment, deployment ID, bootstrap URL, its lead-capture form
 * fields) and its branding (icon, colours, CSS class). Every Chat Icon
 * Block placement selects one, so a site with more than one deployment
 * can assign each to a different area.
 */
interface GenesysChatDeploymentInterface extends ConfigEntityInterface {

  /**
   * Regex a custom field's Field ID must match before it is trusted.
   *
   */
  const FIELD_ID_PATTERN = '/^-?[_a-zA-Z][_a-zA-Z0-9-]*$/';

  /**
   * The default brand colour for a new profile.
   *
   * A neutral, unbranded value, this module isn't tied to any one
   * agency's brand colours. Must match GenesysChatBlock::DEFAULT_BRAND_COLOR.
   */
  const DEFAULT_BRAND_COLOR = '#257976';

  /**
   * The default icon/text colour shown over the brand colour.
   */
  const DEFAULT_TEXT_COLOR = '#ffffff';

  /**
   * The file_usage "id" a profile records its chat icon under.
   *
   * The file_usage "id" column is an integer, so a config entity's string
   * ID can't go there; getFileUsageType() carries the profile instead. This
   * must not be 0: core's file usage delete() only narrows to a type when
   * the id is also non-empty, so with 0 one profile releasing its icon
   * would release every profile's usage of that file.
   */
  const FILE_USAGE_ID = 1;

  /**
   * Gets the Genesys environment name.
   *
   * @return string
   *   The environment name, e.g. 'fedramp-use2-core'.
   */
  public function getEnvironmentName();

  /**
   * Gets the Genesys deployment ID.
   *
   * @return string
   *   The deployment ID.
   */
  public function getDeploymentId();

  /**
   * Gets the Genesys bootstrap script URL.
   *
   * @return string
   *   The bootstrap script URL.
   */
  public function getBootstrapUrl();

  /**
   * Gets the lead-capture form fields.
   *
   * @return array
   *   A list of custom field definitions, each with 'type', 'label',
   *   'required', 'id', 'mapping', 'options' and 'weight' keys.
   */
  public function getCustomFields();

  /**
   * Gets the uploaded chat icon.
   *
   * @return int[]
   *   A single-item list holding the file ID, or an empty list for the
   *   module's default icon.
   */
  public function getChatIcon();

  /**
   * Gets the chat icon alt text.
   *
   * @return string
   *   The alt text, or '' for the module's default wording.
   */
  public function getIconAltText();

  /**
   * Gets the button and header colour.
   *
   * @return string
   *   The stored hex colour. Not trusted for output on its own; see
   *   GenesysChatBlock::sanitizeColor().
   */
  public function getBrandColor();

  /**
   * Gets the icon and text colour.
   *
   * @return string
   *   The stored hex colour. Not trusted for output on its own; see
   *   GenesysChatBlock::sanitizeColor().
   */
  public function getTextColor();

  /**
   * Whether the theme supplies colours instead of this profile.
   *
   * @return bool
   *   TRUE to inject no colour override at all.
   */
  public function useThemeColors();

  /**
   * Gets the additional CSS class for the chat icon and popup.
   *
   * @return string
   *   The stored class. Not trusted for output on its own; see
   *   GenesysChatBlock::sanitizeCssClass().
   */
  public function getCustomCssClass();

  /**
   * Whether Genesys Cloud's own launcher replaces this module's icon/popup.
   *
   * @return bool
   *   TRUE to render only the Genesys bootstrap script.
   */
  public function genesysManagesLauncher();

  /**
   * Gets the file usage "type" this profile records its chat icon under.
   *
   * The file_usage table's "id" column is an integer, so a config entity's
   * string ID can't go there; the profile's config name is used as the
   * type instead, with self::FILE_USAGE_ID as the id.
   *
   * @return string
   *   E.g. 'ap_genesys_cloud_chat.deployment.benefits'.
   */
  public function getFileUsageType();

}
