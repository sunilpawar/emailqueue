<?php

/**
 * EmailqueueAdmin.Healthcheck API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_admin_healthcheck($params) {
  try {
    $clientId = $params['client_id'] ?? null;
    $health = CRM_Emailqueue_Utils_Performance::getSystemHealthCheck('24 HOUR');
    $health['client_id'] = $clientId ?: CRM_Emailqueue_BAO_Queue::getCurrentClientId();

    return civicrm_api3_create_success($health);
  } catch (Exception $e) {
    throw new API_Exception('Health check failed: ' . $e->getMessage());
  }
}

/**
 * EmailqueueAdmin.Healthcheck API specification
 */
function _civicrm_api3_emailqueue_admin_healthcheck_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Check health for specific client (admin only)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
}

function _civicrm_api3_emailqueue_admin_getmetrics_spec(&$spec) {
  $spec['time_range'] = [
    'type' => CRM_Utils_Type::T_STRING,
    'name' => 'time_range',
    'title' => 'Time Range',
    'api.default' => '24 HOUR',
    'description' => 'Time Range for metrics, e.g., "24 HOUR", "7 DAY", "30 DAY".',
  ];
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Get metrics for specific client (admin only)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
}

/**
 * EmailqueueAdmin.Getmetrics API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_admin_getmetrics($params) {
  if (empty($params['time_range'])) {
    $params['time_range'] = '24 HOUR';
  }

  $mapping = [
    '1h' => '1 HOUR',
    '2h' => '2 HOUR',
    '6h' => '6 HOUR',
    '24h' => '24 HOUR',
    '7d' => '7 DAY',
    '30d' => '30 DAY',
    '1m' => '1 MONTH',
    '3m' => '3 MONTHS',
    '6m' => '6 MONTHS',
    '1y' => '1 YEAR',
  ];
  $params['time_range'] = $mapping[$params['time_range']] ?? $params['time_range'];

  try {
    $clientId = $params['client_id'] ?? null;

    // If client_id is specified and user doesn't have admin access, restrict to current client
    if (!empty($clientId) && !CRM_Emailqueue_Config::hasAdminClientAccess()) {
      $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    }

    $metrics = [
      'client_id' => $clientId ?: CRM_Emailqueue_BAO_Queue::getCurrentClientId(),
      'queue_stats' => CRM_Emailqueue_BAO_Queue::getQueueStats($params['time_range']),
      'processing_metrics' => CRM_Emailqueue_Utils_Performance::getProcessingMetrics($params['time_range'], $clientId),
      'database_metrics' => CRM_Emailqueue_Utils_Performance::monitorDatabasePerformance($params['time_range'], $clientId),
      'error_stats' => CRM_Emailqueue_Utils_ErrorHandler::getErrorStats($params['time_range']),
      'system_health' => CRM_Emailqueue_Utils_Performance::getSystemHealthCheck($params['time_range']),
      'charts' => CRM_Emailqueue_Page_DashboardNew::getChartData($params['time_range'], $clientId),
    ];

    return civicrm_api3_create_success($metrics);
  } catch (Exception $e) {
    throw new API_Exception('Failed to get metrics: ' . $e->getMessage());
  }
}

/**
 * EmailqueueAdmin.Getrecommendations API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_admin_getrecommendations($params) {
  try {
    $clientId = $params['client_id'] ?? null;

    // If client_id is specified and user doesn't have admin access, restrict to current client
    if (!empty($clientId) && !CRM_Emailqueue_Config::hasAdminClientAccess()) {
      $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    }

    $recommendations = CRM_Emailqueue_Utils_Performance::getOptimizationRecommendations($clientId);
    return civicrm_api3_create_success($recommendations);
  } catch (Exception $e) {
    throw new API_Exception('Failed to get recommendations: ' . $e->getMessage());
  }
}

/**
 * EmailqueueAdmin.Getrecommendations API specification
 */
function _civicrm_api3_emailqueue_admin_getrecommendations_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Get recommendations for specific client (admin only)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
}

/**
 * EmailqueueAdmin.Cleanup API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_admin_cleanup($params) {
  try {
    $clientId = $params['client_id'] ?? null;
    $allClients = !empty($params['all_clients']);

    // Check admin access for all-clients operations
    if ($allClients && !CRM_Emailqueue_Config::hasAdminClientAccess()) {
      throw new API_Exception('Admin access required for all-clients cleanup');
    }

    // If client_id is specified and user doesn't have admin access, restrict to current client
    if (!empty($clientId) && !CRM_Emailqueue_Config::hasAdminClientAccess()) {
      $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    }

    $options = [
      'client_id' => $clientId,
      'all_clients' => $allClients,
      'sent_retention_days' => $params['sent_retention_days'] ?? null,
      'cancelled_retention_days' => $params['cancelled_retention_days'] ?? null,
      'log_retention_days' => $params['log_retention_days'] ?? null,
      'batch_size' => $params['batch_size'] ?? null,
      'cleanup_failed' => !empty($params['cleanup_failed'])
    ];

    // Remove null values
    $options = array_filter($options, function($value) {
      return $value !== null;
    });

    $result = CRM_Emailqueue_Utils_Cleanup::performFullCleanup($options);
    return civicrm_api3_create_success($result);
  } catch (Exception $e) {
    throw new API_Exception('Cleanup failed: ' . $e->getMessage());
  }
}

/**
 * EmailqueueAdmin.Cleanup API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_admin_cleanup_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Cleanup specific client (admin only)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['all_clients'] = [
    'title' => 'All Clients',
    'description' => 'Cleanup all clients (admin only)',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
  ];
  $spec['sent_retention_days'] = [
    'title' => 'Sent Email Retention Days',
    'description' => 'Number of days to keep sent emails',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
  ];
  $spec['cancelled_retention_days'] = [
    'title' => 'Cancelled Email Retention Days',
    'description' => 'Number of days to keep cancelled emails',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
  ];
  $spec['log_retention_days'] = [
    'title' => 'Log Retention Days',
    'description' => 'Number of days to keep log entries',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
  ];
  $spec['batch_size'] = [
    'title' => 'Batch Size',
    'description' => 'Number of records to process per batch',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
  ];
  $spec['cleanup_failed'] = [
    'title' => 'Cleanup Failed Emails',
    'description' => 'Whether to clean up old failed emails',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
  ];
}

/**
 * EmailqueueAdmin.Analyzehealth API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_admin_analyzehealth($params) {
  try {
    $clientId = $params['client_id'] ?? null;

    // If client_id is specified and user doesn't have admin access, restrict to current client
    if (!empty($clientId) && !CRM_Emailqueue_Config::hasAdminClientAccess()) {
      $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    }

    $analysis = CRM_Emailqueue_Utils_Cleanup::analyzeDatabaseHealth($clientId);
    return civicrm_api3_create_success($analysis);
  } catch (Exception $e) {
    throw new API_Exception('Health analysis failed: ' . $e->getMessage());
  }
}

/**
 * EmailqueueAdmin.Analyzehealth API specification
 */
function _civicrm_api3_emailqueue_admin_analyzehealth_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Analyze health for specific client (admin only)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
}

/**
 * EmailqueueAdmin.Fixissues API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_admin_fixissues($params) {
  try {
    $clientId = $params['client_id'] ?? null;

    // If client_id is specified and user doesn't have admin access, restrict to current client
    if (!empty($clientId) && !CRM_Emailqueue_Config::hasAdminClientAccess()) {
      $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    }

    $options = [
      'client_id' => $clientId,
      'fix_stuck_processing' => !empty($params['fix_stuck_processing']),
      'reset_old_failed' => !empty($params['reset_old_failed']),
      'fix_indexes' => !empty($params['fix_indexes']),
      'failed_reset_days' => $params['failed_reset_days'] ?? 7
    ];

    $result = CRM_Emailqueue_Utils_Cleanup::fixCommonIssues($options);
    return civicrm_api3_create_success($result);
  } catch (Exception $e) {
    throw new API_Exception('Issue fixing failed: ' . $e->getMessage());
  }
}

/**
 * EmailqueueAdmin.Fixissues API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_admin_fixissues_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Fix issues for specific client (admin only)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['fix_stuck_processing'] = [
    'title' => 'Fix Stuck Processing',
    'description' => 'Reset emails stuck in processing status',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
  ];
  $spec['reset_old_failed'] = [
    'title' => 'Reset Old Failed',
    'description' => 'Reset old failed emails for retry',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
  ];
  $spec['fix_indexes'] = [
    'title' => 'Fix Indexes',
    'description' => 'Add missing database indexes',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
  ];
  $spec['failed_reset_days'] = [
    'title' => 'Failed Reset Days',
    'description' => 'Only reset failed emails newer than this many days',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'api.default' => 7,
  ];
}

/**
 * EmailqueueAdmin.Getcleanuprepor API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_admin_getcleanuprepor($params) {
  try {
    $clientId = $params['client_id'] ?? null;

    // If client_id is specified and user doesn't have admin access, restrict to current client
    if (!empty($clientId) && !CRM_Emailqueue_Config::hasAdminClientAccess()) {
      $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    }

    $report = CRM_Emailqueue_Utils_Cleanup::generateCleanupReport($clientId);
    return civicrm_api3_create_success($report);
  } catch (Exception $e) {
    throw new API_Exception('Failed to generate cleanup report: ' . $e->getMessage());
  }
}

/**
 * EmailqueueAdmin.Getcleanuprepor API specification
 */
function _civicrm_api3_emailqueue_admin_getcleanuprepor_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Generate cleanup report for specific client (admin only)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
}

/**
 * EmailqueueAdmin.Geterrorlogs API
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_admin_geterrorlogs($params) {
  try {
    $limit = $params['limit'] ?? 50;
    $logs = CRM_Emailqueue_Utils_ErrorHandler::getRecentErrors($limit);
    return civicrm_api3_create_success($logs);
  } catch (Exception $e) {
    throw new API_Exception('Failed to get error logs: ' . $e->getMessage());
  }
}

/**
 * EmailqueueAdmin.Geterrorlogs API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_admin_geterrorlogs_spec(&$spec) {
  $spec['limit'] = [
    'title' => 'Limit',
    'description' => 'Maximum number of log entries to return',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'api.default' => 50,
  ];
}

/**
 * EmailqueueAdmin.Testsystem API
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_admin_testsystem($params) {
  try {
    $results = [];
    $currentClientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();

    // Test database connection
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      $pdo->query("SELECT 1");
      $results['database_connection'] = 'OK';
    } catch (Exception $e) {
      $results['database_connection'] = 'FAILED: ' . $e->getMessage();
    }

    // Test table existence
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      $pdo->query("SELECT COUNT(*) FROM email_queue LIMIT 1");
      $pdo->query("SELECT COUNT(*) FROM email_queue_log LIMIT 1");
      $results['database_tables'] = 'OK';
    } catch (Exception $e) {
      $results['database_tables'] = 'FAILED: ' . $e->getMessage();
    }

    // Test client_id configuration
    try {
      if (!empty($currentClientId)) {
        $results['client_configuration'] = "OK (Client ID: {$currentClientId})";
      } else {
        $results['client_configuration'] = 'WARNING: No client ID configured';
      }
    } catch (Exception $e) {
      $results['client_configuration'] = 'FAILED: ' . $e->getMessage();
    }

    // Test multi-client features
    try {
      $isMultiClient = CRM_Emailqueue_Config::isMultiClientMode();
      $hasAdminAccess = CRM_Emailqueue_Config::hasAdminClientAccess();

      $results['multi_client_mode'] = $isMultiClient ? 'ENABLED' : 'DISABLED';
      $results['admin_client_access'] = $hasAdminAccess ? 'ENABLED' : 'DISABLED';

      if ($hasAdminAccess) {
        $clientList = CRM_Emailqueue_Config::getClientList();
        $results['available_clients'] = count($clientList) . ' clients found';
      }
    } catch (Exception $e) {
      $results['multi_client_features'] = 'FAILED: ' . $e->getMessage();
    }

    // Test error handling
    $results['error_handling'] = CRM_Emailqueue_Utils_ErrorHandler::testErrorHandling();

    // Test configuration
    $validation = CRM_Emailqueue_Config::validateConfiguration();
    $results['configuration'] = [
      'errors' => $validation['errors'],
      'warnings' => $validation['warnings'],
      'status' => empty($validation['errors']) ? 'OK' : 'FAILED'
    ];

    // Test scheduled job
    $jobId = Civi::settings()->get('emailqueue_job_id');
    if ($jobId) {
      try {
        $job = civicrm_api3('Job', 'getsingle', ['id' => $jobId]);
        $results['scheduled_job'] = $job['is_active'] ? 'OK' : 'DISABLED';
      } catch (Exception $e) {
        $results['scheduled_job'] = 'NOT_FOUND';
      }
    } else {
      $results['scheduled_job'] = 'NOT_CONFIGURED';
    }

    // Overall system status
    $hasErrors = false;
    foreach ($results as $key => $result) {
      if (is_string($result) && strpos($result, 'FAILED') !== FALSE) {
        $hasErrors = true;
        break;
      }
      if (is_array($result) && !empty($result['errors'])) {
        $hasErrors = true;
        break;
      }
    }

    $results['overall_status'] = $hasErrors ? 'FAILED' : 'OK';
    $results['current_client_id'] = $currentClientId;

    return civicrm_api3_create_success($results);
  } catch (Exception $e) {
    throw new API_Exception('System test failed: ' . $e->getMessage());
  }
}

/**
 * EmailqueueAdmin.Optimizeperformance API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_admin_optimizeperformance($params) {
  try {
    $clientId = $params['client_id'] ?? null;

    // If client_id is specified and user doesn't have admin access, restrict to current client
    if (!empty($clientId) && !CRM_Emailqueue_Config::hasAdminClientAccess()) {
      $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    }

    $results = [
      'client_id' => $clientId ?: CRM_Emailqueue_BAO_Queue::getCurrentClientId()
    ];

    // Optimize database tables (affects all clients)
    $optimizeResult = CRM_Emailqueue_Utils_Cleanup::optimizeTables();
    $results['table_optimization'] = $optimizeResult;

    // Update table statistics
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      $pdo->exec("ANALYZE TABLE email_queue");
      $pdo->exec("ANALYZE TABLE email_queue_log");
      $results['table_analysis'] = 'OK';
    } catch (Exception $e) {
      $results['table_analysis'] = 'FAILED: ' . $e->getMessage();
    }

    // Clean up old data if requested
    if (!empty($params['cleanup_old_data'])) {
      $cleanupOptions = [
        'client_id' => $clientId,
        'batch_size' => 5000 // Smaller batches for optimization
      ];

      $cleanupResult = CRM_Emailqueue_Utils_Cleanup::performFullCleanup($cleanupOptions);
      $results['cleanup'] = $cleanupResult;
    }

    // Generate performance report
    $results['performance_metrics'] = CRM_Emailqueue_Utils_Performance::getProcessingMetrics('24 HOUR', $clientId);
    $results['recommendations'] = CRM_Emailqueue_Utils_Performance::getOptimizationRecommendations($clientId);

    return civicrm_api3_create_success($results);
  } catch (Exception $e) {
    throw new API_Exception('Performance optimization failed: ' . $e->getMessage());
  }
}

/**
 * EmailqueueAdmin.Optimizeperformance API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_admin_optimizeperformance_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Optimize performance for specific client (admin only)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['cleanup_old_data'] = [
    'title' => 'Cleanup Old Data',
    'description' => 'Also perform cleanup of old data during optimization',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'api.default' => FALSE,
  ];
}

/**
 * EmailqueueAdmin.Resetfailed API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_admin_resetfailed($params) {
  try {
    $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
    $clientId = $params['client_id'] ?? null;

    // If client_id is specified and user doesn't have admin access, restrict to current client
    if (!empty($clientId) && !CRM_Emailqueue_Config::hasAdminClientAccess()) {
      $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    }

    if (!$clientId) {
      $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    }

    $whereClause = "client_id = ? AND status = 'failed'";
    $bindParams = [$clientId];

    // Add optional filters
    if (!empty($params['max_age_days'])) {
      $whereClause .= " AND created_date >= DATE_SUB(NOW(), INTERVAL ? DAY)";
      $bindParams[] = (int) $params['max_age_days'];
    }

    if (!empty($params['max_retries_only'])) {
      $whereClause .= " AND retry_count >= max_retries";
    }

    $sql = "
      UPDATE email_queue
      SET status = 'pending', retry_count = 0, error_message = NULL, scheduled_date = NULL
      WHERE {$whereClause}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindParams);
    $resetCount = $stmt->rowCount();

    // Log the bulk action
    if ($resetCount > 0) {
      CRM_Emailqueue_Utils_ErrorHandler::info("Bulk reset {$resetCount} failed emails for client {$clientId} via API");
    }

    return civicrm_api3_create_success([
      'reset_count' => $resetCount,
      'client_id' => $clientId,
      'message' => "Reset {$resetCount} failed emails for retry for client {$clientId}"
    ]);

  } catch (Exception $e) {
    throw new API_Exception('Failed to reset failed emails: ' . $e->getMessage());
  }
}

/**
 * EmailqueueAdmin.Resetfailed API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_admin_resetfailed_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Reset failed emails for specific client (admin only)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['max_age_days'] = [
    'title' => 'Maximum Age in Days',
    'description' => 'Only reset failed emails newer than this many days',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
  ];
  $spec['max_retries_only'] = [
    'title' => 'Max Retries Only',
    'description' => 'Only reset emails that have reached maximum retry count',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'api.default' => FALSE,
  ];
}

/**
 * EmailqueueAdmin.Getstatus API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_admin_getstatus($params) {
  try {
    $currentClientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();

    $status = [
      'current_client_id' => $currentClientId,
      'extension_enabled' => CRM_Emailqueue_Config::isEnabled(),
      'extension_version' => CRM_Emailqueue_Config::EXTENSION_VERSION,
      'multi_client_mode' => CRM_Emailqueue_Config::isMultiClientMode(),
      'admin_client_access' => CRM_Emailqueue_Config::hasAdminClientAccess(),
      'configuration_valid' => TRUE,
      'database_connected' => FALSE,
      'scheduled_job_active' => FALSE,
      'queue_stats' => [],
      'last_processed' => NULL,
      'system_health' => 'unknown'
    ];

    // Check configuration
    $validation = CRM_Emailqueue_Config::validateConfiguration();
    $status['configuration_valid'] = empty($validation['errors']);
    $status['configuration_issues'] = $validation;

    // Check database connection
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      $pdo->query("SELECT 1");
      $status['database_connected'] = TRUE;

      // Get queue stats for current client
      $status['queue_stats'] = CRM_Emailqueue_BAO_Queue::getQueueStats();

      // Get last processed email for current client
      $stmt = $pdo->prepare("SELECT MAX(sent_date) as last_sent FROM email_queue WHERE client_id = ? AND status = 'sent'");
      $stmt->execute([$currentClientId]);
      $lastSent = $stmt->fetchColumn();
      $status['last_processed'] = $lastSent;

      // Get client statistics if admin access is enabled
      if ($status['admin_client_access']) {
        try {
          $status['client_statistics'] = CRM_Emailqueue_Config::getClientList();
        } catch (Exception $e) {
          $status['client_statistics_error'] = $e->getMessage();
        }
      }

    }
    catch (Exception $e) {
      $status['database_error'] = $e->getMessage();
    }

    // Check scheduled job
    $jobId = Civi::settings()->get('emailqueue_job_id');
    if ($jobId) {
      try {
        $job = civicrm_api3('Job', 'getsingle', ['id' => $jobId]);
        $status['scheduled_job_active'] = !empty($job['is_active']);
        $status['scheduled_job_details'] = $job;
      }
      catch (Exception $e) {
        $status['scheduled_job_error'] = $e->getMessage();
      }
    }

    // Determine overall system health
    if (!$status['extension_enabled']) {
      $status['system_health'] = 'disabled';
    }
    elseif (!$status['configuration_valid'] || !$status['database_connected']) {
      $status['system_health'] = 'error';
    }
    elseif (!$status['scheduled_job_active']) {
      $status['system_health'] = 'warning';
    }
    else {
      $status['system_health'] = 'healthy';
    }

    return civicrm_api3_create_success($status);

  }
  catch (Exception $e) {
    throw new API_Exception('Failed to get system status: ' . $e->getMessage());
  }
}

/**
 * EmailqueueAdmin.Getmulticlientoverview API for admin users
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_admin_getmulticlientoverview($params) {
  try {
    // Check admin access
    if (!CRM_Emailqueue_Config::hasAdminClientAccess()) {
      throw new API_Exception('Admin client access is required to view multi-client overview');
    }

    $overview = CRM_Emailqueue_Utils_Performance::getMultiClientOverview();
    return civicrm_api3_create_success($overview);
  }
  catch (Exception $e) {
    throw new API_Exception('Failed to get multi-client overview: ' . $e->getMessage());
  }
}
