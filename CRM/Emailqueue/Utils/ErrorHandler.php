<?php

/**
 * Error handling and logging utilities for Email Queue extension.
 */
class CRM_Emailqueue_Utils_ErrorHandler {

  const LOG_LEVEL_DEBUG = 0;
  const LOG_LEVEL_INFO = 1;
  const LOG_LEVEL_WARNING = 2;
  const LOG_LEVEL_ERROR = 3;
  const LOG_LEVEL_CRITICAL = 4;

  protected static $logLevels = [
    self::LOG_LEVEL_DEBUG => 'DEBUG',
    self::LOG_LEVEL_INFO => 'INFO',
    self::LOG_LEVEL_WARNING => 'WARNING',
    self::LOG_LEVEL_ERROR => 'ERROR',
    self::LOG_LEVEL_CRITICAL => 'CRITICAL'
  ];

  /**
   * Log message with appropriate level.
   */
  public static function log($level, $message, $context = []) {
    $currentLevel = self::getCurrentLogLevel();

    // Only log if message level is high enough
    if ($level < $currentLevel) {
      return;
    }

    $logEntry = self::formatLogEntry($level, $message, $context);

    // Log to CiviCRM log
    CRM_Core_Error::debug_log_message($logEntry);

    // Log to database if enabled and level is warning or higher
    if ($level >= self::LOG_LEVEL_WARNING && CRM_Emailqueue_Config::isEnabled()) {
      self::logToDatabase($level, $message, $context);
    }

    // Send alerts for critical errors
    if ($level >= self::LOG_LEVEL_CRITICAL) {
      self::sendCriticalAlert($message, $context);
    }
  }

  /**
   * Log debug message.
   */
  public static function debug($message, $context = []) {
    self::log(self::LOG_LEVEL_DEBUG, $message, $context);
  }

  /**
   * Log info message.
   */
  public static function info($message, $context = []) {
    self::log(self::LOG_LEVEL_INFO, $message, $context);
  }

  /**
   * Log warning message.
   */
  public static function warning($message, $context = []) {
    self::log(self::LOG_LEVEL_WARNING, $message, $context);
  }

  /**
   * Log error message.
   */
  public static function error($message, $context = []) {
    self::log(self::LOG_LEVEL_ERROR, $message, $context);
  }

  /**
   * Log critical message.
   */
  public static function critical($message, $context = []) {
    self::log(self::LOG_LEVEL_CRITICAL, $message, $context);
  }

  /**
   * Handle exception with logging and optional user notification.
   */
  public static function handleException(Exception $e, $context = []) {
    $message = sprintf(
      'Exception: %s in %s:%d',
      $e->getMessage(),
      $e->getFile(),
      $e->getLine()
    );

    $context['exception'] = [
      'class' => get_class($e),
      'message' => $e->getMessage(),
      'file' => $e->getFile(),
      'line' => $e->getLine(),
      'trace' => $e->getTraceAsString()
    ];

    self::error($message, $context);

    return $e;
  }

  /**
   * Handle database errors specifically.
   */
  public static function handleDatabaseError(PDOException $e, $context = []) {
    $context['database_error'] = [
      'code' => $e->getCode(),
      'message' => $e->getMessage(),
      'sql_state' => $e->errorInfo[0] ?? NULL
    ];

    // Classify error severity
    $errorCode = $e->getCode();
    if (in_array($errorCode, [1045, 2002, 2005])) { // Connection errors
      $level = self::LOG_LEVEL_CRITICAL;
    }
    elseif (in_array($errorCode, [1146, 1054])) { // Table/column not found
      $level = self::LOG_LEVEL_ERROR;
    }
    else {
      $level = self::LOG_LEVEL_WARNING;
    }

    self::log($level, 'Database error: ' . $e->getMessage(), $context);

    return $e;
  }

  /**
   * Handle SMTP errors.
   */
  public static function handleSmtpError($error, $emailId = NULL, $context = []) {
    $context['smtp_error'] = $error;
    $context['email_id'] = $emailId;

    // Classify SMTP errors
    $message = is_string($error) ? $error : (string)$error;
    $level = self::LOG_LEVEL_WARNING;

    if (strpos($message, 'timeout') !== FALSE) {
      $level = self::LOG_LEVEL_ERROR;
    }
    elseif (strpos($message, 'authentication') !== FALSE) {
      $level = self::LOG_LEVEL_CRITICAL;
    }

    self::log($level, 'SMTP error: ' . $message, $context);
  }

  /**
   * Log email processing errors.
   */
  public static function logEmailError($emailId, $error, $context = []) {
    $context['email_id'] = $emailId;
    $context['error_type'] = 'email_processing';

    self::error("Email processing failed for ID $emailId: $error", $context);

    // Also log to email queue log table
    try {
      CRM_Emailqueue_BAO_Queue::logAction($emailId, 'error', $error);
    }
    catch (Exception $e) {
      // Avoid recursive errors
      self::debug('Failed to log to email queue log: ' . $e->getMessage());
    }
  }

  /**
   * Get current log level from configuration.
   */
  protected static function getCurrentLogLevel() {
    $configLevel = CRM_Emailqueue_Config::getLogLevel();

    switch (strtolower($configLevel)) {
      case 'debug':
        return self::LOG_LEVEL_DEBUG;
      case 'info':
        return self::LOG_LEVEL_INFO;
      case 'warning':
        return self::LOG_LEVEL_WARNING;
      case 'error':
        return self::LOG_LEVEL_ERROR;
      case 'critical':
        return self::LOG_LEVEL_CRITICAL;
      default:
        return self::LOG_LEVEL_INFO;
    }
  }

  /**
   * Format log entry for output.
   */
  protected static function formatLogEntry($level, $message, $context = []) {
    $levelName = self::$logLevels[$level] ?? 'UNKNOWN';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';

    return "[{$timestamp}] EmailQueue.{$levelName}: {$message}{$contextStr}";
  }

  /**
   * Log to database for persistent storage.
   */
  protected static function logToDatabase($level, $message, $context = []) {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $sql = "
        INSERT INTO email_queue_log (queue_id, action, message, created_date)
        VALUES (?, ?, ?, NOW())
      ";

      $queueId = $context['email_id'] ?? 0;
      $action = self::$logLevels[$level];
      $fullMessage = $message;

      if (!empty($context)) {
        $fullMessage .= ' | ' . json_encode($context);
      }

      $stmt = $pdo->prepare($sql);
      $stmt->execute([$queueId, $action, $fullMessage]);

    }
    catch (Exception $e) {
      // Avoid recursive logging errors
      CRM_Core_Error::debug_log_message('Failed to log to database: ' . $e->getMessage());
    }
  }

  /**
   * Send critical error alerts.
   */
  protected static function sendCriticalAlert($message, $context = []) {
    try {
      // Get admin email from CiviCRM settings
      $adminEmail = civicrm_api3('Setting', 'getvalue', [
        'name' => 'site_admin_email'
      ]);

      if (empty($adminEmail)) {
        return; // No admin email configured
      }

      $subject = 'Critical Email Queue Error - ' . CRM_Utils_System::baseURL();
      $body = self::formatAlertEmail($message, $context);

      // Send alert email (bypass queue for critical alerts)
      $mailer = CRM_Utils_Mail::createMailer();
      $headers = [
        'From' => $adminEmail,
        'To' => $adminEmail,
        'Subject' => $subject,
      ];

      $mailer->send($adminEmail, $headers, $body);

    }
    catch (Exception $e) {
      // Log but don't throw - avoid recursive errors
      CRM_Core_Error::debug_log_message('Failed to send critical alert: ' . $e->getMessage());
    }
  }

  /**
   * Format alert email content.
   */
  protected static function formatAlertEmail($message, $context = []) {
    $body = "A critical error occurred in the Email Queue system:\n\n";
    $body .= "Error: {$message}\n\n";
    $body .= "Time: " . date('Y-m-d H:i:s') . "\n";
    $body .= "Server: " . CRM_Utils_System::baseURL() . "\n";

    if (!empty($context)) {
      $body .= "\nContext:\n";
      $body .= print_r($context, TRUE);
    }

    $body .= "\nPlease check the Email Queue system immediately.\n";
    $body .= "Access the monitor at: " . CRM_Utils_System::url('civicrm/emailqueue/monitor', NULL, TRUE);

    return $body;
  }

  /**
   * Get error statistics.
   */
  public static function getErrorStats($timeframe = '24 HOUR') {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $sql = "
        SELECT
          action as level,
          COUNT(*) as count
        FROM email_queue_log
        WHERE action IN ('WARNING', 'ERROR', 'CRITICAL')
        AND created_date >= DATE_SUB(NOW(), INTERVAL {$timeframe})
        GROUP BY action
      ";
      $stmt = $pdo->query($sql);
      $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

      return [
        'warnings' => $stats['WARNING'] ?? 0,
        'errors' => $stats['ERROR'] ?? 0,
        'critical' => $stats['CRITICAL'] ?? 0,
        'total' => array_sum($stats),
        'timeframe' => $timeframe
      ];

    }
    catch (Exception $e) {
      self::handleException($e);
      return [];
    }
  }

  /**
   * Get recent error log entries.
   */
  public static function getRecentErrors($limit = 50) {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $sql = "
        SELECT
          el.*,
          eq.to_email,
          eq.subject
        FROM email_queue_log el
        LEFT JOIN email_queue eq ON el.queue_id = eq.id
        WHERE el.action IN ('WARNING', 'ERROR', 'CRITICAL')
        ORDER BY el.created_date DESC
        LIMIT ?
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([$limit]);

      return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
    catch (Exception $e) {
      self::handleException($e);
      return [];
    }
  }

  /**
   * Clean up old log entries.
   */
  public static function cleanupLogs($daysToKeep = 30) {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $sql = "
        DELETE FROM email_queue_log
        WHERE created_date < DATE_SUB(NOW(), INTERVAL ? DAY)
        AND action NOT IN ('CRITICAL')
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([$daysToKeep]);

      $deletedCount = $stmt->rowCount();

      self::info("Cleaned up {$deletedCount} old log entries");

      return $deletedCount;

    }
    catch (Exception $e) {
      self::handleException($e);
      return 0;
    }
  }

  /**
   * Test error handling system.
   */
  public static function testErrorHandling() {
    $results = [];

    try {
      // Test different log levels
      self::debug('Test debug message');
      self::info('Test info message');
      self::warning('Test warning message');
      self::error('Test error message');

      $results['logging'] = 'OK';

      // Test exception handling
      try {
        throw new Exception('Test exception');
      }
      catch (Exception $e) {
        self::handleException($e, ['test' => TRUE]);
        $results['exception_handling'] = 'OK';
      }

      // Test database logging
      if (CRM_Emailqueue_Config::isEnabled()) {
        $stats = self::getErrorStats('1 DAY');
        $results['database_logging'] = !empty($stats) ? 'OK' : 'No data';
      }
      else {
        $results['database_logging'] = 'Skipped (extension disabled)';
      }

    }
    catch (Exception $e) {
      $results['error'] = $e->getMessage();
    }

    return $results;
  }
}
