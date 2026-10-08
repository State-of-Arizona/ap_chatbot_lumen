<?php

namespace Drupal\ap_genesys_cloud\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Runtime requirements (Status report) for Drupal 11.2+.
 *
 * Replaces ap_genesys_cloud_requirements('runtime') on newer cores; see that
 * function for why the check exists. Uses the same requirement key. Drupal 9
 * and 10 don't discover hook classes.
 */
class GenesysCloudRequirementsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $available = class_exists('enshrined\svgSanitize\Sanitizer');
    $requirement = [
      'title' => $this->t('Genesys Cloud/API: SVG sanitizer library'),
      'value' => $available ? $this->t('Installed') : $this->t('Missing'),
      'severity' => $available ? RequirementSeverity::OK : RequirementSeverity::Error,
    ];
    if (!$available) {
      $requirement['description'] = $this->t('The Genesys Cloud/API module needs the enshrined/svg-sanitize PHP library to make uploaded SVG chat icons safe. Install the module with Composer, which adds the library automatically; run composer require enshrined/svg-sanitize in the site Composer project; or manually install this library.');
    }
    return ['ap_genesys_cloud_svg_sanitize' => $requirement];
  }

}
