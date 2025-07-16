<?php

/**
 * Database cleanup and maintenance utilities for Email Queue extension.
 */
class CRM_Emailqueue_Utils_Cleanup {

  /**
   * Perform comprehensive cleanup operation.
   */
  public static function performFullCleanup($options = []) {
    $results = [
      'start_time' => date('Y-m-d H:i:s'),
      'operations' => [],
      'errors' => [],
      'total_deleted' => 0,
      'total_optimized' => 0
    ];

    CRM_Emailqueue_Utils_ErrorHandler::info('Starting full cleanup operation', $options);

    try {
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

      // Clean up orphaned logs
      $logsResult = self::cleanupOrphanedLogs($options);
      $results['operations']['orphaned_logs'] = $logsResult;
      $results['total_deleted'] += $logsResult['deleted'];

      // Clean up old log entries
      $oldLogsResult = self::cleanupOldLogs($options);
      $results['operations']['old_logs'] = $oldLogsResult;
      $results['total_deleted'] += $oldLogsResult['deleted'];

      // Optimize database tables
      $optimizeResult = self::optimizeTables($options);
      $results['operations']['table_optimization'] = $optimizeResult;
      $results['total_optimized'] = $optimizeResult['tables_optimized'];

      // Update statistics
      $results['final_stats'] = CRM_Emailqueue_BAO_Queue::getQueueStats();

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
   * Clean up old sent emails.
   */
  public static function cleanupOldSentEmails($options = []) {
    $daysToKeep = $options['sent_retention_days'] ?? CRM_Emailqueue_Config::getCleanupDays();
    $batchSize = $options['batch_size'] ?? 10000;

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      // Count emails to be deleted
      $countSql = "
        SELECT COUNT(*) as count
        FROM email_queue
        WHERE status = 'sent'
        AND sent_date < DATE_SUB(NOW(), INTERVAL ? DAY)
      ";

      $stmt = $pdo->prepare($countSql);
      $stmt->execute([$daysToKeep]);
      $totalCount = $stmt->fetch()['count'];

      if ($totalCount == 0) {
        return ['deleted' => 0, 'message' => 'No old sent emails to clean up'];
      }

      // Delete in batches to avoid locking
      $deletedTotal = 0;
      $iterations = 0;
      $maxIterations = ceil($totalCount / $batchSize);

      $deleteSql = "
        DELETE FROM email_queue
        WHERE status = 'sent'
        AND sent_date < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
      ";

      $stmt = $pdo->prepare($deleteSql);

      while ($iterations < $maxIterations && $deletedTotal < $totalCount) {
        $stmt->execute([$daysToKeep, $batchSize]);
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
        'retention_days' => $daysToKeep,
        'iterations' => $iterations,
        'message' => "Deleted {$deletedTotal} old sent emails (>{$daysToKeep} days old)"
      ];

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleDatabaseError($e, ['operation' => 'cleanup_sent_emails']);
      return ['deleted' => 0, 'error' => $e->getMessage()];
    }
  }

  /**
   * Clean up old cancelled emails.
   */
  public static function cleanupOldCancelledEmails($options = []) {
    $daysToKeep = $options['cancelled_retention_days'] ?? 30; // Shorter retention for cancelled
    $batchSize = $options['batch_size'] ?? 10000;

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $deleteSql = "
        DELETE FROM email_queue
        WHERE status = 'cancelled'
        AND created_date < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
      ";

      $stmt = $pdo->prepare($deleteSql);
      $stmt->execute([$daysToKeep, $batchSize]);
      $deleted = $stmt->rowCount();

      return [
        'deleted' => $deleted,
        'retention_days' => $daysToKeep,
        'message' => "Deleted {$deleted} old cancelled emails (>{$daysToKeep} days old)"
      ];

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleDatabaseError($e, ['operation' => 'cleanup_cancelled_emails']);
      return ['deleted' => 0, 'error' => $e->getMessage()];
    }
  }

  /**
   * Clean up old failed emails (optional, with caution).
   */
  public static function cleanupOldFailedEmails($options = []) {
    $daysToKeep = $options['failed_retention_days'] ?? 60; // Longer retention for failed emails
    $batchSize = $options['batch_size'] ?? 5000; // Smaller batches for failed emails

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      // Only delete failed emails that have reached max retries
      $deleteSql = "
        DELETE FROM email_queue
        WHERE status = 'failed'
        AND retry_count >= max_retries
        AND created_date < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
      ";

      $stmt = $pdo->prepare($deleteSql);
      $stmt->execute([$daysToKeep, $batchSize]);
      $deleted = $stmt->rowCount();

      return [
        'deleted' => $deleted,
        'retention_days' => $daysToKeep,
        'message' => "Deleted {$deleted} old failed emails (>{$daysToKeep} days old, max retries reached)"
      ];

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleDatabaseError($e, ['operation' => 'cleanup_failed_emails']);
      return ['deleted' => 0, 'error' => $e->getMessage()];
    }
  }

  /**
   * Clean up orphaned log entries.
   */
  public static function cleanupOrphanedLogs($options = []) {
    $batchSize = $options['batch_size'] ?? 10000;

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $deleteSql = "
        DELETE el FROM email_queue_log el
        LEFT JOIN email_queue eq ON el.queue_id = eq.id
        WHERE eq.id IS NULL
        AND el.queue_id > 0
        LIMIT ?
      ";

      $stmt = $pdo->prepare($deleteSql);
      $stmt->execute([$batchSize]);
      $deleted = $stmt->rowCount();

      return [
        'deleted' => $deleted,
        'message' => "Deleted {$deleted} orphaned log entries"
      ];

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleDatabaseError($e, ['operation' => 'cleanup_orphaned_logs']);
      return ['deleted' => 0, 'error' => $e->getMessage()];
    }
  }

  /**
   * Clean up old log entries.
   */
  public static function cleanupOldLogs($options = []) {
    return CRM_Emailqueue_Utils_ErrorHandler::cleanupLogs(
      $options['log_retention_days'] ?? 30
    );
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
        'message' => "Optimized {$optimized} tables"
      ];

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleDatabaseError($e, ['operation' => 'optimize_tables']);
      return ['tables_optimized' => 0, 'error' => $e->getMessage()];
    }
  }

  /**
   * Analyze database and provide recommendations.
   */
  public static function analyzeDatabaseHealth() {
    $analysis = [
      'timestamp' => date('Y-m-d H:i:s'),
      'health_score' => 100,
      'issues' => [],
      'recommendations' => [],
      'statistics' => []
    ];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      // Get table statistics
      $tables = ['email_queue', 'email_queue_log'];

      foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLE STATUS LIKE '{$table}'");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($stats) {
          $analysis['statistics'][$table] = [
            'rows' => $stats['Rows'],
            'data_size' => $stats['Data_length'],
            'index_size' => $stats['Index_length'],
            'total_size' => $stats['Data_length'] + $stats['Index_length'],
            'avg_row_length' => $stats['Avg_row_length'],
            'auto_increment' => $stats['Auto_increment']
          ];

          // Check for issues
          if ($stats['Rows'] > 1000000) {
            $analysis['issues'][] = "Table {$table} has over 1M rows ({$stats['Rows']})";
            $analysis['recommendations'][] = "Consider implementing cleanup for {$table}";
            $analysis['health_score'] -= 10;
          }

          if (($stats['Data_length'] + $stats['Index_length']) > 1000000000) { // 1GB
            $analysis['issues'][] = "Table {$table} is very large (" .
              CRM_Emailqueue_Utils_Performance::formatBytes($stats['Data_length'] + $stats['Index_length']) . ")";
            $analysis['recommendations'][] = "Consider archiving old data from {$table}";
            $analysis['health_score'] -= 15;
          }
        }
      }

      // Check for old data
      $stmt = $pdo->query("
        SELECT
          COUNT(*) as old_sent_count,
          MIN(sent_date) as oldest_sent
        FROM email_queue
        WHERE status = 'sent'
        AND sent_date < DATE_SUB(NOW(), INTERVAL 90 DAY)
      ");

      $oldData = $stmt->fetch();
      if ($oldData['old_sent_count'] > 0) {
        $analysis['issues'][] = "Found {$oldData['old_sent_count']} sent emails older than 90 days";
        $analysis['recommendations'][] = "Clean up old sent emails to improve performance";
        $analysis['health_score'] -= 5;
      }

      // Check for failed emails
      $stmt = $pdo->query("
        SELECT COUNT(*) as failed_count
        FROM email_queue
        WHERE status = 'failed'
      ");

      $failedCount = $stmt->fetchColumn();
      if ($failedCount > 100) {
        $analysis['issues'][] = "High number of failed emails: {$failedCount}";
        $analysis['recommendations'][] = "Review and resolve email failures";
        $analysis['health_score'] -= 10;
      }

      // Check for stuck processing emails
      $stmt = $pdo->query("
        SELECT COUNT(*) as stuck_count
        FROM email_queue
        WHERE status = 'processing'
        AND created_date < DATE_SUB(NOW(), INTERVAL 1 HOUR)
      ");

      $stuckCount = $stmt->fetchColumn();
      if ($stuckCount > 0) {
        $analysis['issues'][] = "Found {$stuckCount} emails stuck in processing status";
        $analysis['recommendations'][] = "Reset stuck emails to pending status";
        $analysis['health_score'] -= 20;
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
   * Fix common database issues.
   */
  public static function fixCommonIssues($options = []) {
    $results = [
      'fixes_applied' => [],
      'errors' => []
    ];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      // Fix stuck processing emails
      if (!empty($options['fix_stuck_processing'])) {
        $stmt = $pdo->prepare("
          UPDATE email_queue
          SET status = 'pending'
          WHERE status = 'processing'
          AND created_date < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");

        $stmt->execute();
        $fixed = $stmt->rowCount();

        if ($fixed > 0) {
          $results['fixes_applied'][] = "Reset {$fixed} stuck processing emails to pending";
        }
      }

      // Reset old failed emails for retry
      if (!empty($options['reset_old_failed'])) {
        $daysOld = $options['failed_reset_days'] ?? 7;

        $stmt = $pdo->prepare("
          UPDATE email_queue
          SET status = 'pending', retry_count = 0, error_message = NULL
          WHERE status = 'failed'
          AND created_date > DATE_SUB(NOW(), INTERVAL ? DAY)
          AND retry_count >= max_retries
        ");

        $stmt->execute([$daysOld]);
        $reset = $stmt->rowCount();

        if ($reset > 0) {
          $results['fixes_applied'][] = "Reset {$reset} recent failed emails for retry";
        }
      }

      // Fix missing indexes
      if (!empty($options['fix_indexes'])) {
        $indexes = [
          'idx_status_priority' => 'CREATE INDEX idx_status_priority ON email_queue (status, priority)',
          'idx_created_status' => 'CREATE INDEX idx_created_status ON email_queue (created_date, status)',
          'idx_sent_date' => 'CREATE INDEX idx_sent_date ON email_queue (sent_date)',
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
   * Generate cleanup report.
   */
  public static function generateCleanupReport() {
    $report = [
      'generated_at' => date('Y-m-d H:i:s'),
      'database_health' => self::analyzeDatabaseHealth(),
      'cleanup_recommendations' => [],
      'estimated_savings' => []
    ];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      $retentionDays = CRM_Emailqueue_Config::getCleanupDays();

      // Estimate old sent emails
      $stmt = $pdo->prepare("
        SELECT
          COUNT(*) as count,
          SUM(LENGTH(body_html) + LENGTH(body_text) + LENGTH(subject)) as estimated_size
        FROM email_queue
        WHERE status = 'sent'
        AND sent_date < DATE_SUB(NOW(), INTERVAL ? DAY)
      ");

      $stmt->execute([$retentionDays]);
      $oldSent = $stmt->fetch();

      if ($oldSent['count'] > 0) {
        $report['cleanup_recommendations'][] = [
          'type' => 'old_sent_emails',
          'count' => $oldSent['count'],
          'description' => "Clean up {$oldSent['count']} old sent emails (>{$retentionDays} days)",
          'estimated_size_reduction' => $oldSent['estimated_size']
        ];
      }

      // Estimate old cancelled emails
      $stmt = $pdo->query("
        SELECT
          COUNT(*) as count,
          SUM(LENGTH(body_html) + LENGTH(body_text) + LENGTH(subject)) as estimated_size
        FROM email_queue
        WHERE status = 'cancelled'
        AND created_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
      ");

      $oldCancelled = $stmt->fetch();

      if ($oldCancelled['count'] > 0) {
        $report['cleanup_recommendations'][] = [
          'type' => 'old_cancelled_emails',
          'count' => $oldCancelled['count'],
          'description' => "Clean up {$oldCancelled['count']} old cancelled emails (>30 days)",
          'estimated_size_reduction' => $oldCancelled['estimated_size']
        ];
      }

      // Calculate total estimated savings
      $totalSize = ($oldSent['estimated_size'] ?? 0) + ($oldCancelled['estimated_size'] ?? 0);
      $report['estimated_savings'] = [
        'total_emails' => ($oldSent['count'] ?? 0) + ($oldCancelled['count'] ?? 0),
        'estimated_size_bytes' => $totalSize,
        'estimated_size_formatted' => CRM_Emailqueue_Utils_Performance::formatBytes($totalSize)
      ];

    }
    catch (Exception $e) {
      $report['error'] = 'Failed to generate cleanup report: ' . $e->getMessage();
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
    }

    return $report;
  }
}
