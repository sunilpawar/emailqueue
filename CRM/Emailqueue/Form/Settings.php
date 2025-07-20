<?php

use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Form controller class for Email Queue Settings with multi-client support.
 */
class CRM_Emailqueue_Form_Settings extends CRM_Core_Form {

  public function buildQuickForm() {

    // Add form elements
    $this->add('checkbox', 'emailqueue_enabled', E::ts('Enable Email Queue System'));

    // Multi-client settings
    $this->add('text', 'emailqueue_client_id', E::ts('Client ID'), [
      'class' => 'form-control',
      'placeholder' => 'e.g., organization_name, domain_1, client_abc'
    ], TRUE);

    $this->add('checkbox', 'emailqueue_multi_client_mode', E::ts('Enable Multi-Client Mode'));
    $this->add('checkbox', 'emailqueue_admin_client_access', E::ts('Enable Admin Client Access'));

    // Database settings
    $this->add('text', 'emailqueue_db_host', E::ts('Database Host'), ['class' => 'form-control'], TRUE);
    $this->add('text', 'emailqueue_db_name', E::ts('Database Name'), ['class' => 'form-control'], TRUE);
    $this->add('text', 'emailqueue_db_user', E::ts('Database Username'), ['class' => 'form-control'], TRUE);
    $this->add('password', 'emailqueue_db_pass', E::ts('Database Password'), ['class' => 'form-control']);

    // Processing settings
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

    // Get current client information
    $this->assign('currentClientInfo', CRM_Emailqueue_Config::getClientInfo());

    // Get client statistics if admin access is enabled
    try {
      if (CRM_Emailqueue_Config::hasAdminClientAccess()) {
        $this->assign('clientStats', CRM_Emailqueue_Config::getClientList());
      }
    }
    catch (Exception $e) {
      // Ignore errors when getting client list
    }

    parent::buildQuickForm();
  }

  public function setDefaultValues() {
    $defaults = [];

    $settings = [
      'emailqueue_enabled',
      'emailqueue_client_id',
      'emailqueue_multi_client_mode',
      'emailqueue_admin_client_access',
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

    // If client_id is empty, suggest a generated one
    if (empty($defaults['emailqueue_client_id'])) {
      $defaults['emailqueue_client_id'] = CRM_Emailqueue_Config::getCurrentClientId();
    }

    return $defaults;
  }

  public function addRules() {
    $this->addRule('emailqueue_batch_size', E::ts('Batch size must be a positive integer'), 'positiveInteger');
    $this->addRule('emailqueue_retry_attempts', E::ts('Retry attempts must be a positive integer'), 'positiveInteger');

    // Add custom validation for client_id
    $this->addRule('emailqueue_client_id', E::ts('Client ID is required'), 'required');
    $this->addFormRule([$this, 'validateClientId']);
  }

  /**
   * Custom validation for client ID format.
   */
  public function validateClientId($fields) {
    $errors = [];

    if (!empty($fields['emailqueue_client_id'])) {
      $clientId = trim($fields['emailqueue_client_id']);

      // Check format (alphanumeric, underscore, hyphen only)
      if (!preg_match('/^[a-zA-Z0-9_-]+$/', $clientId)) {
        $errors['emailqueue_client_id'] = E::ts('Client ID can only contain letters, numbers, underscores, and hyphens');
      }

      // Check length
      if (strlen($clientId) > 64) {
        $errors['emailqueue_client_id'] = E::ts('Client ID cannot be longer than 64 characters');
      }

      // Check if it's not just numbers or special characters
      if (!preg_match('/[a-zA-Z]/', $clientId)) {
        $errors['emailqueue_client_id'] = E::ts('Client ID must contain at least one letter');
      }
    }

    return $errors;
  }

  public function postProcess() {
    $values = $this->exportValues();

    // Validate and clean client_id
    $clientId = trim(CRM_Utils_Array::value('emailqueue_client_id', $values, ''));
    if (empty($clientId)) {
      $clientId = CRM_Emailqueue_Config::getCurrentClientId();
    }

    $settings = [
      'emailqueue_enabled' => CRM_Utils_Array::value('emailqueue_enabled', $values, 0),
      'emailqueue_client_id' => $clientId,
      'emailqueue_multi_client_mode' => CRM_Utils_Array::value('emailqueue_multi_client_mode', $values, 0),
      'emailqueue_admin_client_access' => CRM_Utils_Array::value('emailqueue_admin_client_access', $values, 0),
      'emailqueue_db_host' => CRM_Utils_Array::value('emailqueue_db_host', $values),
      'emailqueue_db_name' => CRM_Utils_Array::value('emailqueue_db_name', $values),
      'emailqueue_db_user' => CRM_Utils_Array::value('emailqueue_db_user', $values),
      'emailqueue_db_pass' => CRM_Utils_Array::value('emailqueue_db_pass', $values),
      'emailqueue_batch_size' => (int)CRM_Utils_Array::value('emailqueue_batch_size', $values, 50),
      'emailqueue_retry_attempts' => (int)CRM_Utils_Array::value('emailqueue_retry_attempts', $values, 3),
    ];

    // Check if client_id changed
    $originalClientId = Civi::settings()->get('emailqueue_client_id');
    $clientIdChanged = $originalClientId !== $clientId;

    foreach ($settings as $key => $value) {
      Civi::settings()->set($key, $value);
    }

    // Test database connection if enabled
    if ($settings['emailqueue_enabled']) {
      try {
        CRM_Emailqueue_BAO_Queue::getQueueConnection();
        CRM_Emailqueue_BAO_Queue::createTables();

        $successMessage = E::ts('Email Queue settings saved successfully and database connection tested.');

        // If client_id changed, show additional information
        if ($clientIdChanged && !empty($originalClientId)) {
          $successMessage .= ' ' . E::ts('Client ID changed from "%1" to "%2". Existing data remains under the old client ID.', [
              1 => $originalClientId,
              2 => $clientId
            ]);

          // Offer data migration for admin users
          if ($settings['emailqueue_admin_client_access']) {
            $migrationUrl = CRM_Utils_System::url('civicrm/admin/emailqueue/migrate-client', [
              'old_client_id' => $originalClientId,
              'new_client_id' => $clientId
            ]);
            $successMessage .= ' ' . E::ts('<a href="%1">Click here to migrate existing data to the new client ID</a>.', [1 => $migrationUrl]);
          }
        }

        CRM_Core_Session::setStatus($successMessage, E::ts('Settings Saved'), 'success');
      }
      catch (Exception $e) {
        CRM_Core_Session::setStatus(E::ts('Settings saved but database connection failed: %1', [1 => $e->getMessage()]), E::ts('Connection Error'), 'error');
      }
    }
    else {
      CRM_Core_Session::setStatus(E::ts('Email Queue settings saved successfully.'), E::ts('Settings Saved'), 'success');
    }

    // Log the settings change
    CRM_Emailqueue_Utils_ErrorHandler::info('Email Queue settings updated', [
      'client_id' => $clientId,
      'client_id_changed' => $clientIdChanged,
      'multi_client_mode' => $settings['emailqueue_multi_client_mode'],
      'admin_access' => $settings['emailqueue_admin_client_access']
    ]);

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

  /**
   * Handle AJAX client ID validation request.
   */
  public static function validateClientIdAjax() {
    try {
      $clientId = CRM_Utils_Request::retrieve('client_id', 'String');

      if (empty($clientId)) {
        CRM_Utils_JSON::output(['success' => FALSE, 'message' => 'Client ID is required']);
        return;
      }

      // Validate format
      if (!preg_match('/^[a-zA-Z0-9_-]+$/', $clientId)) {
        CRM_Utils_JSON::output([
          'success' => FALSE,
          'message' => 'Client ID can only contain letters, numbers, underscores, and hyphens'
        ]);
        return;
      }

      // Check length
      if (strlen($clientId) > 64) {
        CRM_Utils_JSON::output([
          'success' => FALSE,
          'message' => 'Client ID cannot be longer than 64 characters'
        ]);
        return;
      }

      // Check if it contains at least one letter
      if (!preg_match('/[a-zA-Z]/', $clientId)) {
        CRM_Utils_JSON::output([
          'success' => FALSE,
          'message' => 'Client ID must contain at least one letter'
        ]);
        return;
      }

      // Check if client exists in database
      $exists = FALSE;
      try {
        $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_queue WHERE client_id = ? LIMIT 1");
        $stmt->execute([$clientId]);
        $exists = $stmt->fetchColumn() > 0;
      }
      catch (Exception $e) {
        // Database connection might not be available yet
      }

      $message = 'Client ID is valid';
      if ($exists) {
        $message .= ' and already exists in database';
      }
      else {
        $message .= ' (new client)';
      }

      CRM_Utils_JSON::output([
        'success' => TRUE,
        'message' => $message,
        'exists' => $exists,
        'is_new' => !$exists
      ]);

    }
    catch (Exception $e) {
      CRM_Utils_JSON::output(['success' => FALSE, 'message' => $e->getMessage()]);
    }
  }

  /**
   * Handle AJAX generate client ID request.
   */
  public static function generateClientIdAjax() {
    try {
      $generatedId = CRM_Emailqueue_Config::getCurrentClientId();

      CRM_Utils_JSON::output([
        'success' => TRUE,
        'client_id' => $generatedId,
        'message' => 'Generated client ID based on current domain/organization'
      ]);

    }
    catch (Exception $e) {
      CRM_Utils_JSON::output(['success' => FALSE, 'message' => $e->getMessage()]);
    }
  }

  /**
   * Handle client data migration request.
   */
  public static function migrateClientData() {
    try {
      // Check admin access
      if (!CRM_Emailqueue_Config::hasAdminClientAccess()) {
        CRM_Core_Session::setStatus(E::ts('Admin access required for data migration'), E::ts('Access Denied'), 'error');
        CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/admin/emailqueue/settings'));
        return;
      }

      $oldClientId = CRM_Utils_Request::retrieve('old_client_id', 'String');
      $newClientId = CRM_Utils_Request::retrieve('new_client_id', 'String');

      if (empty($oldClientId) || empty($newClientId)) {
        CRM_Core_Session::setStatus(E::ts('Both old and new client IDs are required'), E::ts('Migration Error'), 'error');
        CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/admin/emailqueue/settings'));
        return;
      }

      // Perform migration
      $upgrader = new CRM_Emailqueue_Upgrader();
      $result = $upgrader->migrateDataForMultiClient($oldClientId, $newClientId);

      $message = E::ts('Successfully migrated %1 email queue records and %2 log records from client "%3" to "%4"', [
        1 => $result['email_queue_updated'],
        2 => $result['email_queue_log_updated'],
        3 => $oldClientId,
        4 => $newClientId
      ]);

      CRM_Core_Session::setStatus($message, E::ts('Migration Completed'), 'success');

    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus(E::ts('Migration failed: %1', [1 => $e->getMessage()]), E::ts('Migration Error'), 'error');
    }

    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/admin/emailqueue/settings'));
  }
}
