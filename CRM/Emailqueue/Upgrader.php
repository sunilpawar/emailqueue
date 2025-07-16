<?php

use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Emailqueue_Upgrader extends CRM_Extension_Upgrader_Base {
  /**
   * Installation routine.
   */
  public function install() {
    // Set default settings
    $this->setDefaultSettings();

    // Create database tables (will be done when user configures DB settings)
    // Note: We don't create tables here because we use a separate database

    // Add scheduled job
    $this->addScheduledJob();

    // Log installation
    CRM_Core_Error::debug_log_message('Email Queue Extension installed successfully');
  }

  /**
   * Uninstallation routine.
   */
  public function uninstall() {
    // Remove scheduled job
    $this->removeScheduledJob();

    // Clean up settings (keep DB connection settings for manual cleanup)
    $this->cleanupSettings();

    // Note: We don't drop database tables automatically for safety
    // Users should manually drop the separate database if desired

    CRM_Core_Error::debug_log_message('Email Queue Extension uninstalled');
  }

  /**
   * Enable routine.
   */
  public function enable() {
    // Re-enable scheduled job
    $this->enableScheduledJob();

    CRM_Core_Error::debug_log_message('Email Queue Extension enabled');
  }

  /**
   * Disable routine.
   */
  public function disable() {
    // Disable but don't remove scheduled job
    $this->disableScheduledJob();

    CRM_Core_Error::debug_log_message('Email Queue Extension disabled');
  }

  /**
   * Upgrade to version 1.0.1 - Add indexes and optimize tables.
   */
  public function upgrade_1001() {
    $this->ctx->log->info('Upgrading Email Queue to version 1.0.1');

    try {
      // Add additional indexes for better performance
      $this->addPerformanceIndexes();

      // Update default settings
      $this->updateSettingsVersion1001();

      return TRUE;
    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue upgrade 1.0.1 failed: ' . $e->getMessage());
      return FALSE;
    }
  }

  /**
   * Upgrade to version 1.0.2 - Add email validation features.
   */
  public function upgrade_1002() {
    $this->ctx->log->info('Upgrading Email Queue to version 1.0.2');

    try {
      // Add new columns for email validation
      $this->addValidationColumns();

      // Update settings for validation features
      $this->updateSettingsVersion1002();

      return TRUE;
    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue upgrade 1.0.2 failed: ' . $e->getMessage());
      return FALSE;
    }
  }

  /**
   * Set default settings on installation.
   */
  protected function setDefaultSettings() {
    $defaultSettings = CRM_Emailqueue_Config::getDefaultSettings();

    foreach ($defaultSettings as $key => $value) {
      // Only set if not already configured
      if (Civi::settings()->get($key) === NULL) {
        Civi::settings()->set($key, $value);
      }
    }
  }

  /**
   * Add scheduled job for queue processing.
   */
  protected function addScheduledJob() {
    try {
      $result = civicrm_api3('Job', 'create', [
        'name' => 'Email Queue Processor',
        'description' => 'Process emails in the email queue',
        'run_frequency' => 'Every',
        'frequency_unit' => 'minute',
        'frequency_interval' => 5,
        'api_entity' => 'Emailqueue',
        'api_action' => 'processqueue',
        'parameters' => '',
        'is_active' => 1,
      ]);

      CRM_Core_Error::debug_log_message('Email Queue scheduled job created with ID: ' . $result['id']);

      // Store job ID in settings for future reference
      Civi::settings()->set('emailqueue_job_id', $result['id']);

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Failed to create Email Queue scheduled job: ' . $e->getMessage());
    }
  }

  /**
   * Remove scheduled job.
   */
  protected function removeScheduledJob() {
    $jobId = Civi::settings()->get('emailqueue_job_id');

    if ($jobId) {
      try {
        civicrm_api3('Job', 'delete', ['id' => $jobId]);
        Civi::settings()->revert('emailqueue_job_id');
        CRM_Core_Error::debug_log_message('Email Queue scheduled job removed');
      }
      catch (Exception $e) {
        CRM_Core_Error::debug_log_message('Failed to remove Email Queue scheduled job: ' . $e->getMessage());
      }
    }
  }

  /**
   * Enable scheduled job.
   */
  protected function enableScheduledJob() {
    $jobId = Civi::settings()->get('emailqueue_job_id');

    if ($jobId) {
      try {
        civicrm_api3('Job', 'create', [
          'id' => $jobId,
          'is_active' => 1,
        ]);
      }
      catch (Exception $e) {
        CRM_Core_Error::debug_log_message('Failed to enable Email Queue scheduled job: ' . $e->getMessage());
      }
    }
    else {
      // Job doesn't exist, create it
      $this->addScheduledJob();
    }
  }

  /**
   * Disable scheduled job.
   */
  protected function disableScheduledJob() {
    $jobId = Civi::settings()->get('emailqueue_job_id');

    if ($jobId) {
      try {
        civicrm_api3('Job', 'create', [
          'id' => $jobId,
          'is_active' => 0,
        ]);
      }
      catch (Exception $e) {
        CRM_Core_Error::debug_log_message('Failed to disable Email Queue scheduled job: ' . $e->getMessage());
      }
    }
  }

  /**
   * Clean up settings on uninstall.
   */
  protected function cleanupSettings() {
    $settingsToKeep = [
      'emailqueue_db_host',
      'emailqueue_db_name',
      'emailqueue_db_user',
      'emailqueue_db_pass',
      'emailqueue_db_port'
    ];

    $allSettings = array_keys(CRM_Emailqueue_Config::getDefaultSettings());

    foreach ($allSettings as $setting) {
      if (!in_array($setting, $settingsToKeep)) {
        Civi::settings()->revert($setting);
      }
    }
  }

  /**
   * Add performance indexes for version 1.0.1.
   */
  protected function addPerformanceIndexes() {
    if (!CRM_Emailqueue_Config::isEnabled()) {
      return; // Skip if extension is disabled
    }

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      // Add composite indexes for better query performance
      $indexes = [
        'idx_status_priority_created' => 'CREATE INDEX idx_status_priority_created ON email_queue (status, priority, created_date)',
        'idx_status_scheduled' => 'CREATE INDEX idx_status_scheduled ON email_queue (status, scheduled_date)',
        'idx_from_email_status' => 'CREATE INDEX idx_from_email_status ON email_queue (from_email, status)',
        'idx_to_email_status' => 'CREATE INDEX idx_to_email_status ON email_queue (to_email, status)',
      ];

      foreach ($indexes as $name => $sql) {
        try {
          $pdo->exec($sql);
          CRM_Core_Error::debug_log_message("Added index: $name");
        }
        catch (PDOException $e) {
          // Index might already exist, log but continue
          if (strpos($e->getMessage(), 'Duplicate key name') === FALSE) {
            CRM_Core_Error::debug_log_message("Failed to add index $name: " . $e->getMessage());
          }
        }
      }

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Failed to add performance indexes: ' . $e->getMessage());
    }
  }

  /**
   * Update settings for version 1.0.1.
   */
  protected function updateSettingsVersion1001() {
    // Add new settings introduced in 1.0.1
    $newSettings = [
      'emailqueue_cleanup_days' => 90,
      'emailqueue_log_level' => 'info',
    ];

    foreach ($newSettings as $key => $value) {
      if (Civi::settings()->get($key) === NULL) {
        Civi::settings()->set($key, $value);
      }
    }
  }

  /**
   * Add validation columns for version 1.0.2.
   */
  protected function addValidationColumns() {
    if (!CRM_Emailqueue_Config::isEnabled()) {
      return; // Skip if extension is disabled
    }

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      // Add new columns for email validation
      $alterSql = "
        ALTER TABLE email_queue
        ADD COLUMN validation_score TINYINT UNSIGNED NULL AFTER error_message,
        ADD COLUMN validation_warnings TEXT NULL AFTER validation_score,
        ADD COLUMN tracking_code VARCHAR(64) NULL AFTER validation_warnings,
        ADD INDEX idx_validation_score (validation_score),
        ADD INDEX idx_tracking_code (tracking_code)
      ";

      $pdo->exec($alterSql);
      CRM_Core_Error::debug_log_message('Added validation columns to email_queue table');

    }
    catch (PDOException $e) {
      // Columns might already exist
      if (strpos($e->getMessage(), 'Duplicate column name') === FALSE) {
        CRM_Core_Error::debug_log_message('Failed to add validation columns: ' . $e->getMessage());
      }
    }
  }

  /**
   * Update settings for version 1.0.2.
   */
  protected function updateSettingsVersion1002() {
    // Add new settings for validation features
    $newSettings = [
      'emailqueue_enable_validation' => TRUE,
      'emailqueue_enable_tracking' => TRUE,
      'emailqueue_validation_strict' => FALSE,
    ];

    foreach ($newSettings as $key => $value) {
      if (Civi::settings()->get($key) === NULL) {
        Civi::settings()->set($key, $value);
      }
    }
  }

  /**
   * Check system requirements before installation.
   */
  public function preInstall() {
    $requirements = $this->checkRequirements();

    if (!empty($requirements['errors'])) {
      throw new Exception('System requirements not met: ' . implode(', ', $requirements['errors']));
    }

    if (!empty($requirements['warnings'])) {
      foreach ($requirements['warnings'] as $warning) {
        CRM_Core_Error::debug_log_message('Warning: ' . $warning);
      }
    }
  }

  /**
   * Check system requirements.
   */
  protected function checkRequirements() {
    $errors = [];
    $warnings = [];

    // Check PHP version
    if (version_compare(PHP_VERSION, '7.2', '<')) {
      $errors[] = 'PHP 7.2 or higher is required';
    }

    // Check PDO MySQL extension
    if (!extension_loaded('pdo_mysql')) {
      $errors[] = 'PDO MySQL extension is required';
    }

    // Check CiviCRM version
    $civiVersion = CRM_Utils_System::version();
    if (version_compare($civiVersion, '5.50', '<')) {
      $warnings[] = 'CiviCRM 5.50 or higher is recommended';
    }

    // Check if cron is configured
    $jobs = civicrm_api3('Job', 'get', ['sequential' => 1]);
    if (empty($jobs['values'])) {
      $warnings[] = 'No scheduled jobs found. Please ensure CiviCRM cron is configured.';
    }

    // Check memory limit
    $memoryLimit = ini_get('memory_limit');
    if ($memoryLimit && $memoryLimit !== '-1') {
      $memoryBytes = $this->parseMemoryLimit($memoryLimit);
      if ($memoryBytes < 128 * 1024 * 1024) { // 128MB
        $warnings[] = 'PHP memory limit is below 128MB. Consider increasing for better performance.';
      }
    }

    return ['errors' => $errors, 'warnings' => $warnings];
  }

  /**
   * Parse memory limit string to bytes.
   */
  protected function parseMemoryLimit($limit) {
    $limit = trim($limit);
    $last = strtolower($limit[strlen($limit) - 1]);
    $value = (int)$limit;

    switch ($last) {
      case 'g':
        $value *= 1024;
      case 'm':
        $value *= 1024;
      case 'k':
        $value *= 1024;
    }

    return $value;
  }

  /**
   * Post-install tasks.
   */
  public function postInstall() {
    // Clear CiviCRM caches
    CRM_Core_Config::clearDBCache();

    // Rebuild navigation menu
    CRM_Core_BAO_Navigation::resetNavigation();

    CRM_Core_Error::debug_log_message('Email Queue Extension post-install completed');
  }

}
