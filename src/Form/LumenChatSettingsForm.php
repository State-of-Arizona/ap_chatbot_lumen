<?php

namespace Drupal\ap_chatbot_lumen\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class LumenChatSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ap_chatbot_lumen.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ap_chatbot_lumen_settings_form';
  }

  protected function getStoredFields() {
    $config = $this->config('ap_chatbot_lumen.settings');
    return $config->get('custom_fields') ?? [];
  }

  /**
   * Build the settings form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ap_chatbot_lumen.settings');
    $fields = $form_state->get('custom_fields') ?? $config->get('custom_fields') ?? $this->getStoredFields();
    $form_state->set('custom_fields', $fields);

    // Set custom fields for js access
    $form['#attached']['drupalSettings']['apChatbotLumen'] = [
      'customFields' => $fields,
    ];


    $form['environment_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Environment Name'),
      '#description' => $this->t('Enter the Genesys Environment provided by the agency.'),
      '#default_value' => $config->get('environment_name'),
      '#required' => TRUE,
    ];

    $form['deployment_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deployment ID'),
      '#description' => $this->t('Enter the Genesys Deployment ID provided by the agency.'),
      '#default_value' => $config->get('deployment_id'),
      '#required' => TRUE,
    ];

    $form['chat_icon'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Chat Icon'),
      '#description' => $this->t('(Optional) Upload a custom chat icon in PNG, JPG, or SVG format. If left empty, a standard chat bubble icon is used with a colored background of "Setting Sun Magenta" (#8A3A6D) instead.'),
      '#upload_location' => 'public://agencyplatform/chatbot/',
      '#default_value' => $config->get('chat_icon'),
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg svg'],
      ],
      '#multiple' => FALSE,
    ];

    $form['icon_alttext'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Chat Icon Alt Text'),
      '#description' => $this->t('(Optional) Enter a custom chat icon alternative text for accessibility purposes. If left empty, the default text of "Enter a text chat with a live agent!" is used instead.'),
      '#default_value' => $config->get('icon_alttext'),
      '#placeholder' => $this->t('Enter a text chat with a live agent!'),
    ];

    // Add Custom Field Mappings
      $form['custom_fields_header'] = [
          '#type' => 'markup',
          '#markup' => '<h2>' . $this->t('Chat Bot Fields') . '</h2>',
          '#prefix' => '<div>',
          '#suffix' => '</div>',
      ];
    $form['custom_fields_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'custom-fields-wrapper'],
    ];
    $form['custom_fields_wrapper']['custom_fields'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Weight'),
        $this->t('Required?'),
        $this->t('Field Type'),
        $this->t('Label'),
        $this->t('Field ID'),
        $this->t('Mapping Key'),
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

    foreach ($fields as $key => $field) {
      $form['custom_fields_wrapper']['custom_fields'][$key]['#attributes'] = [
        'class' => ['draggable'],
      ];
      $form['custom_fields_wrapper']['custom_fields'][$key]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => $field['weight'] ?? 0, // Default weight to 0
        '#attributes' => ['class' => ['field-weight']],
      ];
      $form['custom_fields_wrapper']['custom_fields'][$key]['required'] = [
        '#type' => 'checkbox',
        '#default_value' => $field['required'],
      ];
      $form['custom_fields_wrapper']['custom_fields'][$key]['type'] = [
        '#type' => 'select',
        '#options' => [
          'text' => $this->t('Text'),
          'select' => $this->t('Select'),
        ],
        '#default_value' => $field['type'],
      ];
      $form['custom_fields_wrapper']['custom_fields'][$key]['label'] = [
        '#type' => 'textfield',
        '#default_value' => $field['label'],
        '#placeholder' => $this->t('Field Label'),
      ];
      $form['custom_fields_wrapper']['custom_fields'][$key]['id'] = [
        '#type' => 'textfield',
        '#default_value' => $field['id'],
        '#placeholder' => $this->t('Field ID'),
      ];
      $form['custom_fields_wrapper']['custom_fields'][$key]['mapping'] = [
        '#type' => 'textfield',
        '#default_value' => $field['mapping'],
        '#placeholder' => $this->t('Mapping Key'),
      ];
      $form['custom_fields_wrapper']['custom_fields'][$key]['options'] = [
        '#type' => 'textarea',
        '#default_value' => $field['options'],
        '#placeholder' => $this->t('Comma-separated options for select'),
      ];
      $form['custom_fields_wrapper']['custom_fields'][$key]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#submit' => ['::removeField'],
        '#name' => 'remove_' . $key,
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'custom-fields-wrapper',
        ],
      ];
    }

    foreach ($fields as $key => $field) {
      $form['custom_fields_wrapper']['custom_fields'][$key]['weight']['#attributes']['class'][] = 'field-weight';
    }

    $form['add_field'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Field'),
      '#submit' => ['::addField'], // Use the proper callable syntax for non-static methods.
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'custom-fields-wrapper',
      ],
    ];

    $fields = $form_state->get('custom_fields') ?? $this->getStoredFields();
    usort($fields, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    return parent::buildForm($form, $form_state);
  }

  /**
   * Handle form submission.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('ap_chatbot_lumen.settings')
      ->set('deployment_id', $form_state->getValue('deployment_id'))
      ->set('environment_name', $form_state->getValue('environment_name'))
      ->set('chat_icon', $form_state->getValue('chat_icon'))
      ->set('custom_fields', $form_state->getValue('custom_fields'))
      ->set('icon_alttext', $form_state->getValue('icon_alttext'))
      ->save();

    // Invalidate the cache tag for the configuration.
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['config:ap_chatbot_lumen.settings']);


    parent::submitForm($form, $form_state);
  }

  /**
   * Add and remove a custom field row.
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

  public function removeField(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $key = str_replace('remove_', '', $trigger['#name']);

    $fields = $form_state->get('custom_fields') ?? [];

    // Convert to an indexed array to match keys.
    $keys = array_keys($fields);

    // Ensure the correct field is removed.
    if (isset($keys[$key])) {
      unset($fields[$keys[$key]]);
    }

    // Re-index the array to avoid skipping indices.
    $form_state->set('custom_fields', array_values($fields));
    $form_state->setRebuild(TRUE);
  }

  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    // Return the custom fields wrapper container.
    return $form['custom_fields_wrapper'];
  }

}
