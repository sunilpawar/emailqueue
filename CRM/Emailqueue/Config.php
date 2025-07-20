<?php

/**
 * Configuration class for Email Queue extension with multi-client support.
 */
class CRM_Emailqueue_Config {

  // Extension constants
  const EXTENSION_KEY = 'com.skvare.emailqueue';
  const EXTENSION_NAME = 'Email Queue System';
  const EXTENSION_VERSION = '1.0.0'; // Updated version for multi-client support

  // Database table names
  const TABLE_EMAIL_QUEUE = 'email_queue';
  const TABLE_EMAIL_QUEUE_LOG = 'email_queue_log';

  // Email statuses
  const STATUS_PENDING = 'pending';
  const STATUS_PROCESSING = 'processing';
  const STATUS_SENT = 'sent';
  const STATUS_FAILED = 'failed';
  const STATUS_CANCELLED = 'cancelled';

  // Priority levels
  const PRIORITY_HIGHEST = 1;
  const PRIORITY_HIGH = 2;
  const PRIORITY_NORMAL = 3;
  const PRIORITY_LOW = 4;
  const PRIORITY_LOWEST = 5;

  // Default settings
  const DEFAULT_BATCH_SIZE = 50;
  const DEFAULT_RETRY_ATTEMPTS = 3;
  const DEFAULT_RETRY_DELAY_MINUTES = 5;
  const MAX_BULK_OPERATION_SIZE = 1000;
  const MAX_EMAIL_SIZE_MB = 25;
  const MAX_SUBJECT_LENGTH = 255;
  const MAX_EMAIL_ADDRESS_LENGTH = 320;

  // Log actions
  const LOG_ACTION_QUEUED = 'queued';
  const LOG_ACTION_VALIDATED = 'validated';
  const LOG_ACTION_SCHEDULED = 'scheduled';
  const LOG_ACTION_PROCESSING = 'processing';
  const LOG_ACTION_SENT = 'sent';
  const LOG_ACTION_FAILED = 'failed';
  const LOG_ACTION_CANCELLED = 'cancelled';
  const LOG_ACTION_RETRY_SCHEDULED = 'retry_scheduled';
  const LOG_ACTION_BULK_CANCELLED = 'bulk_cancelled';
  const LOG_ACTION_BULK_RETRIED = 'bulk_retried';

  // Cache keys
  const CACHE_KEY_STATS = 'emailqueue_stats';
  const CACHE_KEY_FILTER_OPTIONS = 'emailqueue_filter_options';
  const CACHE_TTL_STATS = 300; // 5 minutes
  const CACHE_TTL_FILTER_OPTIONS = 3600; // 1 hour

  /**
   * Get current client ID with fallback logic.
   */
  public static function getCurrentClientId() {
    // Check for temporary client override (for admin operations)
    $tempClientId = self::getSetting('temp_client_id');
    if (!empty($tempClientId)) {
      return $tempClientId;
    }

    // Get configured client ID
    $clientId = self::getSetting('client_id');

    if (empty($clientId)) {
      // Auto-generate client ID based on domain
      $clientId = self::generateClientId();
      // Save it for future use
      self::setSetting('client_id', $clientId);
    }

    return $clientId;
  }

  /**
   * Generate a client ID based on domain and organization.
   */
  protected static function generateClientId() {
    $domain = CRM_Core_Config::domainID();
    $org = CRM_Core_Config::singleton()->userFramework;

    // Try to get organization name
    try {
      $orgContact = civicrm_api3('Domain', 'getsingle', ['id' => $domain]);
      $orgName = $orgContact['name'] ?? '';

      if (!empty($orgName)) {
        // Clean the organization name for use as client ID
        $clientId = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($orgName));
        $clientId = trim($clientId, '_');
      }
      else {
        $clientId = 'domain_' . $domain;
      }
    }
    catch (Exception $e) {
      $clientId = 'domain_' . $domain;
    }

    // Fallback to default if still empty
    if (empty($clientId)) {
      $clientId = 'default';
    }

    return $clientId;
  }

  /**
   * Set client ID for current context.
   */
  public static function setClientId($clientId) {
    if (empty($clientId)) {
      throw new Exception('Client ID cannot be empty');
    }

    // Validate client ID format (alphanumeric, underscore, hyphen only)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $clientId)) {
      throw new Exception('Client ID can only contain letters, numbers, underscores, and hyphens');
    }

    self::setSetting('client_id', $clientId);
  }

  /**
   * Get all available email statuses.
   */
  public static function getEmailStatuses() {
    return [
      self::STATUS_PENDING => 'Pending',
      self::STATUS_PROCESSING => 'Processing',
      self::STATUS_SENT => 'Sent',
      self::STATUS_FAILED => 'Failed',
      self::STATUS_CANCELLED => 'Cancelled'
    ];
  }

  /**
   * Get all priority levels.
   */
  public static function getPriorityLevels() {
    return [
      self::PRIORITY_HIGHEST => 'Highest',
      self::PRIORITY_HIGH => 'High',
      self::PRIORITY_NORMAL => 'Normal',
      self::PRIORITY_LOW => 'Low',
      self::PRIORITY_LOWEST => 'Lowest'
    ];
  }

  /**
   * Get default extension settings.
   */
  public static function getDefaultSettings() {
    return [
      'emailqueue_enabled' => FALSE,
      'emailqueue_client_id' => self::generateClientId(),
      'emailqueue_db_host' => 'localhost',
      'emailqueue_db_name' => 'emailqueue',
      'emailqueue_db_user' => '',
      'emailqueue_db_pass' => '',
      'emailqueue_db_port' => 3306,
      'emailqueue_batch_size' => self::DEFAULT_BATCH_SIZE,
      'emailqueue_retry_attempts' => self::DEFAULT_RETRY_ATTEMPTS,
      'emailqueue_retry_delay' => self::DEFAULT_RETRY_DELAY_MINUTES,
      'emailqueue_max_email_size' => self::MAX_EMAIL_SIZE_MB,
      'emailqueue_cleanup_days' => 90,
      'emailqueue_enable_tracking' => TRUE,
      'emailqueue_enable_validation' => TRUE,
      'emailqueue_log_level' => 'info',
      'emailqueue_multi_client_mode' => FALSE,
      'emailqueue_admin_client_access' => FALSE,
    ];
  }

  /**
   * Get setting key with prefix.
   */
  public static function getSettingKey($key) {
    if (strpos($key, 'emailqueue_') === 0) {
      return $key;
    }
    return 'emailqueue_' . $key;
  }

  /**
   * Get extension setting value.
   */
  public static function getSetting($key, $default = NULL) {
    $settingKey = self::getSettingKey($key);
    return Civi::settings()->get($settingKey) ?? $default;
  }

  /**
   * Set extension setting value.
   */
  public static function setSetting($key, $value) {
    $settingKey = self::getSettingKey($key);
    return Civi::settings()->set($settingKey, $value);
  }

  /**
   * Check if extension is enabled.
   */
  public static function isEnabled() {
    return (bool)self::getSetting('enabled', FALSE);
  }

  /**
   * Check if multi-client mode is enabled.
   */
  public static function isMultiClientMode() {
    return (bool)self::getSetting('multi_client_mode', FALSE);
  }

  /**
   * Check if admin has access to all clients.
   */
  public static function hasAdminClientAccess() {
    return (bool)self::getSetting('admin_client_access', FALSE);
  }

  /**
   * Get database connection settings.
   */
  public static function getDatabaseSettings() {
    return [
      'host' => self::getSetting('db_host', 'localhost'),
      'port' => self::getSetting('db_port', 3306),
      'name' => self::getSetting('db_name', 'emailqueue'),
      'user' => self::getSetting('db_user', ''),
      'pass' => self::getSetting('db_pass', ''),
    ];
  }

  /**
   * Get processing settings.
   */
  public static function getProcessingSettings() {
    return [
      'batch_size' => (int)self::getSetting('batch_size', self::DEFAULT_BATCH_SIZE),
      'retry_attempts' => (int)self::getSetting('retry_attempts', self::DEFAULT_RETRY_ATTEMPTS),
      'retry_delay' => (int)self::getSetting('retry_delay', self::DEFAULT_RETRY_DELAY_MINUTES),
      'max_email_size' => (int)self::getSetting('max_email_size', self::MAX_EMAIL_SIZE_MB),
    ];
  }

  /**
   * Get valid sort fields for search.
   */
  public static function getValidSortFields() {
    return [
      'id' => 'ID',
      'to_email' => 'To Email',
      'from_email' => 'From Email',
      'subject' => 'Subject',
      'status' => 'Status',
      'priority' => 'Priority',
      'created_date' => 'Created Date',
      'sent_date' => 'Sent Date',
      'retry_count' => 'Retry Count'
    ];
  }

  /**
   * Get bulk action options.
   */
  public static function getBulkActions() {
    return [
      'cancel' => 'Cancel Selected Emails',
      'retry' => 'Retry Failed Emails',
      'delete' => 'Delete Selected Emails'
    ];
  }

  /**
   * Get export formats.
   */
  public static function getExportFormats() {
    return [
      'csv' => 'CSV (Comma Separated Values)',
      'xlsx' => 'Excel (.xlsx)',
      'json' => 'JSON'
    ];
  }

  /**
   * Get log levels.
   */
  public static function getLogLevels() {
    return [
      'debug' => 'Debug',
      'info' => 'Info',
      'warning' => 'Warning',
      'error' => 'Error'
    ];
  }

  /**
   * Get permission requirements.
   */
  public static function getPermissions() {
    return [
      'admin' => 'administer CiviCRM',
      'view' => 'access CiviCRM',
      'edit' => 'edit all contacts'
    ];
  }

  /**
   * Get email size limit in bytes.
   */
  public static function getEmailSizeLimit() {
    $sizeMB = self::getSetting('max_email_size', self::MAX_EMAIL_SIZE_MB);
    return $sizeMB * 1024 * 1024; // Convert MB to bytes
  }

  /**
   * Get retry delay for attempt number.
   */
  public static function getRetryDelay($attemptNumber) {
    $baseDelay = self::getSetting('retry_delay', self::DEFAULT_RETRY_DELAY_MINUTES);

    // Exponential backoff: 5, 10, 20, 40 minutes etc.
    return $baseDelay * pow(2, $attemptNumber - 1);
  }

  /**
   * Get maximum allowed retry attempts.
   */
  public static function getMaxRetryAttempts() {
    return (int)self::getSetting('retry_attempts', self::DEFAULT_RETRY_ATTEMPTS);
  }

  /**
   * Get cleanup retention period in days.
   */
  public static function getCleanupDays() {
    return (int)self::getSetting('cleanup_days', 90);
  }

  /**
   * Check if email tracking is enabled.
   */
  public static function isTrackingEnabled() {
    return (bool)self::getSetting('enable_tracking', TRUE);
  }

  /**
   * Check if email validation is enabled.
   */
  public static function isValidationEnabled() {
    return (bool)self::getSetting('enable_validation', TRUE);
  }

  /**
   * Get current log level.
   */
  public static function getLogLevel() {
    return self::getSetting('log_level', 'info');
  }

  /**
   * Check if debug mode is enabled.
   */
  public static function isDebugMode() {
    return self::getLogLevel() === 'debug';
  }

  /**
   * Get database DSN for PDO connection.
   */
  public static function getDatabaseDSN() {
    $settings = self::getDatabaseSettings();

    $dsn = "mysql:host={$settings['host']}";

    if (!empty($settings['port']) && $settings['port'] != 3306) {
      $dsn .= ";port={$settings['port']}";
    }

    $dsn .= ";dbname={$settings['name']};charset=utf8mb4";

    return $dsn;
  }

  /**
   * Get extension info for display.
   */
  public static function getExtensionInfo() {
    return [
      'key' => self::EXTENSION_KEY,
      'name' => self::EXTENSION_NAME,
      'version' => self::EXTENSION_VERSION,
      'description' => 'Alternative email system that queues emails in a separate database for delayed processing with multi-client support',
      'author' => 'Your Organization',
      'license' => 'AGPL-3.0'
    ];
  }

  /**
   * Validate configuration settings.
   */
  public static function validateConfiguration() {
    $errors = [];
    $warnings = [];

    // Check if extension is enabled
    if (!self::isEnabled()) {
      $warnings[] = 'Email Queue System is disabled';
      return ['errors' => $errors, 'warnings' => $warnings];
    }

    // Validate client ID
    $clientId = self::getCurrentClientId();
    if (empty($clientId)) {
      $errors[] = 'Client ID is required';
    }
    elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $clientId)) {
      $errors[] = 'Client ID contains invalid characters';
    }

    // Validate database settings
    $dbSettings = self::getDatabaseSettings();

    if (empty($dbSettings['host'])) {
      $errors[] = 'Database host is required';
    }

    if (empty($dbSettings['name'])) {
      $errors[] = 'Database name is required';
    }

    if (empty($dbSettings['user'])) {
      $errors[] = 'Database user is required';
    }

    // Validate processing settings
    $procSettings = self::getProcessingSettings();

    if ($procSettings['batch_size'] < 1 || $procSettings['batch_size'] > 1000) {
      $warnings[] = 'Batch size should be between 1 and 1000';
    }

    if ($procSettings['retry_attempts'] < 0 || $procSettings['retry_attempts'] > 10) {
      $warnings[] = 'Retry attempts should be between 0 and 10';
    }

    // Multi-client mode warnings
    if (self::isMultiClientMode()) {
      $warnings[] = 'Multi-client mode is enabled - ensure proper client isolation';
    }

    return ['errors' => $errors, 'warnings' => $warnings];
  }

  /**
   * Get client configuration info.
   */
  public static function getClientInfo() {
    return [
      'current_client_id' => self::getCurrentClientId(),
      'multi_client_mode' => self::isMultiClientMode(),
      'admin_client_access' => self::hasAdminClientAccess(),
      'generated_client_id' => self::generateClientId(),
    ];
  }

  /**
   * Initialize client settings for a new client.
   */
  public static function initializeClient($clientId, $settings = []) {
    if (empty($clientId)) {
      throw new Exception('Client ID is required');
    }

    // Validate client ID format
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $clientId)) {
      throw new Exception('Client ID can only contain letters, numbers, underscores, and hyphens');
    }

    // Set client-specific settings
    $clientSettings = array_merge(self::getDefaultSettings(), $settings);
    $clientSettings['emailqueue_client_id'] = $clientId;

    foreach ($clientSettings as $key => $value) {
      self::setSetting($key, $value);
    }

    return TRUE;
  }

  /**
   * Get client list (if admin access is enabled).
   */
  public static function getClientList() {
    if (!self::hasAdminClientAccess()) {
      throw new Exception('Admin client access is not enabled');
    }

    try {
      return CRM_Emailqueue_BAO_Queue::getClientStats();
    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Failed to get client list: ' . $e->getMessage());
      return [];
    }
  }
}
