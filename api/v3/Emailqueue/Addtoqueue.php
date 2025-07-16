<?php
use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Emailqueue.Addtoqueue API
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_addtoqueue($params) {
  try {
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

    $queueId = CRM_Emailqueue_BAO_Queue::addToQueue($emailData);

    return civicrm_api3_create_success(['id' => $queueId, 'message' => 'Email added to queue']);
  }
  catch (Exception $e) {
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
    'description' => 'Email priority (1-5, 1 being highest)',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'api.default' => 3,
  ];
}
