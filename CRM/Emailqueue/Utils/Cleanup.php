<?php

/**
 * Database cleanup and maintenance utilities for Email Queue extension with multi-client support.
 */
class CRM_Emailqueue_Utils_Cleanup {

  /**
   * Perform comprehensive cleanup operation for specific client or all clients.
   */
  public static function performFullCleanup($options = []) {
    $results = [
      'start_time' => date('Y-m-d H:i:s'),
      'operations' => [],
      'errors' => [],
      'total_deleted' => 0,
      'total_optimized' => 0,
      'client_id' => $options['client_id'] ?? CRM_Emailqueue_BAO_Queue::getCurrentClientId(),
      'scope' => isset($options['all_clients']) && $options['all_clients'] ? 'all_clients' : 'single_client'
    ];

    CRM_Emailqueue_Utils_ErrorHandler::info('Starting full cleanup operation', $options);

    try {
      if (!empty($options['all_clients']) && CRM_Emailqueue_Config::hasAdminClientAccess()) {
        // Cleanup all clients
        $results = self::performAllClientsCleanup($options, $results);
      }
      else {
        // Cleanup specific client
        $results = self::performSingleClientCleanup($options, $results);
      }

      // Optimize database tables (affects all clients)
      $optimizeResult = self::optimizeTables($options);
      $results['operations']['table_optimization'] = $optimizeResult;
      $results['total_optimized'] = $optimizeResult['tables_optimized'];

      // Update statistics for the relevant client(s)
      if ($results['scope'] === 'single_client') {
        $results['final_stats'] = CRM_Emailqueue_BAO_Queue::getQueueStats();
      }
      else {
        $results['final_stats'] = CRM_Emailqueue_BAO_Queue::getClientStats();
      }

    }
    catch (Exception $e) {
      $error = 'Cleanup operation failed: ' . $e->getMessage();
      $results['errors'][] = $error;
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e, ['operation' => 'full_cleanup']);
    }

    $results['end_time'] = date('Y-m-d H:i:s');
    $results['duration'] = strtotime($results['end_time']) - strtotime($results['start_time']);

    CRM_Emailqueue_Utils_ErrorHandler::info('Cleanup operation completed', $results);

    return $results;
  }

  /**
   * Perform cleanup for all clients (admin only).
   */
  protected static function performAllClientsCleanup($options, $results) {
    if (!CRM_Emailqueue_Config::hasAdminClientAccess()) {
      throw new Exception('Admin client access required for all-clients cleanup');
    }

    $clientStats = CRM_Emailqueue_BAO_Queue::getClientStats();
    $totalClientsProcessed = 0;

    foreach ($clientStats as $clientInfo) {
      $clientId = $clientInfo['client_id'];

      try {
        CRM_Emailqueue_Utils_ErrorHandler::info("Processing cleanup for client: {$clientId}");

        // Clean up old sent emails for this client
        $sentResult = self::cleanupOldSentEmails(array_merge($options, ['client_id' => $clientId]));
        if (!isset($results['operations']['old_sent_emails'])) {
          $results['operations']['old_sent_emails'] = ['total_deleted' => 0, 'clients_processed' => []];
        }
        $results['operations']['old_sent_emails']['total_deleted'] += $sentResult['deleted'];
        $results['operations']['old_sent_emails']['clients_processed'][$clientId] = $sentResult['deleted'];
        $results['total_deleted'] += $sentResult['deleted'];

        // Clean up old cancelled emails for this client
        $cancelledResult = self::cleanupOldCancelledEmails(array_merge($options, ['client_id' => $clientId]));
        if (!isset($results['operations']['old_cancelled_emails'])) {
          $results['operations']['old_cancelled_emails'] = ['total_deleted' => 0, 'clients_processed' => []];
        }
        $results['operations']['old_cancelled_emails']['total_deleted'] += $cancelledResult['deleted'];
        $results['operations']['old_cancelled_emails']['clients_processed'][$clientId] = $cancelledResult['deleted'];
        $results['total_deleted'] += $cancelledResult['deleted'];

        // Clean up old failed emails (optional) for this client
        if (!empty($options['cleanup_failed'])) {
          $failedResult = self::cleanupOldFailedEmails(array_merge($options, ['client_id' => $clientId]));
          if (!isset($results['operations']['old_failed_emails'])) {
            $results['operations']['old_failed_emails'] = ['total_deleted' => 0, 'clients_processed' => []];
          }
          $results['operations']['old_failed_emails']['total_deleted'] += $failedResult['deleted'];
          $results['operations']['old_failed_emails']['clients_processed'][$clientId] = $failedResult['deleted'];
          $results['total_deleted'] += $failedResult['deleted'];
        }

        $totalClientsProcessed++;

      }
      catch (Exception $e) {
        $results['errors'][] = "Failed to cleanup client {$clientId}: " . $e->getMessage();
        CRM_Emailqueue_Utils_ErrorHandler::handleException($e, ['client_id' => $clientId]);
      }
    }

    // Clean up orphaned logs across all clients
    $logsResult = self::cleanupOrphanedLogs($options);
    $results['operations']['orphaned_logs'] = $logsResult;
    $results['total_deleted'] += $logsResult['deleted'];

    // Clean up old log entries across all clients
    $oldLogsResult = self::cleanupOldLogs($options);
    $results['operations']['old_logs'] = $oldLogsResult;
    $results['total_deleted'] += $oldLogsResult['deleted'];

    $results['clients_processed'] = $totalClientsProcessed;

    return $results;
  }

  /**
   * Perform cleanup for single client.
   */
  protected static function performSingleClientCleanup($options, $results) {
    $clientId = $results['client_id'];

    // Clean up old sent emails
    $sentResult = self::cleanupOldSentEmails($options);
    $results['operations']['old_sent_emails'] = $sentResult;
    $results['total_deleted'] += $sentResult['deleted'];

    // Clean up old cancelled emails
    $cancelledResult = self::cleanupOldCancelledEmails($options);
    $results['operations']['old_cancelled_emails'] = $cancelledResult;
    $results['total_deleted'] += $cancelledResult['deleted'];

    // Clean up old failed emails (optional)
    if (!empty($options['cleanup_failed'])) {
      $failedResult = self::cleanupOldFailedEmails($options);
      $results['operations']['old_failed_emails'] = $failedResult;
      $results['total_deleted'] += $failedResult['deleted'];
    }

    // Clean up orphaned logs for this client
    $logsResult = self::cleanupOrphanedLogs($options);
    $results['operations']['orphaned_logs'] = $logsResult;
    $results['total_deleted'] += $logsResult['deleted'];

    // Clean up old log entries for this client
    $oldLogsResult = self::cleanupOldLogs($options);
    $results['operations']['old_logs'] = $oldLogsResult;
    $results['total_deleted'] += $oldLogsResult['deleted'];

    return $results;
  }

  /**
   * Clean up old sent emails for specific client.
   */
  public static function cleanupOldSentEmails($options = []) {
    $daysToKeep = $options['sent_retention_days'] ?? CRM_Emailqueue_Config::getCleanupDays();
    $batchSize = $options['batch_size'] ?? 10000;
    $clientId = $options['client_id'] ?? CRM_Emailqueue_BAO_Queue::getCurrentClientId();

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      // Count emails to be deleted for specific client
      $countSql = "
        SELECT COUNT(*) as count
        FROM email_queue
        WHERE client_id = ? AND status = 'sent'
        AND sent_date < DATE_SUB(NOW(), INTERVAL ? DAY)
      ";

      $stmt = $pdo->prepare($countSql);
      $stmt->execute([$clientId, $daysToKeep]);
      $totalCount = $stmt->fetch()['count'];

      if ($totalCount == 0) {
        return ['deleted' => 0, 'message' => "No old sent emails to clean up for client {$clientId}", 'client_id' => $clientId];
      }

      // Delete in batches to avoid locking
      $deletedTotal = 0;
      $iterations = 0;
      $maxIterations = ceil($totalCount / $batchSize);

      $deleteSql = "
        DELETE FROM email_queue
        WHERE client_id = ? AND status = 'sent'
        AND sent_date < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
      ";

      $stmt = $pdo->prepare($deleteSql);

      while ($iterations < $maxIterations && $deletedTotal < $totalCount) {
        $stmt->execute([$clientId, $daysToKeep, $batchSize]);
        $deleted = $stmt->rowCount();

        if ($deleted == 0) {
          break; // No more rows to delete
        }

        $deletedTotal += $deleted;
        $iterations++;

        // Small delay to prevent overwhelming the database
        if ($iterations % 10 == 0) {
          usleep(100000); // 0.1 second
        }
      }

      return [
        'deleted' => $deletedTotal,
        'client_id' => $clientId,
        'retention_days' => $daysToKeep,
        'iterations' => $iterations,
        'message' => "Deleted {$deletedTotal} old sent emails for client {$clientId} (>{$daysToKeep} days old)"
      ];

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleDatabaseError($e, ['operation' => 'cleanup_sent_emails', 'client_id' => $clientId]);
      return ['deleted' => 0, 'error' => $e->getMessage(), 'client_id' => $clientId];
    }
  }

  /**
   * Clean up old cancelled emails for specific client.
   */
  public static function cleanupOldCancelledEmails($options = []) {
    $daysToKeep = $options['cancelled_retention_days'] ?? 30; // Shorter retention for cancelled
    $batchSize = $options['batch_size'] ?? 10000;
    $clientId = $options['client_id'] ?? CRM_Emailqueue_BAO_Queue::getCurrentClientId();

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $deleteSql = "
        DELETE FROM email_queue
        WHERE client_id = ? AND status = 'cancelled'
        AND created_date < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
      ";

      $stmt = $pdo->prepare($deleteSql);
      $stmt->execute([$clientId, $daysToKeep, $batchSize]);
      $deleted = $stmt->rowCount();

      return [
        'deleted' => $deleted,
        'client_id' => $clientId,
        'retention_days' => $daysToKeep,
        'message' => "Deleted {$deleted} old cancelled emails for client {$clientId} (>{$daysToKeep} days old)"
      ];

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleDatabaseError($e, ['operation' => 'cleanup_cancelled_emails', 'client_id' => $clientId]);
      return ['deleted' => 0, 'error' => $e->getMessage(), 'client_id' => $clientId];
    }
  }

  /**
   * Clean up old failed emails for specific client (optional, with caution).
   */
  public static function cleanupOldFailedEmails($options = []) {
    $daysToKeep = $options['failed_retention_days'] ?? 60; // Longer retention for failed emails
    $batchSize = $options['batch_size'] ?? 5000; // Smaller batches for failed emails
    $clientId = $options['client_id'] ?? CRM_Emailqueue_BAO_Queue::getCurrentClientId();

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      // Only delete failed emails that have reached max retries for specific client
      $deleteSql = "
        DELETE FROM email_queue
        WHERE client_id = ? AND status = 'failed'
        AND retry_count >= max_retries
        AND created_date < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
      ";

      $stmt = $pdo->prepare($deleteSql);
      $stmt->execute([$clientId, $daysToKeep, $batchSize]);
      $deleted = $stmt->rowCount();

      return [
        'deleted' => $deleted,
        'client_id' => $clientId,
        'retention_days' => $daysToKeep,
        'message' => "Deleted {$deleted} old failed emails for client {$clientId} (>{$daysToKeep} days old, max retries reached)"
      ];

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleDatabaseError($e, ['operation' => 'cleanup_failed_emails', 'client_id' => $clientId]);
      return ['deleted' => 0, 'error' => $e->getMessage(), 'client_id' => $clientId];
    }
  }

  /**
   * Clean up orphaned log entries.
   */
  public static function cleanupOrphanedLogs($options = []) {
    $batchSize = $options['batch_size'] ?? 10000;
    $clientId = $options['client_id'] ?? NULL;

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      if ($clientId) {
        // Clean orphaned logs for specific client
        $deleteSql = "
          DELETE el FROM email_queue_log el
          LEFT JOIN email_queue eq ON el.queue_id = eq.id AND el.client_id = eq.client_id
          WHERE el.client_id = ? AND eq.id IS NULL
          AND el.queue_id > 0
          LIMIT ?
        ";
        $stmt = $pdo->prepare($deleteSql);
        $stmt->execute([$clientId, $batchSize]);
      }
      else {
        // Clean orphaned logs across all clients (admin operation)
        $deleteSql = "
          DELETE el FROM email_queue_log el
          LEFT JOIN email_queue eq ON el.queue_id = eq.id AND el.client_id = eq.client_id
          WHERE eq.id IS NULL
          AND el.queue_id > 0
          LIMIT ?
        ";
        $stmt = $pdo->prepare($deleteSql);
        $stmt->execute([$batchSize]);
      }

      $deleted = $stmt->rowCount();

      return [
        'deleted' => $deleted,
        'client_id' => $clientId,
        'scope' => $clientId ? 'single_client' : 'all_clients',
        'message' => "Deleted {$deleted} orphaned log entries" . ($clientId ? " for client {$clientId}" : " across all clients")
      ];

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleDatabaseError($e, ['operation' => 'cleanup_orphaned_logs', 'client_id' => $clientId]);
      return ['deleted' => 0, 'error' => $e->getMessage(), 'client_id' => $clientId];
    }
  }

  /**
   * Clean up old log entries.
   */
  public static function cleanupOldLogs($options = []) {
    $daysToKeep = $options['log_retention_days'] ?? 30;
    $clientId = $options['client_id'] ?? NULL;

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      if ($clientId) {
        // Clean logs for specific client
        $deleteSql = "
          DELETE FROM email_queue_log
          WHERE client_id = ? AND created_date < DATE_SUB(NOW(), INTERVAL ? DAY)
          AND action NOT IN ('CRITICAL')
        ";
        $stmt = $pdo->prepare($deleteSql);
        $stmt->execute([$clientId, $daysToKeep]);
      }
      else {
        // Clean logs across all clients (admin operation)
        $deleteSql = "
          DELETE FROM email_queue_log
          WHERE created_date < DATE_SUB(NOW(), INTERVAL ? DAY)
          AND action NOT IN ('CRITICAL')
        ";
        $stmt = $pdo->prepare($deleteSql);
        $stmt->execute([$daysToKeep]);
      }

      $deletedCount = $stmt->rowCount();

      CRM_Emailqueue_Utils_ErrorHandler::info("Cleaned up {$deletedCount} old log entries" . ($clientId ? " for client {$clientId}" : ""));

      return [
        'deleted' => $deletedCount,
        'client_id' => $clientId,
        'retention_days' => $daysToKeep,
        'scope' => $clientId ? 'single_client' : 'all_clients',
        'message' => "Deleted {$deletedCount} old log entries" . ($clientId ? " for client {$clientId}" : " across all clients")
      ];

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
      return ['deleted' => 0, 'error' => $e->getMessage(), 'client_id' => $clientId];
    }
  }

  /**
   * Optimize database tables.
   */
  public static function optimizeTables($options = []) {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $tables = ['email_queue', 'email_queue_log'];
      $optimized = 0;
      $results = [];

      foreach ($tables as $table) {
        try {
          $pdo->exec("OPTIMIZE TABLE {$table}");
          $optimized++;
          $results[$table] = 'Optimized';

          // Get table statistics after optimization
          $stmt = $pdo->query("SHOW TABLE STATUS LIKE '{$table}'");
          $stats = $stmt->fetch(PDO::FETCH_ASSOC);

          if ($stats) {
            $results[$table] .= sprintf(
              ' (%s rows, %s)',
              number_format($stats['Rows']),
              CRM_Emailqueue_Utils_Performance::formatBytes($stats['Data_length'] + $stats['Index_length'])
            );
          }

        }
        catch (Exception $e) {
          $results[$table] = 'Failed: ' . $e->getMessage();
          CRM_Emailqueue_Utils_ErrorHandler::handleDatabaseError($e, ['table' => $table]);
        }
      }

      return [
        'tables_optimized' => $optimized,
        'details' => $results,
        'message' => "Optimized {$optimized} tables (affects all clients)"
      ];

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleDatabaseError($e, ['operation' => 'optimize_tables']);
      return ['tables_optimized' => 0, 'error' => $e->getMessage()];
    }
  }

  /**
   * Analyze database health for specific client or all clients.
   */
  public static function analyzeDatabaseHealth($clientId = NULL) {
    $analysis = [
      'timestamp' => date('Y-m-d H:i:s'),
      'health_score' => 100,
      'issues' => [],
      'recommendations' => [],
      'statistics' => [],
      'client_id' => $clientId,
      'scope' => $clientId ? 'single_client' : 'all_clients'
    ];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      if ($clientId) {
        // Analyze specific client
        $analysis = self::analyzeClientHealth($pdo, $clientId, $analysis);
      }
      else {
        // Analyze all clients (admin view)
        if (!CRM_Emailqueue_Config::hasAdminClientAccess()) {
          throw new Exception('Admin access required for all-clients analysis');
        }
        $analysis = self::analyzeAllClientsHealth($pdo, $analysis);
      }

      // Overall health assessment
      if ($analysis['health_score'] >= 90) {
        $analysis['health_status'] = 'Excellent';
      }
      elseif ($analysis['health_score'] >= 75) {
        $analysis['health_status'] = 'Good';
      }
      elseif ($analysis['health_score'] >= 60) {
        $analysis['health_status'] = 'Fair';
      }
      else {
        $analysis['health_status'] = 'Poor';
      }

    }
    catch (Exception $e) {
      $analysis['issues'][] = 'Database analysis failed: ' . $e->getMessage();
      $analysis['health_score'] = 0;
      $analysis['health_status'] = 'Error';
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
    }

    return $analysis;
  }

  /**
   * Analyze health for specific client.
   */
  protected static function analyzeClientHealth($pdo, $clientId, $analysis) {
    // Get table statistics for specific client
    $tables = ['email_queue', 'email_queue_log'];

    foreach ($tables as $table) {
      $stmt = $pdo->prepare("SELECT COUNT(*) as rows FROM {$table} WHERE client_id = ?");
      $stmt->execute([$clientId]);
      $clientRows = $stmt->fetchColumn();

      $analysis['statistics'][$table] = [
        'client_rows' => $clientRows,
        'client_id' => $clientId
      ];

      // Check for issues specific to this client
      if ($clientRows > 500000) {
        $analysis['issues'][] = "Client {$clientId}: Table {$table} has over 500K rows ({$clientRows})";
        $analysis['recommendations'][] = "Consider implementing cleanup for client {$clientId} table {$table}";
        $analysis['health_score'] -= 10;
      }
    }

    // Check for old data for this client
    $stmt = $pdo->prepare("
      SELECT
        COUNT(*) as old_sent_count,
        MIN(sent_date) as oldest_sent
      FROM email_queue
      WHERE client_id = ? AND status = 'sent'
      AND sent_date < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute([$clientId]);

    $oldData = $stmt->fetch();
    if ($oldData['old_sent_count'] > 0) {
      $analysis['issues'][] = "Client {$clientId}: Found {$oldData['old_sent_count']} sent emails older than 90 days";
      $analysis['recommendations'][] = "Clean up old sent emails for client {$clientId} to improve performance";
      $analysis['health_score'] -= 5;
    }

    // Check for failed emails for this client
    $stmt = $pdo->prepare("
      SELECT COUNT(*) as failed_count
      FROM email_queue
      WHERE client_id = ? AND status = 'failed'
    ");
    $stmt->execute([$clientId]);

    $failedCount = $stmt->fetchColumn();
    if ($failedCount > 100) {
      $analysis['issues'][] = "Client {$clientId}: High number of failed emails: {$failedCount}";
      $analysis['recommendations'][] = "Review and resolve email failures for client {$clientId}";
      $analysis['health_score'] -= 10;
    }

    // Check for stuck processing emails for this client
    $stmt = $pdo->prepare("
      SELECT COUNT(*) as stuck_count
      FROM email_queue
      WHERE client_id = ? AND status = 'processing'
      AND created_date < DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$clientId]);

    $stuckCount = $stmt->fetchColumn();
    if ($stuckCount > 0) {
      $analysis['issues'][] = "Client {$clientId}: Found {$stuckCount} emails stuck in processing status";
      $analysis['recommendations'][] = "Reset stuck emails to pending status for client {$clientId}";
      $analysis['health_score'] -= 20;
    }

    return $analysis;
  }

  /**
   * Analyze health for all clients.
   */
  protected static function analyzeAllClientsHealth($pdo, $analysis) {
    // Get overall table statistics
    $tables = ['email_queue', 'email_queue_log'];

    foreach ($tables as $table) {
      $stmt = $pdo->query("SHOW TABLE STATUS LIKE '{$table}'");
      $stats = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($stats) {
        $analysis['statistics'][$table] = [
          'total_rows' => $stats['Rows'],
          'data_size' => $stats['Data_length'],
          'index_size' => $stats['Index_length'],
          'total_size' => $stats['Data_length'] + $stats['Index_length'],
          'avg_row_length' => $stats['Avg_row_length'],
          'auto_increment' => $stats['Auto_increment']
        ];

        // Check for overall issues
        if ($stats['Rows'] > 2000000) {
          $analysis['issues'][] = "Table {$table} has over 2M rows ({$stats['Rows']}) across all clients";
          $analysis['recommendations'][] = "Consider implementing global cleanup procedures for {$table}";
          $analysis['health_score'] -= 10;
        }

        if (($stats['Data_length'] + $stats['Index_length']) > 2000000000) { // 2GB
          $analysis['issues'][] = "Table {$table} is very large (" .
            CRM_Emailqueue_Utils_Performance::formatBytes($stats['Data_length'] + $stats['Index_length']) . ")";
          $analysis['recommendations'][] = "Consider archiving old data from {$table} or implementing partitioning";
          $analysis['health_score'] -= 15;
        }
      }
    }

    // Get client distribution statistics
    $stmt = $pdo->query("
      SELECT
        client_id,
        COUNT(*) as email_count,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count,
        COUNT(CASE WHEN status = 'processing' AND created_date < DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as stuck_count
      FROM email_queue
      GROUP BY client_id
      ORDER BY email_count DESC
    ");

    $clientStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $analysis['statistics']['client_distribution'] = $clientStats;

    // Analyze client-specific issues
    foreach ($clientStats as $clientStat) {
      $clientId = $clientStat['client_id'];

      if ($clientStat['failed_count'] > 100) {
        $analysis['issues'][] = "Client {$clientId}: High failure count ({$clientStat['failed_count']})";
        $analysis['recommendations'][] = "Review email configuration for client {$clientId}";
        $analysis['health_score'] -= 5;
      }

      if ($clientStat['stuck_count'] > 0) {
        $analysis['issues'][] = "Client {$clientId}: Has {$clientStat['stuck_count']} stuck processing emails";
        $analysis['recommendations'][] = "Reset stuck emails for client {$clientId}";
        $analysis['health_score'] -= 10;
      }
    }

    return $analysis;
  }

  /**
   * Fix common database issues for specific client or all clients.
   */
  public static function fixCommonIssues($options = []) {
    $results = [
      'fixes_applied' => [],
      'errors' => [],
      'client_id' => $options['client_id'] ?? NULL,
      'scope' => isset($options['client_id']) ? 'single_client' : 'all_clients'
    ];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      $clientId = $options['client_id'] ?? NULL;

      // Fix stuck processing emails
      if (!empty($options['fix_stuck_processing'])) {
        if ($clientId) {
          $stmt = $pdo->prepare("
            UPDATE email_queue
            SET status = 'pending'
            WHERE client_id = ? AND status = 'processing'
            AND created_date < DATE_SUB(NOW(), INTERVAL 1 HOUR)
          ");
          $stmt->execute([$clientId]);
        }
        else {
          $stmt = $pdo->prepare("
            UPDATE email_queue
            SET status = 'pending'
            WHERE status = 'processing'
            AND created_date < DATE_SUB(NOW(), INTERVAL 1 HOUR)
          ");
          $stmt->execute([]);
        }

        $fixed = $stmt->rowCount();
        if ($fixed > 0) {
          $message = "Reset {$fixed} stuck processing emails";
          if ($clientId) {
            $message .= " for client {$clientId}";
          }
          $results['fixes_applied'][] = $message;
        }
      }

      // Reset old failed emails for retry
      if (!empty($options['reset_old_failed'])) {
        $daysOld = $options['failed_reset_days'] ?? 7;

        if ($clientId) {
          $stmt = $pdo->prepare("
            UPDATE email_queue
            SET status = 'pending', retry_count = 0, error_message = NULL
            WHERE client_id = ? AND status = 'failed'
            AND created_date > DATE_SUB(NOW(), INTERVAL ? DAY)
            AND retry_count >= max_retries
          ");
          $stmt->execute([$clientId, $daysOld]);
        }
        else {
          $stmt = $pdo->prepare("
            UPDATE email_queue
            SET status = 'pending', retry_count = 0, error_message = NULL
            WHERE status = 'failed'
            AND created_date > DATE_SUB(NOW(), INTERVAL ? DAY)
            AND retry_count >= max_retries
          ");
          $stmt->execute([$daysOld]);
        }

        $reset = $stmt->rowCount();
        if ($reset > 0) {
          $message = "Reset {$reset} recent failed emails for retry";
          if ($clientId) {
            $message .= " for client {$clientId}";
          }
          $results['fixes_applied'][] = $message;
        }
      }

      // Fix missing indexes (affects all clients)
      if (!empty($options['fix_indexes'])) {
        $indexes = [
          'idx_client_status_priority' => 'CREATE INDEX idx_client_status_priority ON email_queue (client_id, status, priority)',
          'idx_client_created_status' => 'CREATE INDEX idx_client_created_status ON email_queue (client_id, created_date, status)',
          'idx_client_sent_date' => 'CREATE INDEX idx_client_sent_date ON email_queue (client_id, sent_date)',
        ];

        foreach ($indexes as $name => $sql) {
          try {
            $pdo->exec($sql);
            $results['fixes_applied'][] = "Added missing index: {$name}";
          }
          catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') === FALSE) {
              $results['errors'][] = "Failed to add index {$name}: " . $e->getMessage();
            }
          }
        }
      }

    }
    catch (Exception $e) {
      $results['errors'][] = 'Fix operation failed: ' . $e->getMessage();
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
    }

    return $results;
  }

  /**
   * Generate cleanup report for specific client or all clients.
   */
  public static function generateCleanupReport($clientId = NULL) {
    $report = [
      'generated_at' => date('Y-m-d H:i:s'),
      'client_id' => $clientId,
      'scope' => $clientId ? 'single_client' : 'all_clients',
      'database_health' => self::analyzeDatabaseHealth($clientId),
      'cleanup_recommendations' => [],
      'estimated_savings' => []
    ];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      $retentionDays = CRM_Emailqueue_Config::getCleanupDays();

      if ($clientId) {
        // Generate report for specific client
        $report = self::generateClientCleanupReport($pdo, $clientId, $retentionDays, $report);
      }
      else {
        // Generate report for all clients
        if (!CRM_Emailqueue_Config::hasAdminClientAccess()) {
          throw new Exception('Admin access required for all-clients cleanup report');
        }
        $report = self::generateAllClientsCleanupReport($pdo, $retentionDays, $report);
      }

    }
    catch (Exception $e) {
      $report['error'] = 'Failed to generate cleanup report: ' . $e->getMessage();
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
    }

    return $report;
  }

  /**
   * Generate cleanup report for specific client.
   */
  protected static function generateClientCleanupReport($pdo, $clientId, $retentionDays, $report) {
    // Estimate old sent emails for specific client
    $stmt = $pdo->prepare("
      SELECT
        COUNT(*) as count,
        SUM(LENGTH(body_html) + LENGTH(body_text) + LENGTH(subject)) as estimated_size
      FROM email_queue
      WHERE client_id = ? AND status = 'sent'
      AND sent_date < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");

    $stmt->execute([$clientId, $retentionDays]);
    $oldSent = $stmt->fetch();

    if ($oldSent['count'] > 0) {
      $report['cleanup_recommendations'][] = [
        'type' => 'old_sent_emails',
        'client_id' => $clientId,
        'count' => $oldSent['count'],
        'description' => "Clean up {$oldSent['count']} old sent emails for client {$clientId} (>{$retentionDays} days)",
        'estimated_size_reduction' => $oldSent['estimated_size']
      ];
    }

    // Estimate old cancelled emails for specific client
    $stmt = $pdo->prepare("
      SELECT
        COUNT(*) as count,
        SUM(LENGTH(body_html) + LENGTH(body_text) + LENGTH(subject)) as estimated_size
      FROM email_queue
      WHERE client_id = ? AND status = 'cancelled'
      AND created_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

    $stmt->execute([$clientId]);
    $oldCancelled = $stmt->fetch();

    if ($oldCancelled['count'] > 0) {
      $report['cleanup_recommendations'][] = [
        'type' => 'old_cancelled_emails',
        'client_id' => $clientId,
        'count' => $oldCancelled['count'],
        'description' => "Clean up {$oldCancelled['count']} old cancelled emails for client {$clientId} (>30 days)",
        'estimated_size_reduction' => $oldCancelled['estimated_size']
      ];
    }

    // Calculate total estimated savings for this client
    $totalSize = ($oldSent['estimated_size'] ?? 0) + ($oldCancelled['estimated_size'] ?? 0);
    $report['estimated_savings'] = [
      'client_id' => $clientId,
      'total_emails' => ($oldSent['count'] ?? 0) + ($oldCancelled['count'] ?? 0),
      'estimated_size_bytes' => $totalSize,
      'estimated_size_formatted' => CRM_Emailqueue_Utils_Performance::formatBytes($totalSize)
    ];

    return $report;
  }

  /**
   * Generate cleanup report for all clients.
   */
  protected static function generateAllClientsCleanupReport($pdo, $retentionDays, $report) {
    $clientStats = CRM_Emailqueue_BAO_Queue::getClientStats();
    $totalEmails = 0;
    $totalSize = 0;

    foreach ($clientStats as $clientInfo) {
      $clientId = $clientInfo['client_id'];

      // Get cleanup estimates for each client
      $clientReport = self::generateClientCleanupReport($pdo, $clientId, $retentionDays, [
        'cleanup_recommendations' => [],
        'estimated_savings' => []
      ]);

      // Merge client recommendations into main report
      $report['cleanup_recommendations'] = array_merge(
        $report['cleanup_recommendations'],
        $clientReport['cleanup_recommendations']
      );

      // Add to totals
      if (!empty($clientReport['estimated_savings'])) {
        $totalEmails += $clientReport['estimated_savings']['total_emails'];
        $totalSize += $clientReport['estimated_savings']['estimated_size_bytes'];
      }
    }

    // Set overall estimated savings
    $report['estimated_savings'] = [
      'scope' => 'all_clients',
      'total_emails' => $totalEmails,
      'estimated_size_bytes' => $totalSize,
      'estimated_size_formatted' => CRM_Emailqueue_Utils_Performance::formatBytes($totalSize),
      'clients_analyzed' => count($clientStats)
    ];

    return $report;
  }
}
