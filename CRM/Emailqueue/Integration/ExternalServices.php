<?php

/**
 * Integration with external email services like SendGrid, Mailgun, Amazon SES, etc.
 */
class CRM_Emailqueue_Integration_ExternalServices {

  /**
   * Get available external email services.
   */
  public static function getAvailableServices() {
    return [
      'sendgrid' => [
        'name' => 'SendGrid',
        'class' => 'CRM_Emailqueue_Integration_SendGrid',
        'required_settings' => ['api_key'],
        'features' => ['webhooks', 'tracking', 'templates', 'analytics']
      ],
      'mailgun' => [
        'name' => 'Mailgun',
        'class' => 'CRM_Emailqueue_Integration_Mailgun',
        'required_settings' => ['api_key', 'domain'],
        'features' => ['webhooks', 'tracking', 'validation']
      ],
      'amazon_ses' => [
        'name' => 'Amazon SES',
        'class' => 'CRM_Emailqueue_Integration_AmazonSES',
        'required_settings' => ['access_key', 'secret_key', 'region'],
        'features' => ['webhooks', 'bounce_handling', 'reputation']
      ],
      'postmark' => [
        'name' => 'Postmark',
        'class' => 'CRM_Emailqueue_Integration_Postmark',
        'required_settings' => ['server_token'],
        'features' => ['webhooks', 'tracking', 'templates']
      ],
      'sparkpost' => [
        'name' => 'SparkPost',
        'class' => 'CRM_Emailqueue_Integration_SparkPost',
        'required_settings' => ['api_key'],
        'features' => ['webhooks', 'tracking', 'analytics', 'ab_testing']
      ]
    ];
  }

  /**
   * Create external service instance.
   */
  public static function createService($serviceName, $settings = []) {
    $services = self::getAvailableServices();

    if (!isset($services[$serviceName])) {
      throw new Exception("Unknown email service: $serviceName");
    }

    $serviceConfig = $services[$serviceName];

    // Validate required settings
    foreach ($serviceConfig['required_settings'] as $setting) {
      if (empty($settings[$setting])) {
        throw new Exception("Missing required setting: $setting for $serviceName");
      }
    }

    $className = $serviceConfig['class'];

    if (!class_exists($className)) {
      throw new Exception("Service class not found: $className");
    }

    return new $className($settings);
  }

  /**
   * Test external service connection.
   */
  public static function testService($serviceName, $settings = []) {
    try {
      $service = self::createService($serviceName, $settings);
      return $service->testConnection();
    } catch (Exception $e) {
      return [
        'success' => false,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Send email via external service.
   */
  public static function sendViaExternalService($serviceName, $emailData, $settings = []) {
    try {
      $service = self::createService($serviceName, $settings);
      return $service->sendEmail($emailData);
    } catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e, [
        'service' => $serviceName,
        'email_data' => $emailData
      ]);
      throw $e;
    }
  }

  /**
   * Process webhook from external service.
   */
  public static function processWebhook($serviceName, $webhookData) {
    try {
      $service = self::createService($serviceName);
      return $service->processWebhook($webhookData);
    } catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e, [
        'service' => $serviceName,
        'webhook_data' => $webhookData
      ]);
      return false;
    }
  }
}

/**
 * Base class for external email service integrations.
 */
abstract class CRM_Emailqueue_Integration_BaseService {

  protected $settings;
  protected $apiEndpoint;
  protected $rateLimits = [];

  public function __construct($settings) {
    $this->settings = $settings;
    $this->validateSettings();
  }

  /**
   * Validate service settings.
   */
  abstract protected function validateSettings();

  /**
   * Test service connection.
   */
  abstract public function testConnection();

  /**
   * Send email via service.
   */
  abstract public function sendEmail($emailData);

  /**
   * Process webhook from service.
   */
  abstract public function processWebhook($webhookData);

  /**
   * Get service capabilities.
   */
  abstract public function getCapabilities();

  /**
   * Make HTTP request to service API.
   */
  protected function makeApiRequest($method, $endpoint, $data = null, $headers = []) {
    $url = $this->apiEndpoint . $endpoint;

    // Initialize cURL
    $ch = curl_init();

    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_CUSTOMREQUEST => strtoupper($method),
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_USERAGENT => 'CiviCRM-EmailQueue/1.0'
    ]);

    if ($data) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error) {
      throw new Exception("API request failed: $error");
    }

    $decodedResponse = json_decode($response, true);

    if ($httpCode >= 400) {
      $errorMessage = $decodedResponse['message'] ?? "HTTP $httpCode error";
      throw new Exception("API error: $errorMessage");
    }

    return $decodedResponse;
  }

  /**
   * Check rate limits before making request.
   */
  protected function checkRateLimit($type = 'default') {
    // Implement basic rate limiting
    $key = "emailqueue_ratelimit_{$type}_" . get_class($this);
    $current = Civi::cache()->get($key) ?: 0;

    $limit = $this->rateLimits[$type] ?? 100; // Default 100 requests per minute

    if ($current >= $limit) {
      throw new Exception("Rate limit exceeded for $type");
    }

    Civi::cache()->set($key, $current + 1, 60); // 1 minute TTL
  }

  /**
   * Format email data for service API.
   */
  protected function formatEmailData($emailData) {
    return [
      'to' => $emailData['to_email'],
      'from' => $emailData['from_email'],
      'subject' => $emailData['subject'],
      'html' => $emailData['body_html'],
      'text' => $emailData['body_text'],
      'headers' => json_decode($emailData['headers'] ?? '{}', true)
    ];
  }
}

/**
 * SendGrid integration.
 */
class CRM_Emailqueue_Integration_SendGrid extends CRM_Emailqueue_Integration_BaseService {

  protected $apiEndpoint = 'https://api.sendgrid.com/v3/';
  protected $rateLimits = ['send' => 600, 'api' => 1000]; // per minute

  protected function validateSettings() {
    if (empty($this->settings['api_key'])) {
      throw new Exception('SendGrid API key is required');
    }
  }

  public function testConnection() {
    try {
      $this->checkRateLimit('api');

      $response = $this->makeApiRequest('GET', 'user/profile', null, [
        'Authorization: Bearer ' . $this->settings['api_key'],
        'Content-Type: application/json'
      ]);

      return [
        'success' => true,
        'message' => 'Connection successful',
        'user' => $response['username'] ?? 'Unknown'
      ];

    } catch (Exception $e) {
      return [
        'success' => false,
        'error' => $e->getMessage()
      ];
    }
  }

  public function sendEmail($emailData) {
    $this->checkRateLimit('send');

    $sendGridData = [
      'personalizations' => [[
        'to' => [['email' => $emailData['to_email']]]
      ]],
      'from' => ['email' => $emailData['from_email']],
      'subject' => $emailData['subject'],
      'content' => []
    ];

    if (!empty($emailData['body_text'])) {
      $sendGridData['content'][] = [
        'type' => 'text/plain',
        'value' => $emailData['body_text']
      ];
    }

    if (!empty($emailData['body_html'])) {
      $sendGridData['content'][] = [
        'type' => 'text/html',
        'value' => $emailData['body_html']
      ];
    }

    // Add tracking settings
    $sendGridData['tracking_settings'] = [
      'click_tracking' => ['enable' => true],
      'open_tracking' => ['enable' => true]
    ];

    $response = $this->makeApiRequest('POST', 'mail/send', json_encode($sendGridData), [
      'Authorization: Bearer ' . $this->settings['api_key'],
      'Content-Type: application/json'
    ]);

    return [
      'success' => true,
      'message_id' => $response['message_id'] ?? null,
      'service' => 'sendgrid'
    ];
  }

  public function processWebhook($webhookData) {
    // Process SendGrid webhook events
    foreach ($webhookData as $event) {
      $this->processWebhookEvent($event);
    }

    return true;
  }

  protected function processWebhookEvent($event) {
    $eventType = $event['event'] ?? '';
    $messageId = $event['sg_message_id'] ?? '';

    // Update email status based on event
    switch ($eventType) {
      case 'delivered':
        $this->updateEmailStatus($messageId, 'sent');
        break;
      case 'bounce':
      case 'dropped':
        $this->updateEmailStatus($messageId, 'failed', $event['reason'] ?? '');
        break;
      case 'open':
        $this->trackEmailOpen($messageId);
        break;
      case 'click':
        $this->trackEmailClick($messageId, $event['url'] ?? '');
        break;
    }
  }

  protected function updateEmailStatus($messageId, $status, $error = '') {
    // Update email status in queue based on message ID
    try {
      $pdo = CRM_Emailqueue_BAO_Queue::getQueueConnection();

      $sql = "UPDATE email_queue SET status = ?, error_message = ? WHERE tracking_code = ?";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$status, $error, $messageId]);

    } catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e);
    }
  }

  protected function trackEmailOpen($messageId) {
    // Track email open event
    CRM_Emailqueue_Utils_ErrorHandler::info("Email opened: $messageId");
  }

  protected function trackEmailClick($messageId, $url) {
    // Track email click event
    CRM_Emailqueue_Utils_ErrorHandler::info("Email clicked: $messageId, URL: $url");
  }

  public function getCapabilities() {
    return [
      'send' => true,
      'webhooks' => true,
      'tracking' => true,
      'templates' => true,
      'analytics' => true,
      'suppression' => true
    ];
  }
}

/**
 * Mailgun integration.
 */
class CRM_Emailqueue_Integration_Mailgun extends CRM_Emailqueue_Integration_BaseService {

  protected $apiEndpoint = 'https://api.mailgun.net/v3/';
  protected $rateLimits = ['send' => 300, 'api' => 600];

  protected function validateSettings() {
    if (empty($this->settings['api_key'])) {
      throw new Exception('Mailgun API key is required');
    }
    if (empty($this->settings['domain'])) {
      throw new Exception('Mailgun domain is required');
    }
  }

  public function testConnection() {
    try {
      $this->checkRateLimit('api');

      $response = $this->makeApiRequest('GET', $this->settings['domain'], null, [
        'Authorization: Basic ' . base64_encode('api:' . $this->settings['api_key'])
      ]);

      return [
        'success' => true,
        'message' => 'Connection successful',
        'domain' => $response['domain']['name'] ?? $this->settings['domain']
      ];

    } catch (Exception $e) {
      return [
        'success' => false,
        'error' => $e->getMessage()
      ];
    }
  }

  public function sendEmail($emailData) {
    $this->checkRateLimit('send');

    $postData = [
      'from' => $emailData['from_email'],
      'to' => $emailData['to_email'],
      'subject' => $emailData['subject'],
      'text' => $emailData['body_text'] ?? '',
      'html' => $emailData['body_html'] ?? '',
      'o:tracking' => 'yes',
      'o:tracking-clicks' => 'yes',
      'o:tracking-opens' => 'yes'
    ];

    $response = $this->makeApiRequest('POST', $this->settings['domain'] . '/messages',
      http_build_query($postData), [
        'Authorization: Basic ' . base64_encode('api:' . $this->settings['api_key']),
        'Content-Type: application/x-www-form-urlencoded'
      ]);

    return [
      'success' => true,
      'message_id' => $response['id'] ?? null,
      'service' => 'mailgun'
    ];
  }

  public function processWebhook($webhookData) {
    $eventType = $webhookData['event'] ?? '';
    $messageId = $webhookData['message']['headers']['message-id'] ?? '';

    switch ($eventType) {
      case 'delivered':
        $this->updateEmailStatus($messageId, 'sent');
        break;
      case 'failed':
      case 'rejected':
        $this->updateEmailStatus($messageId, 'failed', $webhookData['reason'] ?? '');
        break;
    }

    return true;
  }

  public function getCapabilities() {
    return [
      'send' => true,
      'webhooks' => true,
      'tracking' => true,
      'validation' => true,
      'routing' => true
    ];
  }
}

/**
 * Service factory for creating appropriate service instances.
 */
class CRM_Emailqueue_Integration_ServiceFactory {

  /**
   * Create service instance based on configuration.
   */
  public static function createFromConfig() {
    $serviceName = CRM_Emailqueue_Config::getSetting('external_service');

    if (empty($serviceName) || $serviceName === 'none') {
      return NULL;
    }

    $settings = [
      'api_key' => CRM_Emailqueue_Config::getSetting('external_service_api_key'),
      'domain' => CRM_Emailqueue_Config::getSetting('external_service_domain'),
      'region' => CRM_Emailqueue_Config::getSetting('external_service_region'),
      'access_key' => CRM_Emailqueue_Config::getSetting('external_service_access_key'),
      'secret_key' => CRM_Emailqueue_Config::getSetting('external_service_secret_key'),
      'server_token' => CRM_Emailqueue_Config::getSetting('external_service_server_token')
    ];

    // Remove empty settings
    $settings = array_filter($settings);

    return CRM_Emailqueue_Integration_ExternalServices::createService($serviceName, $settings);
  }

  /**
   * Check if external service is configured and enabled.
   */
  public static function isExternalServiceEnabled() {
    $serviceName = CRM_Emailqueue_Config::getSetting('external_service');
    return !empty($serviceName) && $serviceName !== 'none';
  }

  /**
   * Get configured service name.
   */
  public static function getConfiguredService() {
    return CRM_Emailqueue_Config::getSetting('external_service', 'none');
  }
}
