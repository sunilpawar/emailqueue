<?php

/**
 * Configuration class for Email Queue extension.
 */
class CRM_Emailqueue_Config {

  // Extension constants
  const EXTENSION_KEY = 'com.skvare.emailqueue';
  const EXTENSION_NAME = 'Email Queue System';
  const EXTENSION_VERSION = '1.0.0';

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
      'emailqueue_log_level' => 'info'
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
      'description' => 'Alternative email system that queues emails in a separate database for delayed processing',
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

    return ['errors' => $errors, 'warnings' => $warnings];
  }
}
