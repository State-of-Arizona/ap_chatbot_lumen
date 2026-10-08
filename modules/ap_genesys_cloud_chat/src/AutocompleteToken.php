<?php

namespace Drupal\ap_genesys_cloud_chat;

/**
 * Validates a lead-capture field's HTML autocomplete value.
 *
 * Fields that collect the visitor's own details (name, phone, email) carry
 * a standard autocomplete token so their purpose is programmatically
 * determinable (WCAG 2.1 SC 1.3.5 Identify Input Purpose). Everything else
 * is rendered with autocomplete="off".
 *
 * Accepts the HTML standard's autofill detail tokens:
 * [section-*] [shipping|billing] [home|work|mobile|fax|pager] field-name,
 * where the home/work/... contact hint is only allowed before a contact
 * field (tel*, email, impp). Credential and payment-card field names are
 * deliberately excluded: that information should never be collected
 * through a chat screen.
 *
 * @see https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#autofill
 */
final class AutocompleteToken {

  /**
   * Autofill field names that aren't contact fields.
   */
  const FIELD_NAMES = [
    'name', 'honorific-prefix', 'given-name', 'additional-name', 'family-name',
    'honorific-suffix', 'nickname', 'username', 'organization-title',
    'organization', 'street-address', 'address-line1', 'address-line2',
    'address-line3', 'address-level4', 'address-level3', 'address-level2',
    'address-level1', 'country', 'country-name', 'postal-code', 'language',
    'bday', 'bday-day', 'bday-month', 'bday-year', 'sex', 'url', 'photo',
  ];

  /**
   * Autofill contact field names; these may follow a contact hint.
   */
  const CONTACT_FIELD_NAMES = [
    'tel', 'tel-country-code', 'tel-national', 'tel-area-code', 'tel-local',
    'tel-local-prefix', 'tel-local-suffix', 'tel-extension', 'email', 'impp',
  ];

  /**
   * Contact hints, allowed only before a contact field name.
   */
  const CONTACT_HINTS = ['home', 'work', 'mobile', 'fax', 'pager'];

  /**
   * Normalizes a stored or submitted value to a valid token string.
   *
   * @param mixed $value
   *   The value, e.g. 'Email' or ' work  tel '.
   *
   * @return string|null
   *   The normalized token string ('' for blank or "off"), or NULL if the
   *   value isn't a valid, allowed autocomplete value.
   */
  public static function normalize($value) {
    if (!is_string($value)) {
      return $value === NULL ? '' : NULL;
    }
    $tokens = preg_split('/\s+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY);
    if (!$tokens || $tokens === ['off']) {
      return '';
    }

    $field_name = array_pop($tokens);
    $is_contact = in_array($field_name, self::CONTACT_FIELD_NAMES, TRUE);
    if (!$is_contact && !in_array($field_name, self::FIELD_NAMES, TRUE)) {
      return NULL;
    }

    // Optional prefixes, each at most once and in this order.
    if ($tokens && in_array(end($tokens), self::CONTACT_HINTS, TRUE)) {
      if (!$is_contact) {
        return NULL;
      }
      array_pop($tokens);
    }
    if ($tokens && in_array(end($tokens), ['shipping', 'billing'], TRUE)) {
      array_pop($tokens);
    }
    if ($tokens && preg_match('/^section-[a-z0-9-]+$/', end($tokens))) {
      array_pop($tokens);
    }
    if ($tokens) {
      return NULL;
    }

    return trim(strtolower(preg_replace('/\s+/', ' ', trim($value))));
  }

  /**
   * Gets the input type that suits a normalized token string.
   *
   * @param string $token
   *   A value returned by normalize().
   *
   * @return string
   *   'email', 'tel', or 'text'.
   */
  public static function inputType($token) {
    $parts = explode(' ', (string) $token);
    $field_name = end($parts);
    if ($field_name === 'email') {
      return 'email';
    }
    if (strpos($field_name, 'tel') === 0) {
      return 'tel';
    }
    return 'text';
  }

}
