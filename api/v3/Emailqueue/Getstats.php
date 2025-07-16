<?php
use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Emailqueue.Getstats API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_emailqueue_Getstats_spec(&$spec) {
  // $spec['magicword']['api.required'] = 1;
}

/**
 * Emailqueue.Getstats API
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_getstats($params) {
  try {
    $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();
    return civicrm_api3_create_success($stats);
  } catch (Exception $e) {
    throw new API_Exception('Failed to get queue statistics: ' . $e->getMessage());
  }
}
