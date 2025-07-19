<?php

use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Enhanced page for monitoring email queue with search and preview functionality.
 */
class CRM_Emailqueue_Page_Monitoradv extends CRM_Core_Page {

  public function getEmailById($id) {
    $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
    try {
      $stmt = $pdo->prepare("
                SELECT *
                FROM email_queue
                WHERE id = :id
            ");

      $stmt->execute(['id' => $id]);
      $email = $stmt->fetch();

      if ($email) {
        // Decode JSON fields
        $email['headers'] = json_decode($email['headers'] ?? '[]', TRUE);
        return $email;
      }

      return NULL;
    }
    catch (PDOException $e) {
      error_log("Database error in getEmailById: " . $e->getMessage());
      throw new Exception("Failed to retrieve email from database");
    }
  }
  public function preProcess() {
    // Set page title
    $this->setTitle(E::ts('Email Queue Monitoring - Advanced'));

    // Add CSS and JS resources
    CRM_Core_Resources::singleton()
      ->addStyleFile(E::LONG_NAME, 'css/report_adv.css')
      ->addScriptFile(E::LONG_NAME, 'js/monitor_adv.js');
  }

  public function run() {
    // Check if email queue is enabled
    $isEnabled = Civi::settings()->get('emailqueue_enabled');

    if (!$isEnabled) {
      CRM_Core_Session::setStatus(E::ts('Email Queue System is not enabled. Please enable it in the settings.'), E::ts('System Disabled'), 'warning');
    }
    // Add custom dashboard JavaScript
    try {
      // Get search parameters
      $searchParams = $this->getSearchParams();
      //echo '<pre>$searchParams-'; print_r($searchParams); exit;
      // Get queue statistics
      $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();
      $this->assign('queueStats', $stats);

      // Search emails
      $searchResult = CRM_Emailqueue_BAO_Queue::searchEmails($searchParams);
      $this->assign('emails', $searchResult['emails']);
      $this->assign('pagination', [
        'total_count' => $searchResult['total_count'],
        'current_page' => $searchResult['current_page'],
        'total_pages' => $searchResult['total_pages'],
        'limit' => $searchResult['limit']
      ]);

      // Get filter options
      $filterOptions = CRM_Emailqueue_BAO_Queue::getFilterOptions();
      $this->assign('filterOptions', $filterOptions);

      // Assign search params for form defaults
      $this->assign('searchParams', $searchParams);

    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus(E::ts('Error connecting to email queue database: %1', [1 => $e->getMessage()]), E::ts('Database Error'), 'error');
      $this->assign('queueStats', []);
      $this->assign('emails', []);
      $this->assign('filterOptions', []);
      $this->assign('searchParams', []);

    }

    $this->assign('isEnabled', $isEnabled);

    parent::run();
  }

  /**
   * Get search parameters from request.
   */
  protected function getSearchParams() {
    $params = [];

    // Text filters
    $textFilters = ['to_email', 'from_email', 'subject'];
    foreach ($textFilters as $filter) {
      $value = CRM_Utils_Request::retrieve($filter, 'String', NULL, FALSE, '');
      if (!empty($value)) {
        $params[$filter] = $value;
      }
    }

    // Status filter (can be multiple)
    $status = CRM_Utils_Request::retrieve('status', 'String',NULL, FALSE, '');
    if (!empty($status)) {
      $params['status'] = explode(',', $status);
    }

    // Priority filter
    $priority = CRM_Utils_Request::retrieve('priority', 'Integer', NULL, FALSE, '');
    if ($priority !== NULL) {
      $params['priority'] = $priority;
    }

    // Date filters
    $dateFilters = ['date_from', 'date_to', 'sent_from', 'sent_to'];
    foreach ($dateFilters as $filter) {
      $value = CRM_Utils_Request::retrieve($filter, 'String', NULL, FALSE, '');
      if (!empty($value)) {
        $params[$filter] = $value;
      }
    }

    // Error filter
    $hasError = CRM_Utils_Request::retrieve('has_error', 'String', NULL, FALSE, '');
    if (!empty($hasError)) {
      $params['has_error'] = $hasError;
    }

    // Retry filters
    $minRetries = CRM_Utils_Request::retrieve('min_retries', 'Integer', NULL, FALSE, '');
    if ($minRetries !== NULL) {
      $params['min_retries'] = $minRetries;
    }

    $maxRetries = CRM_Utils_Request::retrieve('max_retries', 'Integer', NULL, FALSE, '');
    if ($maxRetries !== NULL) {
      $params['max_retries'] = $maxRetries;
    }

    // Pagination
    $page = CRM_Utils_Request::retrieve('page', 'Integer', NULL, FALSE, '1');
    $limit = CRM_Utils_Request::retrieve('limit', 'Integer', NULL, FALSE, 50);
    $params['offset'] = ($page - 1) * $limit;
    $params['limit'] = $limit;

    // Sorting
    $orderBy = CRM_Utils_Request::retrieve('order_by', 'String', NULL, FALSE, 'created_date');
    $orderDir = CRM_Utils_Request::retrieve('order_dir', 'String', NULL, FALSE, 'DESC');
    $params['order_by'] = $orderBy;
    $params['order_dir'] = $orderDir;

    return $params;
  }

  /**
   * Handle AJAX actions.
   */
  public static function ajaxAction() {
    $action = CRM_Utils_Request::retrieve('action', 'String');

    switch ($action) {
      case 'preview':
        self::previewEmail();
        break;

      case 'search':
        self::ajaxSearch();
        break;

      case 'export':
        self::exportEmails();
        break;

      case 'bulk_action':
        self::bulkAction();
        break;

      case 'retry_failed':
        self::retryFailedEmails();
        break;

      case 'process_queue':
        self::processQueueNow();
        break;

      case 'cancel_email':
        $emailId = CRM_Utils_Request::retrieve('email_id', 'Integer');
        self::cancelEmail($emailId);
        break;

      default:
        CRM_Utils_JSON::output(['success' => FALSE, 'message' => 'Invalid action']);
    }
  }

  /**
   * Preview email content.
   */
  protected static function previewEmail() {
    try {
      $emailId = CRM_Utils_Request::retrieve('email_id', 'Integer');

      if (!$emailId) {
        throw new Exception('Email ID is required');
      }

      $email = CRM_Emailqueue_BAO_Queue::getEmailPreview($emailId);

      // Format for output
      $preview = [
        'id' => $email['id'],
        'to_email' => $email['to_email'],
        'from_email' => $email['from_email'],
        'reply_to' => $email['reply_to'],
        'cc' => $email['cc'],
        'bcc' => $email['bcc'],
        'subject' => $email['subject'],
        'body_html' => $email['body_html'],
        'body_text' => $email['body_text'],
        'status' => $email['status'],
        'priority' => $email['priority'],
        'created_date' => $email['created_date'],
        'sent_date' => $email['sent_date'],
        'retry_count' => $email['retry_count'],
        'max_retries' => $email['max_retries'],
        'error_message' => $email['error_message'],
        'headers' => $email['parsed_headers'],
        'logs' => $email['logs']
      ];

      CRM_Utils_JSON::output(['success' => TRUE, 'email' => $preview]);

    }
    catch (Exception $e) {
      CRM_Utils_JSON::output(['success' => FALSE, 'message' => $e->getMessage()]);
    }
  }

  /**
   * AJAX search emails.
   */
  protected static function ajaxSearch() {
    try {
      // Get search parameters from POST data
      $params = [];
      $searchFields = [
        'to_email', 'from_email', 'subject', 'status', 'priority',
        'date_from', 'date_to', 'sent_from', 'sent_to', 'has_error',
        'min_retries', 'max_retries', 'limit', 'offset', 'order_by', 'order_dir'
      ];

      foreach ($searchFields as $field) {
        $value = CRM_Utils_Request::retrieve($field, 'String');
        if (!empty($value)) {
          if ($field === 'status' && strpos($value, ',') !== FALSE) {
            $params[$field] = explode(',', $value);
          }
          elseif (in_array($field, ['priority', 'min_retries', 'max_retries', 'limit', 'offset'])) {
            $params[$field] = (int)$value;
          }
          else {
            $params[$field] = $value;
          }
        }
      }

      $searchResult = CRM_Emailqueue_BAO_Queue::searchEmails($params);

      CRM_Utils_JSON::output(['success' => TRUE, 'data' => $searchResult]);

    }
    catch (Exception $e) {
      CRM_Utils_JSON::output(['success' => FALSE, 'message' => $e->getMessage()]);
    }
  }

  /**
   * Export emails to CSV.
   */
  protected static function exportEmails() {
    try {
      // Get search parameters
      $params = [];
      $searchFields = [
        'to_email', 'from_email', 'subject', 'status', 'priority',
        'date_from', 'date_to', 'sent_from', 'sent_to', 'has_error',
        'min_retries', 'max_retries'
      ];

      foreach ($searchFields as $field) {
        $value = CRM_Utils_Request::retrieve($field, 'String');
        if (!empty($value)) {
          if ($field === 'status' && strpos($value, ',') !== FALSE) {
            $params[$field] = explode(',', $value);
          }
          elseif (in_array($field, ['priority', 'min_retries', 'max_retries'])) {
            $params[$field] = (int)$value;
          }
          else {
            $params[$field] = $value;
          }
        }
      }

      $csvData = CRM_Emailqueue_BAO_Queue::exportEmails($params);

      // Set headers for CSV download
      header('Content-Type: text/csv');
      header('Content-Disposition: attachment; filename="email_queue_export_' . date('Y-m-d_H-i-s') . '.csv"');

      // Output CSV
      $output = fopen('php://output', 'w');
      foreach ($csvData as $row) {
        fputcsv($output, $row);
      }
      fclose($output);
      exit;

    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus(E::ts('Export failed: %1', [1 => $e->getMessage()]), E::ts('Export Error'), 'error');
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/emailqueue/monitoradv'));
    }
  }

  /**
   * Handle bulk actions.
   */
  protected static function bulkAction() {
    try {
      $action = CRM_Utils_Request::retrieve('bulk_action', 'String');
      $emailIds = CRM_Utils_Request::retrieve('email_ids', 'String');

      if (empty($action) || empty($emailIds)) {
        throw new Exception('Action and email IDs are required');
      }

      $emailIds = explode(',', $emailIds);
      $emailIds = array_map('intval', $emailIds);

      $affectedRows = CRM_Emailqueue_BAO_Queue::bulkAction($action, $emailIds);

      $message = '';
      switch ($action) {
        case 'cancel':
          $message = E::ts('%1 emails cancelled', [1 => $affectedRows]);
          break;
        case 'retry':
          $message = E::ts('%1 failed emails queued for retry', [1 => $affectedRows]);
          break;
        case 'delete':
          $message = E::ts('%1 emails deleted', [1 => $affectedRows]);
          break;
      }

      CRM_Utils_JSON::output(['success' => TRUE, 'message' => $message, 'affected_rows' => $affectedRows]);

    }
    catch (Exception $e) {
      CRM_Utils_JSON::output(['success' => FALSE, 'message' => $e->getMessage()]);
    }
  }

  /**
   * Retry failed emails.
   */
  protected static function retryFailedEmails() {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $sql = "UPDATE email_queue SET status = 'pending', retry_count = 0, error_message = NULL WHERE status = 'failed'";
      $stmt = $pdo->prepare($sql);
      $count = $stmt->execute();

      CRM_Utils_JSON::output(['success' => TRUE, 'message' => E::ts('Failed emails have been queued for retry')]);

    }
    catch (Exception $e) {
      CRM_Utils_JSON::output(['success' => FALSE, 'message' => $e->getMessage()]);
    }
  }

  /**
   * Process queue immediately.
   */
  protected static function processQueueNow() {
    try {
      CRM_Emailqueue_BAO_Queue::processQueue();
      CRM_Utils_JSON::output(['success' => TRUE, 'message' => E::ts('Queue processed successfully')]);

    }
    catch (Exception $e) {
      CRM_Utils_JSON::output(['success' => FALSE, 'message' => $e->getMessage()]);
    }
  }

  /**
   * Cancel specific email.
   */
  protected static function cancelEmail($emailId) {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $sql = "UPDATE email_queue SET status = 'cancelled' WHERE id = ? AND status IN ('pending', 'failed')";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$emailId]);

      if ($stmt->rowCount() > 0) {
        CRM_Utils_JSON::output(['success' => TRUE, 'message' => E::ts('Email cancelled successfully')]);
      }
      else {
        CRM_Utils_JSON::output(['success' => FALSE, 'message' => E::ts('Email cannot be cancelled or does not exist')]);
      }

    }
    catch (Exception $e) {
      CRM_Utils_JSON::output(['success' => FALSE, 'message' => $e->getMessage()]);
    }
  }
}
