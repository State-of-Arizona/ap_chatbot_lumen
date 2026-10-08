<?php

namespace Drupal\Tests\ap_genesys_cloud\Unit;

use Drupal\ap_genesys_cloud\ColorContrast;
use Drupal\Tests\UnitTestCase;

/**
 * Tests ColorContrast's WCAG contrast ratio math.
 *
 * @group ap_genesys_cloud
 * @coversDefaultClass \Drupal\ap_genesys_cloud\ColorContrast
 */
class ColorContrastTest extends UnitTestCase {

  /**
   * Black on white is the maximum possible ratio, 21:1.
   */
  public function testBlackOnWhiteIsMaximumRatio() {
    $this->assertEqualsWithDelta(21.0, ColorContrast::ratio('#000000', '#ffffff'), 0.01);
  }

  /**
   * Identical colours have no contrast at all: exactly 1:1.
   */
  public function testIdenticalColorsRatioIsOne() {
    $this->assertEqualsWithDelta(1.0, ColorContrast::ratio('#4a5568', '#4a5568'), 0.001);
  }

  /**
   * The ratio doesn't depend on which colour is passed first.
   */
  public function testArgumentOrderDoesNotMatter() {
    $this->assertEqualsWithDelta(
      ColorContrast::ratio('#4a5568', '#ffffff'),
      ColorContrast::ratio('#ffffff', '#4a5568'),
      0.0001
    );
  }

  /**
   * The module's own default colour pairing clears WCAG 2.2 AA.
   *
   * GenesysChatBlock falls back to this pairing whenever a stored one
   * doesn't clear the threshold (see GenesysChatBlock::buildColorStyle())
   * — if the default itself ever regressed below it, that fallback would
   * be pointless.
   */
  public function testDefaultBrandColorMeetsMinimumRatio() {
    $ratio = ColorContrast::ratio('#4A5568', '#ffffff');
    $this->assertGreaterThanOrEqual(ColorContrast::MIN_RATIO_AA, $ratio);
  }

  /**
   * A visibly low-contrast pairing must fall under the AA threshold.
   */
  public function testLowContrastPairingFailsMinimumRatio() {
    $ratio = ColorContrast::ratio('#eeeeee', '#ffffff');
    $this->assertLessThan(ColorContrast::MIN_RATIO_AA, $ratio);
  }

  /**
   * Hex input is treated case-insensitively.
   */
  public function testHexCaseDoesNotAffectResult() {
    $this->assertEqualsWithDelta(
      ColorContrast::ratio('#4A5568', '#FFFFFF'),
      ColorContrast::ratio('#4a5568', '#ffffff'),
      0.0001
    );
  }

  /**
   * Level A has no numeric contrast requirement in WCAG.
   */
  public function testNoMinimumRatioForLevelA() {
    $this->assertNull(ColorContrast::minRatioForLevel('A'));
  }

  /**
   * Level AA requires 4.5:1.
   */
  public function testLevelAaMinimumRatio() {
    $this->assertSame(4.5, ColorContrast::minRatioForLevel('AA'));
  }

  /**
   * Level AAA requires 7:1.
   */
  public function testLevelAaaMinimumRatio() {
    $this->assertSame(7.0, ColorContrast::minRatioForLevel('AAA'));
  }

  /**
   * An unrecognized level falls back to the AA minimum, not to no check.
   */
  public function testUnrecognizedLevelFallsBackToAa() {
    $this->assertSame(ColorContrast::MIN_RATIO_AA, ColorContrast::minRatioForLevel('not-a-real-level'));
  }

}
