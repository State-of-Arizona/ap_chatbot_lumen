<?php

namespace Drupal\ap_genesys_cloud\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;

/**
 * Install-time requirements for Drupal 11.2+.
 *
 * Replaces ap_genesys_cloud_requirements('install') on newer cores; see that
 * function for why the check exists. Uses the same requirement key, so the
 * two never show as separate rows. Drupal 9 and 10 never load this class.
 */
class GenesysCloudRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    // Self-contained: this module's other classes aren't autoloadable
    // before it's installed.
    if (class_exists('enshrined\svgSanitize\Sanitizer')) {
      return [];
    }
    return [
      'ap_genesys_cloud_svg_sanitize' => [
        'title' => t('Genesys Cloud/API: SVG sanitizer library'),
        'description' => t('The Genesys Cloud/API module needs the enshrined/svg-sanitize PHP library to make uploaded SVG chat icons safe. Install the module with Composer, which adds the library automatically; run composer require enshrined/svg-sanitize in the site Composer project; or manually install this library.'),
        'severity' => RequirementSeverity::Error,
      ],
    ];
  }

}
