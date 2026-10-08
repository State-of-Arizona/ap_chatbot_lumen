<?php

namespace Drupal\Tests\ap_genesys_cloud_chat\Unit;

use Drupal\ap_genesys_cloud_chat\SvgIconSanitizer;
use Drupal\Tests\UnitTestCase;

/**
 * Tests SvgIconSanitizer's use of the enshrined/svg-sanitize library.
 *
 * @group ap_genesys_cloud
 * @coversDefaultClass \Drupal\ap_genesys_cloud_chat\SvgIconSanitizer
 */
class SvgIconSanitizerTest extends UnitTestCase {

  /**
   * A benign SVG passes through with its visible content intact.
   */
  public function testBenignSvgIsPreserved() {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#4A5568"/></svg>';
    $clean = SvgIconSanitizer::sanitize($svg);

    $this->assertNotFalse($clean);
    $this->assertStringContainsString('<circle', $clean);
    $this->assertStringContainsString('fill="#4A5568"', $clean);
  }

  /**
   * An embedded <script> is stripped, not merely inert.
   */
  public function testEmbeddedScriptIsStripped() {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script><circle cx="5" cy="5" r="4"/></svg>';
    $clean = SvgIconSanitizer::sanitize($svg);

    $this->assertNotFalse($clean);
    $this->assertStringNotContainsString('<script', $clean);
    $this->assertStringNotContainsString('alert(', $clean);
    // The rest of the document is still usable — sanitizing isn't the
    // same as rejecting the whole file.
    $this->assertStringContainsString('<circle', $clean);
  }

  /**
   * An onload handler attribute is stripped.
   */
  public function testEventHandlerAttributeIsStripped() {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><circle cx="5" cy="5" r="4"/></svg>';
    $clean = SvgIconSanitizer::sanitize($svg);

    $this->assertNotFalse($clean);
    $this->assertStringNotContainsString('onload', $clean);
  }

  /**
   * An SVG the library can't safely process at all is rejected outright.
   *
   * A custom DOCTYPE/DTD entity (classic XXE shape) is the one case this
   * library refuses to merely clean — sanitize() returns FALSE so the
   * caller can reject the upload entirely, rather than silently saving
   * whatever survived.
   */
  public function testUnparseableDoctypeSvgReturnsFalse() {
    $svg = '<?xml version="1.0" standalone="yes"?>'
      . '<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
      . '<svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>';

    $this->assertFalse(SvgIconSanitizer::sanitize($svg));
  }

  /**
   * Regression test for CVE-2025-55166.
   *
   * Versions of enshrined/svg-sanitize prior to 0.22.0 only checked the
   * lowercase attribute name "xlink:href" in cleanXlinkHrefs(), so a
   * mixed-case variant like "xlink:HrEf" bypassed the javascript: scheme
   * check entirely. This module pins ^1.0, well past the fix, but this
   * test exists to catch a future downgrade regressing the protection.
   */
  public function testCaseVariantXlinkHrefIsNeutralized() {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
      . '<image xlink:HrEf="javascript:alert(1)" /></svg>';
    $clean = SvgIconSanitizer::sanitize($svg);

    $this->assertNotFalse($clean);
    $this->assertStringNotContainsStringIgnoringCase('javascript:', $clean);
  }

}
