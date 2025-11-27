# FastLAN MFA Setup Guide

## Multi-Factor Authentication System

This application now includes a complete MFA (Multi-Factor Authentication) system that sends 6-digit verification codes via email.

## Prerequisites

1. **MailHog** - Email testing service
2. **MySQL** - Database with MFA table
3. **PHP** with mail() function support

## Setup Instructions

### Step 1: Start MailHog

```bash
cd mailhog
docker-compose up -d
```

This will start MailHog with:
- **Web UI**: http://localhost:8025 (view captured emails)
- **SMTP**: localhost:1025 (for sending emails)

### Step 2: Create MFA Database Table

Run the SQL migration:

```bash
mysql -u fastlan -pfastlan123 employee_portal < add_mfa_table.sql
```

Or manually:

```sql
CREATE TABLE IF NOT EXISTS mfa_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_code (user_id, code),
    INDEX idx_expires (expires_at)
);
```

### Step 3: Verify Files

Ensure these files exist:
- ✅ `mfa_helper.php` - MFA functions and email sending
- ✅ `verify_mfa.php` - MFA verification page
- ✅ `login.php` - Updated with MFA integration
- ✅ `add_mfa_table.sql` - Database migration

## How It Works

### Login Flow:

1. **User enters email & password** → `login.php`
2. **If credentials valid** →
   - Generate 6-digit code
   - Store in `mfa_codes` table (expires in 10 min)
   - Send email via MailHog SMTP
   - Redirect to `verify_mfa.php`
3. **User enters code** → `verify_mfa.php`
4. **If code valid** →
   - Mark code as used
   - Complete login
   - Redirect to dashboard

### Email Configuration

The system uses these MailHog settings (configured in `mfa_helper.php`):

```php
MAIL_HOST = 'localhost'
MAIL_PORT = 1025
MAIL_FROM = 'noreply@fastlan.com'
```

**Implementation Note**: The email system uses direct SMTP socket connection (via `fsockopen()`) instead of PHP's `mail()` function. This eliminates the need for sendmail and works directly with MailHog's SMTP server at 127.0.0.1:1025.

## Testing the MFA System

### Test Login Flow:

1. **Start MailHog**:
   ```bash
   cd mailhog && docker-compose up -d
   ```

2. **Start PHP Server**:
   ```bash
   php -S localhost:8080
   ```

3. **Open Application**:
   - Navigate to http://localhost:8080
   - Login with: `admin@fastlan.com` / `Admin@123`

4. **Check Email**:
   - Open MailHog: http://localhost:8025
   - You'll see the email with 6-digit code

5. **Enter Code**:
   - Enter the code from email
   - Complete login

### Burp Suite Testing:

**Intercept MFA Flow**:
1. Configure proxy: 127.0.0.1:8080
2. Intercept POST to `/login.php`
3. Intercept POST to `/verify_mfa.php`
4. Analyze MFA code in requests
5. Test MFA bypass techniques

**Request Examples**:

```http
POST /login.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

email=admin@fastlan.com&password=Admin@123
```

```http
POST /verify_mfa.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

code=123456
```

## Security Testing Scenarios

### 1. **Code Reuse Attack**
- Try using the same code twice
- ✅ System marks code as `is_used=TRUE`

### 2. **Code Expiration**
- Wait 10+ minutes
- Try using expired code
- ✅ System checks `expires_at > NOW()`

### 3. **Brute Force**
- Try multiple codes rapidly
- Monitor rate limiting (not implemented - good for lab)

### 4. **Code Enumeration**
- Try sequential codes: 000000, 000001, etc.
- 6-digit = 1,000,000 possibilities

### 5. **MFA Bypass**
- Skip MFA page and go directly to dashboard
- ✅ System checks `$_SESSION['mfa_pending']`

### 6. **Email Interception**
- View emails in MailHog
- Demonstrates importance of secure email

## Database Queries for Analysis

**View all MFA codes**:
```sql
SELECT * FROM mfa_codes ORDER BY created_at DESC;
```

**View active codes**:
```sql
SELECT * FROM mfa_codes
WHERE expires_at > NOW() AND is_used = FALSE;
```

**Check user's MFA attempts**:
```sql
SELECT u.email, m.code, m.created_at, m.is_used
FROM mfa_codes m
JOIN users u ON m.user_id = u.id
WHERE u.email = 'admin@fastlan.com'
ORDER BY m.created_at DESC;
```

## Activity Logs

All MFA activity is logged in `logs/activity.log`:

```
[2025-11-26 15:30:00] User: guest (anonymous) | IP: 127.0.0.1 | Action: LOGIN_USER_FOUND | Details: User ID: 1
[2025-11-26 15:30:01] User: guest (anonymous) | IP: 127.0.0.1 | Action: MFA_SENT | Details: MFA code sent to: admin@fastlan.com
[2025-11-26 15:30:15] User: guest (anonymous) | IP: 127.0.0.1 | Action: MFA_VERIFY_ATTEMPT | Details: POST data received
[2025-11-26 15:30:15] User: guest (anonymous) | IP: 127.0.0.1 | Action: MFA_SUCCESS | Details: User verified: admin@fastlan.com
```

## Troubleshooting

### MailHog not receiving emails:

1. Check MailHog is running:
   ```bash
   docker ps | grep mailhog
   ```

2. Check port 1025 is accessible:
   ```bash
   telnet localhost 1025
   ```

3. Check PHP mail configuration:
   ```bash
   php -r "phpinfo();" | grep -i mail
   ```

### Database errors:

1. Verify table exists:
   ```bash
   mysql -u fastlan -pfastlan123 -e "USE employee_portal; SHOW TABLES LIKE 'mfa_codes';"
   ```

2. Check table structure:
   ```bash
   mysql -u fastlan -pfastlan123 -e "USE employee_portal; DESCRIBE mfa_codes;"
   ```

## Disable MFA (for testing)

To temporarily disable MFA, comment out the MFA code in `login.php:40-67` and restore the original direct login flow.

## Default Credentials

**Admin**: admin@fastlan.com / Admin@123
**User**: john.doe@fastlan.com / User@123

## MailHog Access

- **Web UI**: http://localhost:8025
- **SMTP**: localhost:1025

Happy Testing! 🔐
