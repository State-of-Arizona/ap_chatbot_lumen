<?php

namespace Drupal\ap_genesys_cloud_chat;

/**
 * Extracts settings from a chat vendor's provided bootstrap/form script.
 *
 * Site admins are often handed a raw HTML file (a full lead-capture form
 * like BSDChatv#.html, or just a short bootstrap-only snippet like
 * ade.html) and asked to manually copy a handful of values out of it.
 * error-prone for anyone unfamiliar with reading JavaScript. This class
 * does that extraction so the settings form can pre-fill itself instead.
 *
 * Deliberately best-effort: every extracted value is surfaced to the
 * admin for review before anything is saved (see
 * GenesysChatDeploymentForm::parseScript()), so a partial or failed parse is
 * safe, it just means less got pre-filled, never a silent wrong value.
 */
class ScriptParser {

  /**
   * Refuse to parse input larger than this many characters.
   *
   * A real vendor script is a few KB; this is generous headroom while
   * still guarding against pathological input (accidental huge pastes,
   * or deliberately adversarial input against the regexes below) tying
   * up a request. This runs behind the module's own admin-only
   * permission, not a public endpoint, but it's cheap insurance.
   */
  const MAX_LENGTH = 100000;

  /**
   * Parses a pasted script for Genesys bootstrap values and form fields.
   *
   * @param string $script
   *   The raw pasted script/HTML content.
   *
   * @return array
   *   An array with keys:
   *   - environment_name (string|null)
   *   - deployment_id (string|null)
   *   - bootstrap_url (string|null)
   *   - custom_fields (array): in the same shape
   *     GenesysChatDeploymentForm/config expect (type, label, required, id,
   *     mapping, options, weight).
   *   - warnings (string[]): human-readable notes about anything that
   *     couldn't be found or looked ambiguous, for display to the admin.
   */
  public function parse(string $script): array {
    $result = [
      'environment_name' => NULL,
      'deployment_id' => NULL,
      'bootstrap_url' => NULL,
      'custom_fields' => [],
      'warnings' => [],
    ];

    if (trim($script) === '') {
      $result['warnings'][] = 'Nothing was pasted.';
      return $result;
    }
    if (strlen($script) > self::MAX_LENGTH) {
      $result['warnings'][] = 'The pasted content is too large to parse (over ' . number_format(self::MAX_LENGTH) . ' characters). Paste only the provided script file, not an entire page.';
      return $result;
    }

    $this->parseBootstrap($script, $result);
    $this->parseFields($script, $result);

    return $result;
  }

  /**
   * Extracts the Genesys environment, deployment ID, and bootstrap URL.
   *
   * @param string $script
   *   The pasted script.
   * @param array $result
   *   The result array, modified by reference.
   */
  protected function parseBootstrap(string $script, array &$result): void {
    // The bootstrap URL is always the third argument to the vendor's
    // IIFE, immediately after the literal 'Genesys' string and consistent
    // across every example script this module has been built against.
    if (preg_match('/[\'"]Genesys[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $script, $matches)) {
      $result['bootstrap_url'] = $matches[1];
    }
    else {
      $result['warnings'][] = 'Could not find the Genesys bootstrap script URL.';
    }

    if (preg_match('/environment\s*:\s*[\'"]([^\'"]+)[\'"]/', $script, $matches)) {
      $result['environment_name'] = $matches[1];
    }
    else {
      $result['warnings'][] = 'Could not find "environment".';
    }

    if (preg_match('/deploymentId\s*:\s*[\'"]([^\'"]+)[\'"]/', $script, $matches)) {
      $result['deployment_id'] = $matches[1];
    }
    else {
      $result['warnings'][] = 'Could not find "deploymentId".';
    }
  }

  /**
   * Extracts lead-capture form fields and their customAttributes mapping.
   *
   * @param string $script
   *   The pasted script.
   * @param array $result
   *   The result array, modified by reference.
   */
  protected function parseFields(string $script, array &$result): void {
    $mapping_by_id = $this->extractMapping($script);
    if (empty($mapping_by_id)) {
      $result['warnings'][] = 'Could not find a "customAttributes" mapping block. Mapping Key will be left blank for any fields found below.';
    }

    $dom = new \DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    // Wrapped in a root element since the pasted content is a fragment,
    // not a full document. DOMDocument otherwise silently loses
    // elements outside <html>/<body> in some libxml versions.
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $script . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
      $result['warnings'][] = 'Could not parse the pasted content as HTML; no form fields detected.';
      return;
    }

    $xpath = new \DOMXPath($dom);
    $custom_fields = [];
    $weight = 0;

    foreach ($xpath->query('//input[@id] | //select[@id]') as $element) {
      /** @var \DOMElement $element */
      $type_attr = $element->getAttribute('type');
      if ($element->tagName === 'input' && in_array($type_attr, ['submit', 'button', 'hidden', 'reset'], TRUE)) {
        continue;
      }

      $id = $element->getAttribute('id');
      $label = $this->findLabel($xpath, $id);

      $options = '';
      $field_type = 'text';
      if ($element->tagName === 'select') {
        $field_type = 'select';
        $options = $this->extractOptions($xpath, $element);
      }

      $custom_fields[] = [
        'type' => $field_type,
        'label' => $label !== '' ? $label : $id,
        'required' => $element->hasAttribute('required'),
        'id' => $id,
        'mapping' => $mapping_by_id[$id] ?? '',
        'options' => $options,
        'weight' => $weight++,
      ];

      if (empty($mapping_by_id[$id])) {
        $result['warnings'][] = 'No mapping key found for field "' . $id . '" -- check its Mapping Key manually.';
      }
    }

    if (empty($custom_fields)) {
      $result['warnings'][] = 'No form fields were detected. If the provided script is just the bootstrap snippet with no form, this is expected -- leave "Chat Bot Fields" empty.';
    }

    $result['custom_fields'] = $custom_fields;
  }

  /**
   * Extracts the customAttributes: {...} mapping as [field id => key].
   *
   * @param string $script
   *   The pasted script.
   *
   * @return array
   *   Field ID to Genesys custom attribute key.
   */
  protected function extractMapping(string $script): array {
    $mapping_by_id = [];
    if (preg_match('/customAttributes\s*:\s*\{(.*?)\}\s*,?\s*\}/s', $script, $block)) {
      if (preg_match_all('/([A-Za-z0-9_]+)\s*:\s*formProps\[[\'"]([^\'"]+)[\'"]\]/', $block[1], $pairs, PREG_SET_ORDER)) {
        foreach ($pairs as $pair) {
          $mapping_by_id[$pair[2]] = $pair[1];
        }
      }
    }
    return $mapping_by_id;
  }

  /**
   * Finds the visible label text for a given field ID.
   *
   * @param \DOMXPath $xpath
   *   The document's XPath evaluator.
   * @param string $id
   *   The field's id attribute.
   *
   * @return string
   *   The label text, with a trailing colon and surrounding whitespace
   *   stripped, or an empty string if none was found.
   */
  protected function findLabel(\DOMXPath $xpath, string $id): string {
    // $id comes from the admin's own pasted vendor script, so it isn't
    // trusted to interpolate directly into an XPath expression (XPath 1.0
    // has no native string-literal escape). Querying every <label> with a
    // "for" and comparing in PHP sidesteps that instead of needing an
    // escaping helper.
    foreach ($xpath->query('//label[@for]') as $label_node) {
      /** @var \DOMElement $label_node */
      if ($label_node->getAttribute('for') === $id) {
        return rtrim(trim($label_node->textContent), ':');
      }
    }
    return '';
  }

  /**
   * Extracts comma-separated <option> labels from a <select> element.
   *
   * @param \DOMXPath $xpath
   *   The document's XPath evaluator.
   * @param \DOMElement $select
   *   The <select> element.
   *
   * @return string
   *   Comma-separated option labels, excluding any blank/placeholder
   *   option (an empty value="" is always a placeholder, never a real
   *   choice — see this module's own field-type semantics).
   */
  protected function extractOptions(\DOMXPath $xpath, \DOMElement $select): string {
    $labels = [];
    foreach ($xpath->query('.//option', $select) as $option) {
      /** @var \DOMElement $option */
      if (trim($option->getAttribute('value')) === '') {
        continue;
      }
      $text = trim($option->textContent);
      $labels[] = $text !== '' ? $text : trim($option->getAttribute('value'));
    }
    return implode(', ', $labels);
  }

}
