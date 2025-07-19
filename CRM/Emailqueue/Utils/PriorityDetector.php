<?php

/**
 * Intelligent priority detection for emails based on subject and headers.
 */
class CRM_Emailqueue_Utils_PriorityDetector {

  // Priority constants
  const PRIORITY_URGENT = 1;      // System critical, bounces, errors
  const PRIORITY_HIGH = 2;        // Transactional, confirmations
  const PRIORITY_NORMAL = 3;      // Regular communications (default)
  const PRIORITY_LOW = 4;         // Marketing, newsletters
  const PRIORITY_BULK = 5;        // Mass mailings, automated

  /**
   * Detect email priority based on subject, headers, and content.
   *
   * @param array $emailData
   *   Email data containing subject, headers, etc.
   *
   * @return int
   *   Priority level (1-5).
   */
  public static function detectPriority(array $emailData): int {
    $priority = self::PRIORITY_NORMAL; // Default priority
    $factors = [];

    try {
      // Parse headers
      $headers = self::parseHeaders($emailData['headers'] ?? '');
      $subject = $emailData['subject'] ?? '';
      $fromEmail = $emailData['from_email'] ?? '';
      $toEmail = $emailData['to_email'] ?? '';

      // Check CiviCRM-specific headers (highest priority)
      $headerPriority = self::detectFromCiviHeaders($headers);
      if ($headerPriority !== NULL) {
        $priority = $headerPriority;
        $factors[] = 'CiviCRM header detection';
      }

      // Check standard email headers
      $standardPriority = self::detectFromStandardHeaders($headers);
      if ($standardPriority !== NULL && ($headerPriority === NULL || $standardPriority < $priority)) {
        $priority = $standardPriority;
        $factors[] = 'Standard header priority';
      }

      // Analyze subject line
      $subjectPriority = self::detectFromSubject($subject);
      if ($subjectPriority !== NULL && $subjectPriority < $priority) {
        $priority = $subjectPriority;
        $factors[] = 'Subject line analysis';
      }

      // Check email type patterns
      $typePriority = self::detectFromEmailType($fromEmail, $toEmail, $subject);
      if ($typePriority !== NULL && $typePriority < $priority) {
        $priority = $typePriority;
        $factors[] = 'Email type detection';
      }

      // Check content patterns
      $contentPriority = self::detectFromContent($emailData);
      if ($contentPriority !== NULL && $contentPriority < $priority) {
        $priority = $contentPriority;
        $factors[] = 'Content analysis';
      }

      // Log priority decision if debug mode is enabled
      if (CRM_Emailqueue_Config::isDebugMode()) {
        CRM_Emailqueue_Utils_ErrorHandler::debug("Priority detected: {$priority}", [
          'email_subject' => $subject,
          'factors' => $factors,
          'headers' => array_keys($headers)
        ]);
      }

      return max(1, min(5, $priority)); // Ensure priority is within valid range

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e, [
        'operation' => 'priority_detection',
        'email_subject' => $subject ?? 'unknown'
      ]);
      return self::PRIORITY_NORMAL; // Fallback to normal priority
    }
  }

  /**
   * Detect priority from CiviCRM-specific headers.
   */
  protected static function detectFromCiviHeaders(array $headers): ?int {
    // System and bounce emails - highest priority
    // Bounce emails are generally low priority, but can be urgent if hard bounce
    if (isset($headers['X-CiviMail-Bounce'])) {
      return self::PRIORITY_BULK;
    }

    if (isset($headers['X-CiviMail-System'])) {
      return self::PRIORITY_URGENT;
    }

    // Scheduled job and system notifications
    if (isset($headers['X-CiviMail-Scheduler'])) {
      return self::PRIORITY_HIGH;
    }

    // Transactional emails
    if (isset($headers['X-CiviMail-Transactional'])) {
      return self::PRIORITY_HIGH;
    }

    // Mass mailings and newsletters
    if (isset($headers['X-CiviMail-Newsletter']) || isset($headers['X-CiviMail-Mass'])) {
      return self::PRIORITY_LOW;
    }

    // CiviMail mailings (general)
    if (isset($headers['X-CiviMail-Track']) || isset($headers['X-CiviMail-ID'])) {
      return self::PRIORITY_LOW;
    }

    // Automated messages
    if (isset($headers['X-CiviMail-Auto']) || isset($headers['Auto-Submitted'])) {
      return self::PRIORITY_BULK;
    }

    return NULL; // No CiviCRM header priority detected
  }

  /**
   * Detect priority from standard email headers.
   */
  protected static function detectFromStandardHeaders(array $headers): ?int {
    // Check X-Priority header (1=highest, 5=lowest)
    if (isset($headers['X-Priority'])) {
      $xPriority = (int)$headers['X-Priority'];
      if ($xPriority >= 1 && $xPriority <= 5) {
        return $xPriority;
      }
    }

    // Check Importance header
    // Indicates the sender's opinion of the message's importance.
    // Used By: Email clients like Outlook, Thunderbird, etc.
    if (isset($headers['Importance'])) {
      switch (strtolower($headers['Importance'])) {
        case 'high':
          return self::PRIORITY_HIGH;
        case 'low':
          return self::PRIORITY_LOW;
        case 'normal':
        default:
          return self::PRIORITY_NORMAL;
      }
    }

    // Check Priority header
    // Indicates the priority level for the message.
    // Used mostly by Microsoft Outlook and some other clients.
    if (isset($headers['Priority'])) {
      switch (strtolower($headers['Priority'])) {
        case 'urgent':
          return self::PRIORITY_URGENT;
        case 'high':
          return self::PRIORITY_HIGH;
        case 'low':
          return self::PRIORITY_LOW;
        case 'normal':
        default:
          return self::PRIORITY_NORMAL;
      }
    }

    // Check Message-ID patterns
    // Provides a globally unique identifier for the email message.
    if (isset($headers['Message-ID'])) {
      $messageId = strtolower($headers['Message-ID']);
      if (strpos($messageId, 'bounce') !== FALSE || strpos($messageId, 'error') !== FALSE) {
        return self::PRIORITY_URGENT;
      }
    }

    return NULL; // No standard header priority detected
  }

  /**
   * Detect priority from email subject line.
   */
  protected static function detectFromSubject(string $subject): ?int {
    $subject = strtolower(trim($subject));

    if (empty($subject)) {
      return NULL;
    }

    // Get configured subject patterns
    $patterns = self::getSubjectPatterns();

    foreach ($patterns as $priority => $patternList) {
      foreach ($patternList as $pattern) {
        if (is_array($pattern)) {
          // Pattern with options
          $regex = $pattern['pattern'];
          $options = $pattern['options'] ?? [];
        }
        else {
          // Simple string pattern
          $regex = $pattern;
          $options = [];
        }

        if (self::matchesPattern($subject, $regex, $options)) {
          return $priority;
        }
      }
    }

    return NULL; // No subject pattern matched
  }

  /**
   * Get configurable subject line patterns for priority detection.
   */
  protected static function getSubjectPatterns(): array {
    // Get user-configured patterns or use defaults
    $customPatterns = CRM_Emailqueue_Config::getSetting('priority_subject_patterns', []);

    if (!empty($customPatterns)) {
      return $customPatterns;
    }

    // Default patterns (can be overridden in settings)
    return [
      self::PRIORITY_URGENT => [
        '/urgent|emergency|critical|asap|immediate/i',
        '/bounce|undeliverable|failed|error|mailer.daemon/i',
        '/system (down|error|alert|failure)/i',
        '/account (suspended|locked|compromised)/i',
        '/security (alert|breach|warning)/i'
      ],

      self::PRIORITY_HIGH => [
        '/important|priority|time.?sensitive/i',
        '/confirmation|receipt|invoice|payment/i',
        '/activation|verification|authenticate/i',
        '/password|reset|recovery/i',
        '/registration|signup|welcome/i',
        '/reminder|expir(ing|ed)|deadline/i',
        '/alert|notification|update/i'
      ],

      self::PRIORITY_LOW => [
        '/newsletter|digest|weekly|monthly/i',
        '/marketing|promotion|offer|sale|discount/i',
        '/announcement|news|update/i',
        '/survey|feedback|review/i',
        '/social|share|like|follow/i'
      ],

      self::PRIORITY_BULK => [
        '/unsubscribe|opt.?out/i',
        '/bulk|mass|broadcast/i',
        '/automated|auto.?generated/i',
        '/do.?not.?reply|noreply/i',
        '/list|mailing/i'
      ]
    ];
  }

  /**
   * Detect priority from email type patterns.
   */
  protected static function detectFromEmailType(string $fromEmail, string $toEmail, string $subject): ?int {
    $fromEmail = strtolower($fromEmail);
    $toEmail = strtolower($toEmail);

    // System/daemon emails
    if (preg_match('/(mailer.daemon|postmaster|bounce|noreply|no.reply|donotreply)@/i', $fromEmail)) {
      return self::PRIORITY_URGENT;
    }

    // Security-related emails
    if (preg_match('/(security|admin|system|support)@/i', $fromEmail)) {
      return self::PRIORITY_HIGH;
    }

    // Marketing emails
    if (preg_match('/(marketing|newsletter|news|promo|offers?)@/i', $fromEmail)) {
      return self::PRIORITY_LOW;
    }

    // Automated emails
    if (preg_match('/(auto|automated|robot|bot)@/i', $fromEmail)) {
      return self::PRIORITY_BULK;
    }

    return NULL; // No email type priority detected
  }

  /**
   * Detect priority from email content.
   */
  protected static function detectFromContent(array $emailData): ?int {
    $bodyHtml = strtolower($emailData['body_html'] ?? '');
    $bodyText = strtolower($emailData['body_text'] ?? '');

    $content = $bodyHtml . ' ' . $bodyText;

    if (empty($content)) {
      return NULL;
    }

    // Check for urgent content patterns
    $urgentPatterns = [
      '/account.*(suspended|locked|disabled|blocked)/i',
      '/payment.*(failed|declined|overdue)/i',
      '/security.*(breach|alert|violation)/i',
      '/system.*(maintenance|outage|error)/i'
    ];

    foreach ($urgentPatterns as $pattern) {
      if (preg_match($pattern, $content)) {
        return self::PRIORITY_URGENT;
      }
    }

    // Check for transactional content patterns
    $transactionalPatterns = [
      '/order.*(confirmation|receipt)/i',
      '/payment.*(received|processed)/i',
      '/account.*(created|activated)/i',
      '/subscription.*(activated|renewed)/i'
    ];

    foreach ($transactionalPatterns as $pattern) {
      if (preg_match($pattern, $content)) {
        return self::PRIORITY_HIGH;
      }
    }

    return NULL; // No content priority detected
  }

  /**
   * Check if subject matches a pattern.
   */
  protected static function matchesPattern(string $subject, string $pattern, array $options = []): bool {
    $flags = $options['flags'] ?? 'i'; // Case-insensitive by default

    // Handle different pattern types
    if (strpos($pattern, '/') === 0) {
      // Regex pattern
      return preg_match($pattern, $subject);
    }
    else {
      // Simple string contains check
      return strpos($subject, strtolower($pattern)) !== FALSE;
    }
  }

  /**
   * Parse email headers into associative array.
   */
  protected static function parseHeaders($headers): array {
    if (is_array($headers)) {
      return $headers;
    }

    if (is_string($headers)) {
      $parsed = json_decode($headers, TRUE);
      if (is_array($parsed)) {
        return $parsed;
      }
    }

    return [];
  }

  /**
   * Get priority name for display.
   */
  public static function getPriorityName(int $priority): string {
    switch ($priority) {
      case self::PRIORITY_URGENT:
        return 'Urgent';
      case self::PRIORITY_HIGH:
        return 'High';
      case self::PRIORITY_NORMAL:
        return 'Normal';
      case self::PRIORITY_LOW:
        return 'Low';
      case self::PRIORITY_BULK:
        return 'Bulk';
      default:
        return 'Unknown';
    }
  }

  /**
   * Get priority color for UI display.
   */
  public static function getPriorityColor(int $priority): string {
    switch ($priority) {
      case self::PRIORITY_URGENT:
        return '#dc3545'; // Red
      case self::PRIORITY_HIGH:
        return '#fd7e14'; // Orange
      case self::PRIORITY_NORMAL:
        return '#ffc107'; // Yellow
      case self::PRIORITY_LOW:
        return '#20c997'; // Teal
      case self::PRIORITY_BULK:
        return '#6c757d'; // Gray
      default:
        return '#6c757d';
    }
  }

  /**
   * Get all priority levels with metadata.
   */
  public static function getAllPriorities(): array {
    return [
      self::PRIORITY_URGENT => [
        'name' => 'Urgent',
        'description' => 'System critical, bounces, security alerts',
        'color' => '#dc3545',
        'examples' => ['Bounce notifications', 'Security alerts', 'System errors']
      ],
      self::PRIORITY_HIGH => [
        'name' => 'High',
        'description' => 'Transactional emails, confirmations',
        'color' => '#fd7e14',
        'examples' => ['Payment confirmations', 'Account activations', 'Password resets']
      ],
      self::PRIORITY_NORMAL => [
        'name' => 'Normal',
        'description' => 'Regular communications',
        'color' => '#ffc107',
        'examples' => ['General notifications', 'Regular correspondence']
      ],
      self::PRIORITY_LOW => [
        'name' => 'Low',
        'description' => 'Marketing emails, newsletters',
        'color' => '#20c997',
        'examples' => ['Newsletters', 'Marketing campaigns', 'Announcements']
      ],
      self::PRIORITY_BULK => [
        'name' => 'Bulk',
        'description' => 'Mass mailings, automated messages',
        'color' => '#6c757d',
        'examples' => ['Mass mailings', 'Automated messages', 'Bulk updates']
      ]
    ];
  }

  /**
   * Test priority detection with sample data.
   */
  public static function testPriorityDetection(): array {
    $testCases = [
      // Urgent priority tests
      [
        'subject' => 'URGENT: Email Bounce Notification',
        'headers' => ['X-CiviMail-Bounce' => 'hard'],
        'expected' => self::PRIORITY_URGENT
      ],
      [
        'subject' => 'Critical System Alert',
        'headers' => [],
        'expected' => self::PRIORITY_URGENT
      ],

      // High priority tests
      [
        'subject' => 'Payment Confirmation #12345',
        'headers' => ['X-Priority' => '2'],
        'expected' => self::PRIORITY_HIGH
      ],
      [
        'subject' => 'Account Activation Required',
        'headers' => ['X-CiviMail-Transactional' => 'true'],
        'expected' => self::PRIORITY_HIGH
      ],

      // Normal priority tests
      [
        'subject' => 'General Update',
        'headers' => [],
        'expected' => self::PRIORITY_NORMAL
      ],

      // Low priority tests
      [
        'subject' => 'Monthly Newsletter',
        'headers' => ['X-CiviMail-Newsletter' => 'true'],
        'expected' => self::PRIORITY_LOW
      ],

      // Bulk priority tests
      [
        'subject' => 'Automated System Update',
        'headers' => ['Auto-Submitted' => 'auto-generated'],
        'expected' => self::PRIORITY_BULK
      ]
    ];

    $results = [];
    foreach ($testCases as $index => $testCase) {
      $emailData = [
        'subject' => $testCase['subject'],
        'headers' => json_encode($testCase['headers']),
        'from_email' => 'test@example.com',
        'to_email' => 'recipient@example.com'
      ];

      $detectedPriority = self::detectPriority($emailData);
      $results[] = [
        'test_case' => $index + 1,
        'subject' => $testCase['subject'],
        'expected' => $testCase['expected'],
        'detected' => $detectedPriority,
        'passed' => $detectedPriority === $testCase['expected'],
        'expected_name' => self::getPriorityName($testCase['expected']),
        'detected_name' => self::getPriorityName($detectedPriority)
      ];
    }

    return $results;
  }

  /**
   * Get priority detection statistics.
   */
  public static function getPriorityStats(): array {
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $sql = "
        SELECT
          priority,
          COUNT(*) as count,
          COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_count,
          COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count,
          AVG(CASE WHEN status = 'sent' THEN TIMESTAMPDIFF(SECOND, created_date, sent_date) END) as avg_processing_time
        FROM email_queue
        WHERE created_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY priority
        ORDER BY priority
      ";

      $stmt = $pdo->query($sql);
      $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $priorities = self::getAllPriorities();
      $result = [];

      foreach ($stats as $stat) {
        $priority = (int)$stat['priority'];
        $priorityInfo = $priorities[$priority] ?? ['name' => 'Unknown', 'color' => '#6c757d'];

        $result[] = [
          'priority' => $priority,
          'name' => $priorityInfo['name'],
          'color' => $priorityInfo['color'],
          'count' => (int)$stat['count'],
          'sent_count' => (int)$stat['sent_count'],
          'failed_count' => (int)$stat['failed_count'],
          'success_rate' => $stat['count'] > 0 ? round(($stat['sent_count'] / $stat['count']) * 100, 2) : 0,
          'avg_processing_time' => $stat['avg_processing_time'] ? round($stat['avg_processing_time'], 2) : NULL
        ];
      }

      return $result;

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
      return [];
    }
  }

  /**
   * Update priority detection configuration.
   */
  public static function updatePriorityConfig(array $config): bool {
    try {
      // Validate configuration
      if (isset($config['subject_patterns'])) {
        foreach ($config['subject_patterns'] as $priority => $patterns) {
          if (!is_int($priority) || $priority < 1 || $priority > 5) {
            throw new Exception("Invalid priority level: $priority");
          }
          if (!is_array($patterns)) {
            throw new Exception("Patterns must be an array for priority $priority");
          }
        }
      }

      // Save configuration
      CRM_Emailqueue_Config::setSetting('priority_subject_patterns', $config['subject_patterns'] ?? []);
      CRM_Emailqueue_Config::setSetting('priority_detection_enabled', $config['enabled'] ?? TRUE);
      CRM_Emailqueue_Config::setSetting('priority_detection_log_decisions', $config['log_decisions'] ?? FALSE);

      return TRUE;

    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
      return FALSE;
    }
  }
}
