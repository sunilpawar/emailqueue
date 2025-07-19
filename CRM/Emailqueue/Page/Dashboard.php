<?php

use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Advanced monitoring dashboard for Email Queue system.
 */
class CRM_Emailqueue_Page_Dashboard extends CRM_Core_Page {

  public function run() {
    // Check permissions
    if (!CRM_Core_Permission::check('administer CiviCRM')) {
      CRM_Core_Error::statusBounce(ts('You do not have permission to access this page.'));
    }

    CRM_Core_Resources::singleton()->addScriptUrl('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js');

    // Add custom dashboard JavaScript
    CRM_Core_Resources::singleton()->addScriptFile(E::LONG_NAME, 'js/dashboard_chart.js');

    try {
      // Get comprehensive metrics
      $this->assign('dashboardData', $this->getDashboardData());

      // Get chart data
      $chartData = $this->getChartData();
      $this->assign('charts', $chartData);

      // Convert chart data to JSON for JavaScript
      $chartDataJson = json_encode($chartData, JSON_NUMERIC_CHECK);
      $this->assign('chartDataJson', $chartDataJson);
      $this->assign('alerts', $this->getSystemAlerts());
      $this->assign('recommendations', $this->getActionableRecommendations());

      // Pass data to JavaScript
      CRM_Core_Resources::singleton()->addVars('emailqueue', [
        'dashboardChartDataJson' => $chartDataJson,
        'refreshUrl' => CRM_Utils_System::url('civicrm/admin/emailqueue/dashboard', 'reset=1'),
        'apiEndpoint' => CRM_Utils_System::url('civicrm/ajax/rest')
      ]);
    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus(E::ts('Error loading dashboard: %1', [1 => $e->getMessage()]), E::ts('Dashboard Error'), 'error');
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
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
      'queue_health' => 'unknown'
    ];

    try {
      // Basic queue statistics
      $data['queue_stats'] = CRM_Emailqueue_BAO_Queue::getQueueStats();

      // Processing metrics
      $data['processing_metrics'] = CRM_Emailqueue_Utils_Performance::getProcessingMetrics();

      // Database performance
      $data['database_metrics'] = CRM_Emailqueue_Utils_Performance::monitorDatabasePerformance();

      // System health
      $data['system_health'] = CRM_Emailqueue_Utils_Performance::getSystemHealthCheck();
      $data['system_status'] = $data['system_health']['overall_status'];

      // Error statistics
      $data['error_stats'] = CRM_Emailqueue_Utils_ErrorHandler::getErrorStats('24 HOUR');

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
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
      $data['error'] = $e->getMessage();
    }

    return $data;
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
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
    }

    return $charts;
  }

  /**
   * Get system alerts and warnings.
   */
  protected function getSystemAlerts() {
    $alerts = [];

    try {
      $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();
      $health = CRM_Emailqueue_Utils_Performance::getSystemHealthCheck();

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
      if ($stats['failed'] > $stats['sent'] * 0.1) {
        $alerts[] = [
          'type' => 'error',
          'title' => 'High Failure Rate',
          'message' => "Failed emails ({$stats['failed']}) exceed 10% of sent emails",
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

      // Database size warning
      $dbStats = CRM_Emailqueue_Utils_Performance::monitorDatabasePerformance();
      if (isset($dbStats['email_queue']['rows']) && $dbStats['email_queue']['rows'] > 500000) {
        $alerts[] = [
          'type' => 'info',
          'title' => 'Large Database',
          'message' => "Email queue table has {$dbStats['email_queue']['rows']} rows",
          'action' => 'Consider implementing cleanup procedures'
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
    try {
      $recommendations = CRM_Emailqueue_Utils_Performance::getOptimizationRecommendations();

      // Add action buttons to recommendations
      foreach ($recommendations as &$rec) {
        $rec['actions'] = $this->getRecommendationActions($rec);
      }

      return $recommendations;

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
      return [];
    }
  }

  /**
   * Get recent activity for timeline.
   */
  protected function getRecentActivity() {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $sql = "
        SELECT
          el.action,
          el.message,
          el.created_date,
          eq.to_email,
          eq.subject,
          eq.status
        FROM email_queue_log el
        LEFT JOIN email_queue eq ON el.queue_id = eq.id
        WHERE el.created_date >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY el.created_date DESC
        LIMIT 20
      ";

      $stmt = $pdo->query($sql);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
      return [];
    }
  }

  /**
   * Get performance trends.
   */
  protected function getPerformanceTrends() {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      // Get hourly processing rates for last 24 hours
      $sql = "
        SELECT
          DATE_FORMAT(sent_date, '%Y-%m-%d %H:00:00') as hour,
          COUNT(*) as sent_count,
          AVG(TIMESTAMPDIFF(SECOND, created_date, sent_date)) as avg_processing_time
        FROM email_queue
        WHERE status = 'sent'
        AND sent_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY DATE_FORMAT(sent_date, '%Y-%m-%d %H:00:00')
        ORDER BY hour
      ";

      $stmt = $pdo->query($sql);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
      return [];
    }
  }

  /**
   * Get capacity metrics.
   */
  protected function getCapacityMetrics() {
    try {
      $processingMetrics = CRM_Emailqueue_Utils_Performance::getProcessingMetrics();
      $batchSize = CRM_Emailqueue_Config::getSetting('batch_size', 50);
      $cronFrequency = 5; // minutes

      // Calculate theoretical and actual capacity
      $theoreticalHourlyCapacity = ($batchSize * 60) / $cronFrequency;
      $actualHourlyRate = $processingMetrics['emails_per_hour'] ?? 0;

      $capacityUtilization = $theoreticalHourlyCapacity > 0
        ? ($actualHourlyRate / $theoreticalHourlyCapacity) * 100
        : 0;

      return [
        'theoretical_hourly_capacity' => $theoreticalHourlyCapacity,
        'actual_hourly_rate' => $actualHourlyRate,
        'capacity_utilization' => round($capacityUtilization, 2),
        'batch_size' => $batchSize,
        'cron_frequency' => $cronFrequency
      ];

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
      return [];
    }
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
      if (isset($stats['pending']) && $stats['pending'] > 500) {
        $penalty = min(20, ($stats['pending'] - 500) / 100);
        $score -= $penalty;
        $factors[] = "High pending count (-{$penalty})";
      }

      // Penalize high failure rate
      $totalProcessed = ($stats['sent'] ?? 0) + ($stats['failed'] ?? 0);
      if ($totalProcessed > 0) {
        $failureRate = ($stats['failed'] ?? 0) / $totalProcessed;
        if ($failureRate > 0.05) { // 5% threshold
          $penalty = min(30, $failureRate * 100);
          $score -= $penalty;
          $factors[] = "High failure rate (-{$penalty})";
        }
      }

      // Penalize system health issues
      $systemHealth = $data['system_health'] ?? [];
      if (!empty($systemHealth['errors'])) {
        $penalty = count($systemHealth['errors']) * 10;
        $score -= $penalty;
        $factors[] = "System errors (-{$penalty})";
      }

      // Penalize stuck processing emails
      if (isset($stats['processing']) && $stats['processing'] > 10) {
        $score -= 15;
        $factors[] = "Stuck processing emails (-15)";
      }

      $score = max(0, min(100, $score));

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
      return ['score' => 0, 'grade' => 'error', 'factors' => ['Calculation error']];
    }
  }

  /**
   * Get volume chart data.
   */
  protected function getVolumeChart($timeframe) {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

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
      $chartData = [];
      foreach ($rawData as $row) {
        $chartData[$row['time_period']][$row['status']] = $row['count'];
      }

      return $chartData;

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
      return [];
    }
  }

  /**
   * Get status distribution for pie chart.
   */
  protected function getStatusDistribution() {
    try {
      $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();
      return [
        ['label' => 'Pending', 'value' => $stats['pending'], 'color' => '#ffc107'],
        ['label' => 'Processing', 'value' => $stats['processing'], 'color' => '#17a2b8'],
        ['label' => 'Sent', 'value' => $stats['sent'], 'color' => '#28a745'],
        ['label' => 'Failed', 'value' => $stats['failed'], 'color' => '#dc3545'],
        ['label' => 'Cancelled', 'value' => $stats['cancelled'], 'color' => '#6c757d']
      ];

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
      return [];
    }
  }

  /**
   * Get performance trend data.
   */
  protected function getPerformanceTrend() {
    try {
      $trends = $this->getPerformanceTrends();

      return array_map(function ($trend) {
        return [
          'time' => $trend['hour'],
          'throughput' => $trend['sent_count'],
          'avg_time' => round($trend['avg_processing_time'], 2)
        ];
      }, $trends);

    }
    catch (Exception $e) {
      return [];
    }
  }

  /**
   * Get error trend data.
   */
  protected function getErrorTrend() {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $sql = "
        SELECT
          DATE_FORMAT(created_date, '%Y-%m-%d %H:00:00') as hour,
          COUNT(*) as error_count
        FROM email_queue_log
        WHERE action IN ('ERROR', 'CRITICAL', 'failed')
        AND created_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY DATE_FORMAT(created_date, '%Y-%m-%d %H:00:00')
        ORDER BY hour
      ";

      $stmt = $pdo->query($sql);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
    catch (Exception $e) {
      return [];
    }
  }

  /**
   * Get priority distribution.
   */
  protected function getPriorityDistribution() {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $sql = "
        SELECT
          priority,
          COUNT(*) as count
        FROM email_queue
        WHERE created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY priority
        ORDER BY priority
      ";

      $stmt = $pdo->query($sql);
      $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $priorities = CRM_Emailqueue_Config::getPriorityLevels();

      return array_map(function ($row) use ($priorities) {
        return [
          'priority' => $row['priority'],
          'label' => $priorities[$row['priority']] ?? "Priority {$row['priority']}",
          'count' => $row['count']
        ];
      }, $data);

    }
    catch (Exception $e) {
      return [];
    }
  }

  /**
   * Get actions for recommendations.
   */
  protected function getRecommendationActions($recommendation) {
    $actions = [];

    switch ($recommendation['category']) {
      case 'performance':
        if (strpos($recommendation['issue'], 'backlog') !== FALSE) {
          $actions[] = [
            'label' => 'Process Queue Now',
            'url' => '#',
            'class' => 'process-queue-btn',
            'type' => 'primary'
          ];
          $actions[] = [
            'label' => 'Adjust Settings',
            'url' => CRM_Utils_System::url('civicrm/admin/emailqueue/settings'),
            'type' => 'secondary'
          ];
        }
        break;

      case 'maintenance':
        $actions[] = [
          'label' => 'Run Cleanup',
          'url' => '#',
          'class' => 'cleanup-btn',
          'type' => 'warning'
        ];
        break;

      case 'database':
        $actions[] = [
          'label' => 'Optimize Database',
          'url' => '#',
          'class' => 'optimize-db-btn',
          'type' => 'info'
        ];
        break;
    }

    return $actions;
  }
}
