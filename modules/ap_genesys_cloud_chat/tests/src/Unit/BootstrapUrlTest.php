<?php

namespace Drupal\Tests\ap_genesys_cloud_chat\Unit;

use Drupal\Core\Site\Settings;
use Drupal\ap_genesys_cloud_chat\BootstrapUrl;
use Drupal\Tests\UnitTestCase;

/**
 * Tests BootstrapUrl.
 *
 * @coversDefaultClass \Drupal\ap_genesys_cloud_chat\BootstrapUrl
 *
 * @group ap_genesys_cloud
 */
class BootstrapUrlTest extends UnitTestCase {

  /**
   * Builds the checker with the given settings.php values.
   *
   * @param array $settings
   *   Settings values.
   *
   * @return \Drupal\ap_genesys_cloud_chat\BootstrapUrl
   *   The checker.
   */
  protected function checker(array $settings = []) {
    return new BootstrapUrl(new Settings($settings));
  }

  /**
   * Genesys Cloud addresses in every region domain are allowed.
   *
   * @covers ::isAllowed
   * @dataProvider allowedProvider
   */
  public function testAllowed($url) {
    $this->assertTrue($this->checker()->isAllowed($url));
  }

  /**
   * Provides allowed URLs.
   */
  public static function allowedProvider() {
    $path = '/genesys-bootstrap/genesys.min.js';
    return [
      'FedRAMP' => ['https://apps.use2.us-gov-pure.cloud' . $path],
      'US East' => ['https://apps.mypurecloud.com' . $path],
      'pure.cloud region' => ['https://apps.usw2.pure.cloud' . $path],
      'Sydney' => ['https://apps.mypurecloud.com.au' . $path],
      'European Sovereign' => ['https://apps.edee1.eusc-pure.cloud' . $path],
      'explicit default port' => ['https://apps.mypurecloud.com:443' . $path],
      'uppercase host' => ['https://APPS.MyPureCloud.com' . $path],
    ];
  }

  /**
   * Anything that isn't an HTTPS Genesys Cloud address is refused.
   *
   * @covers ::isAllowed
   * @dataProvider refusedProvider
   */
  public function testRefused($url) {
    $this->assertFalse($this->checker()->isAllowed($url));
  }

  /**
   * Provides refused URLs.
   */
  public static function refusedProvider() {
    $path = '/genesys-bootstrap/genesys.min.js';
    return [
      'plain HTTP' => ['http://apps.mypurecloud.com' . $path],
      'other host' => ['https://evil.example' . $path],
      'lookalike suffix' => ['https://apps.mypurecloud.com.evil.example' . $path],
      'lookalike prefix' => ['https://evilmypurecloud.com' . $path],
      'user-info trick' => ['https://apps.mypurecloud.com@evil.example' . $path],
      'credentials' => ['https://user:pass@apps.mypurecloud.com' . $path],
      'other port' => ['https://apps.mypurecloud.com:8443' . $path],
      'protocol-relative' => ['//apps.mypurecloud.com' . $path],
      'javascript scheme' => ['javascript:alert(1)'],
      'IDN lookalike' => ['https://apps.mypurecloud.cоm' . $path],
      'empty' => [''],
      'not a string' => [NULL],
    ];
  }

  /**
   * A domain added in settings.php is allowed, alongside the defaults.
   *
   * @covers ::isAllowed
   * @covers ::getAllowedDomains
   */
  public function testSettingsAddsDomains() {
    $checker = $this->checker([BootstrapUrl::SETTINGS_KEY => ['New-Region.example']]);
    $this->assertTrue($checker->isAllowed('https://apps.new-region.example/genesys-bootstrap/genesys.min.js'));
    $this->assertTrue($checker->isAllowed('https://apps.mypurecloud.com/genesys-bootstrap/genesys.min.js'));
    $this->assertFalse($this->checker()->isAllowed('https://apps.new-region.example/genesys-bootstrap/genesys.min.js'));
  }

}
