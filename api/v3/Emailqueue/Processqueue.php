<?php
use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Emailqueue.Processqueue API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_emailqueue_Processqueue_spec(&$spec) {
  //$spec['magicword']['api.required'] = 1;
}

/**
 * Emailqueue.Processqueue API
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_processqueue($params) {
  try {
    CRM_Emailqueue_BAO_Queue::processQueue();
    return civicrm_api3_create_success(['message' => 'Queue processed successfully']);
  } catch (Exception $e) {
    throw new API_Exception('Failed to process queue: ' . $e->getMessage());
  }
}
