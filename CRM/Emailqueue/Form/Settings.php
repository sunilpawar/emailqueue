<?php

use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Form controller class for Email Queue Settings.
 */
class CRM_Emailqueue_Form_Settings extends CRM_Core_Form {

  public function buildQuickForm() {

    // Add form elements
    $this->add('checkbox', 'emailqueue_enabled', E::ts('Enable Email Queue System'));

    $this->add('text', 'emailqueue_db_host', E::ts('Database Host'), ['class' => 'form-control'], TRUE);
    $this->add('text', 'emailqueue_db_name', E::ts('Database Name'), ['class' => 'form-control'], TRUE);
    $this->add('text', 'emailqueue_db_user', E::ts('Database Username'), ['class' => 'form-control'], TRUE);
    $this->add('password', 'emailqueue_db_pass', E::ts('Database Password'), ['class' => 'form-control']);

    $this->add('text', 'emailqueue_batch_size', E::ts('Batch Size'), ['class' => 'form-control'], TRUE);
    $this->add('text', 'emailqueue_retry_attempts', E::ts('Max Retry Attempts'), ['class' => 'form-control'], TRUE);

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Save'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => E::ts('Cancel'),
      ],
    ]);

    // Add test connection button
    $this->assign('elementNames', $this->getRenderableElementNames());
    //$this->assign('queueStats', CRM_Emailqueue_BAO_Queue::getQueueStats());

    parent::buildQuickForm();
  }

  public function setDefaultValues() {
    $defaults = [];

    $settings = [
      'emailqueue_enabled',
      'emailqueue_db_host',
      'emailqueue_db_name',
      'emailqueue_db_user',
      'emailqueue_db_pass',
      'emailqueue_batch_size',
      'emailqueue_retry_attempts'
    ];

    foreach ($settings as $setting) {
      $defaults[$setting] = Civi::settings()->get($setting);
    }

    return $defaults;
  }

  public function addRules() {
    $this->addRule('emailqueue_batch_size', E::ts('Batch size must be a positive integer'), 'positiveInteger');
    $this->addRule('emailqueue_retry_attempts', E::ts('Retry attempts must be a positive integer'), 'positiveInteger');
  }

  public function postProcess() {
    $values = $this->exportValues();

    $settings = [
      'emailqueue_enabled' => CRM_Utils_Array::value('emailqueue_enabled', $values, 0),
      'emailqueue_db_host' => CRM_Utils_Array::value('emailqueue_db_host', $values),
      'emailqueue_db_name' => CRM_Utils_Array::value('emailqueue_db_name', $values),
      'emailqueue_db_user' => CRM_Utils_Array::value('emailqueue_db_user', $values),
      'emailqueue_db_pass' => CRM_Utils_Array::value('emailqueue_db_pass', $values),
      'emailqueue_batch_size' => (int)CRM_Utils_Array::value('emailqueue_batch_size', $values, 50),
      'emailqueue_retry_attempts' => (int)CRM_Utils_Array::value('emailqueue_retry_attempts', $values, 3),
    ];

    foreach ($settings as $key => $value) {
      Civi::settings()->set($key, $value);
    }

    // Test database connection if enabled
    if ($settings['emailqueue_enabled']) {
      try {
        CRM_Emailqueue_BAO_Queue::getQueueConnection();
        CRM_Emailqueue_BAO_Queue::createTables();
        CRM_Core_Session::setStatus(E::ts('Email Queue settings saved successfully and database connection tested.'), E::ts('Settings Saved'), 'success');
      }
      catch (Exception $e) {
        CRM_Core_Session::setStatus(E::ts('Settings saved but database connection failed: %1', [1 => $e->getMessage()]), E::ts('Connection Error'), 'error');
      }
    }
    else {
      CRM_Core_Session::setStatus(E::ts('Email Queue settings saved successfully.'), E::ts('Settings Saved'), 'success');
    }

    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  /**
   * Handle AJAX test connection request.
   */
  public static function testConnection() {
    try {
      $connection = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      $result = ['success' => TRUE, 'message' => E::ts('Database connection successful')];
    }
    catch (Exception $e) {
      $result = ['success' => FALSE, 'message' => $e->getMessage()];
    }

    CRM_Utils_JSON::output($result);
  }
}
