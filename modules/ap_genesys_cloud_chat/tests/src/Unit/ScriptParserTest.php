<?php

namespace Drupal\Tests\ap_genesys_cloud_chat\Unit;

use Drupal\ap_genesys_cloud_chat\ScriptParser;
use Drupal\Tests\UnitTestCase;

/**
 * Tests ScriptParser against realistic vendor script shapes.
 *
 * Fixtures below mirror the two real patterns this module is built
 * against: a short bootstrap-only snippet (no form, like the vendor's
 * "ade.html") and a full lead-capture form + customAttributes mapping
 * (like the vendor's "BSDChatv#.html" files) with both text and select
 * fields.
 *
 * @group ap_genesys_cloud
 * @coversDefaultClass \Drupal\ap_genesys_cloud_chat\ScriptParser
 */
class ScriptParserTest extends UnitTestCase {

  /**
   * A bootstrap-only snippet, with no lead-capture form at all.
   */
  const BOOTSTRAP_ONLY = <<<'SCRIPT'
<script type="text/javascript" charset="utf-8">
    (function (g, e, n, es, ys) {
        g['_genesysJs'] = e;
        g[e] = g[e] || function () {
            (g[e].q = g[e].q || []).push(arguments)
        };
        g[e].t = 1 * new Date();
        g[e].c = es;
        ys = document.createElement('script'); ys.async = 1; ys.src = n; ys.charset = 'utf-8'; document.head.appendChild(ys);
    })(window, 'Genesys', 'https://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js', {
        environment: 'fedramp-use2-core',
        deploymentId: '10e75ed6-9ba1-4fda-fake-id8657b96a92'
    });
</script>
SCRIPT;

  /**
   * A full lead-capture form with text fields only.
   */
  const FORM_WITH_TEXT_FIELDS = <<<'SCRIPT'
<script type="text/javascript" charset="utf-8">
    (function (g, e, n, es, ys) {
        g["_genesysJs"] = e;
    })(
        window,
        "Genesys",
        "https://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js",
        {
            environment: "fedramp-use2",
            deploymentId: "6228dd6c-8b07-4fb7-fake-ida5a90ec71f",
        }
    );
</script>
<form id="contactForm" class="needs-validation" novalidate>
    <div class="form-group">
        <label for="fullName">Full Name:</label>
        <input type="text" class="form-control" id="fullName" name="fullName" required aria-label="*Full Name" />
        <div class="invalid-feedback">Please enter your full name.</div>
    </div>
    <div class="form-group">
        <label for="phoneNumber">Phone Number:</label>
        <input type="tel" class="form-control" id="phoneNumber" name="phoneNumber" required aria-label="*Phone Number" />
    </div>
    <div class="form-group">
        <label for="license">License Number:</label>
        <input type="text" class="form-control" id="license" name="license" aria-label="License Number" />
    </div>
    <button type="submit" class="btn btn-primary">Submit</button>
</form>
<script>
    Genesys("command", "Database.set", {
        messaging: {
            customAttributes: {
                customerFullName: formProps["fullName"],
                customerPhoneNumber: formProps["phoneNumber"],
                customerLicense: formProps["license"],
            },
        },
    });
</script>
SCRIPT;

  /**
   * A form mixing text and select fields.
   */
  const FORM_WITH_SELECT_FIELDS = <<<'SCRIPT'
<script type="text/javascript" charset="utf-8">
    (function (g, e, n, es, ys) {})(
        window, "Genesys", "https://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js",
        { environment: "fedramp-use2", deploymentId: "86556c15-fe22-4123-fake-id87596ffa33" }
    );
</script>
<form id="contactForm">
    <div class="form-group">
        <label for="firstName">First Name:</label>
        <input type="text" id="firstName" name="firstName" required />
    </div>
    <div class="form-group">
        <label for="status">Your Status:</label>
        <select id="status" name="status" required>
            <option value="">Choose an option</option>
            <option value="Active">Active Employee</option>
            <option value="Retiree">Retiree</option>
            <option value="COBRA">COBRA</option>
        </select>
    </div>
    <button type="submit">Submit</button>
</form>
<script>
    Genesys("command", "Database.set", {
        messaging: {
            customAttributes: {
                customerFirstName: formProps["firstName"],
                customerStatus: formProps["status"],
            },
        },
    });
</script>
SCRIPT;

  /**
   * @covers ::parse
   */
  public function testBootstrapOnlySnippet() {
    $result = (new ScriptParser())->parse(self::BOOTSTRAP_ONLY);

    $this->assertSame('fedramp-use2-core', $result['environment_name']);
    $this->assertSame('10e75ed6-9ba1-4fda-fake-id8657b96a92', $result['deployment_id']);
    $this->assertSame('https://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js', $result['bootstrap_url']);
    $this->assertSame([], $result['custom_fields']);
    // Expected: no form present, so this should be noted, not silently
    // ignored.
    $this->assertNotEmpty($result['warnings']);
  }

  /**
   * @covers ::parse
   */
  public function testFormWithTextFields() {
    $result = (new ScriptParser())->parse(self::FORM_WITH_TEXT_FIELDS);

    $this->assertSame('fedramp-use2', $result['environment_name']);
    $this->assertSame('6228dd6c-8b07-4fb7-fake-ida5a90ec71f', $result['deployment_id']);
    $this->assertCount(3, $result['custom_fields']);

    $by_id = [];
    foreach ($result['custom_fields'] as $field) {
      $by_id[$field['id']] = $field;
    }

    $this->assertSame('Full Name', $by_id['fullName']['label']);
    $this->assertSame('text', $by_id['fullName']['type']);
    $this->assertTrue($by_id['fullName']['required']);
    $this->assertSame('customerFullName', $by_id['fullName']['mapping']);

    $this->assertSame('Phone Number', $by_id['phoneNumber']['label']);
    $this->assertTrue($by_id['phoneNumber']['required']);
    $this->assertSame('customerPhoneNumber', $by_id['phoneNumber']['mapping']);

    $this->assertFalse($by_id['license']['required']);
    $this->assertSame('customerLicense', $by_id['license']['mapping']);

    // Weights should be sequential in document order.
    $this->assertSame(0, $by_id['fullName']['weight']);
    $this->assertSame(1, $by_id['phoneNumber']['weight']);
    $this->assertSame(2, $by_id['license']['weight']);
  }

  /**
   * @covers ::parse
   */
  public function testFormWithSelectFields() {
    $result = (new ScriptParser())->parse(self::FORM_WITH_SELECT_FIELDS);

    $this->assertCount(2, $result['custom_fields']);
    $by_id = [];
    foreach ($result['custom_fields'] as $field) {
      $by_id[$field['id']] = $field;
    }

    $this->assertSame('text', $by_id['firstName']['type']);
    $this->assertSame('select', $by_id['status']['type']);
    $this->assertSame('Active Employee, Retiree, COBRA', $by_id['status']['options']);
    // The placeholder option (value="") must never appear in Options.
    $this->assertStringNotContainsString('Choose an option', $by_id['status']['options']);
    $this->assertSame('customerStatus', $by_id['status']['mapping']);
  }

  /**
   * A field ID containing a double quote doesn't break the label lookup.
   *
   * Regression test for A11Y-SECURITY.md S6: findLabel() previously
   * concatenated the id directly into an XPath expression string, so a
   * value containing a literal double quote would break the query.
   *
   * @covers ::parse
   * @covers ::findLabel
   */
  public function testFieldIdContainingQuoteDoesNotBreakLabelLookup() {
    $script = <<<'SCRIPT'
<form id="contactForm">
    <div class="form-group">
        <label for='full"name'>Full Name:</label>
        <input type="text" id='full"name' name='full"name' />
    </div>
</form>
SCRIPT;

    $result = (new ScriptParser())->parse($script);

    $this->assertCount(1, $result['custom_fields']);
    $this->assertSame('full"name', $result['custom_fields'][0]['id']);
    $this->assertSame('Full Name', $result['custom_fields'][0]['label']);
  }

  /**
   * @covers ::parse
   */
  public function testEmptyInput() {
    $result = (new ScriptParser())->parse('');

    $this->assertNull($result['environment_name']);
    $this->assertSame([], $result['custom_fields']);
    $this->assertNotEmpty($result['warnings']);
  }

  /**
   * @covers ::parse
   */
  public function testGarbageInputDoesNotCrash() {
    $result = (new ScriptParser())->parse('this is not a script at all, just some <p>text</p>.');

    $this->assertNull($result['environment_name']);
    $this->assertNull($result['deployment_id']);
    $this->assertNull($result['bootstrap_url']);
    $this->assertSame([], $result['custom_fields']);
    $this->assertNotEmpty($result['warnings']);
  }

  /**
   * @covers ::parse
   */
  public function testOversizedInputIsRejected() {
    $result = (new ScriptParser())->parse(str_repeat('a', ScriptParser::MAX_LENGTH + 1));

    $this->assertNull($result['environment_name']);
    $this->assertStringContainsString('too large', $result['warnings'][0]);
  }

}
