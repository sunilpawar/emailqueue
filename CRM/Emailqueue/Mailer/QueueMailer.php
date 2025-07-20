<?php

/**
 * Custom mailer that queues emails instead of sending them immediately with multi-client support.
 */
class CRM_Emailqueue_Mailer_QueueMailer {

  protected $params;
  protected $clientId;

  public function __construct($params = []) {
    $this->params = $params;
    $this->clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
  }

  /**
   * Send method that queues the email instead of sending it.
   */
  public function send($recipients, $headers, $body) {
    try {
      // Parse recipients
      $recipientList = $this->parseRecipients($recipients);

      // Queue each email with client context
      foreach ($recipientList as $recipient) {
        $emailData = [
          'to_email' => $recipient,
          'subject' => isset($headers['Subject']) ? $headers['Subject'] : '',
          'from_email' => isset($headers['From']) ? $headers['From'] : '',
          'reply_to' => isset($headers['Reply-To']) ? $headers['Reply-To'] : '',
          'cc' => isset($headers['Cc']) ? $headers['Cc'] : '',
          'bcc' => isset($headers['Bcc']) ? $headers['Bcc'] : '',
          'body_html' => $this->getHtmlBody($body),
          'body_text' => $this->getTextBody($body),
          'headers' => json_encode($headers),
          'created_date' => date('Y-m-d H:i:s'),
          'status' => 'pending',
          'priority' => isset($headers['X-Priority']) ? (int)$headers['X-Priority'] : 3,
          'retry_count' => 0
        ];

        // Detect priority based on email content
        $priorityDetected = CRM_Emailqueue_Utils_PriorityDetector::detectPriority($emailData);
        if ($priorityDetected !== NULL) {
          $emailData['priority'] = $priorityDetected;
        }

        // Add tracking code if tracking is enabled
        if (CRM_Emailqueue_Config::isTrackingEnabled()) {
          $emailData['tracking_code'] = $this->generateTrackingCode($recipient, $emailData['subject']);
        }

        // Validate email if validation is enabled
        if (CRM_Emailqueue_Config::isValidationEnabled()) {
          $validation = CRM_Emailqueue_Utils_Email::validateEmail($recipient);
          $reputation = CRM_Emailqueue_Utils_Email::getEmailReputationScore($recipient);

          $emailData['validation_score'] = $reputation['score'];
          if (!empty($validation['warnings'])) {
            $emailData['validation_warnings'] = json_encode($validation['warnings']);
          }

          // Log validation issues
          if (!$validation['is_valid']) {
            CRM_Emailqueue_Utils_ErrorHandler::warning(
              "Email validation failed for client {$clientId}: " . implode(', ', $validation['errors']),
              ['client_id' => $clientId, 'email' => $recipient]
            );
          }

          $emailData['validation_score'] = $validation['is_valid'] ? 100 : 0;
          $emailData['validation_warnings'] = !empty($validation['warnings']) ?
            json_encode($validation['warnings']) : NULL;
        }

        $queueId = CRM_Emailqueue_BAO_Queue::addToQueue($emailData);

        // Log successful queueing
        if (CRM_Emailqueue_Config::isDebugMode()) {
          CRM_Emailqueue_Utils_ErrorHandler::debug(
            "Email queued for client {$clientId}",
            [
              'queue_id' => $queueId,
              'client_id' => $this->clientId,
              'recipient' => $recipient,
              'subject' => $emailData['subject'],
              'priority' => $emailData['priority']
            ]
          );
        }
      }

      return TRUE;
    }
    catch (Exception $e) {
      $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e, [
        'operation' => 'queue_email',
        'client_id' => $this->clientId,
        'recipients' => is_array($recipients) ? implode(', ', $recipients) : $recipients
      ]);

      return FALSE;
    }
  }

  /**
   * Parse recipients from various formats.
   */
  protected function parseRecipients($recipients) {
    if (is_string($recipients)) {
      return [$recipients];
    }

    if (is_array($recipients)) {
      $emailList = [];
      foreach ($recipients as $recipient) {
        if (is_string($recipient)) {
          $emailList[] = $recipient;
        }
        elseif (is_array($recipient) && isset($recipient[0])) {
          $emailList[] = $recipient[0]; // Email address
        }
      }
      return $emailList;
    }

    return [];
  }

  /**
   * Extract HTML body from email body.
   */
  protected function getHtmlBody($body) {
    if (is_array($body)) {
      return isset($body['html']) ? $body['html'] : (isset($body[1]) ? $body[1] : '');
    }

    // Check if body contains HTML
    if (strip_tags($body) != $body) {
      return $body;
    }

    return '';
  }

  /**
   * Extract text body from email body.
   */
  protected function getTextBody($body) {
    if (is_array($body)) {
      return isset($body['text']) ? $body['text'] : (isset($body[0]) ? $body[0] : '');
    }

    // If it's HTML, strip tags for text version
    if (strip_tags($body) != $body) {
      return strip_tags($body);
    }

    return $body;
  }

  /**
   * Generate tracking code for email.
   */
  protected function generateTrackingCode($recipient, $subject) {
    $clientId = CRM_Emailqueue_BAO_Queue::getCurrentClientId();
    $data = $clientId . '|' . $recipient . '|' . $subject . '|' . time();
    return substr(hash('sha256', $data), 0, 32);
  }

  /**
   * Set client context for this mailer instance.
   */
  public function setClientId($clientId) {
    if (!empty($clientId)) {
      $this->clientId = $clientId;
    }
  }

  /**
   * Get current client context.
   */
  public function getClientId() {
    return $this->clientId;
  }

  /**
   * Queue multiple emails efficiently (batch operation).
   */
  public function sendBatch($emailBatch) {
    try {
      $queuedCount = 0;
      $errors = [];

      foreach ($emailBatch as $emailData) {
        try {
          $recipients = $emailData['recipients'] ?? [];
          $headers = $emailData['headers'] ?? [];
          $body = $emailData['body'] ?? '';

          if ($this->send($recipients, $headers, $body)) {
            $queuedCount += is_array($recipients) ? count($recipients) : 1;
          }
        }
        catch (Exception $e) {
          $errors[] = $e->getMessage();
        }
      }

      // Log batch results
      CRM_Emailqueue_Utils_ErrorHandler::info(
        "Batch email queuing completed",
        [
          'client_id' => $this->clientId,
          'queued_count' => $queuedCount,
          'error_count' => count($errors),
          'batch_size' => count($emailBatch)
        ]
      );

      return [
        'success' => TRUE,
        'queued_count' => $queuedCount,
        'errors' => $errors
      ];

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e, [
        'operation' => 'batch_queue_email',
        'client_id' => $this->clientId
      ]);

      return [
        'success' => FALSE,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Get mailer statistics for current client.
   */
  public function getMailerStats() {
    try {
      return [
        'client_id' => $this->clientId,
        'queue_stats' => CRM_Emailqueue_BAO_Queue::getQueueStats(),
        'processing_metrics' => CRM_Emailqueue_Utils_Performance::getProcessingMetrics(),
        'is_multi_client_mode' => CRM_Emailqueue_Config::isMultiClientMode()
      ];
    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e, [
        'operation' => 'get_mailer_stats',
        'client_id' => $this->clientId
      ]);

      return [
        'client_id' => $this->clientId,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Test mailer configuration for current client.
   */
  public function testConfiguration() {
    try {
      $results = [
        'client_id' => $this->clientId,
        'tests' => [],
        'overall_status' => 'success'
      ];

      // Test database connection
      try {
        $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
        $pdo->query("SELECT 1");
        $results['tests']['database'] = 'OK';
      }
      catch (Exception $e) {
        $results['tests']['database'] = 'FAILED: ' . $e->getMessage();
        $results['overall_status'] = 'error';
      }

      // Test client configuration
      if (empty($this->clientId)) {
        $results['tests']['client_configuration'] = 'FAILED: No client ID configured';
        $results['overall_status'] = 'error';
      }
      else {
        $results['tests']['client_configuration'] = 'OK';
      }

      // Test email queue table structure
      try {
        $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();
        $stmt = $pdo->query("SHOW COLUMNS FROM email_queue LIKE 'client_id'");
        if ($stmt->rowCount() > 0) {
          $results['tests']['table_structure'] = 'OK';
        }
        else {
          $results['tests']['table_structure'] = 'FAILED: client_id column missing';
          $results['overall_status'] = 'error';
        }
      }
      catch (Exception $e) {
        $results['tests']['table_structure'] = 'FAILED: ' . $e->getMessage();
        $results['overall_status'] = 'error';
      }

      // Test priority detection
      try {
        $testEmail = [
          'subject' => 'Test Priority Detection',
          'headers' => json_encode(['From' => 'test@example.com']),
          'from_email' => 'test@example.com'
        ];
        $priority = CRM_Emailqueue_Utils_PriorityDetector::detectPriority($testEmail);
        $results['tests']['priority_detection'] = 'OK';
      }
      catch (Exception $e) {
        $results['tests']['priority_detection'] = 'FAILED: ' . $e->getMessage();
        $results['overall_status'] = 'warning';
      }

      return $results;

    }
    catch (Exception $e) {
      return [
        'client_id' => $this->clientId,
        'overall_status' => 'error',
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Required method for mailer interface compatibility.
   */
  public function __call($method, $args) {
    // Handle any other methods that might be called on the mailer
    CRM_Emailqueue_Utils_ErrorHandler::debug(
      "Unknown mailer method called: {$method}",
      ['client_id' => $this->clientId, 'args' => $args]
    );

    return TRUE;
  }

  /**
   * Get supported mailer capabilities.
   */
  public function getCapabilities() {
    return [
      'queue_emails' => TRUE,
      'batch_processing' => TRUE,
      'priority_detection' => TRUE,
      'email_validation' => CRM_Emailqueue_Config::isValidationEnabled(),
      'email_tracking' => CRM_Emailqueue_Config::isTrackingEnabled(),
      'multi_client_support' => CRM_Emailqueue_Config::isMultiClientMode(),
      'client_id' => $this->clientId
    ];
  }

  /**
   * Estimate queue processing time for current client.
   */
  public function estimateProcessingTime() {
    try {
      $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();
      $metrics = CRM_Emailqueue_Utils_Performance::getProcessingMetrics();

      $pendingEmails = $stats['pending'] ?? 0;
      $emailsPerHour = $metrics['emails_per_hour'] ?? 60; // Default assumption

      if ($emailsPerHour > 0) {
        $hoursToProcess = $pendingEmails / $emailsPerHour;
        $estimatedTime = [
          'pending_emails' => $pendingEmails,
          'processing_rate_per_hour' => $emailsPerHour,
          'estimated_hours' => round($hoursToProcess, 2),
          'estimated_completion' => date('Y-m-d H:i:s', time() + ($hoursToProcess * 3600)),
          'client_id' => $this->clientId
        ];
      }
      else {
        $estimatedTime = [
          'pending_emails' => $pendingEmails,
          'message' => 'Unable to estimate - no processing history available',
          'client_id' => $this->clientId
        ];
      }

      return $estimatedTime;

    }
    catch (Exception $e) {
      return [
        'error' => $e->getMessage(),
        'client_id' => $this->clientId
      ];
    }
  }

  /**
   * Get current client context.
   */
  public function getClientContext() {
    return CRM_Emailqueue_BAO_Queue::getCurrentClientId();
  }
}
