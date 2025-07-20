<?php

use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Advanced monitoring dashboard for Email Queue system with multi-client support.
 */
class CRM_Emailqueue_Page_DashboardNew extends CRM_Core_Page {

  public function run() {
    // Check permissions
    if (!CRM_Core_Permission::check('administer CiviCRM')) {
      CRM_Core_Error::statusBounce(ts('You do not have permission to access this page.'));
    }

    // Get current client context
    $currentClientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    $hasAdminAccess = CRM_Emailqueue_Config::hasAdminClientAccess();
    $isMultiClientMode = CRM_Emailqueue_Config::isMultiClientMode();

    // Handle client switching for admin users
    $selectedClientId = CRM_Utils_Request::retrieve('client_id', 'String');
    if ($hasAdminAccess && !empty($selectedClientId)) {
      try {
        CRM_Emailqueue_BAO_Queue::switchClientContext($selectedClientId);
        $currentClientId = $selectedClientId;
      }
      catch (Exception $e) {
        CRM_Core_Session::setStatus(
          E::ts('Failed to switch to client %1: %2', [1 => $selectedClientId, 2 => $e->getMessage()]),
          E::ts('Client Switch Error'),
          'error'
        );
      }
    }

    // Add Chart.js library
    CRM_Core_Resources::singleton()->addScriptUrl('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js');

    // Add custom dashboard JavaScript
    CRM_Core_Resources::singleton()->addScriptFile(E::LONG_NAME, 'js/dashboard_chart.js');

    try {
      // Get comprehensive metrics for current client
      $dashboardData = $this->getDashboardData($currentClientId);
      $this->assign('dashboardData', $dashboardData);

      // Get chart data for current client
      $chartData = self::getChartData('24 HOUR', $currentClientId);
      $this->assign('charts', $chartData);

      // Convert chart data to JSON for JavaScript
      $chartDataJson = json_encode($chartData, JSON_NUMERIC_CHECK);
      $this->assign('chartDataJson', $chartDataJson);

      // Get alerts and recommendations for current client
      $this->assign('alerts', $this->getSystemAlerts($currentClientId));
      $this->assign('recommendations', $this->getActionableRecommendations($currentClientId));

      // Multi-client information
      $this->assign('currentClientId', $currentClientId);
      $this->assign('hasAdminAccess', $hasAdminAccess);
      $this->assign('isMultiClientMode', $isMultiClientMode);

      // Get available clients for admin users
      if ($hasAdminAccess) {
        try {
          $availableClients = CRM_Emailqueue_Config::getClientList();
          $this->assign('availableClients', $availableClients);
        }
        catch (Exception $e) {
          $this->assign('availableClients', []);
        }
      }

      // Pass data to JavaScript
      CRM_Core_Resources::singleton()->addVars('emailqueue', [
        'dashboardChartDataJson' => $chartDataJson,
        'refreshUrl' => CRM_Utils_System::url('civicrm/admin/emailqueue/dashboard-new', 'reset=1'),
        'apiEndpoint' => CRM_Utils_System::url('civicrm/ajax/rest'),
        'currentClientId' => $currentClientId,
        'hasAdminAccess' => $hasAdminAccess,
        'isMultiClientMode' => $isMultiClientMode
      ]);

    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus(
        E::ts('Error loading dashboard for client %1: %2', [1 => $currentClientId, 2 => $e->getMessage()]),
        E::ts('Dashboard Error'),
        'error'
      );

      // Log the error
      if (class_exists('CRM_Emailqueue_Utils_ErrorHandler')) {
        CRM_Emailqueue_Utils_ErrorHandler::handleException($e, ['client_id' => $currentClientId]);
      }
      else {
        error_log('EmailQueue Dashboard Error for client ' . $currentClientId . ': ' . $e->getMessage());
      }

      // Set default empty values
      $this->assign('dashboardData', $this->getDefaultDashboardData($currentClientId));
      $this->assign('charts', []);
      $this->assign('chartDataJson', '{}');
      $this->assign('alerts', []);
      $this->assign('recommendations', []);
      $this->assign('currentClientId', $currentClientId);
      $this->assign('hasAdminAccess', $hasAdminAccess);
      $this->assign('isMultiClientMode', $isMultiClientMode);
    }

    parent::run();
  }

  /**
   * Get comprehensive dashboard data for specific client.
   */
  protected function getDashboardData($clientId) {
    $data = [
      'timestamp' => date('Y-m-d H:i:s'),
      'client_id' => $clientId,
      'system_status' => 'unknown',
      'queue_health' => [
        'score' => 0,
        'grade' => 'unknown',
        'factors' => []
      ]
    ];

    try {
      // Temporarily switch context if needed
      $originalClientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
      if ($clientId !== $originalClientId) {
        CRM_Emailqueue_BAO_Queue::switchClientContext($clientId);
      }

      // Basic queue statistics for this client
      $data['queue_stats'] = CRM_Emailqueue_BAO_Queue::getQueueStats();

      // Processing metrics for this client
      $data['processing_metrics'] = CRM_Emailqueue_Utils_Performance::getProcessingMetrics('24 HOUR', $clientId);

      // Database performance for this client
      $data['database_metrics'] = CRM_Emailqueue_Utils_Performance::monitorDatabasePerformance('24 HOUR', $clientId);

      // System health (includes client-specific checks)
      $data['system_health'] = CRM_Emailqueue_Utils_Performance::getSystemHealthCheck('24 HOUR');
      $data['system_status'] = $data['system_health']['overall_status'] ?? 'unknown';

      // Error statistics for this client
      $data['error_stats'] = CRM_Emailqueue_Utils_ErrorHandler::getErrorStats('24 HOUR');

      // Recent activity for this client
      $data['recent_activity'] = $this->getRecentActivity($clientId);

      // Performance trends for this client
      $data['trends'] = $this->getPerformanceTrends($clientId);

      // Capacity metrics for this client
      $data['capacity'] = $this->getCapacityMetrics($clientId);

      // Queue health score for this client
      $data['queue_health'] = $this->calculateQueueHealth($data, $clientId);

      // Restore original context
      if ($clientId !== $originalClientId) {
        CRM_Emailqueue_BAO_Queue::switchClientContext($originalClientId);
      }

    }
    catch (Exception $e) {
      error_log('Dashboard data error for client ' . $clientId . ': ' . $e->getMessage());
      $data['error'] = $e->getMessage();
    }

    return $data;
  }

  /**
   * Get default dashboard data structure for error cases.
   */
  protected function getDefaultDashboardData($clientId) {
    return [
      'timestamp' => date('Y-m-d H:i:s'),
      'client_id' => $clientId,
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
   * Get queue statistics for specific client.
   */
  protected function getQueueStats($clientId) {
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
        WHERE client_id = ? AND created_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY status
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([$clientId]);
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = strtolower($row['status']);
        if (isset($stats[$status])) {
          $stats[$status] = (int)$row['count'];
        }
      }

    }
    catch (Exception $e) {
      error_log('Queue stats error for client ' . $clientId . ': ' . $e->getMessage());
    }

    return $stats;
  }

  /**
   * Get processing metrics for specific client.
   */
  protected function getProcessingMetrics($clientId) {
    return CRM_Emailqueue_Utils_Performance::getProcessingMetrics('24 HOUR', $clientId);
  }

  /**
   * Get chart data for visualizations with client support.
   */
  public static function getChartData($timeframe = '24 HOUR', $clientId = NULL) {
    $charts = [];

    try {
      if ($clientId === NULL) {
        $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
      }

      // Email volume over time for specific client
      $charts['volume_24h'] = self::getVolumeChart($timeframe, $clientId);

      // Email status distribution for specific client
      $charts['status_distribution'] = self::getStatusDistribution($timeframe, $clientId);

      // Processing performance for specific client
      $charts['performance_trend'] = self::getPerformanceTrend($timeframe, $clientId);

      // Error rate trend for specific client
      $charts['error_trend'] = self::getErrorTrend($timeframe, $clientId);

      // Priority distribution for specific client
      $charts['priority_distribution'] = self::getPriorityDistribution($timeframe, $clientId);

    }
    catch (Exception $e) {
      error_log('Chart data error for client ' . $clientId . ': ' . $e->getMessage());
    }

    return $charts;
  }

  /**
   * Get volume chart data for specific client.
   */
  public static function getVolumeChart($timeframe, $clientId = NULL) {
    $chartData = [];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      if (!$pdo) {
        return $chartData;
      }

      if ($clientId === NULL) {
        $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
      }

      $sql = "
        SELECT
          DATE_FORMAT(created_date, '%Y-%m-%d %H:00:00') as time_period,
          status,
          COUNT(*) as count
        FROM email_queue
        WHERE client_id = ? AND created_date >= DATE_SUB(NOW(), INTERVAL {$timeframe})
        GROUP BY DATE_FORMAT(created_date, '%Y-%m-%d %H:00:00'), status
        ORDER BY time_period
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([$clientId]);
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
      error_log('Volume chart error for client ' . $clientId . ': ' . $e->getMessage());
    }

    return $chartData;
  }

  /**
   * Get status distribution for pie chart for specific client.
   */
  public static function getStatusDistribution($timeframe, $clientId = NULL) {
    $distribution = [];

    try {
      if ($clientId === NULL) {
        $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
      }

      // Temporarily switch context if needed
      $originalClientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
      if ($clientId !== $originalClientId) {
        CRM_Emailqueue_BAO_Queue::switchClientContext($clientId);
      }

      $stats = CRM_Emailqueue_BAO_Queue::getQueueStats($timeframe);

      $statusColors = [
        'pending' => '#ffc107',
        'processing' => '#17a2b8',
        'sent' => '#28a745',
        'failed' => '#dc3545',
        'cancelled' => '#6c757d'
      ];

      foreach ($stats as $status => $count) {
        if (TRUE || $count > 0) {
          $distribution[] = [
            'label' => ucfirst($status),
            'value' => $count,
            'color' => $statusColors[$status] ?? '#6c757d'
          ];
        }
      }

      // Restore original context
      if ($clientId !== $originalClientId) {
        CRM_Emailqueue_BAO_Queue::switchClientContext($originalClientId);
      }
    }
    catch (Exception $e) {
      error_log('Status distribution error for client ' . $clientId . ': ' . $e->getMessage());
    }

    return $distribution;
  }

  /**
   * Get performance trend data for specific client.
   */
  public static function getPerformanceTrend($timeframe = '24 HOUR', $clientId = NULL) {
    $trends = [];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      if (!$pdo) {
        return $trends;
      }

      if ($clientId === NULL) {
        $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
      }

      $sql = "
        SELECT
          DATE_FORMAT(sent_date, '%Y-%m-%d %H:00:00') as hour,
          COUNT(*) as sent_count,
          AVG(TIMESTAMPDIFF(SECOND, created_date, sent_date)) as avg_processing_time
        FROM email_queue
        WHERE client_id = ? AND status = 'sent'
        AND sent_date >= DATE_SUB(NOW(), INTERVAL {$timeframe})
        AND created_date IS NOT NULL
        AND sent_date IS NOT NULL
        GROUP BY DATE_FORMAT(sent_date, '%Y-%m-%d %H:00:00')
        ORDER BY hour
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([$clientId]);
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
      error_log('Performance trend error for client ' . $clientId . ': ' . $e->getMessage());
    }

    return $trends;
  }

  /**
   * Get error trend data for specific client.
   */
  public static function getErrorTrend($timeframe = '24 HOUR', $clientId = NULL) {
    $errors = [];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      if (!$pdo) {
        return $errors;
      }

      if ($clientId === NULL) {
        $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
      }

      $sql = "
        SELECT
          DATE_FORMAT(created_date, '%Y-%m-%d %H:00:00') as hour,
          COUNT(*) as error_count
        FROM email_queue
        WHERE client_id = ? AND status = 'failed'
        AND created_date >= DATE_SUB(NOW(), INTERVAL {$timeframe})
        GROUP BY DATE_FORMAT(created_date, '%Y-%m-%d %H:00:00')
        ORDER BY hour
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([$clientId]);
      $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
    catch (Exception $e) {
      error_log('Error trend error for client ' . $clientId . ': ' . $e->getMessage());
    }

    return $errors;
  }

  /**
   * Get priority distribution for specific client.
   */
  public static function getPriorityDistribution($timeframe = '7 DAY', $clientId = NULL) {
    $distribution = [];

    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      if (!$pdo) {
        return $distribution;
      }

      if ($clientId === NULL) {
        $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
      }

      $sql = "
        SELECT
          COALESCE(priority, 0) as priority,
          COUNT(*) as count
        FROM email_queue
        WHERE client_id = ? AND created_date >= DATE_SUB(NOW(), INTERVAL {$timeframe})
        GROUP BY COALESCE(priority, 0)
        ORDER BY priority
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([$clientId]);
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
      error_log('Priority distribution error for client ' . $clientId . ': ' . $e->getMessage());
    }

    return $distribution;
  }

  /**
   * Get recent activity for timeline for specific client.
   */
  protected function getRecentActivity($clientId) {
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
        WHERE client_id = ? AND status = 'sent'
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
        WHERE client_id = ? AND status = 'failed'
        AND created_date >= DATE_SUB(NOW(), INTERVAL 2 HOUR)

        ORDER BY created_date DESC
        LIMIT 20
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([$clientId, $clientId]);
      $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
    catch (Exception $e) {
      error_log('Recent activity error for client ' . $clientId . ': ' . $e->getMessage());
    }

    return $activities;
  }

  /**
   * Get performance trends for specific client.
   */
  protected function getPerformanceTrends($clientId) {
    return self::getPerformanceTrend('24 HOUR', $clientId);
  }

  /**
   * Get capacity metrics for specific client.
   */
  protected function getCapacityMetrics($clientId) {
    $capacity = [
      'theoretical_hourly_capacity' => 0,
      'actual_hourly_rate' => 0,
      'capacity_utilization' => 0,
      'batch_size' => 50,
      'cron_frequency' => 5,
      'client_id' => $clientId
    ];

    try {
      $processingMetrics = $this->getProcessingMetrics($clientId);
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
        'cron_frequency' => $cronFrequency,
        'client_id' => $clientId
      ];

    }
    catch (Exception $e) {
      error_log('Capacity metrics error for client ' . $clientId . ': ' . $e->getMessage());
    }

    return $capacity;
  }

  /**
   * Calculate overall queue health score for specific client.
   */
  protected function calculateQueueHealth($data, $clientId) {
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
        'factors' => $factors,
        'client_id' => $clientId
      ];

    }
    catch (Exception $e) {
      return [
        'score' => 0,
        'grade' => 'error',
        'factors' => ['Calculation error: ' . $e->getMessage()],
        'client_id' => $clientId
      ];
    }
  }

  /**
   * Get system alerts and warnings for specific client.
   */
  protected function getSystemAlerts($clientId) {
    $alerts = [];

    try {
      // Temporarily switch context if needed
      $originalClientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
      if ($clientId !== $originalClientId) {
        CRM_Emailqueue_BAO_Queue::switchClientContext($clientId);
      }

      $stats = $this->getQueueStats($clientId);
      $health = CRM_Emailqueue_Utils_Performance::getSystemHealthCheck();

      // High queue backlog
      if ($stats['pending'] > 1000) {
        $alerts[] = [
          'type' => 'warning',
          'title' => 'High Queue Backlog',
          'message' => "Client '{$clientId}' has {$stats['pending']} emails pending in the queue",
          'action' => 'Consider increasing processing frequency or batch size'
        ];
      }

      // High failure rate
      $totalProcessed = $stats['sent'] + $stats['failed'];
      if ($totalProcessed > 0 && $stats['failed'] > $totalProcessed * 0.1) {
        $alerts[] = [
          'type' => 'error',
          'title' => 'High Failure Rate',
          'message' => "Client '{$clientId}' failed emails ({$stats['failed']}) exceed 10% of processed emails",
          'action' => 'Review SMTP configuration and email content'
        ];
      }

      // System health issues
      if ($health['overall_status'] !== 'healthy') {
        $alerts[] = [
          'type' => 'error',
          'title' => 'System Health Issues',
          'message' => "Client '{$clientId}': " . count($health['errors']) . ' critical issues detected',
          'action' => 'Review system health check details'
        ];
      }

      // Restore original context
      if ($clientId !== $originalClientId) {
        CRM_Emailqueue_BAO_Queue::switchClientContext($originalClientId);
      }

    }
    catch (Exception $e) {
      $alerts[] = [
        'type' => 'error',
        'title' => 'Dashboard Error',
        'message' => "Failed to load system alerts for client '{$clientId}': " . $e->getMessage(),
        'action' => 'Check system logs for details'
      ];
    }

    return $alerts;
  }

  /**
   * Get actionable recommendations for specific client.
   */
  protected function getActionableRecommendations($clientId) {
    $recommendations = [];

    try {
      $stats = $this->getQueueStats($clientId);
      $metrics = $this->getProcessingMetrics($clientId);

      // High backlog recommendation
      if ($stats['pending'] > 500) {
        $recommendations[] = [
          'issue' => 'High Email Queue Backlog',
          'description' => "Client '{$clientId}' has {$stats['pending']} emails waiting to be processed.",
          'suggestion' => 'Increase batch size or processing frequency to clear the backlog faster.',
          'priority' => 'high',
          'category' => 'performance',
          'client_id' => $clientId,
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
          'description' => "Client '{$clientId}' current success rate is {$metrics['success_rate']}%.",
          'suggestion' => 'Review SMTP settings and email content for delivery issues.',
          'priority' => 'medium',
          'category' => 'delivery',
          'client_id' => $clientId,
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
      error_log('Recommendations error for client ' . $clientId . ': ' . $e->getMessage());
    }

    return $recommendations;
  }
}
