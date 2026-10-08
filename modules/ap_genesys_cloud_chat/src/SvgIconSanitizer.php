<?php

namespace Drupal\ap_genesys_cloud_chat;

use enshrined\svgSanitize\Sanitizer;

/**
 * Strips executable content from an uploaded SVG chat icon.
 *
 * A thin wrapper around enshrined/svg-sanitize, kept as its own class (no
 * Drupal service dependencies) so it can be exercised by a plain Unit test,
 * the same way ColorContrast is.
 */
class SvgIconSanitizer {

  /**
   * Sanitizes raw SVG markup.
   *
   * @param string $svg
   *   The raw, untrusted SVG file contents.
   *
   * @return string|false
   *   The sanitized SVG markup, or FALSE if the input couldn't be safely
   *   processed (e.g. a custom DOCTYPE/DTD entity) and should be rejected
   *   outright rather than saved in any form.
   */
  public static function sanitize(string $svg) {
    $sanitizer = new Sanitizer();
    // Strips <script>, event-handler attributes, javascript: hrefs (xlink
    // and plain), and other executable content by default; this only adds
    // the opt-in check for externally-referenced content (e.g. a remote
    // <image>/<use> href), which the library doesn't strip unless asked.
    $sanitizer->removeRemoteReferences(TRUE);

    return $sanitizer->sanitize($svg);
  }

}
