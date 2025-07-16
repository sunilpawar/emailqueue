# CiviCRM Email Queue Extension

This extension provides an alternative email system for CiviCRM that queues emails in a separate database for delayed processing, instead of sending them immediately via SMTP.

## Features

- **Email Queuing**: Emails are stored in a separate database queue instead of being sent immediately
- **Separate Database**: Uses a dedicated database (not CiviCRM's database) for storing email queue
- **Retry Logic**: Automatic retry with exponential backoff for failed emails
- **Priority Support**: Email priority levels for queue processing order
- **Admin Interface**: Settings page and monitoring dashboard
- **Cron Integration**: Automatic processing via CiviCRM's cron system
- **API Support**: Full API support for queue management

## Installation

1. **Download and Install**:
   ```bash
   cd /path/to/civicrm/ext
   git clone [repository-url] com.yourorg.emailqueue
   ```

2. **Install via CiviCRM**:
  - Go to Administer → System Settings → Extensions
  - Find "Email Queue System" and click Install

3. **Create Email Queue Database**:
   ```sql
   CREATE DATABASE emailqueue;
   GRANT ALL PRIVILEGES ON emailqueue.* TO 'emailqueue_user'@'localhost' IDENTIFIED BY 'your_password';
   FLUSH PRIVILEGES;
   ```

## Configuration

### 1. Enable the Extension

Navigate to **Mailings → Email Queue Settings** and configure:

- **Enable Email Queue System**: Check to activate the system
- **Database Host**: Hostname for the email queue database (e.g., localhost)
- **Database Name**: Name of the email queue database (e.g., emailqueue)
- **Database Username**: Database user with read/write access
- **Database Password**: Database user password
- **Batch Size**: Number of emails to process per cron run (default: 50)
- **Max Retry Attempts**: Maximum retry attempts for failed emails (default: 3)

### 2. Test Database Connection

Use the "Test Database Connection" button to verify your database settings. The extension will automatically create the required tables.

### 3. Configure Cron Job

The email queue is processed automatically via CiviCRM's cron system. Ensure your CiviCRM cron is running:

```bash
# Add to crontab (adjust path as needed)
*/5 * * * * /path/to/civicrm/bin/cron.php -u username -p password
```

Or use the API to process the queue manually:
```bash
cv api3 Emailqueue.processqueue
```

## Database Schema

The extension creates two tables in the separate database:

### email_queue
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
  error_message TEXT
);
```

### email_queue_log
```sql
CREATE TABLE email_queue_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  queue_id INT NOT NULL,
  action VARCHAR(50) NOT NULL,
  message TEXT,
  created_date DATETIME NOT NULL,
  FOREIGN KEY (queue_id) REFERENCES email_queue(id) ON DELETE CASCADE
);
```

## Monitoring

### Email Queue Monitor

Access the monitor at **Mailings → Email Queue Monitor** to:

- View queue statistics (pending, sent, failed emails)
- See recent email activity
- Review failed emails with error messages
- Manually process the queue
- Retry failed emails
- Cancel pending emails

### Queue Statistics

The monitor shows:
- **Pending**: Emails waiting to be sent
- **Processing**: Emails currently being processed
- **Sent**: Successfully delivered emails
- **Failed**: Emails that failed after max retries
- **Cancelled**: Manually cancelled emails

## API Usage

### Add Email to Queue
```php
$result = civicrm_api3('Emailqueue', 'addtoqueue', [
  'to_email' => 'recipient@example.com',
  'subject' => 'Test Subject',
  'from_email' => 'sender@example.com',
  'body_html' => '<p>HTML content</p>',
  'body_text' => 'Plain text content',
  'priority' => 1, // 1-5, 1 being highest priority
]);
```

### Process Queue Manually
```php
$result = civicrm_api3('Emailqueue', 'processqueue');
```

### Get Queue Statistics
```php
$stats = civicrm_api3('Emailqueue', 'getstats');
```

## How It Works

1. **Email Interception**: Uses `hook_civicrm_alterMailer` to intercept outbound emails
2. **Queue Storage**: Instead of sending, emails are stored in the separate database
3. **Cron Processing**: CiviCRM's cron system processes the queue in batches
4. **Direct Sending**: Queued emails are sent using direct SMTP (bypassing the hook)
5. **Retry Logic**: Failed emails are retried with exponential backoff

## Priority Levels

- **1**: Highest priority (sent first)
- **2**: High priority
- **3**: Normal priority (default)
- **4**: Low priority
- **5**: Lowest priority (sent last)

## Retry Logic

Failed emails are automatically retried with exponential backoff:
- Retry 1: 5 minutes delay
- Retry 2: 10 minutes delay
- Retry 3: 20 minutes delay
- After max retries: Marked as failed

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
  - Verify database credentials
  - Ensure database exists and user has proper permissions
  - Check database host connectivity

2. **Emails Not Being Processed**
  - Verify CiviCRM cron is running
  - Check Email Queue System is enabled
  - Review CiviCRM logs for errors

3. **High Number of Failed Emails**
  - Check SMTP settings in CiviCRM
  - Review error messages in failed emails
  - Verify email content and headers

### Logs

Check CiviCRM logs for email queue related errors:
- Location: Usually in `civicrm/ConfigAndLog/`
- Look for entries containing "Email Queue"

### Manual Queue Processing

Process queue via API:
```bash
cv api3 Emailqueue.processqueue
```

Or via URL:
```
https://yoursite.com/civicrm/ajax/emailqueue/action?action=process_queue
```

## Security Considerations

- Use a dedicated database user with minimal required permissions
- Store database credentials securely
- Monitor failed emails for potential security issues
- Regularly clean up old queue logs

## Performance Tips

- Adjust batch size based on server capacity
- Monitor queue size during high-volume periods
- Consider separate database server for high-volume installations
- Set appropriate cron frequency for your email volume

## Uninstallation

1. Disable the extension in CiviCRM
2. Uninstall via Extensions page
3. Manually drop the email queue database if no longer needed:
   ```sql
   DROP DATABASE emailqueue;
   ```

## Support

For issues and feature requests, please:
1. Check the CiviCRM logs for error details
2. Review this documentation
3. Contact your system administrator
4. File an issue in the project repository

## License

This extension is licensed under AGPL-3.0.
