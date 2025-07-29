<?php

/**
 * Performance monitoring and optimization utilities for Email Queue extension.
 */
class CRM_Emailqueue_Utils_Performance {

  protected static $timers = [];
  protected static $memoryUsage = [];
  protected static $queryCount = 0;

  /**
   * Start performance timer.
   */
  public static function startTimer($label) {
    self::$timers[$label] = [
      'start' => microtime(TRUE),
      'memory_start' => memory_get_usage(TRUE)
    ];
  }

  /**
   * Stop performance timer and return elapsed time.
   */
  public static function stopTimer($label) {
    if (!isset(self::$timers[$label])) {
      return NULL;
    }

    $timer = self::$timers[$label];
    $elapsed = microtime(TRUE) - $timer['start'];
    $memoryUsed = memory_get_usage(TRUE) - $timer['memory_start'];

    $result = [
      'elapsed_time' => $elapsed,
      'memory_used' => $memoryUsed,
      'formatted_time' => self::formatTime($elapsed),
      'formatted_memory' => self::formatBytes($memoryUsed)
    ];

    unset(self::$timers[$label]);

    if (CRM_Emailqueue_Config::isDebugMode()) {
      CRM_Core_Error::debug_log_message("Performance: {$label} - {$result['formatted_time']}, {$result['formatted_memory']}");
    }

    return $result;
  }

  /**
   * Get current memory usage.
   */
  public static function getMemoryUsage() {
    return [
      'current' => memory_get_usage(TRUE),
      'peak' => memory_get_peak_usage(TRUE),
      'formatted_current' => self::formatBytes(memory_get_usage(TRUE)),
      'formatted_peak' => self::formatBytes(memory_get_peak_usage(TRUE))
    ];
  }

  /**
   * Monitor database performance.
   */
  public static function monitorDatabasePerformance($timeframe = '24 HOUR') {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      // Get table statistics
      $stats = [];

      // Email queue table stats
      $stmt = $pdo->query("SHOW TABLE STATUS LIKE 'email_queue'");
      $queueStats = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($queueStats) {
        $stats['email_queue'] = [
          'rows' => $queueStats['Rows'],
          'avg_row_length' => $queueStats['Avg_row_length'],
          'data_length' => $queueStats['Data_length'],
          'index_length' => $queueStats['Index_length'],
          'formatted_size' => self::formatBytes($queueStats['Data_length'] + $queueStats['Index_length'])
        ];
      }

      // Email queue log table stats
      $stmt = $pdo->query("SHOW TABLE STATUS LIKE 'email_queue_log'");
      $logStats = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($logStats) {
        $stats['email_queue_log'] = [
          'rows' => $logStats['Rows'],
          'avg_row_length' => $logStats['Avg_row_length'],
          'data_length' => $logStats['Data_length'],
          'index_length' => $logStats['Index_length'],
          'formatted_size' => self::formatBytes($logStats['Data_length'] + $logStats['Index_length'])
        ];
      }

      // Check for slow queries or performance issues
      $slowQueries = self::identifySlowQueries($pdo);
      if (!empty($slowQueries)) {
        $stats['slow_queries'] = $slowQueries;
      }

      // Check index usage
      $indexAnalysis = self::analyzeIndexUsage($pdo);
      if (!empty($indexAnalysis)) {
        $stats['index_analysis'] = $indexAnalysis;
      }

      return $stats;

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Performance monitoring failed: ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Identify slow queries that could be optimized.
   */
  protected static function identifySlowQueries($pdo) {
    $recommendations = [];

    try {
      // Check for queries without proper indexes
      $stmt = $pdo->query("
        EXPLAIN SELECT * FROM email_queue
        WHERE status = 'pending'
        ORDER BY priority ASC, created_date ASC
      ");

      $explanation = $stmt->fetchAll(PDO::FETCH_ASSOC);

      foreach ($explanation as $row) {
        if ($row['key'] === NULL || $row['rows'] > 1000) {
          $recommendations[] = [
            'type' => 'missing_index',
            'table' => $row['table'],
            'rows_examined' => $row['rows'],
            'suggestion' => 'Consider adding composite index on (status, priority, created_date)'
          ];
        }
      }

    }
    catch (Exception $e) {
      // Ignore errors in analysis
    }

    return $recommendations;
  }

  /**
   * Analyze index usage and recommendations.
   */
  protected static function analyzeIndexUsage($pdo) {
    $analysis = [];

    try {
      // Check index cardinality
      $stmt = $pdo->query("
        SHOW INDEX FROM email_queue
        WHERE Cardinality > 0
      ");

      $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

      foreach ($indexes as $index) {
        if ($index['Cardinality'] < 10) {
          $analysis[] = [
            'type' => 'low_cardinality',
            'index' => $index['Key_name'],
            'column' => $index['Column_name'],
            'cardinality' => $index['Cardinality'],
            'suggestion' => 'Consider dropping or combining this index due to low cardinality'
          ];
        }
      }

    }
    catch (Exception $e) {
      // Ignore errors in analysis
    }

    return $analysis;
  }

  /**
   * Get queue processing performance metrics.
   */
  public static function getProcessingMetrics($timeframe = '24 HOUR') {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      // Calculate throughput metrics
      $stmt = $pdo->query("
        SELECT
          COUNT(*) as total_sent,
          COUNT(CASE WHEN sent_date >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as sent_last_hour,
          COUNT(CASE WHEN sent_date >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as sent_last_day,
          AVG(TIMESTAMPDIFF(SECOND, created_date, sent_date)) as avg_processing_time,
          MIN(sent_date) as first_sent,
          MAX(sent_date) as last_sent
        FROM email_queue
        WHERE created_date >= DATE_SUB(NOW(), INTERVAL {$timeframe})
        AND status = 'sent' AND sent_date IS NOT NULL
      ");
      $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($metrics && $metrics['total_sent'] > 0) {
        $metrics['emails_per_hour'] = round($metrics['sent_last_hour']);
        $metrics['emails_per_day'] = round($metrics['sent_last_day']);
        $metrics['avg_processing_time_formatted'] = self::formatTime($metrics['avg_processing_time']);

        // Calculate overall throughput
        if ($metrics['first_sent'] && $metrics['last_sent']) {
          $totalHours = (strtotime($metrics['last_sent']) - strtotime($metrics['first_sent'])) / 3600;
          if ($totalHours > 0) {
            $metrics['overall_emails_per_hour'] = round($metrics['total_sent'] / $totalHours, 2);
          }
        }
      }

      // Get failure metrics
      $stmt = $pdo->query("
        SELECT
          COUNT(*) as total_failed,
          COUNT(CASE WHEN created_date >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as failed_last_day,
          AVG(retry_count) as avg_retry_count
        FROM email_queue
        WHERE created_date >= DATE_SUB(NOW(), INTERVAL {$timeframe})
        AND status = 'failed'
      ");

      $failureMetrics = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($failureMetrics) {
        $metrics = array_merge($metrics, $failureMetrics);
      }

      // Calculate success rate
      if (isset($metrics['total_sent']) && isset($metrics['total_failed'])) {
        $totalProcessed = $metrics['total_sent'] + $metrics['total_failed'];
        $metrics['success_rate'] = $totalProcessed > 0 ? round(($metrics['total_sent'] / $totalProcessed) * 100, 2) : 0;
      }

      return $metrics;

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Failed to get processing metrics: ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Get optimization recommendations.
   */
  public static function getOptimizationRecommendations() {
    $recommendations = [];

    try {
      $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();
      $dbStats = self::monitorDatabasePerformance();
      $processingMetrics = self::getProcessingMetrics();

      // Check queue backlog
      if (isset($stats['pending']) && $stats['pending'] > 1000) {
        $recommendations[] = [
          'priority' => 'high',
          'category' => 'performance',
          'issue' => 'Large queue backlog',
          'description' => "You have {$stats['pending']} pending emails in the queue",
          'suggestion' => 'Consider increasing batch size or running queue processing more frequently'
        ];
      }

      // Check failure rate
      if (isset($processingMetrics['success_rate']) && $processingMetrics['success_rate'] < 90) {
        $recommendations[] = [
          'priority' => 'high',
          'category' => 'reliability',
          'issue' => 'High failure rate',
          'description' => "Success rate is {$processingMetrics['success_rate']}%",
          'suggestion' => 'Review SMTP settings and email content for common failure causes'
        ];
      }

      // Check database size
      if (isset($dbStats['email_queue']['rows']) && $dbStats['email_queue']['rows'] > 100000) {
        $recommendations[] = [
          'priority' => 'medium',
          'category' => 'maintenance',
          'issue' => 'Large database size',
          'description' => "Email queue table has {$dbStats['email_queue']['rows']} rows",
          'suggestion' => 'Consider implementing cleanup routine for old emails'
        ];
      }

      // Check processing speed
      if (isset($processingMetrics['avg_processing_time']) && $processingMetrics['avg_processing_time'] > 60) {
        $recommendations[] = [
          'priority' => 'medium',
          'category' => 'performance',
          'issue' => 'Slow processing time',
          'description' => "Average processing time is {$processingMetrics['avg_processing_time_formatted']}",
          'suggestion' => 'Review SMTP connection settings and consider using a faster mail service'
        ];
      }

      // Add database-specific recommendations
      if (isset($dbStats['slow_queries'])) {
        foreach ($dbStats['slow_queries'] as $query) {
          $recommendations[] = [
            'priority' => 'medium',
            'category' => 'database',
            'issue' => $query['type'],
            'description' => $query['suggestion'],
            'suggestion' => 'Add recommended database indexes'
          ];
        }
      }

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Failed to generate optimization recommendations: ' . $e->getMessage());
    }

    return $recommendations;
  }

  /**
   * Perform cleanup operations to improve performance.
   */
  public static function performCleanup() {
    $results = [];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      $cleanupDays = CRM_Emailqueue_Config::getCleanupDays();

      // Clean up old sent emails
      $stmt = $pdo->prepare("
        DELETE FROM email_queue
        WHERE status = 'sent'
        AND sent_date < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT 10000
      ");
      $stmt->execute([$cleanupDays]);
      $results['old_sent_emails'] = $stmt->rowCount();

      // Clean up old cancelled emails
      $stmt = $pdo->prepare("
        DELETE FROM email_queue
        WHERE status = 'cancelled'
        AND created_date < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT 10000
      ");
      $stmt->execute([$cleanupDays]);
      $results['old_cancelled_emails'] = $stmt->rowCount();

      // Clean up orphaned log entries
      $stmt = $pdo->query("
        DELETE el FROM email_queue_log el
        LEFT JOIN email_queue eq ON el.queue_id = eq.id
        WHERE eq.id IS NULL
        LIMIT 10000
      ");
      $results['orphaned_logs'] = $stmt->rowCount();

      // Optimize tables
      $pdo->exec("OPTIMIZE TABLE email_queue");
      $pdo->exec("OPTIMIZE TABLE email_queue_log");
      $results['tables_optimized'] = 2;

      if (CRM_Emailqueue_Config::isDebugMode()) {
        CRM_Core_Error::debug_log_message('Cleanup completed: ' . json_encode($results));
      }

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Cleanup failed: ' . $e->getMessage());
      $results['error'] = $e->getMessage();
    }

    return $results;
  }

  /**
   * Format time in human-readable format.
   */
  protected static function formatTime($seconds) {
    if ($seconds < 1) {
      return round($seconds * 1000, 2) . 'ms';
    }
    elseif ($seconds < 60) {
      return round($seconds, 2) . 's';
    }
    elseif ($seconds < 3600) {
      return round($seconds / 60, 1) . 'm';
    }
    else {
      return round($seconds / 3600, 1) . 'h';
    }
  }

  /**
   * Format bytes in human-readable format.
   */
  protected static function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
  }

  /**
   * Get system health check.
   */
  public static function getSystemHealthCheck($timeframe = '24 HOUR') {
    $health = [
      'overall_status' => 'healthy',
      'checks' => [],
      'warnings' => [],
      'errors' => []
    ];

    try {
      // Check if extension is enabled
      if (!CRM_Emailqueue_Config::isEnabled()) {
        $health['checks'][] = ['name' => 'Extension Status', 'status' => 'disabled', 'message' => 'Email Queue extension is disabled'];
        $health['overall_status'] = 'warning';
      }
      else {
        $health['checks'][] = ['name' => 'Extension Status', 'status' => 'ok', 'message' => 'Extension is enabled'];
      }

      // Check database connection
      try {
        $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
        $pdo->query("SELECT 1");
        $health['checks'][] = ['name' => 'Database Connection', 'status' => 'ok', 'message' => 'Database connection successful'];
      }
      catch (Exception $e) {
        $health['checks'][] = ['name' => 'Database Connection', 'status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
        $health['errors'][] = 'Database connection failed';
        $health['overall_status'] = 'error';
      }

      // Check scheduled job
      $resultJob = civicrm_api3('Job', 'get', [
        'api_entity' => "Emailqueue",
        'api_action' => "processqueue",
      ]);
      $jobId = $resultJob['id'] ?? NULL;
      if ($jobId) {
        try {
          $job = reset($resultJob['values']);
          if ($job['is_active']) {
            $health['checks'][] = ['name' => 'Scheduled Job', 'status' => 'ok', 'message' => 'Scheduled job is active'];
          }
          else {
            $health['checks'][] = ['name' => 'Scheduled Job', 'status' => 'warning', 'message' => 'Scheduled job exists but is inactive'];
            $health['warnings'][] = 'Scheduled job is inactive';
            if ($health['overall_status'] === 'healthy') {
              $health['overall_status'] = 'warning';
            }
          }
        }
        catch (Exception $e) {
          $health['checks'][] = ['name' => 'Scheduled Job', 'status' => 'error', 'message' => 'Scheduled job not found'];
          $health['errors'][] = 'Scheduled job not found';
          $health['overall_status'] = 'error';
        }
      }
      else {
        $health['checks'][] = ['name' => 'Scheduled Job', 'status' => 'warning', 'message' => 'No scheduled job configured'];
        $health['warnings'][] = 'No scheduled job configured';
        if ($health['overall_status'] === 'healthy') {
          $health['overall_status'] = 'warning';
        }
      }

      // Check queue backlog
      if (CRM_Emailqueue_Config::isEnabled()) {
        $stats = CRM_Emailqueue_BAO_Queue::getQueueStats($timeframe);
        if ($stats['pending'] > 5000) {
          $health['checks'][] = ['name' => 'Queue Backlog', 'status' => 'warning', 'message' => "Large queue backlog: {$stats['pending']} emails"];
          $health['warnings'][] = 'Large queue backlog';
          if ($health['overall_status'] === 'healthy') {
            $health['overall_status'] = 'warning';
          }
        }
        else {
          $health['checks'][] = ['name' => 'Queue Backlog', 'status' => 'ok', 'message' => "Queue backlog: {$stats['pending']} emails"];
        }
      }

    }
    catch (Exception $e) {
      $health['errors'][] = 'Health check failed: ' . $e->getMessage();
      $health['overall_status'] = 'error';
    }

    return $health;
  }
}
