<?php

/**
 * Custom mailer that queues emails instead of sending them immediately.
 */
class CRM_Emailqueue_Mailer_QueueMailer {

  protected $params;

  public function __construct($params = []) {
    $this->params = $params;
  }

  /**
   * Send method that queues the email instead of sending it.
   */
  public function send($recipients, $headers, $body) {
    try {
      // Parse recipients
      $recipientList = $this->parseRecipients($recipients);

      // Queue each email
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

        $priorityDetected = CRM_Emailqueue_Utils_PriorityDetector::detectPriority($emailData);
        if ($priorityDetected !== NULL) {
          $emailData['priority'] = $priorityDetected;
        }

        CRM_Emailqueue_BAO_Queue::addToQueue($emailData);
      }

      return TRUE;
    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Error: ' . $e->getMessage());
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
   * Required method for mailer interface compatibility.
   */
  public function __call($method, $args) {
    // Handle any other methods that might be called on the mailer
    return TRUE;
  }
}
