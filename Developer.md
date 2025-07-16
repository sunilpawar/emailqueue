# Email Queue Extension - Developer Documentation

## Architecture Overview

The Email Queue Extension is built using a modular architecture that separates concerns and provides extensibility:

```
CRM/Emailqueue/
├── BAO/           # Business Access Objects (data layer)
├── Form/          # Form controllers (presentation layer)
├── Page/          # Page controllers (presentation layer)
├── Mailer/        # Custom mailer implementations
├── Utils/         # Utility classes
└── Config.php     # Configuration management

api/v3/            # API endpoints
templates/         # Smarty templates
xml/              # Menu and route definitions
tests/            # Unit and integration tests
scripts/          # Deployment and maintenance scripts
```

## Core Components

### 1. Queue Mailer (CRM_Emailqueue_Mailer_QueueMailer)

The core component that intercepts emails and queues them instead of sending immediately.

**Key Methods:**
- `send()` - Queues email instead of sending
- `parseRecipients()` - Handles various recipient formats
- `getHtmlBody()` / `getTextBody()` - Extracts email content

### 2. Queue BAO (CRM_Emailqueue_BAO_Queue)

Handles all database operations for the email queue.

**Key Methods:**
- `addToQueue()` - Add email to queue
- `searchEmails()` - Advanced search functionality
- `getEmailPreview()` - Get email for preview
- `processQueue()` - Process queued emails
- `bulkAction()` - Bulk operations

### 3. Configuration (CRM_Emailqueue_Config)

Centralized configuration management with constants and helper methods.

**Features:**
- Default settings management
- Validation functions
- Environment-specific configuration

### 4. Error Handling (CRM_Emailqueue_Utils_ErrorHandler)

Comprehensive error handling and logging system.

**Features:**
- Multiple log levels
- Database logging
- Critical error alerts
- Exception handling

## Database Schema

### email_queue Table

```sql
CREATE TABLE email_queue (
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
  validation_score TINYINT UNSIGNED NULL,
  validation_warnings TEXT NULL,
  tracking_code VARCHAR(64) NULL,

  INDEX idx_status (status),
  INDEX idx_scheduled (scheduled_date),
  INDEX idx_priority (priority),
  INDEX idx_created (created_date),
  INDEX idx_status_priority_created (status, priority, created_date),
  INDEX idx_validation_score (validation_score),
  INDEX idx_tracking_code (tracking_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### email_queue_log Table

```sql
CREATE TABLE email_queue_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  queue_id INT NOT NULL,
  action VARCHAR(50) NOT NULL,
  message TEXT,
  created_date DATETIME NOT NULL,

  FOREIGN KEY (queue_id) REFERENCES email_queue(id) ON DELETE CASCADE,
  INDEX idx_queue_id (queue_id),
  INDEX idx_action (action),
  INDEX idx_created (created_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## API Reference

### Core APIs

#### Search Emails
```php
$result = civicrm_api3('Emailqueue', 'search', [
  'to_email' => 'user@example.com',
  'status' => ['pending', 'failed'],
  'date_from' => '2025-07-01',
  'limit' => 50,
  'order_by' => 'created_date',
  'order_dir' => 'DESC'
]);
```

#### Preview Email
```php
$email = civicrm_api3('Emailqueue', 'preview', [
  'id' => 12345
]);
```

#### Bulk Actions
```php
$result = civicrm_api3('Emailqueue', 'bulkaction', [
  'action' => 'cancel',
  'email_ids' => [123, 124, 125]
]);
```

### Administrative APIs

#### Health Check
```php
$health = civicrm_api3('EmailqueueAdmin', 'healthcheck');
```

#### System Metrics
```php
$metrics = civicrm_api3('EmailqueueAdmin', 'getmetrics');
```

#### Cleanup Operations
```php
$result = civicrm_api3('EmailqueueAdmin', 'cleanup', [
  'sent_retention_days' => 90,
  'cleanup_failed' => true
]);
```

## Hooks and Extension Points

### Email Processing Hooks

```php
/**
 * Modify email before queuing
 */
function mymodule_civicrm_emailqueue_pre_queue(&$emailData) {
  // Modify email data before queuing
  $emailData['priority'] = 1; // High priority
}

/**
 * Process email after queuing
 */
function mymodule_civicrm_emailqueue_post_queue($emailId, $emailData) {
  // Log or track queued email
}

/**
 * Modify email before sending
 */
function mymodule_civicrm_emailqueue_pre_send(&$emailData) {
  // Last-minute modifications before sending
  return true; // Return false to cancel sending
}

/**
 * Process email after sending
 */
function mymodule_civicrm_emailqueue_post_send($emailId, $result) {
  // Track sent email or update external systems
}
```

### Custom Validation

```php
/**
 * Add custom email validation
 */
function mymodule_civicrm_emailqueue_validate($email, &$validation) {
  // Add custom validation logic
  if (strpos($email, 'blocked-domain.com') !== false) {
    $validation['errors'][] = 'Domain is blocked';
  }
}
```

### Custom Mailer

```php
/**
 * Provide custom mailer for specific emails
 */
function mymodule_civicrm_emailqueue_get_mailer($emailData, &$mailer) {
  if ($emailData['priority'] == 1) {
    // Use premium mailer for high priority
    $mailer = new MyCustomMailer();
  }
}
```

## Development Setup

### Prerequisites

- PHP 7.2+
- MySQL 5.7+ or MariaDB 10.3+
- CiviCRM 5.50+
- PDO MySQL extension
- CV (CiviCRM CLI tool) - recommended

### Installation for Development

1. **Clone Repository**
```bash
cd /path/to/civicrm/ext
git clone [repository-url] com.yourorg.emailqueue
```

2. **Install Dependencies**
```bash
cd com.yourorg.emailqueue
composer install --dev
```

3. **Setup Test Database**
```bash
mysql -u root -p
CREATE DATABASE emailqueue_test;
GRANT ALL ON emailqueue_test.* TO 'civicrm_test'@'localhost';
```

4. **Configure for Testing**
```bash
cp config/samples/database.conf.sample config/database.test.conf
# Edit test configuration
```

5. **Install Extension**
```bash
cv ext:install com.yourorg.emailqueue
```

### Running Tests

```bash
# Unit tests
phpunit tests/phpunit/

# Integration tests
phpunit tests/integration/

# API tests
phpunit tests/api/

# All tests
phpunit
```

### Code Standards

Follow CiviCRM coding standards:

```bash
# Check code standards
phpcs --standard=CiviCRM src/

# Fix code standards
phpcbf --standard=CiviCRM src/
```

### Debugging

Enable debug mode:

```php
// In civicrm.settings.php or via API
Civi::settings()->set('emailqueue_log_level', 'debug');
```

Check logs:
- CiviCRM logs: `civicrm/ConfigAndLog/`
- Extension logs: Database `email_queue_log` table
- Error logs: Server error logs

## Testing Framework

### Unit Test Example

```php
class CRM_Emailqueue_BAO_QueueTest extends CiviUnitTestCase {

  public function testAddToQueue() {
    $emailData = [
      'to_email' => 'test@example.com',
      'subject' => 'Test Email',
      'body_html' => '<p>Test content</p>',
      'status' => 'pending',
      'priority' => 3
    ];

    $queueId = CRM_Emailqueue_BAO_Queue::addToQueue($emailData);

    $this->assertIsInt($queueId);
    $this->assertGreaterThan(0, $queueId);
  }
}
```

### Integration Test Example

```php
class CRM_Emailqueue_IntegrationTest extends CiviIntegrationTestCase {

  public function testEmailQueueing() {
    // Enable extension
    Civi::settings()->set('emailqueue_enabled', true);

    // Send test email
    $result = civicrm_api3('Email', 'send', [
      'to' => 'test@example.com',
      'subject' => 'Test Email',
      'html' => '<p>Test content</p>'
    ]);

    // Verify email was queued
    $queued = civicrm_api3('Emailqueue', 'search', [
      'to_email' => 'test@example.com',
      'status' => 'pending'
    ]);

    $this->assertEquals(1, $queued['count']);
  }
}
```

## Performance Optimization

### Database Optimization

1. **Indexes**
  - Composite indexes for common queries
  - Regular index maintenance
  - Query optimization

2. **Partitioning** (for large datasets)
```sql
ALTER TABLE email_queue PARTITION BY RANGE (YEAR(created_date)) (
  PARTITION p2024 VALUES LESS THAN (2025),
  PARTITION p2025 VALUES LESS THAN (2026),
  PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

3. **Archiving**
  - Move old emails to archive tables
  - Implement data retention policies
  - Regular cleanup procedures

### Application Optimization

1. **Batch Processing**
  - Optimize batch sizes
  - Implement efficient querying
  - Use prepared statements

2. **Caching**
  - Cache configuration settings
  - Cache filter options
  - Use Redis/Memcached for session data

3. **Connection Pooling**
  - Reuse database connections
  - Implement connection pooling
  - Monitor connection usage

## Security Considerations

### Data Protection

1. **Email Content**
  - Encrypt sensitive content
  - Sanitize HTML content
  - Validate email addresses

2. **Database Security**
  - Use dedicated database user
  - Limit database permissions
  - Enable SSL connections

3. **Access Control**
  - Implement role-based access
  - Log all administrative actions
  - Monitor for suspicious activity

### Input Validation

```php
// Example validation
function validateEmailData($data) {
  $errors = [];

  // Validate email address
  if (!filter_var($data['to_email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address';
  }

  // Check email size
  $size = strlen($data['body_html']) + strlen($data['body_text']);
  if ($size > CRM_Emailqueue_Config::getEmailSizeLimit()) {
    $errors[] = 'Email too large';
  }

  // Sanitize HTML content
  $data['body_html'] = CRM_Emailqueue_Utils_Email::sanitizeHtmlContent($data['body_html']);

  return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
}
```

## Deployment Strategies

### Production Deployment

1. **Blue-Green Deployment**
  - Maintain two identical environments
  - Switch traffic after testing
  - Quick rollback capability

2. **Rolling Updates**
  - Update servers one by one
  - Monitor during deployment
  - Gradual traffic migration

3. **Database Migrations**
  - Test migrations on staging
  - Backup before deployment
  - Monitor performance impact

### Monitoring

1. **Application Monitoring**
  - Queue size monitoring
  - Processing rate tracking
  - Error rate monitoring

2. **Infrastructure Monitoring**
  - Database performance
  - Server resources
  - Network connectivity

3. **Alerting**
  - Critical error alerts
  - Performance degradation
  - Capacity warnings

## Contributing

### Development Workflow

1. **Feature Development**
  - Create feature branch
  - Write tests first (TDD)
  - Implement feature
  - Update documentation

2. **Code Review**
  - Submit pull request
  - Peer review process
  - Address feedback
  - Final approval

3. **Release Process**
  - Version bumping
  - Changelog updates
  - Tag releases
  - Deploy to production

### Guidelines

- Follow PSR-12 coding standards
- Write comprehensive tests
- Document all public APIs
- Maintain backward compatibility
- Update user documentation

## Troubleshooting

### Common Issues

1. **Database Connection Issues**
  - Check credentials
  - Verify network connectivity
  - Check firewall settings
  - Review SSL configuration

2. **Performance Issues**
  - Analyze slow queries
  - Check index usage
  - Monitor resource usage
  - Optimize batch sizes

3. **Email Delivery Issues**
  - Verify SMTP configuration
  - Check email content
  - Review bounce handling
  - Monitor sending limits

### Debug Tools

```bash
# Check system status
cv api3 EmailqueueAdmin.getstatus

# Run health check
cv api3 EmailqueueAdmin.healthcheck

# Get error logs
cv api3 EmailqueueAdmin.geterrorlogs limit=20

# Test system
cv api3 EmailqueueAdmin.testsystem
```

## Resources

- [CiviCRM Developer Guide](https://docs.civicrm.org/dev/)
- [CiviCRM API Documentation](https://docs.civicrm.org/dev/en/latest/api/)
- [Extension Development](https://docs.civicrm.org/dev/en/latest/extensions/)
- [Testing Framework](https://docs.civicrm.org/dev/en/latest/testing/)

## License

This extension is licensed under AGPL-3.0. See LICENSE file for details.
