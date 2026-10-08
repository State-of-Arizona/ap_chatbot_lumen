<?php

namespace Drupal\ap_genesys_cloud_chat;

use Drupal\Core\Site\Settings;

/**
 * Decides whether a Genesys bootstrap script URL can be loaded.
 *
 * A chat deployment's bootstrap URL becomes a <script src> on every page
 * the Chat Icon Block appears on, so it must point at Genesys Cloud: HTTPS,
 * the default port, no user/password part, and a host on one of Genesys
 * Cloud's domains (or a subdomain of one, e.g. apps.mypurecloud.com).
 *
 * Genesys Cloud's region domains are listed in GENESYS_DOMAINS. If Genesys
 * adds a region on a new domain, a developer can allow it in settings.php
 * without a code change (never through the admin UI):
 * @code
 * $settings['ap_genesys_cloud_chat_bootstrap_domains'] = ['example-new-region.cloud'];
 * @endcode
 *
 * @see https://help.genesys.cloud/articles/aws-regions-for-genesys-cloud-deployment/
 */
class BootstrapUrl {

  /**
   * Genesys Cloud's region domains (checked 2026-10-01).
   *
   * Most regions are a subdomain of pure.cloud (usw2, cac1, sae1, mxc1,
   * euw2, euc2, mec1, aps1, apne2, apne3, apse1); the others have their
   * own domain. us-gov-pure.cloud is the FedRAMP region, eusc-pure.cloud
   * the European Sovereign region.
   */
  const GENESYS_DOMAINS = [
    'mypurecloud.com',
    'mypurecloud.com.au',
    'mypurecloud.de',
    'mypurecloud.ie',
    'mypurecloud.jp',
    'pure.cloud',
    'us-gov-pure.cloud',
    'eusc-pure.cloud',
  ];

  /**
   * The settings.php key for extra allowed domains.
   */
  const SETTINGS_KEY = 'ap_genesys_cloud_chat_bootstrap_domains';

  /**
   * The site settings.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * Constructs a BootstrapUrl.
   *
   * @param \Drupal\Core\Site\Settings $settings
   *   The site settings.
   */
  public function __construct(Settings $settings) {
    $this->settings = $settings;
  }

  /**
   * Gets the domains a bootstrap URL's host may belong to.
   *
   * @return string[]
   *   Lowercase domain names.
   */
  public function getAllowedDomains() {
    $extra = $this->settings->get(self::SETTINGS_KEY, []);
    $extra = is_array($extra) ? array_filter($extra, 'is_string') : [];
    return array_values(array_unique(array_map('strtolower', array_merge(self::GENESYS_DOMAINS, $extra))));
  }

  /**
   * Whether a URL is HTTPS.
   *
   * @param string $url
   *   The URL.
   *
   * @return bool
   *   TRUE if its scheme is https.
   */
  public function isHttps($url) {
    return is_string($url) && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
  }

  /**
   * Whether a URL may be loaded as the Genesys bootstrap script.
   *
   * @param mixed $url
   *   The URL.
   *
   * @return bool
   *   TRUE if it is HTTPS, uses the default port, has no user/password
   *   part, and its host is an allowed domain or a subdomain of one.
   */
  public function isAllowed($url) {
    if (!is_string($url) || !$this->isHttps($url)) {
      return FALSE;
    }
    $parts = parse_url($url);
    if (!$parts || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
      return FALSE;
    }
    if (isset($parts['port']) && (int) $parts['port'] !== 443) {
      return FALSE;
    }

    // Plain ASCII hostnames only, so lookalike/IDN tricks can't match.
    $host = strtolower($parts['host']);
    if (!preg_match('/^[a-z0-9.-]+$/', $host)) {
      return FALSE;
    }
    foreach ($this->getAllowedDomains() as $domain) {
      if ($host === $domain || substr($host, -strlen('.' . $domain)) === '.' . $domain) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
