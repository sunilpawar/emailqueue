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
}

/**
 * Process the email queue - Main API function
 *
 * This API processes queued emails that are waiting to be sent. It's typically
 * called by cron jobs or scheduled tasks to handle bulk email delivery.
 *
 * The function prevents infinite loops by setting a global flag to skip
 * the mail alter hook, then delegates the actual processing to the BAO class.
 *
 * @param array $params
 *   API parameters.
 * @return array
 *   API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_emailqueue_processqueue($params) {
  try {
    /**
     * Prevent recursive email processing
     *
     * The $skipAlterMailerHook global variable prevents the extension's
     * hook_civicrm_alterMailer from being triggered during queue processing.
     * This is crucial because:
     * 1. It prevents emails from being re-queued while processing the queue
     * 2. Avoids infinite loops during bulk email operations
     * 3. Ensures direct email delivery during queue processing
     */
    global $skipAlterMailerHook;
    $skipAlterMailerHook = TRUE;

    /**
     * Delegate queue processing to Business Access Object (BAO)
     *
     * The actual queue processing logic is handled by the BAO class.
     */
    CRM_Emailqueue_BAO_Queue::processQueue();
    return civicrm_api3_create_success(['message' => 'Queue processed successfully']);
  } catch (Exception $e) {
    throw new API_Exception('Failed to process queue: ' . $e->getMessage());
  }
}
