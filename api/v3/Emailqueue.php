<?php

/**
 * Emailqueue.Search API
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_search($params) {
  try {
    $searchResult = CRM_Emailqueue_BAO_Queue::searchEmails($params);
    return civicrm_api3_create_success($searchResult);
  } catch (Exception $e) {
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
 * Emailqueue.Preview API
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
    $email = CRM_Emailqueue_BAO_Queue::getEmailPreview($emailId);
    return civicrm_api3_create_success($email);
  } catch (Exception $e) {
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
 * Emailqueue.Getfilteroptions API
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_getfilteroptions($params) {
  try {
    $options = CRM_Emailqueue_BAO_Queue::getFilterOptions();
    return civicrm_api3_create_success($options);
  } catch (Exception $e) {
    throw new API_Exception('Failed to get filter options: ' . $e->getMessage());
  }
}

/**
 * Emailqueue.Export API
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_export($params) {
  try {
    $csvData = CRM_Emailqueue_BAO_Queue::exportEmails($params);
    return civicrm_api3_create_success(['csv_data' => $csvData]);
  } catch (Exception $e) {
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
 * Emailqueue.Bulkaction API
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

    $affectedRows = CRM_Emailqueue_BAO_Queue::bulkAction($action, $emailIds);

    return civicrm_api3_create_success([
      'message' => "Bulk action '{$action}' completed",
      'affected_rows' => $affectedRows
    ]);
  } catch (Exception $e) {
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
   // 'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'options' => ['cancel', 'retry', 'delete'],
  ];
  $spec['email_ids'] = [
    'title' => 'Email IDs',
    'description' => 'Array or comma-separated list of email IDs',
    'api.required' => 1,
  ];
}
