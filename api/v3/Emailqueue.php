<?php

/**
 * Emailqueue.Search API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_search($params) {
  try {
    // Add client_id context if not provided and user doesn't have admin access
    if (empty($params['client_id']) && !CRM_Emailqueue_Config::hasAdminClientAccess()) {
      $params['client_id'] = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    }

    $searchResult = CRM_Emailqueue_BAO_Queue::searchEmails($params);
    return civicrm_api3_create_success($searchResult);
  }
  catch (Exception $e) {
    throw new API_Exception('Failed to search emails: ' . $e->getMessage());
  }
}

/**
 * Emailqueue.Search API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_search_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Filter by client ID (admin only)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['to_email'] = [
    'title' => 'To Email',
    'description' => 'Filter by recipient email (partial match)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['from_email'] = [
    'title' => 'From Email',
    'description' => 'Filter by sender email (partial match)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['subject'] = [
    'title' => 'Subject',
    'description' => 'Filter by subject (partial match)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['status'] = [
    'title' => 'Status',
    'description' => 'Filter by status (can be array)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'options' => ['pending', 'processing', 'sent', 'failed', 'cancelled'],
  ];
  $spec['priority'] = [
    'title' => 'Priority',
    'description' => 'Filter by priority level',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
  ];
  $spec['date_from'] = [
    'title' => 'Date From',
    'description' => 'Filter created date from (YYYY-MM-DD)',
    'type' => CRM_Utils_Type::T_DATE,
    'api.required' => 0,
  ];
  $spec['date_to'] = [
    'title' => 'Date To',
    'description' => 'Filter created date to (YYYY-MM-DD)',
    'type' => CRM_Utils_Type::T_DATE,
    'api.required' => 0,
  ];
  $spec['sent_from'] = [
    'title' => 'Sent From',
    'description' => 'Filter sent date from (YYYY-MM-DD)',
    'type' => CRM_Utils_Type::T_DATE,
    'api.required' => 0,
  ];
  $spec['sent_to'] = [
    'title' => 'Sent To',
    'description' => 'Filter sent date to (YYYY-MM-DD)',
    'type' => CRM_Utils_Type::T_DATE,
    'api.required' => 0,
  ];
  $spec['has_error'] = [
    'title' => 'Has Error',
    'description' => 'Filter by error presence (yes/no)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'options' => ['yes', 'no'],
  ];
  $spec['min_retries'] = [
    'title' => 'Minimum Retries',
    'description' => 'Filter by minimum retry count',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
  ];
  $spec['max_retries'] = [
    'title' => 'Maximum Retries',
    'description' => 'Filter by maximum retry count',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
  ];
  $spec['limit'] = [
    'title' => 'Limit',
    'description' => 'Number of results to return',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'api.default' => 50,
  ];
  $spec['offset'] = [
    'title' => 'Offset',
    'description' => 'Number of results to skip',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'api.default' => 0,
  ];
  $spec['order_by'] = [
    'title' => 'Order By',
    'description' => 'Field to order by',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'api.default' => 'created_date',
    'options' => ['id', 'to_email', 'subject', 'status', 'priority', 'created_date', 'sent_date', 'retry_count'],
  ];
  $spec['order_dir'] = [
    'title' => 'Order Direction',
    'description' => 'Sort direction',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'api.default' => 'DESC',
    'options' => ['ASC', 'DESC'],
  ];
}

/**
 * Emailqueue.Preview API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_preview($params) {
  try {
    $emailId = $params['id'];

    // The getEmailPreview method already checks client_id internally
    $email = CRM_Emailqueue_BAO_Queue::getEmailPreview($emailId);

    return civicrm_api3_create_success($email);
  }
  catch (Exception $e) {
    throw new API_Exception('Failed to preview email: ' . $e->getMessage());
  }
}

/**
 * Emailqueue.Preview API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_preview_spec(&$spec) {
  $spec['id'] = [
    'title' => 'Email ID',
    'description' => 'ID of the email to preview',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 1,
  ];
}

/**
 * Emailqueue.Getfilteroptions API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_getfilteroptions($params) {
  try {
    // Filter options are automatically client-specific via getCurrentClientId()
    $options = CRM_Emailqueue_BAO_Queue::getFilterOptions();
    return civicrm_api3_create_success($options);
  }
  catch (Exception $e) {
    throw new API_Exception('Failed to get filter options: ' . $e->getMessage());
  }
}

/**
 * Emailqueue.Export API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_export($params) {
  try {
    // Add client_id context if not provided and user doesn't have admin access
    if (empty($params['client_id']) && !CRM_Emailqueue_Config::hasAdminClientAccess()) {
      $params['client_id'] = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    }

    $csvData = CRM_Emailqueue_BAO_Queue::exportEmails($params);
    return civicrm_api3_create_success(['csv_data' => $csvData]);
  }
  catch (Exception $e) {
    throw new API_Exception('Failed to export emails: ' . $e->getMessage());
  }
}

/**
 * Emailqueue.Export API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_export_spec(&$spec) {
  // Same as search spec, but without pagination
  _civicrm_api3_emailqueue_search_spec($spec);
  unset($spec['limit'], $spec['offset']);
}

/**
 * Emailqueue.Bulkaction API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_bulkaction($params) {
  try {
    $action = $params['action'];
    $emailIds = $params['email_ids'];

    if (is_string($emailIds)) {
      $emailIds = explode(',', $emailIds);
    }
    $emailIds = array_map('intval', $emailIds);

    // Bulk actions are automatically client-specific via getCurrentClientId() in BAO
    $affectedRows = CRM_Emailqueue_BAO_Queue::bulkAction($action, $emailIds);

    return civicrm_api3_create_success([
      'message' => "Bulk action '{$action}' completed",
      'affected_rows' => $affectedRows,
      'client_id' => CRM_Emailqueue_BAO_Queue::getCurrentClientId()
    ]);
  }
  catch (Exception $e) {
    throw new API_Exception('Failed to perform bulk action: ' . $e->getMessage());
  }
}

/**
 * Emailqueue.Bulkaction API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_bulkaction_spec(&$spec) {
  $spec['action'] = [
    'title' => 'Action',
    'description' => 'Bulk action to perform',
    'api.required' => 1,
    'options' => ['cancel', 'retry', 'delete'],
  ];
  $spec['email_ids'] = [
    'title' => 'Email IDs',
    'description' => 'Array or comma-separated list of email IDs',
    'api.required' => 1,
  ];
}

/**
 * Emailqueue.Getclientstats API for admin users
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_getclientstats($params) {
  try {
    // Check admin access
    if (!CRM_Emailqueue_Config::hasAdminClientAccess()) {
      throw new API_Exception('Admin client access is required to view client statistics');
    }

    $clientStats = CRM_Emailqueue_BAO_Queue::getClientStats();
    return civicrm_api3_create_success($clientStats);
  }
  catch (Exception $e) {
    throw new API_Exception('Failed to get client statistics: ' . $e->getMessage());
  }
}

/**
 * Emailqueue.Switchclient API for admin users to switch context
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_switchclient($params) {
  try {
    // Check admin access
    if (!CRM_Emailqueue_Config::hasAdminClientAccess()) {
      throw new API_Exception('Admin client access is required to switch client context');
    }

    $clientId = $params['client_id'];
    CRM_Emailqueue_BAO_Queue::switchClientContext($clientId);

    return civicrm_api3_create_success([
      'message' => "Switched to client context: {$clientId}",
      'previous_client_id' => CRM_Emailqueue_Config::getCurrentClientId(),
      'new_client_id' => $clientId
    ]);
  }
  catch (Exception $e) {
    throw new API_Exception('Failed to switch client context: ' . $e->getMessage());
  }
}

/**
 * Emailqueue.Switchclient API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_switchclient_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Client ID to switch to',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
  ];
}

/**
 * Emailqueue.Resetclientcontext API to reset to default client
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_resetclientcontext($params) {
  try {
    $previousClientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    CRM_Emailqueue_BAO_Queue::resetClientContext();
    $newClientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();

    return civicrm_api3_create_success([
      'message' => "Reset client context",
      'previous_client_id' => $previousClientId,
      'current_client_id' => $newClientId
    ]);
  }
  catch (Exception $e) {
    throw new API_Exception('Failed to reset client context: ' . $e->getMessage());
  }
}

/**
 * Emailqueue.Getclientinfo API to get current client information
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_getclientinfo($params) {
  try {
    $clientInfo = CRM_Emailqueue_Config::getClientInfo();

    // Add current client statistics
    $clientInfo['current_stats'] = CRM_Emailqueue_BAO_Queue::getQueueStats();

    // Add multi-client mode information
    $clientInfo['multi_client_enabled'] = CRM_Emailqueue_Config::isMultiClientMode();
    $clientInfo['admin_access'] = CRM_Emailqueue_Config::hasAdminClientAccess();

    // If admin access is enabled, add client list
    if ($clientInfo['admin_access']) {
      try {
        $clientInfo['available_clients'] = CRM_Emailqueue_Config::getClientList();
      }
      catch (Exception $e) {
        // Ignore errors when getting client list
        $clientInfo['available_clients'] = [];
      }
    }

    return civicrm_api3_create_success($clientInfo);
  }
  catch (Exception $e) {
    throw new API_Exception('Failed to get client information: ' . $e->getMessage());
  }
}

/**
 * Emailqueue.Validateclientid API to validate a client ID
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_validateclientid($params) {
  try {
    $clientId = $params['client_id'];

    // Validate format
    $isValid = preg_match('/^[a-zA-Z0-9_-]+$/', $clientId);

    $result = [
      'client_id' => $clientId,
      'is_valid' => $isValid,
      'format_valid' => $isValid,
    ];

    if ($isValid) {
      // Check if client exists in database
      try {
        $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_queue WHERE client_id = ? LIMIT 1");
        $stmt->execute([$clientId]);
        $exists = $stmt->fetchColumn() > 0;

        $result['exists_in_database'] = $exists;
        $result['message'] = $exists ? 'Client ID is valid and exists' : 'Client ID is valid but does not exist in database';
      }
      catch (Exception $e) {
        $result['exists_in_database'] = FALSE;
        $result['database_error'] = $e->getMessage();
        $result['message'] = 'Client ID format is valid but database check failed';
      }
    }
    else {
      $result['message'] = 'Client ID format is invalid. Only letters, numbers, underscores, and hyphens are allowed.';
    }

    return civicrm_api3_create_success($result);
  }
  catch (Exception $e) {
    throw new API_Exception('Failed to validate client ID: ' . $e->getMessage());
  }
}

/**
 * Emailqueue.Validateclientid API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_validateclientid_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Client ID to validate',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
  ];
}

/**
 * Emailqueue.Addbatch API for batch adding multiple emails
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_addbatch($params) {
  try {
    $emails = $params['emails'] ?? [];

    if (empty($emails) || !is_array($emails)) {
      throw new API_Exception('Emails parameter must be a non-empty array');
    }

    // Determine client ID
    $clientId = NULL;
    if (!empty($params['client_id'])) {
      // Client ID specified in parameters (admin only)
      if (!CRM_Emailqueue_Config::hasAdminClientAccess()) {
        throw new API_Exception('Admin access required to specify client_id');
      }
      $clientId = $params['client_id'];
    }
    else {
      // Use current client context
      $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    }

    // Temporarily switch client context if needed
    $originalClientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    if ($clientId !== $originalClientId) {
      CRM_Emailqueue_BAO_Queue::switchClientContext($clientId);
    }

    $results = [];
    $successCount = 0;
    $errorCount = 0;

    foreach ($emails as $index => $emailParams) {
      try {
        // Add client_id to each email's parameters
        $emailParams['client_id'] = $clientId;

        // Call individual add API
        $result = civicrm_api3('Emailqueue', 'addtoqueue', $emailParams);
        $results[$index] = [
          'success' => TRUE,
          'id' => $result['id'],
          'message' => 'Email queued successfully'
        ];
        $successCount++;
      }
      catch (Exception $e) {
        $results[$index] = [
          'success' => FALSE,
          'error' => $e->getMessage()
        ];
        $errorCount++;
      }
    }

    // Restore original client context
    if ($clientId !== $originalClientId) {
      CRM_Emailqueue_BAO_Queue::switchClientContext($originalClientId);
    }

    return civicrm_api3_create_success([
      'client_id' => $clientId,
      'total_emails' => count($emails),
      'success_count' => $successCount,
      'error_count' => $errorCount,
      'results' => $results,
      'message' => "Batch processing completed: {$successCount} successful, {$errorCount} errors"
    ]);

  }
  catch (Exception $e) {
    // Ensure client context is restored even on error
    if (isset($originalClientId) && $originalClientId !== CRM_Emailqueue_BAO_Queue::getCurrentClientId()) {
      try {
        CRM_Emailqueue_BAO_Queue::switchClientContext($originalClientId);
      }
      catch (Exception $restoreError) {
        CRM_Emailqueue_Utils_ErrorHandler::warning('Failed to restore client context: ' . $restoreError->getMessage());
      }
    }

    throw new API_Exception('Failed to process email batch: ' . $e->getMessage());
  }
}

/**
 * Emailqueue.Addbatch API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_addbatch_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Target client ID (admin only)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['emails'] = [
    'title' => 'Emails Array',
    'description' => 'Array of email objects to queue',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
  ];
}

/**
 * Emailqueue.Addwithtemplate API for adding emails using templates
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_addwithtemplate($params) {
  try {
    $templateId = $params['template_id'] ?? NULL;
    $contactId = $params['contact_id'] ?? NULL;
    $recipients = $params['recipients'] ?? [];

    if (empty($templateId)) {
      throw new API_Exception('Template ID is required');
    }

    // Get message template
    $template = civicrm_api3('MessageTemplate', 'getsingle', ['id' => $templateId]);

    $baseEmailData = [
      'subject' => $template['msg_subject'],
      'body_html' => $template['msg_html'],
      'body_text' => $template['msg_text'],
      'from_email' => $params['from_email'] ?? '',
      'priority' => $params['priority'] ?? 3,
    ];

    // Determine client ID
    $clientId = NULL;
    if (!empty($params['client_id'])) {
      if (!CRM_Emailqueue_Config::hasAdminClientAccess()) {
        throw new API_Exception('Admin access required to specify client_id');
      }
      $clientId = $params['client_id'];
    }
    else {
      $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    }

    $results = [];
    $successCount = 0;

    // If contact_id is provided, use it as single recipient
    if ($contactId) {
      $contact = civicrm_api3('Contact', 'getsingle', ['id' => $contactId]);
      $recipients = [$contact['email']];
    }

    foreach ($recipients as $recipient) {
      try {
        // Process template tokens if contact data is available
        $processedEmailData = $baseEmailData;
        if ($contactId) {
          // Token replacement would go here
          // This is a simplified version - full token processing would be more complex
        }

        $processedEmailData['to_email'] = $recipient;
        $processedEmailData['client_id'] = $clientId;

        $result = civicrm_api3('Emailqueue', 'addtoqueue', $processedEmailData);
        $results[] = [
          'success' => TRUE,
          'id' => $result['id'],
          'recipient' => $recipient
        ];
        $successCount++;
      }
      catch (Exception $e) {
        $results[] = [
          'success' => FALSE,
          'recipient' => $recipient,
          'error' => $e->getMessage()
        ];
      }
    }

    return civicrm_api3_create_success([
      'client_id' => $clientId,
      'template_id' => $templateId,
      'total_recipients' => count($recipients),
      'success_count' => $successCount,
      'results' => $results,
      'message' => "Template-based emails queued: {$successCount} successful"
    ]);

  }
  catch (Exception $e) {
    throw new API_Exception('Failed to add emails with template: ' . $e->getMessage());
  }
}

/**
 * Emailqueue.Addwithtemplate API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_addwithtemplate_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Target client ID (admin only)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['template_id'] = [
    'title' => 'Message Template ID',
    'description' => 'CiviCRM Message Template ID to use',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 1,
  ];
  $spec['contact_id'] = [
    'title' => 'Contact ID',
    'description' => 'Contact ID for token replacement (optional)',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
  ];
  $spec['recipients'] = [
    'title' => 'Recipients',
    'description' => 'Array of email addresses',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['from_email'] = [
    'title' => 'From Email',
    'description' => 'Sender email address',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['priority'] = [
    'title' => 'Priority',
    'description' => 'Email priority (1=highest, 5=lowest)',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'api.default' => 3,
  ];
}
