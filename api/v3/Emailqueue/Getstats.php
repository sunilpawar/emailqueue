<?php
use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Emailqueue.Getstats API specification with client_id support
 *
 * @param array $spec description of fields supported by this API call
 */
function _civicrm_api3_emailqueue_Getstats_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Get stats for specific client (admin only, defaults to current client)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['timeframe'] = [
    'title' => 'Timeframe',
    'description' => 'Time period for statistics (e.g., "24 HOUR", "7 DAY", "30 DAY")',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'api.default' => '24 HOUR',
    'options' => [
      '1 HOUR' => 'Last Hour',
      '24 HOUR' => 'Last 24 Hours',
      '7 DAY' => 'Last 7 Days',
      '30 DAY' => 'Last 30 Days',
      '90 DAY' => 'Last 90 Days'
    ],
  ];
  $spec['include_client_comparison'] = [
    'title' => 'Include Client Comparison',
    'description' => 'Include comparison with other clients (admin only)',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'api.default' => FALSE,
  ];
}

/**
 * Emailqueue.Getstats API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_getstats($params) {
  try {
    $timeframe = $params['timeframe'] ?? '24 HOUR';
    $clientId = $params['client_id'] ?? NULL;
    $includeComparison = $params['include_client_comparison'] ?? FALSE;

    // Determine which client to get stats for
    if (!empty($clientId)) {
      // Check if user has permission to view other clients
      if (!CRM_Emailqueue_Config::hasAdminClientAccess()) {
        $currentClientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
        if ($clientId !== $currentClientId) {
          throw new API_Exception('You can only view statistics for your own client');
        }
      }
    }
    else {
      $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    }

    // Temporarily switch client context if needed
    $originalClientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    if ($clientId !== $originalClientId) {
      CRM_Emailqueue_BAO_Queue::switchClientContext($clientId);
    }

    // Get basic queue statistics for the specified client
    $stats = CRM_Emailqueue_BAO_Queue::getQueueStats($timeframe);

    // Add client context information
    $stats['client_id'] = $clientId;
    $stats['timeframe'] = $timeframe;
    $stats['generated_at'] = date('Y-m-d H:i:s');

    // Calculate additional metrics
    $totalProcessed = $stats['sent'] + $stats['failed'];
    $stats['total_processed'] = $totalProcessed;
    $stats['success_rate'] = $totalProcessed > 0 ? round(($stats['sent'] / $totalProcessed) * 100, 2) : 0;
    $stats['failure_rate'] = $totalProcessed > 0 ? round(($stats['failed'] / $totalProcessed) * 100, 2) : 0;

    // Get processing performance metrics
    $processingMetrics = CRM_Emailqueue_Utils_Performance::getProcessingMetrics($timeframe, $clientId);
    $stats['processing_metrics'] = $processingMetrics;

    // Include client comparison if requested and user has admin access
    if ($includeComparison && CRM_Emailqueue_Config::hasAdminClientAccess()) {
      try {
        $allClients = CRM_Emailqueue_BAO_Queue::getClientStats();
        $stats['client_comparison'] = [];

        foreach ($allClients as $client) {
          if ($client['client_id'] !== $clientId) {
            // Get basic stats for comparison
            $comparisonStats = [
              'client_id' => $client['client_id'],
              'total_emails' => $client['total_emails'],
              'pending' => $client['pending'],
              'sent' => $client['sent'],
              'failed' => $client['failed'],
              'last_activity' => $client['last_activity']
            ];

            $clientTotal = $client['sent'] + $client['failed'];
            $comparisonStats['success_rate'] = $clientTotal > 0 ? round(($client['sent'] / $clientTotal) * 100, 2) : 0;

            $stats['client_comparison'][] = $comparisonStats;
          }
        }

        // Add summary comparison
        $stats['comparison_summary'] = [
          'total_clients' => count($allClients),
          'current_client_rank_by_volume' => $this->calculateClientRank($clientId, $allClients, 'total_emails'),
          'current_client_rank_by_success_rate' => $this->calculateClientRank($clientId, $allClients, 'success_rate')
        ];

      }
      catch (Exception $e) {
        // If comparison fails, just add a note
        $stats['client_comparison_error'] = 'Could not load client comparison: ' . $e->getMessage();
      }
    }

    // Get trend data for the specified timeframe
    try {
      $trendData = self::getTrendData($timeframe, $clientId);
      $stats['trends'] = $trendData;
    }
    catch (Exception $e) {
      $stats['trends_error'] = 'Could not load trend data: ' . $e->getMessage();
    }

    // Restore original client context
    if ($clientId !== $originalClientId) {
      CRM_Emailqueue_BAO_Queue::switchClientContext($originalClientId);
    }

    return civicrm_api3_create_success($stats);

  }
  catch (Exception $e) {
    throw new API_Exception('Failed to get queue statistics: ' . $e->getMessage());
  }
}

/**
 * Get trend data for charts and analysis.
 */
function getTrendData($timeframe, $clientId) {
  try {
    $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

    // Determine the appropriate grouping based on timeframe
    $groupFormat = '%Y-%m-%d %H:00:00'; // Hourly by default
    if (strpos($timeframe, 'DAY') !== FALSE) {
      $days = (int)str_replace(' DAY', '', $timeframe);
      if ($days > 7) {
        $groupFormat = '%Y-%m-%d'; // Daily for longer periods
      }
    }

    $sql = "
      SELECT
        DATE_FORMAT(created_date, '{$groupFormat}') as time_period,
        status,
        COUNT(*) as count
      FROM email_queue
      WHERE client_id = ?
      AND created_date >= DATE_SUB(NOW(), INTERVAL {$timeframe})
      GROUP BY DATE_FORMAT(created_date, '{$groupFormat}'), status
      ORDER BY time_period
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$clientId]);
    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Transform data for trend analysis
    $trends = [];
    foreach ($rawData as $row) {
      $period = $row['time_period'];
      if (!isset($trends[$period])) {
        $trends[$period] = [
          'time_period' => $period,
          'pending' => 0,
          'processing' => 0,
          'sent' => 0,
          'failed' => 0,
          'cancelled' => 0,
          'total' => 0
        ];
      }

      $status = strtolower($row['status']);
      $count = (int)$row['count'];
      $trends[$period][$status] = $count;
      $trends[$period]['total'] += $count;
    }

    return array_values($trends);

  }
  catch (Exception $e) {
    CRM_Emailqueue_Utils_ErrorHandler::handleException($e, ['operation' => 'get_trend_data', 'client_id' => $clientId]);
    return [];
  }
}

/**
 * Calculate client rank for comparison.
 */
function calculateClientRank($clientId, $allClients, $metric) {
  // Sort clients by the specified metric
  usort($allClients, function ($a, $b) use ($metric) {
    if ($metric === 'success_rate') {
      $aTotal = $a['sent'] + $a['failed'];
      $bTotal = $b['sent'] + $b['failed'];
      $aRate = $aTotal > 0 ? ($a['sent'] / $aTotal) * 100 : 0;
      $bRate = $bTotal > 0 ? ($b['sent'] / $bTotal) * 100 : 0;
      return $bRate <=> $aRate; // Descending order
    }
    else {
      return $b[$metric] <=> $a[$metric]; // Descending order
    }
  });

  // Find the rank of the current client
  foreach ($allClients as $index => $client) {
    if ($client['client_id'] === $clientId) {
      return $index + 1; // Rank starts from 1
    }
  }

  return NULL; // Client not found
}
