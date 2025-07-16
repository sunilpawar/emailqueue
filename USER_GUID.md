# CiviCRM Email Queue Extension - Complete Usage Guide

## Overview

The Email Queue System is a comprehensive CiviCRM extension that provides an alternative to direct SMTP email sending by queuing emails in a separate database for delayed processing. This guide covers all features including the enhanced search and preview functionality.

## Features

### Core Features
- ✅ **Email Queuing** - Queue emails instead of sending immediately
- ✅ **Separate Database** - Uses dedicated database for queue storage
- ✅ **Retry Logic** - Automatic retry with exponential backoff
- ✅ **Priority Support** - Email priority levels (1-5)
- ✅ **Bulk Operations** - Cancel, retry, or delete multiple emails
- ✅ **Performance Monitoring** - Real-time metrics and optimization

### Search & Preview Features
- 🔍 **Advanced Search** - Filter emails by multiple criteria
- 👁️ **Email Preview** - View email content, headers, and logs
- 📊 **Real-time Statistics** - Live queue status updates
- 📤 **Export Functionality** - Export filtered results to CSV
- 🎯 **Smart Filters** - Quick filter by status, date, priority
- 📜 **Activity Logs** - Detailed email processing history

### Admin Features
- ⚙️ **Settings Management** - Complete configuration interface
- 📈 **Performance Dashboard** - Monitor queue health and metrics
- 🧹 **Automatic Cleanup** - Configurable retention policies
- 🔐 **Security Features** - Email validation and sanitization
- 📱 **Responsive Design** - Works on all devices

## Quick Start

### 1. Installation

```bash
cd /path/to/civicrm/ext
git clone [repository-url] com.yourorg.emailqueue
```

Navigate to **Administer → System Settings → Extensions** and install "Email Queue System".

### 2. Database Setup

Create a separate database for the email queue:

```sql
CREATE DATABASE emailqueue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'emailqueue_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON emailqueue.* TO 'emailqueue_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Configuration

Go to **Mailings → Email Queue Settings**:

1. **Enable Email Queue System** ✓
2. **Database Settings**:
  - Host: `localhost`
  - Database: `emailqueue`
  - Username: `emailqueue_user`
  - Password: `secure_password`
3. **Processing Settings**:
  - Batch Size: `50` (adjust based on server capacity)
  - Max Retry Attempts: `3`
4. **Test Connection** - Verify settings work correctly

### 4. Cron Setup

Ensure CiviCRM cron is running:

```bash
# Add to crontab
*/5 * * * * /path/to/civicrm/bin/cron.php -u username -p password
```

## Using the Monitor Interface

### Accessing the Monitor

Navigate to **Mailings → Email Queue Monitor** to access the comprehensive monitoring dashboard.

### Dashboard Overview

The monitor interface provides:

1. **Queue Statistics Cards**
  - Pending, Processing, Sent, Failed, Cancelled email counts
  - Real-time updates every 30 seconds
  - Click-to-refresh functionality

2. **Action Buttons**
  - Process Queue Now - Manual queue processing
  - Retry Failed Emails - Reset all failed emails for retry
  - Export Results - Download current view as CSV
  - Settings - Quick access to configuration

### Advanced Search & Filtering

#### Opening the Search Panel

Click **"🔍 Advanced Search & Filters"** to expand the search interface.

#### Available Filters

| Filter | Description | Example |
|--------|-------------|---------|
| **To Email** | Recipient email address | `john@example.com` |
| **From Email** | Sender email (dropdown of known senders) | `noreply@org.com` |
| **Subject** | Email subject line | `Newsletter` |
| **Status** | Email status (multi-select) | `Pending, Failed` |
| **Priority** | Email priority level | `1 - Highest` |
| **Created From/To** | Date range for email creation | `2025-07-01 to 2025-07-15` |
| **Sent From/To** | Date range for email sending | `2025-07-10 to 2025-07-15` |
| **Has Error** | Filter by error presence | `With Errors` |
| **Min/Max Retries** | Filter by retry count | `Min: 2, Max: 3` |

#### Search Examples

**Find all failed emails from last week:**
- Status: `Failed`
- Created From: `2025-07-08`
- Created To: `2025-07-15`

**Find high-priority pending emails:**
- Status: `Pending`
- Priority: `1 - Highest, 2 - High`

**Find emails that failed after multiple retries:**
- Status: `Failed`
- Min Retries: `3`

#### Active Filters

Applied filters appear as tags below the search form:
- **Remove Individual Filters**: Click the `×` on any filter tag
- **Clear All Filters**: Click "Clear Filters" button

### Email Preview Feature

#### Opening Email Preview

Click the **"👁️ Preview"** link next to any email in the results table.

#### Preview Tabs

**📋 Details Tab**
- Email metadata (To, From, Subject, Status, Priority)
- Timing information (Created, Sent, Scheduled)
- Retry information and error messages
- CC, BCC, and Reply-To addresses

**🌐 HTML View Tab**
- Rendered HTML email content
- Safe HTML sanitization
- Responsive layout for readability

**📄 Text View Tab**
- Plain text version of email
- Preserves formatting and line breaks
- Easy copy-paste functionality

**📜 Logs Tab**
- Complete email processing history
- Timestamped actions (Queued, Validated, Sent, Failed)
- Error messages and retry information
- Color-coded log levels (Success, Info, Error)

#### Preview Controls

- **Close**: Click `×` or click outside modal
- **Tab Navigation**: Click tab headers to switch views
- **Keyboard Support**: `Esc` key to close

### Bulk Operations

#### Selecting Emails

1. **Individual Selection**: Check boxes next to specific emails
2. **Select All**: Use "Select All" checkbox in table header
3. **Select Filtered**: Applies to all emails matching current filters

#### Bulk Actions

When emails are selected, the bulk actions bar appears:

- **Cancel Selected**: Cancel pending/failed emails
- **Retry Selected**: Reset failed emails for retry
- **Delete Selected**: Permanently remove cancelled/failed emails

#### Bulk Action Limits

- Maximum 1,000 emails per bulk operation
- Operations are logged for audit purposes
- Confirmation required for destructive actions

### Export Functionality

#### Export Options

1. **Export Current View**: Exports visible emails with current filters
2. **Export All Filtered**: Exports all emails matching current search criteria
3. **Export Selected**: Exports only selected emails

#### Export Formats

- **CSV**: Comma-separated values (default)
- **Excel**: `.xlsx` format (if supported)
- **JSON**: Machine-readable format

#### Export Contents

Exported files include:
- Email ID, To/From addresses, Subject
- Status, Priority, Created/Sent dates
- Retry count and error messages

### Real-time Updates

#### Auto-refresh

- Page refreshes every 30 seconds automatically
- Green indicator shows auto-refresh is active
- Manual refresh available via browser or action buttons

#### Live Statistics

Statistics cards update automatically to show:
- Queue backlog changes
- Processing progress
- Success/failure rates

## API Usage

### Search API

```php
// Basic search
$result = civicrm_api3('Emailqueue', 'search', [
  'status' => ['pending', 'failed'],
  'priority' => 1,
  'limit' => 100
]);

// Advanced search with date filters
$result = civicrm_api3('Emailqueue', 'search', [
  'to_email' => 'john@example.com',
  'date_from' => '2025-07-01',
  'date_to' => '2025-07-15',
  'has_error' => 'yes',
  'order_by' => 'created_date',
  'order_dir' => 'DESC'
]);
```

### Preview API

```php
// Get email preview
$result = civicrm_api3('Emailqueue', 'preview', [
  'id' => 12345
]);

$email = $result['values'];
echo $email['subject'];
echo $email['body_html'];
print_r($email['logs']);
```

### Bulk Operations API

```php
// Cancel multiple emails
$result = civicrm_api3('Emailqueue', 'bulkaction', [
  'action' => 'cancel',
  'email_ids' => [123, 124, 125]
]);

// Retry failed emails
$result = civicrm_api3('Emailqueue', 'bulkaction', [
  'action' => 'retry',
  'email_ids' => '126,127,128'  // Can use comma-separated string
]);
```

### Export API

```php
// Export search results
$result = civicrm_api3('Emailqueue', 'export', [
  'status' => 'failed',
  'date_from' => '2025-07-01'
]);

$csvData = $result['values']['csv_data'];
```

## Performance Optimization

### Database Optimization

#### Recommended Indexes

The extension automatically creates these indexes:

```sql
-- Composite indexes for query performance
CREATE INDEX idx_status_priority_created ON email_queue (status, priority, created_date);
CREATE INDEX idx_status_scheduled ON email_queue (status, scheduled_date);
CREATE INDEX idx_from_email_status ON email_queue (from_email, status);
CREATE INDEX idx_to_email_status ON email_queue (to_email, status);
```

#### Table Maintenance

Set up regular maintenance:

```sql
-- Weekly optimization (can be scheduled)
OPTIMIZE TABLE email_queue;
OPTIMIZE TABLE email_queue_log;

-- Archive old data (adjust retention as needed)
DELETE FROM email_queue
WHERE status = 'sent'
AND sent_date < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### Processing Optimization

#### Batch Size Tuning

Adjust batch size based on server capacity:

- **Small server** (shared hosting): 10-25 emails
- **Medium server** (VPS): 25-75 emails
- **Large server** (dedicated): 75-200 emails

#### Cron Frequency

Adjust processing frequency based on volume:

- **Low volume** (<100 emails/day): Every 15 minutes
- **Medium volume** (100-1000 emails/day): Every 5 minutes
- **High volume** (>1000 emails/day): Every 1-2 minutes

### Performance Monitoring

#### Built-in Metrics

Access via API or admin interface:

```php
// Get performance metrics
$metrics = civicrm_api3('Emailqueue', 'getmetrics');
print_r($metrics['values']);

// Get optimization recommendations
$recommendations = civicrm_api3('Emailqueue', 'getrecommendations');
foreach ($recommendations['values'] as $rec) {
    echo $rec['issue'] . ': ' . $rec['suggestion'] . "\n";
}
```

#### Key Metrics to Monitor

- **Queue Backlog**: Should remain under 1,000 emails
- **Processing Time**: Average under 60 seconds per email
- **Success Rate**: Should be above 95%
- **Database Size**: Monitor growth and implement cleanup

## Troubleshooting

### Common Issues

#### 1. Emails Not Processing

**Symptoms**: Emails stuck in "Pending" status

**Solutions**:
1. Check CiviCRM cron is running
2. Verify SMTP settings in CiviCRM
3. Check scheduled job is active
4. Review error logs

```bash
# Manual processing for testing
cv api3 Emailqueue.processqueue
```

#### 2. Database Connection Errors

**Symptoms**: "Database connection failed" messages

**Solutions**:
1. Verify database credentials
2. Check database server is running
3. Confirm user permissions
4. Test connection manually

```bash
# Test database connection
mysql -h localhost -u emailqueue_user -p emailqueue
```

#### 3. High Failure Rate

**Symptoms**: Many emails in "Failed" status

**Solutions**:
1. Review SMTP configuration
2. Check email content for issues
3. Verify recipient addresses
4. Monitor bounce rates

#### 4. Performance Issues

**Symptoms**: Slow queue processing or timeouts

**Solutions**:
1. Reduce batch size
2. Optimize database indexes
3. Clean up old emails
4. Monitor server resources

### Log Files

#### CiviCRM Logs

Check standard CiviCRM logs for errors:
- Location: `civicrm/ConfigAndLog/`
- Search for "Email Queue" entries

#### Database Logs

Query email queue logs directly:

```sql
-- Recent error logs
SELECT * FROM email_queue_log
WHERE action LIKE '%error%'
ORDER BY created_date DESC
LIMIT 10;

-- Processing timeline for specific email
SELECT * FROM email_queue_log
WHERE queue_id = 12345
ORDER BY created_date ASC;
```

### Debug Mode

Enable debug mode for detailed logging:

```php
// In civicrm.settings.php or via API
Civi::settings()->set('emailqueue_log_level', 'debug');
```

## Security Considerations

### Database Security

- Use dedicated database user with minimal privileges
- Store credentials securely
- Enable SSL connections if possible
- Regular security updates

### Email Content Security

- HTML content is automatically sanitized
- Dangerous scripts and attributes removed
- Email addresses validated before processing
- Size limits enforced

### Access Control

- Admin permissions required for all operations
- API calls require proper authentication
- Bulk operations logged for audit

## Advanced Configuration

### Custom Settings

```php
// Advanced settings via API
CRM_Emailqueue_Config::setSetting('max_email_size', 50); // MB
CRM_Emailqueue_Config::setSetting('cleanup_days', 120);
CRM_Emailqueue_Config::setSetting('enable_tracking', true);
```

### Webhooks and Integration

```php
// Hook into email processing
function mymodule_civicrm_emailqueue_pre_send($email) {
  // Custom validation or modification
  if ($email['to_email'] === 'blocked@example.com') {
    return false; // Block sending
  }
  return true;
}
```

### Custom Validation

```php
// Add custom email validation
function mymodule_civicrm_emailqueue_validate($email) {
  $validation = CRM_Emailqueue_Utils_Email::validateEmail($email);

  // Add custom checks
  if (strpos($email, 'spam') !== false) {
    $validation['warnings'][] = 'Email contains spam keyword';
  }

  return $validation;
}
```

## Best Practices

### Email Management

1. **Monitor Queue Regularly**: Check dashboard daily
2. **Set Appropriate Batch Sizes**: Based on server capacity
3. **Implement Cleanup**: Remove old emails regularly
4. **Monitor Bounce Rates**: Track delivery success
5. **Test Configuration**: Verify settings before production

### Performance

1. **Index Optimization**: Monitor query performance
2. **Database Maintenance**: Regular optimization
3. **Resource Monitoring**: Watch CPU and memory usage
4. **Scaling Strategy**: Plan for growth

### Security

1. **Access Control**: Limit admin access
2. **Regular Updates**: Keep extension current
3. **Audit Logs**: Review processing logs
4. **Backup Strategy**: Regular database backups

## Support and Resources

### Getting Help

1. **Check Logs**: Review CiviCRM and extension logs
2. **Test Configuration**: Use built-in test tools
3. **Community Forum**: Post questions with details
4. **Documentation**: Review this guide thoroughly

### Useful Commands

```bash
# Process queue manually
cv api3 Emailqueue.processqueue

# Get queue statistics
cv api3 Emailqueue.getstats

# Search emails
cv api3 Emailqueue.search status=failed limit=10

# Export emails
cv api3 Emailqueue.export status=sent date_from=2025-07-01

# Test database connection
cv api3 Emailqueue.testconnection host=localhost name=emailqueue user=user pass=pass
```

### Performance Monitoring

```bash
# Check system health
cv api3 Emailqueue.healthcheck

# Get optimization recommendations
cv api3 Emailqueue.getrecommendations

# View processing metrics
cv api3 Emailqueue.getmetrics
```

This completes the comprehensive usage guide for the Email Queue Extension with advanced search and preview functionality. The extension provides a robust, scalable alternative to direct SMTP email sending with extensive monitoring and management capabilities.
