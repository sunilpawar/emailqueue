<?php

/**
 * Scheduled job to process email queue with multi-client support.
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

      // Determine processing mode (single client vs all clients)
      $processAllClients = !empty($params['process_all_clients']) && CRM_Emailqueue_Config::hasAdminClientAccess();
      $specificClientId = $params['client_id'] ?? NULL;

      if ($processAllClients) {
        // Process all clients (admin mode)
        $processResult = self::processAllClients($params);
      }
      elseif ($specificClientId) {
        // Process specific client
        $processResult = self::processSpecificClient($specificClientId, $params);
      }
      else {
        // Process current client context
        $processResult = self::processCurrentClient($params);
      }

      $result['messages'] = array_merge($result['messages'], $processResult['messages']);
      $result['total_processed'] = $processResult['total_processed'];
      $result['clients_processed'] = $processResult['clients_processed'] ?? 1;

    }
    catch (Exception $e) {
      $result['is_error'] = 1;
      $result['messages'][] = 'Error processing email queue: ' . $e->getMessage();
      CRM_Core_Error::debug_log_message('Email Queue Job Error: ' . $e->getMessage());
    }

    return $result;
  }

  /**
   * Process email queue for all clients (admin only).
   */
  protected static function processAllClients($params = []) {
    if (!CRM_Emailqueue_Config::hasAdminClientAccess()) {
      throw new Exception('Admin client access required to process all clients');
    }

    $result = [
      'messages' => [],
      'total_processed' => 0,
      'clients_processed' => 0
    ];

    try {
      $clientStats = CRM_Emailqueue_BAO_Queue::getClientStats();

      foreach ($clientStats as $clientInfo) {
        $clientId = $clientInfo['client_id'];

        // Skip clients with no pending emails to save processing time
        if ($clientInfo['pending'] == 0) {
          continue;
        }

        try {
          $clientResult = self::processSpecificClient($clientId, $params);
          $result['total_processed'] += $clientResult['total_processed'];
          $result['clients_processed']++;

          if ($clientResult['total_processed'] > 0) {
            $result['messages'][] = "Client {$clientId}: " . $clientResult['messages'][0];
          }
        }
        catch (Exception $e) {
          $result['messages'][] = "Client {$clientId}: Error - " . $e->getMessage();
          CRM_Emailqueue_Utils_ErrorHandler::handleException($e, ['client_id' => $clientId]);
        }
      }

      if ($result['clients_processed'] == 0) {
        $result['messages'][] = 'No clients had pending emails to process';
      }
      else {
        $result['messages'][] = "Processed {$result['total_processed']} emails across {$result['clients_processed']} clients";
      }

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e, ['operation' => 'process_all_clients']);
      throw $e;
    }

    return $result;
  }

  /**
   * Process email queue for specific client.
   */
  protected static function processSpecificClient($clientId, $params = []) {
    $result = [
      'messages' => [],
      'total_processed' => 0
    ];

    try {
      // Temporarily switch to client context
      $originalClientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
      CRM_Emailqueue_BAO_Queue::switchClientContext($clientId);

      // Process the queue for this client
      $startTime = microtime(TRUE);
      $processedCount = self::processQueueBatch($clientId, $params);
      $endTime = microtime(TRUE);

      $executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds

      $result['total_processed'] = $processedCount;
      $result['messages'][] = "Processed {$processedCount} emails in {$executionTime}ms";

      // Get queue statistics for reporting
      $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();
      $result['messages'][] = "Queue status - Pending: {$stats['pending']}, Failed: {$stats['failed']}, Sent: {$stats['sent']}";

      // Restore original client context
      if ($originalClientId !== $clientId) {
        CRM_Emailqueue_BAO_Queue::switchClientContext($originalClientId);
      }

    }
    catch (Exception $e) {
      // Ensure we restore context even on error
      try {
        CRM_Emailqueue_BAO_Queue::resetClientContext();
      }
      catch (Exception $restoreError) {
        // Ignore restore errors
      }

      CRM_Emailqueue_Utils_ErrorHandler::handleException($e, ['client_id' => $clientId]);
      throw $e;
    }

    return $result;
  }

  /**
   * Process email queue for current client context.
   */
  protected static function processCurrentClient($params = []) {
    $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();

    $result = [
      'messages' => [],
      'total_processed' => 0
    ];

    try {
      // Get batch size from params or settings
      $batchSize = isset($params['batch_size']) ? (int)$params['batch_size'] : Civi::settings()->get('emailqueue_batch_size');

      // Temporarily override batch size if specified
      if (isset($params['batch_size'])) {
        $originalBatchSize = Civi::settings()->get('emailqueue_batch_size');
        Civi::settings()->set('emailqueue_batch_size', $batchSize);
      }

      // Process the queue
      $startTime = microtime(TRUE);
      $processedCount = self::processQueueBatch($clientId, $params);
      $endTime = microtime(TRUE);

      // Restore original batch size if it was overridden
      if (isset($originalBatchSize)) {
        Civi::settings()->set('emailqueue_batch_size', $originalBatchSize);
      }

      $executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds

      $result['total_processed'] = $processedCount;
      $result['messages'][] = "Processed {$processedCount} emails for client {$clientId} in {$executionTime}ms";

      // Get queue statistics for reporting
      $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();
      $result['messages'][] = "Client {$clientId} queue status - Pending: {$stats['pending']}, Failed: {$stats['failed']}, Sent: {$stats['sent']}";

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e, ['client_id' => $clientId]);
      throw $e;
    }

    return $result;
  }

  /**
   * Process a batch of emails from the queue for specific client.
   *
   * @param string $clientId
   *   Client ID to process.
   * @param array $params
   *   Processing parameters.
   * @return int
   *   Number of emails processed.
   */
  protected static function processQueueBatch($clientId, $params = []) {
    try {
      $batchSize = isset($params['batch_size']) ? (int)$params['batch_size'] : Civi::settings()->get('emailqueue_batch_size');
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      // Get pending emails for specific client
      $sql = "
        SELECT * FROM email_queue
        WHERE client_id = :client_id
        AND status = 'pending'
        AND (scheduled_date IS NULL OR scheduled_date <= NOW())
        ORDER BY priority ASC, created_date ASC
        LIMIT :batch_size
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
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
      CRM_Core_Error::debug_log_message('Email Queue Batch Process Error for client ' . $clientId . ': ' . $e->getMessage());
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
        self::logAction($email['id'], $email['client_id'], 'sent', 'Email sent successfully');
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
   * Handle failed email with client context.
   */
  protected static function handleFailedEmail($email, $errorMessage = '') {
    $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
    $clientId = $email['client_id'];

    $retryCount = $email['retry_count'] + 1;
    $maxRetries = $email['max_retries'];

    if ($retryCount >= $maxRetries) {
      // Max retries reached, mark as failed
      $pdo->prepare("UPDATE email_queue SET status = 'failed', retry_count = ?, error_message = ? WHERE id = ?")
        ->execute([$retryCount, $errorMessage, $email['id']]);
      self::logAction($email['id'], $clientId, 'failed', "Max retries reached. Error: " . $errorMessage);
    }
    else {
      // Schedule for retry with exponential backoff
      $delayMinutes = pow(2, $retryCount) * 5; // 5, 10, 20, 40 minutes etc.
      $nextRetry = date('Y-m-d H:i:s', strtotime("+{$delayMinutes} minutes"));
      $pdo->prepare("UPDATE email_queue SET status = 'pending', retry_count = ?, scheduled_date = ?, error_message = ? WHERE id = ?")
        ->execute([$retryCount, $nextRetry, $errorMessage, $email['id']]);
      self::logAction($email['id'], $clientId, 'retry_scheduled', "Retry {$retryCount} scheduled for {$nextRetry}. Error: " . $errorMessage);
    }
  }

  /**
   * Log action to email queue log with client context.
   */
  protected static function logAction($queueId, $clientId, $action, $message) {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
      $sql = "INSERT INTO email_queue_log (client_id, queue_id, action, message, created_date) VALUES (?, ?, ?, ?, NOW())";
      $pdo->prepare($sql)->execute([$clientId, $queueId, $action, $message]);
    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Log Error: ' . $e->getMessage());
    }
  }

  /**
   * Get processing statistics for all clients or specific client.
   */
  public static function getProcessingStats($clientId = NULL) {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      if ($clientId) {
        // Stats for specific client
        $sql = "
          SELECT
            client_id,
            COUNT(*) as total_emails,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing,
            COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
            MAX(created_date) as last_activity
          FROM email_queue
          WHERE client_id = ?
          GROUP BY client_id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
      }
      else {
        // Stats for all clients
        return CRM_Emailqueue_BAO_Queue::getClientStats();
      }

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Failed to get processing stats: ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Clean up stuck processing emails across all clients or specific client.
   */
  public static function cleanupStuckEmails($clientId = NULL, $hoursStuck = 1) {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      if ($clientId) {
        // Cleanup for specific client
        $sql = "
          UPDATE email_queue
          SET status = 'pending', error_message = CONCAT(COALESCE(error_message, ''), ' [Reset from stuck processing status]')
          WHERE client_id = ? AND status = 'processing'
          AND created_date < DATE_SUB(NOW(), INTERVAL ? HOUR)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clientId, $hoursStuck]);
      }
      else {
        // Cleanup for all clients
        $sql = "
          UPDATE email_queue
          SET status = 'pending', error_message = CONCAT(COALESCE(error_message, ''), ' [Reset from stuck processing status]')
          WHERE status = 'processing'
          AND created_date < DATE_SUB(NOW(), INTERVAL ? HOUR)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hoursStuck]);
      }

      $resetCount = $stmt->rowCount();

      if ($resetCount > 0) {
        $message = "Reset {$resetCount} stuck processing emails";
        if ($clientId) {
          $message .= " for client {$clientId}";
        }
        CRM_Core_Error::debug_log_message($message);
      }

      return $resetCount;

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Failed to cleanup stuck emails: ' . $e->getMessage());
      return 0;
    }
  }

  /**
   * Process queue with automatic stuck email cleanup.
   */
  public static function processWithCleanup($params = []) {
    // First cleanup any stuck emails
    $cleanupCount = self::cleanupStuckEmails();

    // Then process the queue normally
    $result = self::run($params);

    if ($cleanupCount > 0) {
      array_unshift($result['messages'], "Cleaned up {$cleanupCount} stuck processing emails");
    }

    return $result;
  }
}
