<?php

use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Page for monitoring email queue status.
 */
class CRM_Emailqueue_Page_Monitor extends CRM_Core_Page {

  public function run() {

    // Check if email queue is enabled
    $isEnabled = Civi::settings()->get('emailqueue_enabled');

    if (!$isEnabled) {
      CRM_Core_Session::setStatus(E::ts('Email Queue System is not enabled. Please enable it in the settings.'), E::ts('System Disabled'), 'warning');
    }

    try {
      // Get queue statistics
      $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();
      $this->assign('queueStats', $stats);

      // Get recent emails
      $recentEmails = $this->getRecentEmails();
      $this->assign('recentEmails', $recentEmails);

      // Get failed emails
      $failedEmails = $this->getFailedEmails();
      $this->assign('failedEmails', $failedEmails);

    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus(E::ts('Error connecting to email queue database: %1', [1 => $e->getMessage()]), E::ts('Database Error'), 'error');
      $this->assign('queueStats', []);
      $this->assign('recentEmails', []);
      $this->assign('failedEmails', []);
    }

    $this->assign('isEnabled', $isEnabled);

    parent::run();
  }

  /**
   * Get recent emails from queue.
   */
  protected function getRecentEmails($limit = 20) {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $sql = "
        SELECT
          id, to_email, subject, status, created_date, sent_date, retry_count, priority
        FROM email_queue
        ORDER BY created_date DESC
        LIMIT :limit
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->execute();

      return $stmt->fetchAll();

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Recent Emails Error: ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Get failed emails from queue.
   */
  protected function getFailedEmails($limit = 10) {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $sql = "
        SELECT
          id, to_email, subject, error_message, created_date, retry_count
        FROM email_queue
        WHERE status = 'failed'
        ORDER BY created_date DESC
        LIMIT :limit
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->execute();

      return $stmt->fetchAll();

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Failed Emails Error: ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Handle AJAX actions.
   */
  public static function ajaxAction() {
    $action = CRM_Utils_Request::retrieve('action', 'String');

    switch ($action) {
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
