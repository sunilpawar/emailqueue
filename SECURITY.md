# Security Documentation

This document outlines the security measures, best practices, and procedures for the CiviCRM Email Queue Extension.

## 🔒 Security Overview

The Email Queue Extension handles sensitive email data and requires robust security measures to protect user information and prevent unauthorized access.

### Security Principles
- **Defense in Depth**: Multiple layers of security controls
- **Least Privilege**: Minimal access rights for all operations
- **Data Protection**: Encryption and secure handling of sensitive data
- **Input Validation**: Comprehensive validation of all user inputs
- **Audit Trail**: Complete logging of all security-relevant events

## 🛡️ Security Features

### 1. Input Validation and Sanitization

#### Email Content Sanitization
```php
// HTML content sanitization
$sanitizedHtml = CRM_Emailqueue_Utils_Email::sanitizeHtmlContent($rawHtml);

// Text content sanitization
$sanitizedText = CRM_Emailqueue_Utils_Email::sanitizeTextContent($rawText);

// Email address validation
$validation = CRM_Emailqueue_Utils_Email::validateEmail($email);
```

#### Dangerous Content Removal
- JavaScript code removal from HTML emails
- SQL injection pattern detection
- XSS attack vector elimination
- File upload restriction and validation

#### Input Size Limits
- Maximum email size: 25MB (configurable)
- Subject line limit: 255 characters
- Header size restrictions
- Attachment size controls

### 2. SQL Injection Prevention

#### Prepared Statements
All database operations use prepared statements:

```php
// Correct approach - parameterized query
$sql = "SELECT * FROM email_queue WHERE status = ? AND created_date > ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$status, $date]);

// Never use direct string interpolation
// WRONG: $sql = "SELECT * FROM email_queue WHERE status = '$status'";
```

#### Query Parameter Validation
- Type checking for all parameters
- Range validation for numeric inputs
- Whitelist validation for enum values
- Length limits for string parameters

### 3. Access Control

#### Permission Checks
```php
// Check administrative permissions
if (!CRM_Core_Permission::check('administer CiviCRM')) {
    CRM_Core_Error::statusBounce(ts('You do not have permission to access this page.'));
}

// API permission validation
function _civicrm_api3_emailqueue_search_spec(&$spec) {
    // Require specific permissions for sensitive operations
    $spec['_permission'] = 'access CiviCRM';
}
```

#### Role-Based Access Control (RBAC)
- **Administrators**: Full access to all features
- **Queue Managers**: Queue monitoring and management
- **Operators**: Basic queue operations only
- **Viewers**: Read-only access to statistics

#### API Security
- Authentication required for all API calls
- Rate limiting on API endpoints
- Request validation and sanitization
- Response data filtering

### 4. Data Protection

#### Sensitive Data Handling
```php
// Email addresses and content encryption in transit
$encryptedData = openssl_encrypt($emailData, 'AES-256-CBC', $key, 0, $iv);

// Secure password storage (for external service credentials)
$hashedPassword = password_hash($password, PASSWORD_ARGON2ID);
```

#### Database Security
- Separate database credentials with minimal privileges
- Connection encryption when available
- Regular credential rotation
- Database access logging

#### Email Content Security
- Content sanitization before storage
- HTML email safety checks
- Attachment scanning and validation
- Personal data identification and protection

### 5. Session Security

#### Session Management
- Secure session configuration
- Session timeout enforcement
- Session ID regeneration
- Cross-site request forgery (CSRF) protection

#### Cookie Security
```php
// Secure cookie settings
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
```

## 🔍 Security Monitoring

### 1. Audit Logging

#### Security Event Logging
```php
// Log security-relevant events
CRM_Emailqueue_Utils_ErrorHandler::info('User login attempt', [
    'user_id' => $userId,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'timestamp' => date('Y-m-d H:i:s')
]);
```

#### Logged Events
- Authentication attempts (success/failure)
- Permission escalation attempts
- Bulk operations performed
- Configuration changes
- API access patterns
- Error conditions and exceptions

### 2. Intrusion Detection

#### Suspicious Activity Detection
- Multiple failed login attempts
- Unusual API usage patterns
- Large data export operations
- Configuration tampering attempts
- SQL injection attempt patterns

#### Automated Responses
- Account lockout after failed attempts
- Rate limiting for suspicious IPs
- Alert generation for security events
- Automatic security report generation

### 3. Vulnerability Monitoring

#### Security Scanning
- Regular dependency vulnerability scans
- Code security analysis
- Configuration security reviews
- Penetration testing recommendations

#### Update Management
- Security patch tracking
- Dependency update monitoring
- Security advisory subscriptions
- Emergency patch procedures

## ⚠️ Security Vulnerabilities

### Reporting Security Issues

**🚨 IMPORTANT: Do NOT create public GitHub issues for security vulnerabilities.**

#### Responsible Disclosure Process

1. **Report Privately**
  - Email: security@yourorg.com
  - PGP Key: [Available on request]
  - Include: Detailed description, reproduction steps, impact assessment

2. **Response Timeline**
  - **24 hours**: Initial acknowledgment
  - **72 hours**: Initial assessment and triage
  - **7 days**: Detailed analysis and remediation plan
  - **30 days**: Security patch release (if needed)

3. **Disclosure Policy**
  - Security issues will be kept confidential until patched
  - Public disclosure after fix is available
  - Credit given to responsible reporters
  - CVE numbers assigned for significant vulnerabilities

#### Security Contact Information
- **Primary Contact**: security@yourorg.com
- **Backup Contact**: admin@yourorg.com
- **PGP Fingerprint**: [To be provided]

### Known Security Considerations

#### Current Limitations
- Email content stored in separate database (not encrypted at rest by default)
- External service credentials stored in CiviCRM settings
- Limited built-in rate limiting (depends on web server configuration)
- No built-in email content scanning for malware

#### Mitigation Recommendations
- Use database encryption at rest
- Implement credential management system
- Configure web server rate limiting
- Use external email scanning services

## 🔧 Security Configuration

### 1. Database Security

#### Connection Security
```php
// Enable SSL for database connections
$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
$options = [
    PDO::MYSQL_ATTR_SSL_CA => '/path/to/ca.pem',
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
];
```

#### User Privileges
```sql
-- Create dedicated user with minimal privileges
CREATE USER 'emailqueue'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON emailqueue.* TO 'emailqueue'@'localhost';
FLUSH PRIVILEGES;

-- Avoid granting unnecessary privileges like CREATE, DROP, ALTER
```

### 2. Web Server Configuration

#### Apache Security Headers
```apache
# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set Content-Security-Policy "default-src 'self'"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

#### Nginx Security Configuration
```nginx
# Security headers
add_header X-Content-Type-Options nosniff;
add_header X-Frame-Options DENY;
add_header X-XSS-Protection "1; mode=block";
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";
add_header Content-Security-Policy "default-src 'self'";

# Rate limiting
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
limit_req zone=api burst=20 nodelay;
```

### 3. PHP Security Configuration

#### Recommended php.ini Settings
```ini
# Disable dangerous functions
disable_functions = exec,passthru,shell_exec,system,proc_open,popen

# Hide PHP version
expose_php = Off

# Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1

# File upload restrictions
file_uploads = On
upload_max_filesize = 25M
max_file_uploads = 5

# Error handling
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log
```

## 🚨 Security Incidents

### Incident Response Plan

#### 1. Detection and Analysis
- Monitor security alerts and logs
- Analyze suspicious activities
- Determine impact and scope
- Document initial findings

#### 2. Containment and Eradication
- Isolate affected systems
- Stop ongoing attacks
- Remove malicious content
- Patch vulnerabilities

#### 3. Recovery and Post-Incident
- Restore normal operations
- Monitor for recurring issues
- Update security measures
- Conduct lessons learned review

#### Emergency Contacts
- **Security Team**: security@yourorg.com
- **System Administrator**: admin@yourorg.com
- **Project Lead**: lead@yourorg.com

### Security Incident Classification

#### Severity Levels
- **Critical**: System compromise, data breach
- **High**: Privilege escalation, service disruption
- **Medium**: Unauthorized access attempts
- **Low**: Minor security policy violations

#### Response Times
- **Critical**: Immediate response (within 1 hour)
- **High**: Fast response (within 4 hours)
- **Medium**: Standard response (within 24 hours)
- **Low**: Routine response (within 72 hours)

## 📋 Security Checklist

### Deployment Security Checklist

#### Pre-Deployment
- [ ] Security code review completed
- [ ] Vulnerability scan performed
- [ ] Dependencies updated to latest secure versions
- [ ] Security configuration validated
- [ ] Access controls tested
- [ ] Encryption properly configured

#### Post-Deployment
- [ ] Security monitoring enabled
- [ ] Audit logging configured
- [ ] Incident response plan activated
- [ ] Security contacts updated
- [ ] Documentation updated
- [ ] Security training completed

### Regular Security Maintenance

#### Weekly Tasks
- [ ] Review security logs
- [ ] Check for security updates
- [ ] Validate backup integrity
- [ ] Monitor access patterns

#### Monthly Tasks
- [ ] Security configuration review
- [ ] Access permissions audit
- [ ] Incident response plan testing
- [ ] Security awareness training

#### Quarterly Tasks
- [ ] Penetration testing
- [ ] Security architecture review
- [ ] Vendor security assessments
- [ ] Emergency response drills

## 🔗 Security Resources

### Security Standards and Frameworks
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [CIS Security Controls](https://www.cisecurity.org/controls/)
- [NIST Cybersecurity Framework](https://www.nist.gov/cyberframework)
- [ISO 27001](https://www.iso.org/isoiec-27001-information-security.html)

### CiviCRM Security Resources
- [CiviCRM Security Guidelines](https://docs.civicrm.org/sysadmin/en/latest/security/)
- [CiviCRM Security Advisories](https://civicrm.org/advisory)
- [CiviCRM Security Best Practices](https://docs.civicrm.org/sysadmin/en/latest/security/best-practices/)

### Security Tools
- **Static Analysis**: PHPStan, Psalm
- **Dependency Scanning**: Composer Audit, Snyk
- **Web Scanning**: OWASP ZAP, Nikto
- **Container Scanning**: Trivy, Clair

## 📞 Security Support

### For Security Questions
- **Documentation**: This security guide
- **Community**: CiviCRM security forum
- **Direct Contact**: security@yourorg.com

### For Security Incidents
- **Emergency**: security@yourorg.com
- **Phone**: [Emergency security hotline]
- **Escalation**: [Management contact]

---

**Remember**: Security is everyone's responsibility. When in doubt, err on the side of caution and contact the security team.

*This security documentation is regularly updated to reflect current threats and best practices. Last updated: July 2025*
