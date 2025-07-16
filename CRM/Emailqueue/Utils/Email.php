<?php

/**
 * Email utility functions for the Email Queue extension.
 */
class CRM_Emailqueue_Utils_Email {

  /**
   * Validate email address with comprehensive checks.
   */
  public static function validateEmail($email) {
    $result = [
      'is_valid' => FALSE,
      'errors' => [],
      'warnings' => []
    ];

    // Basic format validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $result['errors'][] = 'Invalid email format';
      return $result;
    }

    // Length checks
    if (strlen($email) > 320) { // RFC 5321 limit
      $result['errors'][] = 'Email address too long';
      return $result;
    }

    // Split email into local and domain parts
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
      $result['errors'][] = 'Invalid email structure';
      return $result;
    }

    [$local, $domain] = $parts;

    // Local part validation
    if (strlen($local) > 64) {
      $result['errors'][] = 'Local part too long';
      return $result;
    }

    if (empty($local)) {
      $result['errors'][] = 'Local part cannot be empty';
      return $result;
    }

    // Domain validation
    if (strlen($domain) > 253) {
      $result['errors'][] = 'Domain too long';
      return $result;
    }

    if (empty($domain)) {
      $result['errors'][] = 'Domain cannot be empty';
      return $result;
    }

    // Check for common typos in popular domains
    $commonDomains = [
      'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
      'aol.com', 'icloud.com', 'protonmail.com'
    ];

    $suggestions = self::suggestDomainCorrection($domain, $commonDomains);
    if (!empty($suggestions)) {
      $result['warnings'][] = 'Did you mean: ' . implode(', ', $suggestions) . '?';
    }

    // Check for disposable email domains
    if (self::isDisposableEmailDomain($domain)) {
      $result['warnings'][] = 'This appears to be a disposable email address';
    }

    // Check for role-based email addresses
    if (self::isRoleBasedEmail($local)) {
      $result['warnings'][] = 'This appears to be a role-based email address';
    }

    $result['is_valid'] = empty($result['errors']);
    return $result;
  }

  /**
   * Suggest domain corrections for common typos.
   */
  protected static function suggestDomainCorrection($domain, $commonDomains) {
    $suggestions = [];
    $domain = strtolower($domain);

    foreach ($commonDomains as $correctDomain) {
      $distance = levenshtein($domain, $correctDomain);

      // Suggest if the edit distance is 1-2 characters
      if ($distance > 0 && $distance <= 2) {
        $suggestions[] = $correctDomain;
      }
    }

    return array_slice($suggestions, 0, 3); // Limit to 3 suggestions
  }

  /**
   * Check if domain is a known disposable email provider.
   */
  protected static function isDisposableEmailDomain($domain) {
    $disposableDomains = [
      '10minutemail.com', 'tempmail.org', 'guerrillamail.com',
      'mailinator.com', 'yopmail.com', 'temp-mail.org',
      'throwaway.email', 'getnada.com', 'maildrop.cc'
    ];

    return in_array(strtolower($domain), $disposableDomains);
  }

  /**
   * Check if email is role-based (info@, admin@, etc.).
   */
  protected static function isRoleBasedEmail($localPart) {
    $roleBasedPrefixes = [
      'admin', 'administrator', 'info', 'support', 'help',
      'sales', 'marketing', 'noreply', 'no-reply', 'postmaster',
      'webmaster', 'hostmaster', 'listserv', 'abuse', 'security'
    ];

    return in_array(strtolower($localPart), $roleBasedPrefixes);
  }

  /**
   * Extract email addresses from various formats.
   */
  public static function extractEmails($input) {
    if (is_array($input)) {
      return array_map([self::class, 'extractSingleEmail'], $input);
    }

    return [self::extractSingleEmail($input)];
  }

  /**
   * Extract single email address from various formats.
   */
  protected static function extractSingleEmail($input) {
    if (empty($input)) {
      return '';
    }

    // Handle "Name <email@domain.com>" format
    if (preg_match('/<([^>]+)>/', $input, $matches)) {
      return trim($matches[1]);
    }

    // Handle "email@domain.com (Name)" format
    if (preg_match('/^([^\s(]+)/', $input, $matches)) {
      return trim($matches[1]);
    }

    return trim($input);
  }

  /**
   * Normalize email address for storage.
   */
  public static function normalizeEmail($email) {
    $email = trim($email);
    $email = strtolower($email);

    // Extract the actual email address
    $email = self::extractSingleEmail($email);

    return $email;
  }

  /**
   * Check if email is likely to bounce.
   */
  public static function isLikelyToBounce($email) {
    $reasons = [];

    // Check against known bounce patterns
    $bouncePatterns = [
      '/noreply/i' => 'No-reply address',
      '/donotreply/i' => 'Do-not-reply address',
      '/test@/i' => 'Test email address',
      '/example\./i' => 'Example domain',
    ];

    foreach ($bouncePatterns as $pattern => $reason) {
      if (preg_match($pattern, $email)) {
        $reasons[] = $reason;
      }
    }

    return [
      'likely_to_bounce' => !empty($reasons),
      'reasons' => $reasons
    ];
  }

  /**
   * Get email reputation score (0-100).
   */
  public static function getEmailReputationScore($email) {
    $score = 100;
    $factors = [];

    $validation = self::validateEmail($email);

    // Deduct points for validation issues
    if (!$validation['is_valid']) {
      $score -= 50;
      $factors[] = 'Invalid format';
    }

    if (!empty($validation['warnings'])) {
      $score -= count($validation['warnings']) * 10;
      $factors = array_merge($factors, $validation['warnings']);
    }

    // Check bounce likelihood
    $bounceCheck = self::isLikelyToBounce($email);
    if ($bounceCheck['likely_to_bounce']) {
      $score -= 20;
      $factors = array_merge($factors, $bounceCheck['reasons']);
    }

    // Domain-specific checks
    $domain = substr(strrchr($email, '@'), 1);
    if ($domain) {
      // Check if domain has MX record
      if (!checkdnsrr($domain, 'MX')) {
        $score -= 30;
        $factors[] = 'No MX record found';
      }
    }

    $score = max(0, min(100, $score));

    return [
      'score' => $score,
      'factors' => $factors,
      'grade' => self::getScoreGrade($score)
    ];
  }

  /**
   * Convert numerical score to letter grade.
   */
  protected static function getScoreGrade($score) {
    if ($score >= 90) {
      return 'A';
    }
    if ($score >= 80) {
      return 'B';
    }
    if ($score >= 70) {
      return 'C';
    }
    if ($score >= 60) {
      return 'D';
    }
    return 'F';
  }

  /**
   * Sanitize email content for safe storage.
   */
  public static function sanitizeEmailContent($content, $type = 'html') {
    if (empty($content)) {
      return '';
    }

    if ($type === 'html') {
      return self::sanitizeHtmlContent($content);
    }
    else {
      return self::sanitizeTextContent($content);
    }
  }

  /**
   * Sanitize HTML email content.
   */
  protected static function sanitizeHtmlContent($html) {
    // Remove potentially dangerous elements and attributes
    $allowedTags = [
      'p', 'br', 'div', 'span', 'a', 'strong', 'b', 'em', 'i',
      'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
      'table', 'tr', 'td', 'th', 'thead', 'tbody', 'img'
    ];

    $html = strip_tags($html, '<' . implode('><', $allowedTags) . '>');

    // Remove dangerous attributes
    $html = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    $html = preg_replace('/javascript\s*:/i', '', $html);
    $html = preg_replace('/data\s*:/i', '', $html);
    $html = preg_replace('/vbscript\s*:/i', '', $html);

    return $html;
  }

  /**
   * Sanitize text email content.
   */
  protected static function sanitizeTextContent($text) {
    // Remove control characters except line breaks and tabs
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

    // Normalize line endings
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    return trim($text);
  }

  /**
   * Parse email headers from various formats.
   */
  public static function parseHeaders($headers) {
    if (is_string($headers)) {
      $headers = json_decode($headers, TRUE) ?: [];
    }

    if (!is_array($headers)) {
      return [];
    }

    $parsed = [];

    foreach ($headers as $key => $value) {
      $key = ucfirst(strtolower(trim($key)));
      $parsed[$key] = trim($value);
    }

    return $parsed;
  }

  /**
   * Generate email tracking information.
   */
  public static function generateTrackingInfo($emailId, $recipientEmail) {
    return [
      'email_id' => $emailId,
      'recipient' => $recipientEmail,
      'tracking_code' => self::generateTrackingCode($emailId, $recipientEmail),
      'timestamp' => date('Y-m-d H:i:s')
    ];
  }

  /**
   * Generate unique tracking code for email.
   */
  protected static function generateTrackingCode($emailId, $recipientEmail) {
    $data = $emailId . '|' . $recipientEmail . '|' . time();
    return hash('sha256', $data);
  }
}
