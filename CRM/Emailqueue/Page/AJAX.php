<?php

use CRM_Emailqueue_ExtensionUtil as E;

/**
 * AJAX handler for email queue operations.
 */
class CRM_Emailqueue_Page_AJAX extends CRM_Core_Page {

  public function run() {
    $action = CRM_Utils_Request::retrieve('action', 'String');

    // Set JSON headers
    header('Content-Type: application/json');

    // Check permissions
    if (!CRM_Core_Permission::check('administer CiviCRM')) {
      $this->sendError('Permission denied');
      return;
    }

    try {
      switch ($action) {
        case 'preview':
          $this->handlePreview();
          break;

        case 'search':
          $this->handleSearch();
          break;

        case 'export':
          $this->handleExport();
          break;

        case 'bulk_action':
          $this->handleBulkAction();
          break;

        case 'process_queue':
          $this->handleProcessQueue();
          break;

        case 'retry_failed':
          $this->handleRetryFailed();
          break;

        case 'cancel_email':
          $this->handleCancelEmail();
          break;

        case 'get_stats':
          $this->handleGetStats();
          break;

        case 'validate_email':
          $this->handleValidateEmail();
          break;

        default:
          $this->sendError('Invalid action');
      }
    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue AJAX Error: ' . $e->getMessage());
      $this->sendError($e->getMessage());
    }
  }

  /**
   * Handle email preview request.
   */
  protected function handlePreview() {
    $emailId = CRM_Utils_Request::retrieve('email_id', 'Integer');

    if (!$emailId) {
      $this->sendError('Email ID is required');
      return;
    }

    $email = CRM_Emailqueue_BAO_Queue::getEmailPreview($emailId);

    // Sanitize content for display
    $email['body_html'] = $this->sanitizeHtmlContent($email['body_html']);
    $email['body_text'] = $this->sanitizeTextContent($email['body_text']);

    // Format dates
    $email['created_date'] = CRM_Utils_Date::customFormat($email['created_date']);
    $email['sent_date'] = $email['sent_date'] ? CRM_Utils_Date::customFormat($email['sent_date']) : NULL;

    // Format logs
    if (!empty($email['logs'])) {
      foreach ($email['logs'] as &$log) {
        $log['created_date'] = CRM_Utils_Date::customFormat($log['created_date']);
        $log['message'] = htmlspecialchars($log['message'], ENT_QUOTES, 'UTF-8');
      }
    }

    $this->sendSuccess(['email' => $email]);
  }

  /**
   * Handle search request.
   */
  protected function handleSearch() {
    $params = $this->getSearchParams();
    $searchResult = CRM_Emailqueue_BAO_Queue::searchEmails($params);

    // Format data for display
    foreach ($searchResult['emails'] as &$email) {
      $email['created_date'] = CRM_Utils_Date::customFormat($email['created_date']);
      $email['sent_date'] = $email['sent_date'] ? CRM_Utils_Date::customFormat($email['sent_date']) : NULL;
      $email['to_email'] = htmlspecialchars($email['to_email'], ENT_QUOTES, 'UTF-8');
      $email['subject'] = htmlspecialchars($email['subject'], ENT_QUOTES, 'UTF-8');
    }

    $this->sendSuccess($searchResult);
  }

  /**
   * Handle export request.
   */
  protected function handleExport() {
    $params = $this->getSearchParams();
    $csvData = CRM_Emailqueue_BAO_Queue::exportEmails($params);

    // Generate filename
    $filename = 'email_queue_export_' . date('Y-m-d_H-i-s') . '.csv';

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    // Output CSV
    $output = fopen('php://output', 'w');
    foreach ($csvData as $row) {
      fputcsv($output, $row);
    }
    fclose($output);
    exit;
  }

  /**
   * Handle bulk action request.
   */
  protected function handleBulkAction() {
    $action = CRM_Utils_Request::retrieve('bulk_action', 'String');
    $emailIds = CRM_Utils_Request::retrieve('email_ids', 'String');

    if (empty($action) || empty($emailIds)) {
      $this->sendError('Action and email IDs are required');
      return;
    }

    // Validate action
    $allowedActions = ['cancel', 'retry', 'delete'];
    if (!in_array($action, $allowedActions)) {
      $this->sendError('Invalid bulk action');
      return;
    }

    $emailIds = explode(',', $emailIds);
    $emailIds = array_map('intval', $emailIds);
    $emailIds = array_filter($emailIds); // Remove invalid IDs

    if (empty($emailIds)) {
      $this->sendError('No valid email IDs provided');
      return;
    }

    // Limit bulk operations to prevent timeout
    if (count($emailIds) > 1000) {
      $this->sendError('Too many emails selected. Maximum 1000 emails per bulk operation.');
      return;
    }

    $affectedRows = CRM_Emailqueue_BAO_Queue::bulkAction($action, $emailIds);

    $message = $this->getBulkActionMessage($action, $affectedRows);
    $this->sendSuccess(['message' => $message, 'affected_rows' => $affectedRows]);
  }

  /**
   * Handle process queue request.
   */
  protected function handleProcessQueue() {
    $batchSize = CRM_Utils_Request::retrieve('batch_size', 'Integer');

    if ($batchSize) {
      // Temporarily override batch size
      $originalBatchSize = Civi::settings()->get('emailqueue_batch_size');
      Civi::settings()->set('emailqueue_batch_size', $batchSize);
    }

    try {
      CRM_Emailqueue_BAO_Queue::processQueue();
      $message = E::ts('Queue processed successfully');

      // Get updated stats
      $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();

      $this->sendSuccess([
        'message' => $message,
        'stats' => $stats
      ]);

    }
    finally {
      if (isset($originalBatchSize)) {
        Civi::settings()->set('emailqueue_batch_size', $originalBatchSize);
      }
    }
  }

  /**
   * Handle retry failed emails request.
   */
  protected function handleRetryFailed() {
    $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

    $sql = "UPDATE email_queue SET status = 'pending', retry_count = 0, error_message = NULL, scheduled_date = NULL WHERE status = 'failed'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $affectedRows = $stmt->rowCount();

    $message = E::ts('%1 failed emails have been queued for retry', [1 => $affectedRows]);

    // Get updated stats
    $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();

    $this->sendSuccess([
      'message' => $message,
      'affected_rows' => $affectedRows,
      'stats' => $stats
    ]);
  }

  /**
   * Handle cancel email request.
   */
  protected function handleCancelEmail() {
    $emailId = CRM_Utils_Request::retrieve('email_id', 'Integer');

    if (!$emailId) {
      $this->sendError('Email ID is required');
      return;
    }

    $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

    $sql = "UPDATE email_queue SET status = 'cancelled' WHERE id = ? AND status IN ('pending', 'failed')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$emailId]);

    if ($stmt->rowCount() > 0) {
      // Log the action
      CRM_Emailqueue_BAO_Queue::logAction($emailId, 'cancelled', 'Email cancelled via AJAX request');

      $this->sendSuccess(['message' => E::ts('Email cancelled successfully')]);
    }
    else {
      $this->sendError('Email cannot be cancelled or does not exist');
    }
  }

  /**
   * Handle get stats request.
   */
  protected function handleGetStats() {
    $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();
    $this->sendSuccess(['stats' => $stats]);
  }

  /**
   * Handle email validation request.
   */
  protected function handleValidateEmail() {
    $email = CRM_Utils_Request::retrieve('email', 'String');

    if (!$email) {
      $this->sendError('Email address is required');
      return;
    }

    $isValid = CRM_Utils_Rule::email($email);
    $message = $isValid ? 'Email address is valid' : 'Invalid email address format';

    $this->sendSuccess([
      'is_valid' => $isValid,
      'message' => $message,
      'email' => $email
    ]);
  }

  /**
   * Get search parameters from request.
   */
  protected function getSearchParams() {
    $params = [];

    // Text filters
    $textFilters = ['to_email', 'from_email', 'subject'];
    foreach ($textFilters as $filter) {
      $value = CRM_Utils_Request::retrieve($filter, 'String');
      if (!empty($value)) {
        $params[$filter] = trim($value);
      }
    }

    // Status filter (can be multiple)
    $status = CRM_Utils_Request::retrieve('status', 'String');
    if (!empty($status)) {
      $statuses = explode(',', $status);
      $statuses = array_map('trim', $statuses);
      $statuses = array_filter($statuses);
      if (!empty($statuses)) {
        $params['status'] = $statuses;
      }
    }

    // Numeric filters
    $numericFilters = ['priority', 'min_retries', 'max_retries', 'limit', 'offset'];
    foreach ($numericFilters as $filter) {
      $value = CRM_Utils_Request::retrieve($filter, 'Integer');
      if ($value !== NULL && $value >= 0) {
        $params[$filter] = $value;
      }
    }

    // Date filters
    $dateFilters = ['date_from', 'date_to', 'sent_from', 'sent_to'];
    foreach ($dateFilters as $filter) {
      $value = CRM_Utils_Request::retrieve($filter, 'String');
      if (!empty($value) && CRM_Utils_Rule::date($value)) {
        $params[$filter] = $value;
      }
    }

    // Error filter
    $hasError = CRM_Utils_Request::retrieve('has_error', 'String');
    if (in_array($hasError, ['yes', 'no'])) {
      $params['has_error'] = $hasError;
    }

    // Sorting
    $orderBy = CRM_Utils_Request::retrieve('order_by', 'String');
    $allowedOrderFields = ['id', 'to_email', 'subject', 'status', 'priority', 'created_date', 'sent_date', 'retry_count'];
    if (in_array($orderBy, $allowedOrderFields)) {
      $params['order_by'] = $orderBy;
    }

    $orderDir = CRM_Utils_Request::retrieve('order_dir', 'String');
    if (in_array(strtoupper($orderDir), ['ASC', 'DESC'])) {
      $params['order_dir'] = strtoupper($orderDir);
    }

    return $params;
  }

  /**
   * Sanitize HTML content for display.
   */
  protected function sanitizeHtmlContent($html) {
    if (empty($html)) {
      return '';
    }

    // Basic HTML sanitization - you might want to use a more robust library like HTMLPurifier
    $html = strip_tags($html, '<p><br><div><span><a><strong><b><em><i><ul><ol><li><h1><h2><h3><h4><h5><h6><table><tr><td><th><thead><tbody>');

    // Remove potentially dangerous attributes
    $html = preg_replace('/on\w+="[^"]*"/i', '', $html);
    $html = preg_replace('/javascript:/i', '', $html);

    return $html;
  }

  /**
   * Sanitize text content for display.
   */
  protected function sanitizeTextContent($text) {
    if (empty($text)) {
      return '';
    }

    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  }

  /**
   * Get bulk action message.
   */
  protected function getBulkActionMessage($action, $count) {
    switch ($action) {
      case 'cancel':
        return E::ts('%1 emails cancelled', [1 => $count]);
      case 'retry':
        return E::ts('%1 failed emails queued for retry', [1 => $count]);
      case 'delete':
        return E::ts('%1 emails deleted', [1 => $count]);
      default:
        return E::ts('%1 emails processed', [1 => $count]);
    }
  }

  /**
   * Send JSON success response.
   */
  protected function sendSuccess($data = []) {
    $response = array_merge(['success' => TRUE], $data);
    echo json_encode($response);
    CRM_Utils_System::civiExit();
  }

  /**
   * Send JSON error response.
   */
  protected function sendError($message) {
    $response = [
      'success' => FALSE,
      'message' => $message
    ];
    echo json_encode($response);
    CRM_Utils_System::civiExit();
  }
}
