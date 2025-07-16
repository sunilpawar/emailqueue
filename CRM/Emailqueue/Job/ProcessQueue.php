<?php

/**
 * Scheduled job to process email queue.
 */
class CRM_Emailqueue_Job_ProcessQueue {

  /**
   * Process the email queue.
   *
   * @param array $params
   *   Job parameters.
   * @return array
   *   Job result.
   */
  public static function run($params = []) {
    $result = [
      'is_error' => 0,
      'messages' => [],
    ];

    try {
      // Check if email queue is enabled
      $isEnabled = Civi::settings()->get('emailqueue_enabled');

      if (!$isEnabled) {
        $result['messages'][] = 'Email Queue System is disabled';
        return $result;
      }

      // Get batch size from params or settings
      $batchSize = isset($params['batch_size']) ? (int)$params['batch_size'] : Civi::settings()->get('emailqueue_batch_size');

      // Temporarily override batch size if specified
      if (isset($params['batch_size'])) {
        $originalBatchSize = Civi::settings()->get('emailqueue_batch_size');
        Civi::settings()->set('emailqueue_batch_size', $batchSize);
      }

      // Process the queue
      $startTime = microtime(TRUE);
      $processedCount = self::processQueueBatch();
      $endTime = microtime(TRUE);

      // Restore original batch size if it was overridden
      if (isset($originalBatchSize)) {
        Civi::settings()->set('emailqueue_batch_size', $originalBatchSize);
      }

      $executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds

      $result['messages'][] = "Processed {$processedCount} emails in {$executionTime}ms";

      // Get queue statistics for reporting
      $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();
      $result['messages'][] = "Queue status - Pending: {$stats['pending']}, Failed: {$stats['failed']}, Sent: {$stats['sent']}";

    }
    catch (Exception $e) {
      $result['is_error'] = 1;
      $result['messages'][] = 'Error processing email queue: ' . $e->getMessage();
      CRM_Core_Error::debug_log_message('Email Queue Job Error: ' . $e->getMessage());
    }

    return $result;
  }

  /**
   * Process a batch of emails from the queue.
   *
   * @return int
   *   Number of emails processed.
   */
  protected static function processQueueBatch() {
    try {
      $batchSize = Civi::settings()->get('emailqueue_batch_size');
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      // Get pending emails
      $sql = "
        SELECT * FROM email_queue
        WHERE status = 'pending'
        AND (scheduled_date IS NULL OR scheduled_date <= NOW())
        ORDER BY priority ASC, created_date ASC
        LIMIT :batch_size
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':batch_size', $batchSize, PDO::PARAM_INT);
      $stmt->execute();

      $emails = $stmt->fetchAll();
      $processedCount = 0;

      foreach ($emails as $email) {
        if (self::processEmail($email)) {
          $processedCount++;
        }
      }

      return $processedCount;

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Batch Process Error: ' . $e->getMessage());
      throw $e;
    }
  }

  /**
   * Process individual email.
   *
   * @param array $email
   *   Email data from queue.
   * @return bool
   *   TRUE if email was processed successfully.
   */
  protected static function processEmail($email) {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      // Mark as processing
      $pdo->prepare("UPDATE email_queue SET status = 'processing' WHERE id = ?")->execute([$email['id']]);

      // Create SMTP mailer (bypassing the alterMailer hook)
      $mailer = self::createDirectMailer();

      // Prepare email data
      $headers = json_decode($email['headers'], TRUE) ?: [];
      $headers['Subject'] = $email['subject'];
      $headers['From'] = $email['from_email'];

      if ($email['reply_to']) {
        $headers['Reply-To'] = $email['reply_to'];
      }
      if ($email['cc']) {
        $headers['Cc'] = $email['cc'];
      }
      if ($email['bcc']) {
        $headers['Bcc'] = $email['bcc'];
      }

      // Prepare body
      $body = [];
      if ($email['body_text']) {
        $body['text'] = $email['body_text'];
      }
      if ($email['body_html']) {
        $body['html'] = $email['body_html'];
      }

      // Send email using direct mailer
      $result = $mailer->send($email['to_email'], $headers, $body);

      if ($result) {
        // Mark as sent
        $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_date = NOW() WHERE id = ?")->execute([$email['id']]);
        self::logAction($email['id'], 'sent', 'Email sent successfully');
        return TRUE;
      }
      else {
        self::handleFailedEmail($email, 'Mailer returned false');
        return FALSE;
      }

    }
    catch (Exception $e) {
      self::handleFailedEmail($email, $e->getMessage());
      return FALSE;
    }
  }

  /**
   * Create direct mailer bypassing hooks.
   *
   * @return object
   *   Mail driver instance.
   */
  protected static function createDirectMailer() {
    // Get mail settings
    $mailSettings = Civi::settings()->get('mailing_backend');

    if (empty($mailSettings) || !is_array($mailSettings)) {
      // Fallback to SMTP settings
      $mailSettings = [
        'outBound_option' => CRM_Mailing_Config::OUTBOUND_OPTION_SMTP,
        'smtpServer' => Civi::settings()->get('smtpServer'),
        'smtpPort' => Civi::settings()->get('smtpPort'),
        'smtpAuth' => Civi::settings()->get('smtpAuth'),
        'smtpUsername' => Civi::settings()->get('smtpUsername'),
        'smtpPassword' => Civi::settings()->get('smtpPassword'),
      ];
    }

    // Create mailer directly without hooks
    return CRM_Utils_Mail::createMailer($mailSettings);
  }

  /**
   * Handle failed email.
   */
  protected static function handleFailedEmail($email, $errorMessage = '') {
    $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

    $retryCount = $email['retry_count'] + 1;
    $maxRetries = $email['max_retries'];

    if ($retryCount >= $maxRetries) {
      // Max retries reached, mark as failed
      $pdo->prepare("UPDATE email_queue SET status = 'failed', retry_count = ?, error_message = ? WHERE id = ?")
        ->execute([$retryCount, $errorMessage, $email['id']]);
      self::logAction($email['id'], 'failed', "Max retries reached. Error: " . $errorMessage);
    }
    else {
      // Schedule for retry with exponential backoff
      $delayMinutes = pow(2, $retryCount) * 5; // 5, 10, 20, 40 minutes etc.
      $nextRetry = date('Y-m-d H:i:s', strtotime("+{$delayMinutes} minutes"));
      $pdo->prepare("UPDATE email_queue SET status = 'pending', retry_count = ?, scheduled_date = ?, error_message = ? WHERE id = ?")
        ->execute([$retryCount, $nextRetry, $errorMessage, $email['id']]);
      self::logAction($email['id'], 'retry_scheduled', "Retry {$retryCount} scheduled for {$nextRetry}. Error: " . $errorMessage);
    }
  }

  /**
   * Log action to email queue log.
   */
  protected static function logAction($queueId, $action, $message) {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      $sql = "INSERT INTO email_queue_log (queue_id, action, message, created_date) VALUES (?, ?, ?, NOW())";
      $pdo->prepare($sql)->execute([$queueId, $action, $message]);
    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Log Error: ' . $e->getMessage());
    }
  }
}
