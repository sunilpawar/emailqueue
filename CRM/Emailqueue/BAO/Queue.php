<?php

/**
 * Business Access Object for Email Queue operations.
 */
class CRM_Emailqueue_BAO_Queue {

  private static $mailerCache = [];

  /**
   * Get database connection for email queue.
   */
  public static function getQueueConnection() {
    static $connection = NULL;

    if ($connection === NULL) {
      $host = Civi::settings()->get('emailqueue_db_host');
      $dbname = Civi::settings()->get('emailqueue_db_name');
      $username = Civi::settings()->get('emailqueue_db_user');
      $password = Civi::settings()->get('emailqueue_db_pass');

      try {
       // $availableDrivers = PDO::getAvailableDrivers();
        $option = [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"];
        $extra = ';charset=utf8';
        $connection = new PDO('mysql' . ":dbname=" . $dbname . ";host={$host}{$extra}", $username, $password, $option);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      }
      catch (PDOException $e) {
        throw new Exception('Failed to connect to email queue database');
      }
    }

    return $connection;
  }

  /**
   * Create email queue database tables.
   */
  public static function createTables() {
    try {
      $pdo = self::getQueueConnection();

      $sql = "
        CREATE TABLE IF NOT EXISTS email_queue (
          id INT AUTO_INCREMENT PRIMARY KEY,
          to_email VARCHAR(255) NOT NULL,
          subject TEXT,
          from_email VARCHAR(255),
          reply_to VARCHAR(255),
          cc TEXT,
          bcc TEXT,
          body_html LONGTEXT,
          body_text LONGTEXT,
          headers TEXT,
          created_date DATETIME NOT NULL,
          scheduled_date DATETIME NULL,
          sent_date DATETIME NULL,
          status ENUM('pending', 'processing', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
          priority TINYINT DEFAULT 3,
          retry_count INT DEFAULT 0,
          max_retries INT DEFAULT 3,
          error_message TEXT,
          INDEX idx_status (status),
          INDEX idx_scheduled (scheduled_date),
          INDEX idx_priority (priority),
          INDEX idx_created (created_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      ";

      $pdo->exec($sql);

      // Create email queue log table
      $logSql = "
        CREATE TABLE IF NOT EXISTS email_queue_log (
          id INT AUTO_INCREMENT PRIMARY KEY,
          queue_id INT NOT NULL,
          action VARCHAR(50) NOT NULL,
          message TEXT,
          created_date DATETIME NOT NULL,
          FOREIGN KEY (queue_id) REFERENCES email_queue(id) ON DELETE CASCADE,
          INDEX idx_queue_id (queue_id),
          INDEX idx_action (action),
          INDEX idx_created (created_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      ";

      $pdo->exec($logSql);

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Table Creation Error: ' . $e->getMessage());
      throw $e;
    }
  }

  /**
   * Add email to queue.
   */
  public static function addToQueue($emailData) {
    try {
      $pdo = self::getQueueConnection();

      $sql = "
        INSERT INTO email_queue (
          to_email, subject, from_email, reply_to, cc, bcc,
          body_html, body_text, headers, created_date, status,
          priority, retry_count, max_retries
        ) VALUES (
          :to_email, :subject, :from_email, :reply_to, :cc, :bcc,
          :body_html, :body_text, :headers, :created_date, :status,
          :priority, :retry_count, :max_retries
        )
      ";

      $stmt = $pdo->prepare($sql);

      $emailData['max_retries'] = Civi::settings()->get('emailqueue_retry_attempts');

      $stmt->execute($emailData);

      $queueId = $pdo->lastInsertId();

      // Log the action
      self::logAction($queueId, 'queued', 'Email added to queue');

      return $queueId;

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Add Error: ' . $e->getMessage());
      throw $e;
    }
  }

  /**
   * Process email queue.
   */
  public static function processQueue() {
    try {
      $batchSize = Civi::settings()->get('emailqueue_batch_size');
      $pdo = self::getQueueConnection();

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
      // Mailer class:

      foreach ($emails as $email) {
        self::processEmail($email);
      }

      CRM_Core_Error::debug_log_message("Processed " . count($emails) . " emails from queue");

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Process Error: ' . $e->getMessage());
    }
  }

  /**
   * Process individual email.
   */
  protected static function processEmail($email) {
    global $skipAlterMailerHook;
    try {
      $pdo = self::getQueueConnection();

      // Mark as processing
      self::updateStatus($email['id'], 'processing');

      // Get SMTP settings from CiviCRM
      $smtpParams = self::getSmtpSettings();

      // Create actual mailer
      $skipAlterMailerHook = TRUE;
      $mailer = self::createSmtpMailer($smtpParams);

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

      $body = [];
      if ($email['body_text']) {
        $body['text'] = $email['body_text'];
      }
      if ($email['body_html']) {
        $body['html'] = $email['body_html'];
      }

      // Send email
      $result = $mailer->send($email['to_email'], $headers, $body);

      if ($result) {
        // Mark as sent
        self::updateStatus($email['id'], 'sent', 'Email sent successfully');
        $pdo->prepare("UPDATE email_queue SET sent_date = NOW() WHERE id = ?")->execute([$email['id']]);
      }
      else {
        self::handleFailedEmail($email);
      }

    }
    catch (Exception $e) {
      self::handleFailedEmail($email, $e->getMessage());
    }
  }

  /**
   * Handle failed email.
   */
  protected static function handleFailedEmail($email, $errorMessage = '') {
    $pdo = self::getQueueConnection();

    $retryCount = $email['retry_count'] + 1;
    $maxRetries = $email['max_retries'];

    if ($retryCount >= $maxRetries) {
      // Max retries reached, mark as failed
      self::updateStatus($email['id'], 'failed', "Max retries reached. Error: " . $errorMessage);
      $pdo->prepare("UPDATE email_queue SET retry_count = ?, error_message = ? WHERE id = ?")
        ->execute([$retryCount, $errorMessage, $email['id']]);
    }
    else {
      // Schedule for retry
      self::updateStatus($email['id'], 'pending', "Retry attempt {$retryCount}. Error: " . $errorMessage);
      $nextRetry = date('Y-m-d H:i:s', strtotime('+' . (pow(2, $retryCount)) . ' minutes'));
      $pdo->prepare("UPDATE email_queue SET retry_count = ?, scheduled_date = ?, error_message = ? WHERE id = ?")
        ->execute([$retryCount, $nextRetry, $errorMessage, $email['id']]);
    }
  }

  /**
   * Update email status.
   */
  protected static function updateStatus($queueId, $status, $message = '') {
    $pdo = self::getQueueConnection();
    $pdo->prepare("UPDATE email_queue SET status = ? WHERE id = ?")->execute([$status, $queueId]);

    if ($message) {
      self::logAction($queueId, $status, $message);
    }
  }

  /**
   * Log action to email queue log.
   */
  protected static function logAction($queueId, $action, $message) {
    try {
      $pdo = self::getQueueConnection();
      $sql = "INSERT INTO email_queue_log (queue_id, action, message, created_date) VALUES (?, ?, ?, NOW())";
      $pdo->prepare($sql)->execute([$queueId, $action, $message]);
    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Log Error: ' . $e->getMessage());
    }
  }

  /**
   * Get SMTP settings from CiviCRM.
   */
  protected static function getSmtpSettings() {
    return [
      'host' => civicrm_api3('Setting', 'getvalue', ['name' => 'smtpServer']),
      'port' => civicrm_api3('Setting', 'getvalue', ['name' => 'smtpPort']),
      'auth' => civicrm_api3('Setting', 'getvalue', ['name' => 'smtpAuth']),
      'username' => civicrm_api3('Setting', 'getvalue', ['name' => 'smtpUsername']),
      'password' => civicrm_api3('Setting', 'getvalue', ['name' => 'smtpPassword']),
      'localpart' => civicrm_api3('Setting', 'getvalue', ['name' => 'smtpLocalpart']),
    ];
  }

  /**
   * Create SMTP mailer.
   */
  protected static function createSmtpMailer($params) {
    $cacheKey = __FUNCTION__;
    // This would create the actual SMTP mailer using CiviCRM's mail system
    // We bypass the alterMailer hook by creating the mailer directly
    if (isset(self::$mailerCache[$cacheKey])) {
      return self::$mailerCache[$cacheKey];
    }
    $originalMailer = CRM_Utils_Mail::createMailer();
    self::$mailerCache[$cacheKey] = $originalMailer;

    return $originalMailer;
  }

  /**
   * Get queue statistics.
   */
  public static function getQueueStats($timeframe = '24 HOUR') {
    try {
      $pdo = self::getQueueConnection();

      $sql = "
        SELECT
          status,
          COUNT(*) as count
        FROM email_queue
        WHERE created_date >= DATE_SUB(NOW(), INTERVAL {$timeframe})
        GROUP BY status
      ";
      $stmt = $pdo->query($sql);
      $stats = $stmt->fetchAll();

      $result = [
        'pending' => 0,
        'processing' => 0,
        'sent' => 0,
        'failed' => 0,
        'cancelled' => 0
      ];

      foreach ($stats as $stat) {
        $result[$stat['status']] = $stat['count'];
      }

      return $result;

    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Stats Error: ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Search emails in queue with filters.
   */
  public static function searchEmails($params = []) {
    try {
      $pdo = self::getQueueConnection();

      $where = [];
      $bindings = [];

      // Build WHERE clause based on filters
      if (!empty($params['to_email'])) {
        $where[] = "to_email LIKE ?";
        $bindings[] = '%' . $params['to_email'] . '%';
      }

      if (!empty($params['from_email'])) {
        $where[] = "from_email LIKE ?";
        $bindings[] = '%' . $params['from_email'] . '%';
      }

      if (!empty($params['subject'])) {
        $where[] = "subject LIKE ?";
        $bindings[] = '%' . $params['subject'] . '%';
      }

      if (!empty($params['status'])) {
        if (is_array($params['status'])) {
          $placeholders = str_repeat('?,', count($params['status']) - 1) . '?';
          $where[] = "status IN ($placeholders)";
          $bindings = array_merge($bindings, $params['status']);
        } else {
          $where[] = "status = ?";
          $bindings[] = $params['status'];
        }
      }

      if (!empty($params['priority'])) {
        $where[] = "priority = ?";
        $bindings[] = (int) $params['priority'];
      }

      // Date range filters
      if (!empty($params['date_from'])) {
        $where[] = "created_date >= ?";
        $bindings[] = $params['date_from'] . ' 00:00:00';
      }

      if (!empty($params['date_to'])) {
        $where[] = "created_date <= ?";
        $bindings[] = $params['date_to'] . ' 23:59:59';
      }

      if (!empty($params['sent_from'])) {
        $where[] = "sent_date >= ?";
        $bindings[] = $params['sent_from'] . ' 00:00:00';
      }

      if (!empty($params['sent_to'])) {
        $where[] = "sent_date <= ?";
        $bindings[] = $params['sent_to'] . ' 23:59:59';
      }

      // Error filter
      if (!empty($params['has_error'])) {
        if ($params['has_error'] === 'yes') {
          $where[] = "error_message IS NOT NULL AND error_message != ''";
        } else {
          $where[] = "(error_message IS NULL OR error_message = '')";
        }
      }

      // Retry count filter
      if (isset($params['min_retries'])) {
        $where[] = "retry_count >= ?";
        $bindings[] = (int) $params['min_retries'];
      }

      if (isset($params['max_retries'])) {
        $where[] = "retry_count <= ?";
        $bindings[] = (int) $params['max_retries'];
      }

      // Build the query
      $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

      // Get total count for pagination
      $countSql = "SELECT COUNT(*) as total FROM email_queue $whereClause";
      $countStmt = $pdo->prepare($countSql);
      $countStmt->execute($bindings);
      $totalCount = $countStmt->fetch()['total'];

      // Set defaults for pagination
      $limit = isset($params['limit']) ? (int) $params['limit'] : 50;
      $offset = isset($params['offset']) ? (int) $params['offset'] : 0;
      $orderBy = isset($params['order_by']) ? $params['order_by'] : 'created_date';
      $orderDir = isset($params['order_dir']) && strtoupper($params['order_dir']) === 'ASC' ? 'ASC' : 'DESC';

      // Validate order_by field
      $allowedOrderFields = ['id', 'to_email', 'subject', 'status', 'priority', 'created_date', 'sent_date', 'retry_count'];
      if (!in_array($orderBy, $allowedOrderFields)) {
        $orderBy = 'created_date';
      }

      // Get the emails
      $sql = "
        SELECT
          id, to_email, from_email, subject, status, priority,
          created_date, sent_date, retry_count, error_message,
          CASE
            WHEN LENGTH(body_html) > 0 THEN 'html'
            WHEN LENGTH(body_text) > 0 THEN 'text'
            ELSE 'none'
          END as body_type
        FROM email_queue
        $whereClause
        ORDER BY $orderBy $orderDir
        LIMIT $limit OFFSET $offset
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->execute($bindings);
      $emails = $stmt->fetchAll();

      return [
        'emails' => $emails,
        'total_count' => $totalCount,
        'limit' => $limit,
        'offset' => $offset,
        'current_page' => floor($offset / $limit) + 1,
        'total_pages' => ceil($totalCount / $limit)
      ];

    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Search Error: ' . $e->getMessage());
      throw $e;
    }
  }

  /**
   * Get email details for preview.
   */
  public static function getEmailPreview($emailId) {
    try {
      $pdo = self::getQueueConnection();

      $sql = "
        SELECT
          id, to_email, from_email, reply_to, cc, bcc, subject,
          body_html, body_text, headers, created_date, sent_date,
          status, priority, retry_count, error_message, max_retries
        FROM email_queue
        WHERE id = ?
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([$emailId]);
      $email = $stmt->fetch();

      if (!$email) {
        throw new Exception('Email not found');
      }

      // Parse headers
      $headers = json_decode($email['headers'], true) ?: [];
      $email['parsed_headers'] = $headers;
      $parser = new CRM_Emailqueue_Utils_EmailParser();
      $priorityLevels = CRM_Emailqueue_Config::getPriorityLevels();
      $email['priority'] = $priorityLevels[$email['priority']] ?? 'Normal222';
      $resultParsed = $parser->parse($email['body_html']);
      $email['body_text'] = $resultParsed['text_parts'][0]['content'] ?? '';
      $email['body_html'] = $resultParsed['html_parts'][0]['content'] ?? '';
      //$email['attachments'] = $resultParsed['attachments'][0]['content'] ??'';
      //CRM_Core_Error::debug_var('Email Queue Preview $resultParsed: ',$resultParsed);
      // Get email logs
      $logSql = "
        SELECT action, message, created_date
        FROM email_queue_log
        WHERE queue_id = ?
        ORDER BY created_date DESC
      ";

      $logStmt = $pdo->prepare($logSql);
      $logStmt->execute([$emailId]);
      $email['logs'] = $logStmt->fetchAll();

      return $email;

    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Preview Error: ' . $e->getMessage());
      throw $e;
    }
  }

  /**
   * Get filter options for search form.
   */
  public static function getFilterOptions() {
    try {
      $pdo = self::getQueueConnection();

      // Get unique statuses
      $statusSql = "SELECT DISTINCT status FROM email_queue ORDER BY status";
      $statusStmt = $pdo->query($statusSql);
      $statuses = $statusStmt->fetchAll(PDO::FETCH_COLUMN);

      // Get unique priorities
      $prioritySql = "SELECT DISTINCT priority FROM email_queue ORDER BY priority";
      $priorityStmt = $pdo->query($prioritySql);
      $priorities = $priorityStmt->fetchAll(PDO::FETCH_COLUMN);

      // Get unique from emails (top 20)
      $fromSql = "
        SELECT DISTINCT from_email
        FROM email_queue
        WHERE from_email IS NOT NULL AND from_email != ''
        ORDER BY from_email
        LIMIT 20
      ";
      $fromStmt = $pdo->query($fromSql);
      $fromEmails = $fromStmt->fetchAll(PDO::FETCH_COLUMN);

      return [
        'statuses' => $statuses,
        'priorities' => $priorities,
        'from_emails' => $fromEmails
      ];

    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Filter Options Error: ' . $e->getMessage());
      return [
        'statuses' => ['pending', 'processing', 'sent', 'failed', 'cancelled'],
        'priorities' => [1, 2, 3, 4, 5],
        'from_emails' => []
      ];
    }
  }

  /**
   * Export emails to CSV based on search criteria.
   */
  public static function exportEmails($params = []) {
    try {
      // Remove pagination for export
      unset($params['limit'], $params['offset']);

      $searchResult = self::searchEmails($params);
      $emails = $searchResult['emails'];

      // Prepare CSV data
      $csvData = [];
      $csvData[] = [
        'ID', 'To Email', 'From Email', 'Subject', 'Status', 'Priority',
        'Created Date', 'Sent Date', 'Retry Count', 'Error Message'
      ];

      foreach ($emails as $email) {
        $csvData[] = [
          $email['id'],
          $email['to_email'],
          $email['from_email'],
          $email['subject'],
          $email['status'],
          $email['priority'],
          $email['created_date'],
          $email['sent_date'] ?: '',
          $email['retry_count'],
          $email['error_message'] ?: ''
        ];
      }

      return $csvData;

    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Export Error: ' . $e->getMessage());
      throw $e;
    }
  }

  /**
   * Bulk actions on emails.
   */
  public static function bulkAction($action, $emailIds) {
    try {
      $pdo = self::getQueueConnection();

      if (empty($emailIds) || !is_array($emailIds)) {
        throw new Exception('No email IDs provided');
      }

      $placeholders = str_repeat('?,', count($emailIds) - 1) . '?';
      $affectedRows = 0;
      switch ($action) {
        case 'cancel':
          $sql = "UPDATE email_queue SET status = 'cancelled' WHERE id IN ($placeholders) AND status IN ('pending', 'failed')";
          $stmt = $pdo->prepare($sql);
          $stmt->execute($emailIds);
          $affectedRows = $stmt->rowCount();

          // Log the action
          foreach ($emailIds as $emailId) {
            self::logAction($emailId, 'bulk_cancelled', 'Email cancelled via bulk action');
          }
          break;

        case 'retry':
          $sql = "UPDATE email_queue SET status = 'pending', retry_count = 0, error_message = NULL, scheduled_date = NULL WHERE id IN ($placeholders) AND status = 'failed'";
          $stmt = $pdo->prepare($sql);
          $stmt->execute($emailIds);
          $affectedRows = $stmt->rowCount();

          // Log the action
          foreach ($emailIds as $emailId) {
            self::logAction($emailId, 'bulk_retried', 'Email reset for retry via bulk action');
          }
          break;

        case 'delete':
          // Only allow deletion of cancelled or failed emails
          $sql = "DELETE FROM email_queue WHERE id IN ($placeholders) AND status IN ('cancelled', 'failed')";
          $stmt = $pdo->prepare($sql);
          $stmt->execute($emailIds);
          $affectedRows = $stmt->rowCount();
          break;

        default:
          throw new Exception('Invalid bulk action');
      }

      return $affectedRows;

    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Email Queue Bulk Action Error: ' . $e->getMessage());
      throw $e;
    }
  }


}
