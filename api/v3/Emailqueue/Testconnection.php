<?php

/**
 * Emailqueue.Testconnection API
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_testconnection($params) {
  try {
    // Temporarily override settings for testing
    $originalSettings = [];
    $testSettings = [
      'emailqueue_db_host' => CRM_Utils_Array::value('host', $params),
      'emailqueue_db_name' => CRM_Utils_Array::value('name', $params),
      'emailqueue_db_user' => CRM_Utils_Array::value('user', $params),
      'emailqueue_db_pass' => CRM_Utils_Array::value('pass', $params),
    ];

    foreach ($testSettings as $key => $value) {
      $originalSettings[$key] = Civi::settings()->get($key);
      Civi::settings()->set($key, $value);
    }

    // Test connection
    $connection = CRM_Emailqueue_BAO_Queue::getQueueConnection();

    // Test table creation
    CRM_Emailqueue_BAO_Queue::createTables();

    // Restore original settings
    foreach ($originalSettings as $key => $value) {
      Civi::settings()->set($key, $value);
    }

    return civicrm_api3_create_success([
      'success' => TRUE,
      'message' => 'Database connection successful and tables verified'
    ]);

  } catch (Exception $e) {
    // Restore original settings on error
    if (isset($originalSettings)) {
      foreach ($originalSettings as $key => $value) {
        Civi::settings()->set($key, $value);
      }
    }

    return civicrm_api3_create_success([
      'success' => FALSE,
      'message' => $e->getMessage()
    ]);
  }
}

/**
 * Emailqueue.Testconnection API specification
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_emailqueue_testconnection_spec(&$spec) {
  $spec['host'] = [
    'title' => 'Database Host',
    'description' => 'Database host to test',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
  ];
  $spec['name'] = [
    'title' => 'Database Name',
    'description' => 'Database name to test',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
  ];
  $spec['user'] = [
    'title' => 'Database User',
    'description' => 'Database username to test',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
  ];
  $spec['pass'] = [
    'title' => 'Database Password',
    'description' => 'Database password to test',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
}
