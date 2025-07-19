<?php

use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Advanced monitoring dashboard for Email Queue system.
 */
class CRM_Emailqueue_Page_DashboardNew extends CRM_Core_Page {

  public function run() {
    // Check permissions
    if (!CRM_Core_Permission::check('administer CiviCRM')) {
      CRM_Core_Error::statusBounce(ts('You do not have permission to access this page.'));
    }

    // Add Chart.js library
    CRM_Core_Resources::singleton()->addScriptUrl('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js');

    // Add custom dashboard JavaScript
    CRM_Core_Resources::singleton()->addScriptFile(E::LONG_NAME, 'js/dashboard_chart.js');

    try {
      // Get comprehensive metrics
      $dashboardData = $this->getDashboardData();
      $this->assign('dashboardData', $dashboardData);

      // Get chart data
      $chartData = $this->getChartData();
      $this->assign('charts', $chartData);

      // Convert chart data to JSON for JavaScript
      $chartDataJson = json_encode($chartData, JSON_NUMERIC_CHECK);
      $this->assign('chartDataJson', $chartDataJson);

      // Get alerts and recommendations
      $this->assign('alerts', $this->getSystemAlerts());
      $this->assign('recommendations', $this->getActionableRecommendations());

      // Pass data to JavaScript
      CRM_Core_Resources::singleton()->addVars('emailqueue', [
        'dashboardChartDataJson' => $chartDataJson,
        'refreshUrl' => CRM_Utils_System::url('civicrm/admin/emailqueue/dashboard-new', 'reset=1'),
        'apiEndpoint' => CRM_Utils_System::url('civicrm/ajax/rest')
      ]);

    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus(
        E::ts('Error loading dashboard: %1', [1 => $e->getMessage()]),
        E::ts('Dashboard Error'),
        'error'
      );

      // Log the error
      if (class_exists('CRM_Emailqueue_Utils_ErrorHandler')) {
        CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
      }
      else {
        error_log('EmailQueue Dashboard Error: ' . $e->getMessage());
      }

      // Set default empty values
      $this->assign('dashboardData', $this->getDefaultDashboardData());
      $this->assign('charts', []);
      $this->assign('chartDataJson', '{}');
      $this->assign('alerts', []);
      $this->assign('recommendations', []);
    }

    parent::run();
  }

  /**
   * Get comprehensive dashboard data.
   */
  protected function getDashboardData() {
    $data = [
      'timestamp' => date('Y-m-d H:i:s'),
      'system_status' => 'unknown',
      'queue_health' => [
        'score' => 0,
        'grade' => 'unknown',
        'factors' => []
      ]
    ];

    try {
      // Basic queue statistics
      $data['queue_stats'] = $this->getQueueStats();

      // Processing metrics
      $data['processing_metrics'] = CRM_Emailqueue_Utils_Performance::getProcessingMetrics();$this->getProcessingMetrics();

      // Database performance
      $data['database_metrics'] = $this->getDatabaseMetrics();

      // System health
      $data['system_health'] = $this->getSystemHealthCheck();
      $data['system_status'] = $data['system_health']['overall_status'] ?? 'unknown';

      // Error statistics
      $data['error_stats'] = $this->getErrorStats();

      // Recent activity
      $data['recent_activity'] = $this->getRecentActivity();

      // Performance trends
      $data['trends'] = $this->getPerformanceTrends();

      // Capacity metrics
      $data['capacity'] = $this->getCapacityMetrics();

      // Queue health score
      $data['queue_health'] = $this->calculateQueueHealth($data);

    }
    catch (Exception $e) {
      error_log('Dashboard data error: ' . $e->getMessage());
      $data['error'] = $e->getMessage();
    }

    return $data;
  }

  /**
   * Get default dashboard data structure for error cases.
   */
  protected function getDefaultDashboardData() {
    return [
      'timestamp' => date('Y-m-d H:i:s'),
      'system_status' => 'error',
      'queue_health' => [
        'score' => 0,
        'grade' => 'error',
        'factors' => ['Unable to load dashboard data']
      ],
      'queue_stats' => [
        'pending' => 0,
        'processing' => 0,
        'sent' => 0,
        'failed' => 0,
        'cancelled' => 0
      ],
      'processing_metrics' => [],
      'error_stats' => [],
      'recent_activity' => [],
      'capacity' => []
    ];
  }

  /**
   * Get queue statistics.
   */
  protected function getQueueStats() {
    $stats = [
      'pending' => 0,
      'processing' => 0,
      'sent' => 0,
      'failed' => 0,
      'cancelled' => 0
    ];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      if (!$pdo) {
        return $stats;
      }

      $sql = "
        SELECT status, COUNT(*) as count
        FROM email_queue
        WHERE created_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY status
      ";

      $stmt = $pdo->query($sql);
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = strtolower($row['status']);
        if (isset($stats[$status])) {
          $stats[$status] = (int)$row['count'];
        }
      }

    }
    catch (Exception $e) {
      error_log('Queue stats error: ' . $e->getMessage());
    }

    return $stats;
  }

  /**
   * Get processing metrics.
   */
  protected function getProcessingMetrics() {
    $metrics = [
      'emails_per_hour' => 0,
      'avg_processing_time' => 0,
      'avg_processing_time_formatted' => '0s',
      'success_rate' => 0
    ];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      if (!$pdo) {
        return $metrics;
      }

      // Get hourly rate
      $sql = "
        SELECT COUNT(*) as count
        FROM email_queue
        WHERE status = 'sent'
        AND sent_date >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
      ";

      $stmt = $pdo->query($sql);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $metrics['emails_per_hour'] = (int)($row['count'] ?? 0);

      // Get average processing time
      $sql = "
        SELECT AVG(TIMESTAMPDIFF(SECOND, created_date, sent_date)) as avg_time
        FROM email_queue
        WHERE status = 'sent'
        AND sent_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND created_date IS NOT NULL
        AND sent_date IS NOT NULL
      ";

      $stmt = $pdo->query($sql);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $avgTime = (float)($row['avg_time'] ?? 0);
      $metrics['avg_processing_time'] = $avgTime;
      $metrics['avg_processing_time_formatted'] = $this->formatProcessingTime($avgTime);

      // Get success rate
      $sql = "
        SELECT
          COUNT(*) as total,
          SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent
        FROM email_queue
        WHERE created_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND status IN ('sent', 'failed')
      ";

      $stmt = $pdo->query($sql);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $total = (int)($row['total'] ?? 0);
      $sent = (int)($row['sent'] ?? 0);
      $metrics['success_rate'] = $total > 0 ? round(($sent / $total) * 100, 2) : 0;

    }
    catch (Exception $e) {
      error_log('Processing metrics error: ' . $e->getMessage());
    }

    return $metrics;
  }

  /**
   * Format processing time for display.
   */
  protected function formatProcessingTime($seconds) {
    if ($seconds < 60) {
      return round($seconds, 1) . 's';
    }
    elseif ($seconds < 3600) {
      return round($seconds / 60, 1) . 'm';
    }
    else {
      return round($seconds / 3600, 1) . 'h';
    }
  }

  /**
   * Get database metrics.
   */
  protected function getDatabaseMetrics() {
    $metrics = [];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      if (!$pdo) {
        return $metrics;
      }

      $sql = "
        SELECT
          COUNT(*) as total_rows,
          SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_rows
        FROM email_queue
      ";

      $stmt = $pdo->query($sql);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      $metrics['email_queue'] = [
        'rows' => (int)($row['total_rows'] ?? 0),
        'pending_rows' => (int)($row['pending_rows'] ?? 0)
      ];

    }
    catch (Exception $e) {
      error_log('Database metrics error: ' . $e->getMessage());
    }

    return $metrics;
  }

  /**
   * Get system health check.
   */
  protected function getSystemHealthCheck() {
    $health = [
      'overall_status' => 'healthy',
      'checks' => [],
      'errors' => []
    ];

    try {
      // Check database connection
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      if ($pdo) {
        $health['checks'][] = [
          'name' => 'Database Connection',
          'status' => 'healthy',
          'message' => 'Connected'
        ];
      }
      else {
        $health['checks'][] = [
          'name' => 'Database Connection',
          'status' => 'error',
          'message' => 'Connection failed'
        ];
        $health['errors'][] = 'Database connection failed';
        $health['overall_status'] = 'error 1';
      }

      // Check table existence
      if ($pdo) {
        $sql = "SHOW TABLES LIKE 'email_queue'";
        $stmt = $pdo->query($sql);
        if ($stmt->rowCount() > 0) {
          $health['checks'][] = [
            'name' => 'Email Queue Table',
            'status' => 'healthy',
            'message' => 'Table exists'
          ];
        }
        else {
          $health['checks'][] = [
            'name' => 'Email Queue Table',
            'status' => 'error',
            'message' => 'Table not found'
          ];
          $health['errors'][] = 'Email queue table not found';
          $health['overall_status'] = 'error 2';
        }
      }

    }
    catch (Exception $e) {
      $health['overall_status'] = 'error 3';
      $health['errors'][] = $e->getMessage();
      error_log('System health check error: ' . $e->getMessage());
    }

    return $health;
  }

  /**
   * Get error statistics.
   */
  protected function getErrorStats() {
    $stats = [];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      if (!$pdo) {
        return $stats;
      }

      $sql = "
        SELECT COUNT(*) as error_count
        FROM email_queue
        WHERE status = 'failed'
        AND created_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
      ";

      $stmt = $pdo->query($sql);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $stats['failed_24h'] = (int)($row['error_count'] ?? 0);

    }
    catch (Exception $e) {
      error_log('Error stats error: ' . $e->getMessage());
    }

    return $stats;
  }

  /**
   * Get chart data for visualizations.
   */
  protected function getChartData() {
    $charts = [];

    try {
      // Email volume over time (last 24 hours)
      $charts['volume_24h'] = $this->getVolumeChart('24 HOUR');

      // Email status distribution
      $charts['status_distribution'] = $this->getStatusDistribution();

      // Processing performance
      $charts['performance_trend'] = $this->getPerformanceTrend();

      // Error rate trend
      $charts['error_trend'] = $this->getErrorTrend();

      // Priority distribution
      $charts['priority_distribution'] = $this->getPriorityDistribution();

    }
    catch (Exception $e) {
      error_log('Chart data error: ' . $e->getMessage());
    }

    return $charts;
  }

  /**
   * Get volume chart data.
   */
  protected function getVolumeChart($timeframe) {
    $chartData = [];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      if (!$pdo) {
        return $chartData;
      }

      $sql = "
        SELECT
          DATE_FORMAT(created_date, '%Y-%m-%d %H:00:00') as time_period,
          status,
          COUNT(*) as count
        FROM email_queue
        WHERE created_date >= DATE_SUB(NOW(), INTERVAL {$timeframe})
        GROUP BY DATE_FORMAT(created_date, '%Y-%m-%d %H:00:00'), status
        ORDER BY time_period
      ";

      $stmt = $pdo->query($sql);
      $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Transform data for charting
      foreach ($rawData as $row) {
        $time = $row['time_period'];
        $status = strtolower($row['status']);
        $count = (int)$row['count'];

        if (!isset($chartData[$time])) {
          $chartData[$time] = [];
        }
        $chartData[$time][$status] = $count;
      }

    }
    catch (Exception $e) {
      error_log('Volume chart error: ' . $e->getMessage());
    }

    return $chartData;
  }

  /**
   * Get status distribution for pie chart.
   */
  protected function getStatusDistribution() {
    $distribution = [];

    try {
      $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();
      // $this->getQueueStats();

      $statusColors = [
        'pending' => '#ffc107',
        'processing' => '#17a2b8',
        'sent' => '#28a745',
        'failed' => '#dc3545',
        'cancelled' => '#6c757d'
      ];

      foreach ($stats as $status => $count) {
        if ($count > 0) {
          $distribution[] = [
            'label' => ucfirst($status),
            'value' => $count,
            'color' => $statusColors[$status] ?? '#6c757d'
          ];
        }
      }

    }
    catch (Exception $e) {
      error_log('Status distribution error: ' . $e->getMessage());
    }

    return $distribution;
  }

  /**
   * Get performance trend data.
   */
  protected function getPerformanceTrend() {
    $trends = [];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      if (!$pdo) {
        return $trends;
      }

      $sql = "
        SELECT
          DATE_FORMAT(sent_date, '%Y-%m-%d %H:00:00') as hour,
          COUNT(*) as sent_count,
          AVG(TIMESTAMPDIFF(SECOND, created_date, sent_date)) as avg_processing_time
        FROM email_queue
        WHERE status = 'sent'
        AND sent_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND created_date IS NOT NULL
        AND sent_date IS NOT NULL
        GROUP BY DATE_FORMAT(sent_date, '%Y-%m-%d %H:00:00')
        ORDER BY hour
      ";

      $stmt = $pdo->query($sql);
      $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

      foreach ($rawData as $row) {
        $trends[] = [
          'time' => $row['hour'],
          'throughput' => (int)$row['sent_count'],
          'avg_time' => round((float)($row['avg_processing_time'] ?? 0), 2)
        ];
      }

    }
    catch (Exception $e) {
      error_log('Performance trend error: ' . $e->getMessage());
    }

    return $trends;
  }

  /**
   * Get error trend data.
   */
  protected function getErrorTrend() {
    $errors = [];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      if (!$pdo) {
        return $errors;
      }

      $sql = "
        SELECT
          DATE_FORMAT(created_date, '%Y-%m-%d %H:00:00') as hour,
          COUNT(*) as error_count
        FROM email_queue
        WHERE status = 'failed'
        AND created_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY DATE_FORMAT(created_date, '%Y-%m-%d %H:00:00')
        ORDER BY hour
      ";

      $stmt = $pdo->query($sql);
      $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
    catch (Exception $e) {
      error_log('Error trend error: ' . $e->getMessage());
    }

    return $errors;
  }

  /**
   * Get priority distribution.
   */
  protected function getPriorityDistribution() {
    $distribution = [];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      if (!$pdo) {
        return $distribution;
      }

      $sql = "
        SELECT
          COALESCE(priority, 0) as priority,
          COUNT(*) as count
        FROM email_queue
        WHERE created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY COALESCE(priority, 0)
        ORDER BY priority
      ";

      $stmt = $pdo->query($sql);
      $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $priorities = [
        0 => 'Normal',
        1 => 'Low',
        2 => 'High',
        3 => 'Urgent'
      ];

      foreach ($data as $row) {
        $priority = (int)$row['priority'];
        $distribution[] = [
          'priority' => $priority,
          'label' => $priorities[$priority] ?? "Priority {$priority}",
          'count' => (int)$row['count']
        ];
      }

    }
    catch (Exception $e) {
      error_log('Priority distribution error: ' . $e->getMessage());
    }

    return $distribution;
  }

  /**
   * Get recent activity for timeline.
   */
  protected function getRecentActivity() {
    $activities = [];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      if (!$pdo) {
        return $activities;
      }

      $sql = "
        SELECT
          'sent' as action,
          CONCAT('Email sent successfully') as message,
          sent_date as created_date,
          to_email,
          subject,
          status
        FROM email_queue
        WHERE status = 'sent'
        AND sent_date >= DATE_SUB(NOW(), INTERVAL 2 HOUR)

        UNION ALL

        SELECT
          'failed' as action,
          CONCAT('Email delivery failed: ', COALESCE(error_message, 'Unknown error')) as message,
          created_date,
          to_email,
          subject,
          status
        FROM email_queue
        WHERE status = 'failed'
        AND created_date >= DATE_SUB(NOW(), INTERVAL 2 HOUR)

        ORDER BY created_date DESC
        LIMIT 20
      ";

      $stmt = $pdo->query($sql);
      $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
    catch (Exception $e) {
      error_log('Recent activity error: ' . $e->getMessage());
    }

    return $activities;
  }

  /**
   * Get performance trends.
   */
  protected function getPerformanceTrends() {
    return $this->getPerformanceTrend();
  }

  /**
   * Get capacity metrics.
   */
  protected function getCapacityMetrics() {
    $capacity = [
      'theoretical_hourly_capacity' => 0,
      'actual_hourly_rate' => 0,
      'capacity_utilization' => 0,
      'batch_size' => 50,
      'cron_frequency' => 5
    ];

    try {
      $processingMetrics = $this->getProcessingMetrics();
      $batchSize = 50; // Default batch size
      $cronFrequency = 5; // Default frequency in minutes

      // Calculate theoretical and actual capacity
      $theoreticalHourlyCapacity = ($batchSize * 60) / $cronFrequency;
      $actualHourlyRate = $processingMetrics['emails_per_hour'] ?? 0;

      $capacityUtilization = $theoreticalHourlyCapacity > 0
        ? ($actualHourlyRate / $theoreticalHourlyCapacity) * 100
        : 0;

      $capacity = [
        'theoretical_hourly_capacity' => $theoreticalHourlyCapacity,
        'actual_hourly_rate' => $actualHourlyRate,
        'capacity_utilization' => round($capacityUtilization, 2),
        'batch_size' => $batchSize,
        'cron_frequency' => $cronFrequency
      ];

    }
    catch (Exception $e) {
      error_log('Capacity metrics error: ' . $e->getMessage());
    }

    return $capacity;
  }

  /**
   * Calculate overall queue health score.
   */
  protected function calculateQueueHealth($data) {
    $score = 100;
    $factors = [];

    try {
      $stats = $data['queue_stats'] ?? [];

      // Penalize high pending count
      if (isset($stats['pending']) && $stats['pending'] > 1000) {
        $penalty = min(20, ($stats['pending'] - 1000) / 200);
        $score -= $penalty;
        $factors[] = "High pending count (-" . round($penalty) . ")";
      }

      // Penalize high failure rate
      $totalProcessed = ($stats['sent'] ?? 0) + ($stats['failed'] ?? 0);
      if ($totalProcessed > 0) {
        $failureRate = ($stats['failed'] ?? 0) / $totalProcessed;
        if ($failureRate > 0.05) { // 5% threshold
          $penalty = min(30, $failureRate * 100 * 2);
          $score -= $penalty;
          $factors[] = "High failure rate (-" . round($penalty) . ")";
        }
      }

      // Penalize system health issues
      $systemHealth = $data['system_health'] ?? [];
      if (!empty($systemHealth['errors'])) {
        $penalty = count($systemHealth['errors']) * 15;
        $score -= $penalty;
        $factors[] = "System errors (-{$penalty})";
      }

      // Penalize stuck processing emails
      if (isset($stats['processing']) && $stats['processing'] > 10) {
        $score -= 15;
        $factors[] = "Stuck processing emails (-15)";
      }

      $score = max(0, min(100, $score));

      // Determine grade
      if ($score >= 90) {
        $grade = 'excellent';
      }
      elseif ($score >= 75) {
        $grade = 'good';
      }
      elseif ($score >= 60) {
        $grade = 'fair';
      }
      elseif ($score >= 40) {
        $grade = 'poor';
      }
      else {
        $grade = 'critical';
      }

      return [
        'score' => round($score),
        'grade' => $grade,
        'factors' => $factors
      ];

    }
    catch (Exception $e) {
      error_log('Queue health calculation error: ' . $e->getMessage());
      return [
        'score' => 0,
        'grade' => 'error',
        'factors' => ['Calculation error: ' . $e->getMessage()]
      ];
    }
  }

  /**
   * Get system alerts and warnings.
   */
  protected function getSystemAlerts() {
    $alerts = [];

    try {
      $stats = $this->getQueueStats();
      $health = $this->getSystemHealthCheck();

      // High queue backlog
      if ($stats['pending'] > 1000) {
        $alerts[] = [
          'type' => 'warning',
          'title' => 'High Queue Backlog',
          'message' => "There are {$stats['pending']} emails pending in the queue",
          'action' => 'Consider increasing processing frequency or batch size'
        ];
      }

      // High failure rate
      $totalProcessed = $stats['sent'] + $stats['failed'];
      if ($totalProcessed > 0 && $stats['failed'] > $totalProcessed * 0.1) {
        $alerts[] = [
          'type' => 'error',
          'title' => 'High Failure Rate',
          'message' => "Failed emails ({$stats['failed']}) exceed 10% of processed emails",
          'action' => 'Review SMTP configuration and email content'
        ];
      }

      // System health issues
      if ($health['overall_status'] !== 'healthy') {
        $alerts[] = [
          'type' => 'error',
          'title' => 'System Health Issues',
          'message' => count($health['errors']) . ' critical issues detected',
          'action' => 'Review system health check details'
        ];
      }

    }
    catch (Exception $e) {
      $alerts[] = [
        'type' => 'error',
        'title' => 'Dashboard Error',
        'message' => 'Failed to load system alerts: ' . $e->getMessage(),
        'action' => 'Check system logs for details'
      ];
    }

    return $alerts;
  }

  /**
   * Get actionable recommendations.
   */
  protected function getActionableRecommendations() {
    $recommendations = [];

    try {
      $stats = $this->getQueueStats();
      $metrics = $this->getProcessingMetrics();

      // High backlog recommendation
      if ($stats['pending'] > 500) {
        $recommendations[] = [
          'issue' => 'High Email Queue Backlog',
          'description' => "There are {$stats['pending']} emails waiting to be processed.",
          'suggestion' => 'Increase batch size or processing frequency to clear the backlog faster.',
          'priority' => 'high',
          'category' => 'performance',
          'actions' => [
            [
              'label' => 'Process Queue Now',
              'url' => '#',
              'class' => 'process-queue-btn',
              'type' => 'primary'
            ]
          ]
        ];
      }

      // Low success rate recommendation
      if ($metrics['success_rate'] < 95 && $metrics['success_rate'] > 0) {
        $recommendations[] = [
          'issue' => 'Low Email Delivery Success Rate',
          'description' => "Current success rate is {$metrics['success_rate']}%.",
          'suggestion' => 'Review SMTP settings and email content for delivery issues.',
          'priority' => 'medium',
          'category' => 'delivery',
          'actions' => [
            [
              'label' => 'Check Settings',
              'url' => CRM_Utils_System::url('civicrm/admin/emailqueue/settings'),
              'type' => 'secondary'
            ]
          ]
        ];
      }

    }
    catch (Exception $e) {
      error_log('Recommendations error: ' . $e->getMessage());
    }

    return $recommendations;
  }

  /**
   * Get database connection for queue operations.
   */
  protected function getQueueConnection() {
    try {
      // Use CiviCRM's database connection
      $dsn = DB::parseDSN(CIVICRM_DSN);
      $pdo = new PDO(
        "mysql:host={$dsn['hostspec']};dbname={$dsn['database']};charset=utf8",
        $dsn['username'],
        $dsn['password'],
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
      );
      return $pdo;
    }
    catch (Exception $e) {
      error_log('Database connection error: ' . $e->getMessage());
      return NULL;
    }
  }
}
