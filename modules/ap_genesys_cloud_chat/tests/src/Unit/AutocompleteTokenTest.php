<?php

namespace Drupal\Tests\ap_genesys_cloud_chat\Unit;

use Drupal\ap_genesys_cloud_chat\AutocompleteToken;
use Drupal\Tests\UnitTestCase;

/**
 * Tests AutocompleteToken.
 *
 * @coversDefaultClass \Drupal\ap_genesys_cloud_chat\AutocompleteToken
 *
 * @group ap_genesys_cloud
 */
class AutocompleteTokenTest extends UnitTestCase {

  /**
   * Valid values normalize to their canonical form.
   *
   * @covers ::normalize
   * @dataProvider validProvider
   */
  public function testValidValues($value, $expected) {
    $this->assertSame($expected, AutocompleteToken::normalize($value));
  }

  /**
   * Provides valid values.
   */
  public static function validProvider() {
    return [
      'blank is off' => ['', ''],
      'null is off' => [NULL, ''],
      'explicit off' => ['off', ''],
      'full name' => ['name', 'name'],
      'given name' => ['given-name', 'given-name'],
      'family name' => ['family-name', 'family-name'],
      'email, case-insensitive' => ['Email', 'email'],
      'phone' => ['tel', 'tel'],
      'contact hint' => ['work email', 'work email'],
      'extra whitespace' => ['  mobile   tel ', 'mobile tel'],
      'section and shipping' => ['section-a shipping postal-code', 'section-a shipping postal-code'],
    ];
  }

  /**
   * Invalid or disallowed values are rejected.
   *
   * @covers ::normalize
   * @dataProvider invalidProvider
   */
  public function testInvalidValues($value) {
    $this->assertNull(AutocompleteToken::normalize($value));
  }

  /**
   * Provides invalid values.
   */
  public static function invalidProvider() {
    return [
      'common typo' => ['phone'],
      'hyphenated typo' => ['e-mail'],
      '"on" names no purpose' => ['on'],
      'contact hint before a non-contact field' => ['home name'],
      'unknown prefix' => ['personal email'],
      'payment card' => ['cc-number'],
      'password' => ['new-password'],
      'one-time code' => ['one-time-code'],
      'markup' => ['"><script>alert(1)</script>'],
      'not a string' => [['email']],
    ];
  }

  /**
   * The input type follows the field name.
   *
   * @covers ::inputType
   */
  public function testInputType() {
    $this->assertSame('email', AutocompleteToken::inputType('email'));
    $this->assertSame('email', AutocompleteToken::inputType('work email'));
    $this->assertSame('tel', AutocompleteToken::inputType('tel'));
    $this->assertSame('tel', AutocompleteToken::inputType('mobile tel-national'));
    $this->assertSame('text', AutocompleteToken::inputType('name'));
    $this->assertSame('text', AutocompleteToken::inputType('postal-code'));
  }

}
