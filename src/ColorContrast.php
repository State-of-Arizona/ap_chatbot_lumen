<?php

namespace Drupal\ap_genesys_cloud;

/**
 * WCAG relative-luminance contrast ratio calculations.
 *
 * Pure math, no Drupal dependencies.
 *
 * @todo Provide an APCA validation checker method.
 */
class ColorContrast {

  /**
   * The minimum ratio WCAG 2.2 AA requires for normal-sized text (1.4.3).
   *
   * Used as the fallback if a stored/configured WCAG level isn't one of
   * the recognized keys in self::LEVEL_RATIOS.
   */
  const MIN_RATIO_AA = 4.5;

  /**
   * Minimum contrast ratios per WCAG conformance level (1.4.3 / 1.4.6).
   *
   * These are the same numbers in WCAG 2.0, 2.1, and 2.2.
   * Level A has no numeric text-contrast success criterion at all, hence NULL.
   */
  const LEVEL_RATIOS = [
    'A' => NULL,
    'AA' => 4.5,
    'AAA' => 7.0,
  ];

  /**
   * Resolves the minimum contrast ratio for a WCAG conformance level.
   *
   * @param string $level
   *   'A', 'AA', or 'AAA'.
   *
   * @return float|null
   *   The minimum ratio, or NULL if the level has no numeric contrast
   *   requirement (WCAG Level A) — callers should treat NULL as "nothing
   *   to check," not as an error. An unrecognized level falls back to
   *   the AA minimum rather than silently skipping the check.
   */
  public static function minRatioForLevel(string $level): ?float {
    return array_key_exists($level, self::LEVEL_RATIOS) ? self::LEVEL_RATIOS[$level] : self::MIN_RATIO_AA;
  }

  /**
   * Computes the WCAG contrast ratio between two colours.
   *
   * @param string $hex_a
   *   A strict 6-digit hex colour, e.g. '#4A5568'.
   * @param string $hex_b
   *   A strict 6-digit hex colour, e.g. '#ffffff'.
   *
   * @return float
   *   The contrast ratio, from 1 (no contrast) to 21 (black on white).
   */
  public static function ratio(string $hex_a, string $hex_b): float {
    $luminance_a = self::relativeLuminance($hex_a);
    $luminance_b = self::relativeLuminance($hex_b);
    $lighter = max($luminance_a, $luminance_b);
    $darker = min($luminance_a, $luminance_b);

    return ($lighter + 0.05) / ($darker + 0.05);
  }

  /**
   * Computes a colour's relative luminance per the WCAG formula.
   *
   * @param string $hex
   *   A strict 6-digit hex colour, e.g. '#4A5568'.
   *
   * @return float
   *   The relative luminance, from 0 (black) to 1 (white).
   */
  protected static function relativeLuminance(string $hex): float {
    $channels = array_map(function (string $channel): float {
      $value = hexdec($channel) / 255;
      return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, str_split(ltrim($hex, '#'), 2));

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
  }

}
