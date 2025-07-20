<?php
use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Emailqueue.Addtoqueue API with client_id support
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_addtoqueue($params) {
  try {
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

    $emailData = [
      'to_email' => $params['to_email'],
      'subject' => CRM_Utils_Array::value('subject', $params, ''),
      'from_email' => CRM_Utils_Array::value('from_email', $params, ''),
      'reply_to' => CRM_Utils_Array::value('reply_to', $params, ''),
      'cc' => CRM_Utils_Array::value('cc', $params, ''),
      'bcc' => CRM_Utils_Array::value('bcc', $params, ''),
      'body_html' => CRM_Utils_Array::value('body_html', $params, ''),
      'body_text' => CRM_Utils_Array::value('body_text', $params, ''),
      'headers' => json_encode(CRM_Utils_Array::value('headers', $params, [])),
      'created_date' => date('Y-m-d H:i:s'),
      'status' => 'pending',
      'priority' => CRM_Utils_Array::value('priority', $params, 3),
      'retry_count' => 0
    ];

    // Detect priority if not explicitly set
    if (!isset($params['priority']) || $params['priority'] === 3) {
      $detectedPriority = CRM_Emailqueue_Utils_PriorityDetector::detectPriority($emailData);
      if ($detectedPriority !== NULL) {
        $emailData['priority'] = $detectedPriority;
      }
    }

    // Add tracking information if enabled
    if (CRM_Emailqueue_Config::isTrackingEnabled()) {
      $trackingInfo = CRM_Emailqueue_Utils_Email::generateTrackingInfo(NULL, $emailData['to_email']);
      $emailData['tracking_code'] = $trackingInfo['tracking_code'];
    }

    // Validate email if validation is enabled
    if (CRM_Emailqueue_Config::isValidationEnabled()) {
      $validation = CRM_Emailqueue_Utils_Email::validateEmail($emailData['to_email']);
      $emailData['validation_score'] = $validation['is_valid'] ? 100 : 0;

      if (!empty($validation['warnings'])) {
        $emailData['validation_warnings'] = json_encode($validation['warnings']);
      }

      // Optionally reject invalid emails based on configuration
      if (!$validation['is_valid'] && CRM_Emailqueue_Config::getSetting('validation_strict', FALSE)) {
        throw new API_Exception('Email validation failed: ' . implode(', ', $validation['errors']));
      }
    }

    $queueId = CRM_Emailqueue_BAO_Queue::addToQueue($emailData);

    // Restore original client context
    if ($clientId !== $originalClientId) {
      CRM_Emailqueue_BAO_Queue::switchClientContext($originalClientId);
    }

    $result = [
      'id' => $queueId,
      'client_id' => $clientId,
      'message' => 'Email added to queue',
      'priority' => $emailData['priority'],
      'validation_score' => $emailData['validation_score'] ?? NULL
    ];

    // Add tracking information to result if available
    if (!empty($emailData['tracking_code'])) {
      $result['tracking_code'] = $emailData['tracking_code'];
    }

    return civicrm_api3_create_success($result);
  }
  catch (Exception $e) {
    // Ensure client context is restored even on error
    if (isset($originalClientId) && $originalClientId !== CRM_Emailqueue_BAO_Queue::getCurrentClientId()) {
      try {
        CRM_Emailqueue_BAO_Queue::switchClientContext($originalClientId);
      }
      catch (Exception $restoreError) {
        // Log but don't fail on restore error
        CRM_Emailqueue_Utils_ErrorHandler::warning('Failed to restore client context: ' . $restoreError->getMessage());
      }
    }

    throw new API_Exception('Failed to add email to queue: ' . $e->getMessage());
  }
}

/**
 * Emailqueue.Addtoqueue API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_addtoqueue_spec(&$spec) {
  $spec['client_id'] = [
    'title' => 'Client ID',
    'description' => 'Target client ID (admin only)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['to_email'] = [
    'title' => 'To Email',
    'description' => 'Recipient email address',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
  ];
  $spec['subject'] = [
    'title' => 'Subject',
    'description' => 'Email subject',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['from_email'] = [
    'title' => 'From Email',
    'description' => 'Sender email address',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['reply_to'] = [
    'title' => 'Reply To',
    'description' => 'Reply-to email address',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['cc'] = [
    'title' => 'CC',
    'description' => 'CC email addresses (comma-separated)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['bcc'] = [
    'title' => 'BCC',
    'description' => 'BCC email addresses (comma-separated)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['body_html'] = [
    'title' => 'HTML Body',
    'description' => 'HTML email body',
    'type' => CRM_Utils_Type::T_LONGTEXT,
    'api.required' => 0,
  ];
  $spec['body_text'] = [
    'title' => 'Text Body',
    'description' => 'Plain text email body',
    'type' => CRM_Utils_Type::T_LONGTEXT,
    'api.required' => 0,
  ];
  $spec['priority'] = [
    'title' => 'Priority',
    'description' => 'Email priority (1=highest, 5=lowest)',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'api.default' => 3,
    'options' => [
      1 => 'Highest',
      2 => 'High',
      3 => 'Normal',
      4 => 'Low',
      5 => 'Lowest'
    ],
  ];
  $spec['headers'] = [
    'title' => 'Custom Headers',
    'description' => 'Additional email headers as associative array',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
  $spec['validate_email'] = [
    'title' => 'Validate Email',
    'description' => 'Whether to validate email address before queuing',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'api.default' => TRUE,
  ];
  $spec['enable_tracking'] = [
    'title' => 'Enable Tracking',
    'description' => 'Whether to enable email tracking',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'api.default' => TRUE,
  ];
}
