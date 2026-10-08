<?php

namespace Drupal\ap_genesys_cloud_chat\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ap_genesys_cloud_chat\AutocompleteToken;
use Drupal\ap_genesys_cloud_chat\BootstrapUrl;
use Drupal\ap_genesys_cloud_chat\Entity\GenesysChatDeployment;
use Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentInterface;
use Drupal\ap_genesys_cloud_chat\ScriptParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Add/edit form for a Genesys deployment profile's deployment values.
 *
 * Covers the environment, deployment ID, bootstrap URL, lead-capture
 * fields, and Import from Script. The profile's look is edited separately
 * on its Branding tab (GenesysChatDeploymentBrandingForm).
 *
 * @property \Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentInterface $entity
 */
class GenesysChatDeploymentForm extends EntityForm {

  /**
   * The vendor script parser.
   *
   * @var \Drupal\ap_genesys_cloud_chat\ScriptParser
   */
  protected $scriptParser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The bootstrap URL checker.
   *
   * @var \Drupal\ap_genesys_cloud_chat\BootstrapUrl
   */
  protected $bootstrapUrl;

  /**
   * Constructs a GenesysChatDeploymentForm.
   *
   * @param \Drupal\ap_genesys_cloud_chat\ScriptParser $script_parser
   *   The vendor script parser.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\ap_genesys_cloud_chat\BootstrapUrl $bootstrap_url
   *   The bootstrap URL checker.
   */
  public function __construct(ScriptParser $script_parser, RequestStack $request_stack, BootstrapUrl $bootstrap_url) {
    $this->scriptParser = $script_parser;
    $this->requestStack = $request_stack;
    $this->bootstrapUrl = $bootstrap_url;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ap_genesys_cloud_chat.script_parser'),
      $container->get('request_stack'),
      $container->get('ap_genesys_cloud_chat.bootstrap_url')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $deployment = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t('A name for site admins, e.g. the area of the site this deployment serves.'),
      '#maxlength' => 255,
      '#default_value' => $deployment->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $deployment->id(),
      // The profile's config name ('ap_genesys_cloud_chat.deployment.' plus
      // this) is stored as its chat icon's file usage type
      '#maxlength' => 28,
      '#machine_name' => [
        'exists' => [GenesysChatDeployment::class, 'load'],
      ],
      '#disabled' => !$deployment->isNew(),
    ];

    // Admin form styles (css/settings-form.css), shared with the Branding tab.
    $form['#attached']['library'][] = 'ap_genesys_cloud_chat/settings_form';

    return $this->buildDeploymentElements($form, $form_state, [
      'environment_name' => $deployment->getEnvironmentName(),
      'deployment_id' => $deployment->getDeploymentId(),
      'bootstrap_url' => $deployment->getBootstrapUrl(),
      'custom_fields' => $deployment->getCustomFields(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $this->validateDeploymentElements($form_state);
  }

  /**
   * {@inheritdoc}
   *
   * Copies only this entity's own properties. The parent would copy every
   * form value (script_paste, each row's 'remove' button, ...) onto the
   * entity as undeclared properties. Each key is only copied when present,
   * since EntityForm also rebuilds the entity during AJAX rebuilds whose
   * #limit_validation_errors leave most values out.
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    foreach (['label', 'id', 'environment_name', 'deployment_id', 'bootstrap_url'] as $key) {
      if ($form_state->hasValue($key)) {
        $entity->set($key, $form_state->getValue($key));
      }
    }
    if ($form_state->hasValue('custom_fields')) {
      $entity->set('custom_fields', $this->getSubmittedCustomFields($form_state));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    $args = ['%label' => $this->entity->label()];
    if ($status === SAVED_NEW) {
      // Branding is a separate tab, so take a new profile straight there.
      $this->messenger()->addStatus($this->t('Created the %label chat deployment. Next, set up how its chat icon looks below, then select it in a Chat Icon Block placement.', $args));
      $form_state->setRedirectUrl($this->entity->toUrl('branding-form'));
    }
    else {
      $this->messenger()->addStatus($this->t('Updated the %label chat deployment.', $args));
      $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    }
    return $status;
  }

  /**
   * Adds the deployment elements to the form.
   *
   * @param array $form
   *   The form to add to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $defaults
   *   The stored values, keyed 'environment_name', 'deployment_id',
   *   'bootstrap_url' and 'custom_fields'.
   *
   * @return array
   *   The form with 'fields_wrapper' and 'import' added.
   */
  protected function buildDeploymentElements(array $form, FormStateInterface $form_state, array $defaults) {
    // Custom fields live in form state across AJAX add/remove/parse/rebuild
    // cycles; seed it from stored values only on the first build. Guard
    // against non-array values (e.g. stale config saved before this guard
    // existed) rather than just NULL, since #type 'table' can submit ''
    // when it has zero rows
    $fields = $form_state->get('custom_fields');
    if (!is_array($fields)) {
      $fields = $defaults['custom_fields'] ?? [];
      if (!is_array($fields)) {
        $fields = [];
      }
      $form_state->set('custom_fields', $fields);
    }

    // A parsed-but-not-yet-saved value (see parseScript()) takes priority
    // over stored values, so "Parse Script" can pre-fill these fields for
    // review without writing anything until the admin clicks Save.
    $parsed = $form_state->get('parsed_values') ?? [];

    $form['fields_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'apgc-fields-wrapper'],
    ];

    $request = $this->requestStack->getCurrentRequest();
    if ($request && $request->isXmlHttpRequest()) {
      $form['fields_wrapper']['messages'] = ['#type' => 'status_messages'];
    }

    $form['fields_wrapper']['environment_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Environment Name'),
      '#description' => $this->t('Enter the Genesys Environment provided by the Genesys Cloud service.'),
      '#default_value' => $parsed['environment_name'] ?? $defaults['environment_name'] ?? NULL,
      '#required' => TRUE,
    ];

    $form['fields_wrapper']['deployment_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deployment ID'),
      '#description' => $this->t('Enter the Genesys Deployment ID provided by the Genesys Cloud service.'),
      '#default_value' => $parsed['deployment_id'] ?? $defaults['deployment_id'] ?? NULL,
      '#required' => TRUE,
    ];

    $form['fields_wrapper']['bootstrap_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Genesys Bootstrap Script URL'),
      '#description' => $this->t('The Genesys bootstrap script endpoint for your region/environment. This is found in the early &lt;script&gt; block of the file your chat service had provided.'),
      '#default_value' => $parsed['bootstrap_url'] ?? $defaults['bootstrap_url'] ?? NULL,
      '#required' => TRUE,
    ];

    $form['fields_wrapper']['custom_fields_header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Chat Bot Fields'),
    ];

    $form['fields_wrapper']['custom_fields_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'apgc-custom-fields-wrapper'],
    ];
    $form['fields_wrapper']['custom_fields_wrapper']['custom_fields'] = [
      '#type' => 'table',
      '#attributes' => ['class' => ['apgc-custom-fields']],
      '#default_value' => [],
      '#header' => [
        $this->t('Weight'),
        $this->t('Required?'),
        $this->t('Field Type'),
        $this->t('Label'),
        $this->t('Field ID'),
        $this->t('Mapping Key'),
        $this->t('Autocomplete'),
        $this->t('Options'),
        $this->t('Manage'),
      ],
      '#empty' => $this->t('No custom fields have been added yet.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'field-weight',
        ],
      ],
    ];

    // Keep row order stable across rebuilds (weight is user-controlled).
    uasort($fields, function (array $a, array $b) {
      return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
    });

    foreach ($fields as $key => $field) {
      $row = &$form['fields_wrapper']['custom_fields_wrapper']['custom_fields'][$key];
      $row['#attributes']['class'][] = 'draggable';
      $row['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => $field['weight'] ?? 0,
        '#attributes' => ['class' => ['field-weight']],
      ];
      $row['required'] = [
        '#type' => 'checkbox',
        '#default_value' => $field['required'] ?? FALSE,
      ];
      $row['type'] = [
        '#type' => 'select',
        '#options' => [
          'text' => $this->t('Text'),
          'select' => $this->t('Select'),
        ],
        '#default_value' => $field['type'] ?? 'text',
      ];
      $row['label'] = [
        '#type' => 'textfield',
        '#default_value' => $field['label'] ?? '',
        '#placeholder' => $this->t('Field Label'),
      ];
      $row['id'] = [
        '#type' => 'textfield',
        '#default_value' => $field['id'] ?? '',
        '#placeholder' => $this->t('Field ID'),
        '#description' => $this->t('Letters, digits, hyphens, or underscores; cannot start with a digit. Must be unique across all fields.'),
      ];
      $row['mapping'] = [
        '#type' => 'textfield',
        '#default_value' => $field['mapping'] ?? '',
        '#placeholder' => $this->t('Mapping Key'),
      ];
      $row['autocomplete'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Autocomplete'),
        '#title_display' => 'invisible',
        '#default_value' => $field['autocomplete'] ?? '',
        '#size' => 16,
        '#maxlength' => 128,
        '#placeholder' => 'off',
        '#description' => $this->t('Text fields only. For fields asking for details about the visitor, a standard HTML autocomplete value, e.g. <code>name</code>, <code>given-name</code>, <code>family-name</code>, <code>email</code>, or <code>tel</code> (needed for WCAG 1.3.5); see the <a href=":url">full list of autocomplete values</a>. Leave blank for anything else, such as an ID or licence number, to keep autocomplete off.', [
          ':url' => 'https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Attributes/autocomplete',
        ]),
      ];
      $row['options'] = [
        '#type' => 'textarea',
        '#default_value' => $field['options'] ?? '',
        '#placeholder' => $this->t('Comma-separated options for select'),
      ];
      $row['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#submit' => ['::removeField'],
        '#name' => 'remove_' . $key,
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'apgc-fields-wrapper',
        ],
      ];
    }
    unset($row);

    $form['fields_wrapper']['add_field'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Field'),
      '#submit' => ['::addField'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'apgc-fields-wrapper',
      ],
    ];

    $form['import'] = [
      '#type' => 'details',
      '#title' => $this->t('Import from Script'),
      '#open' => FALSE,
      '#description' => $this->t('Unsure how to grab the data? Paste the entire file your chat service provided (a full form like with HTML and JS, or a short JS only snippet) and click Parse Script. The fields above will be filled in for you to review. Nothing is saved until you save this form, and the code entered here is not retained on the database.'),
    ];
    $form['import']['script_paste'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Paste Script'),
      '#title_display' => 'invisible',
      '#rows' => 8,
      '#placeholder' => $this->t('Paste the vendor-provided script here…'),
    ];
    $form['import']['parse'] = [
      '#type' => 'submit',
      '#value' => $this->t('Parse Script'),
      '#submit' => ['::parseScript'],
      '#limit_validation_errors' => [['script_paste']],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'apgc-fields-wrapper',
      ],
    ];

    return $form;
  }

  /**
   * Validates the deployment elements.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function validateDeploymentElements(FormStateInterface $form_state) {
    $bootstrap_url = (string) $form_state->getValue('bootstrap_url');
    if ($bootstrap_url !== '' && !$this->bootstrapUrl->isHttps($bootstrap_url)) {
      $form_state->setErrorByName('bootstrap_url', $this->t('Genesys Bootstrap Script URL must use HTTPS.'));
    }
    elseif ($bootstrap_url !== '' && !$this->bootstrapUrl->isAllowed($bootstrap_url)) {
      $form_state->setErrorByName('bootstrap_url', $this->t('Genesys Bootstrap Script URL must be a Genesys Cloud address, such as %example. Copy it exactly from the script your chat vendor provided.', [
        '%example' => 'https://apps.mypurecloud.com/genesys-bootstrap/genesys.min.js',
      ]));
    }

    $custom_fields = $form_state->getValue('custom_fields');
    if (!is_array($custom_fields)) {
      return;
    }

    // A completely untouched row (e.g. a leftover "Add Field" click with
    // nothing filled in) is silently dropped in
    // getSubmittedCustomFields()
    $seen_ids = [];
    foreach ($custom_fields as $key => $field) {
      if ($this->isBlankFieldRow($field)) {
        continue;
      }

      $id = (string) ($field['id'] ?? '');
      $error_name = 'custom_fields][' . $key . '][id';
      if ($id === '') {
        $form_state->setErrorByName($error_name, $this->t('Field ID is required.'));
      }
      elseif (!preg_match(GenesysChatDeploymentInterface::FIELD_ID_PATTERN, $id)) {
        $form_state->setErrorByName($error_name, $this->t('Field ID must be a valid identifier (letters, digits, hyphens, or underscores; cannot start with a digit).'));
      }
      elseif (isset($seen_ids[$id])) {
        $form_state->setErrorByName($error_name, $this->t('Field ID %id is used by more than one field — each Field ID must be unique.', ['%id' => $id]));
      }
      else {
        $seen_ids[$id] = TRUE;
      }

      $autocomplete = (string) ($field['autocomplete'] ?? '');
      $autocomplete_error = 'custom_fields][' . $key . '][autocomplete';
      if (AutocompleteToken::normalize($autocomplete) === NULL) {
        $form_state->setErrorByName($autocomplete_error, $this->t("Autocomplete %value isn't a standard HTML autocomplete value that this module allows. Use one such as name, given-name, family-name, email, or tel, or leave it blank for off. Password, one-time-code and payment-card values aren't allowed.", ['%value' => $autocomplete]));
      }
      elseif (AutocompleteToken::normalize($autocomplete) !== '' && ($field['type'] ?? 'text') === 'select') {
        $form_state->setErrorByName($autocomplete_error, $this->t('Autocomplete only applies to text fields. Leave it blank for select fields.'));
      }
    }
  }

  /**
   * Whether a submitted custom field row has nothing filled in at all.
   *
   * Shared by validateDeploymentElements() (skips validating it) and
   * getSubmittedCustomFields() (drops it before saving), so the two can't
   * drift on what "blank" means.
   *
   * @param array $field
   *   One row's submitted values.
   *
   * @return bool
   *   TRUE if label, id, and mapping are all empty.
   */
  protected function isBlankFieldRow(array $field) {
    return ($field['label'] ?? '') === '' && ($field['id'] ?? '') === '' && ($field['mapping'] ?? '') === '';
  }

  /**
   * Gets the submitted custom fields, cleaned for saving.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   A reindexed list of field definitions holding only schema-defined
   *   keys, with fully blank rows removed.
   */
  protected function getSubmittedCustomFields(FormStateInterface $form_state) {
    $custom_fields = $form_state->getValue('custom_fields');
    if (!is_array($custom_fields)) {
      $custom_fields = [];
    }
    foreach ($custom_fields as $key => $field) {
      $custom_fields[$key] = array_intersect_key($field, array_flip([
        'type', 'label', 'required', 'id', 'autocomplete', 'mapping', 'options', 'weight',
      ]));
      // Stored normalized (lowercase, single-spaced, '' for off).
      // Validation already rejected anything invalid.
      $custom_fields[$key]['autocomplete'] = (string) AutocompleteToken::normalize($field['autocomplete'] ?? '');
    }

    return array_values(array_filter($custom_fields, function (array $field) {
      return !$this->isBlankFieldRow($field);
    }));
  }

  /**
   * Submit handler: appends a blank custom field row.
   */
  public function addField(array &$form, FormStateInterface $form_state) {
    $fields = $form_state->get('custom_fields') ?? [];
    $fields[] = [
      'type' => 'text',
      'label' => '',
      'required' => FALSE,
      'id' => '',
      'mapping' => '',
      'options' => '',
      'weight' => 0,
    ];
    $form_state->set('custom_fields', $fields);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler: removes the triggering row's custom field.
   */
  public function removeField(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $key = str_replace('remove_', '', $trigger['#name']);

    $fields = $form_state->get('custom_fields') ?? [];
    unset($fields[$key]);

    $form_state->set('custom_fields', array_values($fields));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler: parses a pasted vendor script and pre-fills the form.
   *
   * Never saves anything. only populates form_state so the rebuilt form
   * shows the parsed values for the admin to review (and correct, if the
   * best-effort parse got something wrong) before saving. See
   * ScriptParser for the actual extraction logic.
   */
  public function parseScript(array &$form, FormStateInterface $form_state) {
    $script = (string) $form_state->getValue('script_paste');
    $result = $this->scriptParser->parse($script);

    $form_state->set('parsed_values', [
      'environment_name' => $result['environment_name'],
      'deployment_id' => $result['deployment_id'],
      'bootstrap_url' => $result['bootstrap_url'],
    ]);

    $user_input = $form_state->getUserInput();
    foreach (['environment_name', 'deployment_id', 'bootstrap_url'] as $key) {
      if ($result[$key] !== NULL) {
        $user_input[$key] = $result[$key];
      }
    }

    // Only replace the custom fields table if the parse actually found
    // rows. an empty result shouldn't wipe out fields the admin already configured or is mid-editing.
    if (!empty($result['custom_fields'])) {
      $form_state->set('custom_fields', $result['custom_fields']);
      unset($user_input['custom_fields']);
    }
    $form_state->setUserInput($user_input);

    $found = array_filter([
      $result['environment_name'],
      $result['deployment_id'],
      $result['bootstrap_url'],
      !empty($result['custom_fields']) ? 'fields' : NULL,
    ]);
    if ($found) {
      $this->messenger()->addStatus($this->t('Parsed values have been filled in below. Review the fields for correctness, then click Save.'));
    }
    foreach ($result['warnings'] as $warning) {
      $this->messenger()->addWarning($warning);
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX callback: returns the rebuilt fields wrapper.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['fields_wrapper'];
  }

}
