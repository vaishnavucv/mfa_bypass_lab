# Burp Suite Configuration Guide

## Overview

This guide explains how to capture all HTTP requests from the FastLAN web application using Burp Suite proxy.

## Prerequisites

- Burp Suite Community/Professional installed
- PHP development server running on `localhost:8080`
- Browser (Chrome/Firefox recommended)

## Step 1: Configure Burp Suite Proxy

### Start Burp Suite

1. Open Burp Suite
2. Go to **Proxy** tab → **Options**
3. Check the **Proxy Listeners** section

### Default Burp Proxy Settings

By default, Burp listens on:
- **Address**: `127.0.0.1:8080`
- **Port**: `8080`

### Change Burp Proxy Port (Recommended)

Since PHP server is using port `8080`, change Burp to a different port:

1. Click on the existing listener (`127.0.0.1:8080`)
2. Click **Edit**
3. Change port to: `8081` (or any free port)
4. Click **OK**

**Your setup will be**:
- PHP Server: `http://localhost:8080`
- Burp Proxy: `127.0.0.1:8081`

## Step 2: Configure Browser to Use Burp Proxy

### Option A: Firefox (Recommended)

1. Open Firefox
2. Go to **Settings** → **Network Settings** → **Settings**
3. Select **Manual proxy configuration**
4. Configure:
   - **HTTP Proxy**: `127.0.0.1`
   - **Port**: `8081`
   - Check: **Also use this proxy for HTTPS**
5. Click **OK**

### Option B: Chrome/Chromium

**Method 1: Use FoxyProxy Extension**

1. Install FoxyProxy extension
2. Add new proxy:
   - **Title**: Burp Suite
   - **Proxy Type**: HTTP
   - **Proxy IP**: 127.0.0.1
   - **Port**: 8081
3. Enable the proxy

**Method 2: Launch Chrome with Proxy**

```bash
google-chrome --proxy-server="127.0.0.1:8081" http://localhost:8080
```

Or for Chromium:
```bash
chromium-browser --proxy-server="127.0.0.1:8081" http://localhost:8080
```

### Option C: System-wide Proxy (Linux)

```bash
# Set proxy environment variables
export http_proxy=http://127.0.0.1:8081
export https_proxy=http://127.0.0.1:8081

# Launch browser
firefox http://localhost:8080
```

## Step 3: Configure Burp Suite Intercept

### Enable Intercept

1. Go to **Proxy** tab → **Intercept**
2. Make sure **Intercept is on** button is active (orange)

### Scope Configuration (Optional)

To only capture localhost traffic:

1. Go to **Target** tab → **Scope**
2. Click **Add**
3. Enter:
   - **Protocol**: http
   - **Host**: `localhost`
   - **Port**: `8080`
4. Go to **Proxy** → **Options**
5. Under **Intercept Client Requests**, enable:
   - ✅ **And URL is in target scope**

## Step 4: Test the Setup

### Test Request

1. Make sure PHP server is running:
   ```bash
   php -S localhost:8080
   ```

2. Make sure Burp proxy is listening on `127.0.0.1:8081`

3. Open browser (configured with proxy)

4. Navigate to: `http://localhost:8080`

5. Check Burp Suite:
   - Should see intercepted request in **Proxy** → **Intercept** tab
   - Click **Forward** to send request
   - Click **Intercept is on** to toggle off for automatic forwarding

### Verify Captured Traffic

1. Go to **Proxy** → **HTTP history** tab
2. You should see all requests:
   - `GET /` (index page)
   - `GET /login.php`
   - `POST /login.php` (login submission)
   - `POST /verify_mfa.php` (MFA verification)
   - `GET /dashboard.php`

## Step 5: Test Complete Login Flow

### Capture Login Request

1. Browse to: `http://localhost:8080/login.php`
2. Enter credentials:
   - Email: `admin@fastlan.com`
   - Password: `Admin@123`
3. Click **Login**

**In Burp Suite, you should see:**

```http
POST /login.php HTTP/1.1
Host: localhost:8080
Content-Type: application/x-www-form-urlencoded
Content-Length: 56

email=admin%40fastlan.com&password=Admin%40123
```

### Capture MFA Verification Request

1. Enter the 6-digit code from MailHog
2. Click **Verify & Login**

**In Burp Suite, you should see:**

```http
POST /verify_mfa.php HTTP/1.1
Host: localhost:8080
Content-Type: application/x-www-form-urlencoded
Content-Length: 11

code=123456
```

### Capture Registration Request

1. Browse to: `http://localhost:8080/register.php`
2. Fill registration form
3. Submit

**In Burp Suite, you should see:**

```http
POST /register.php HTTP/1.1
Host: localhost:8080
Content-Type: application/x-www-form-urlencoded

email=test%40fastlan.com&password=Test%40123&confirm_password=Test%40123&full_name=Test+User&department=IT&position=Developer
```

## Step 6: Testing Scenarios

### Scenario 1: Test MFA Bypass

1. Intercept `POST /login.php` request
2. Forward it
3. Try to directly access: `http://localhost:8080/dashboard.php`
4. Should redirect to login (session check)

### Scenario 2: Test Code Reuse

1. Login and capture MFA code
2. Complete login
3. Logout
4. Login again (new code generated)
5. Try using old code → Should fail

### Scenario 3: Test Brute Force MFA

1. Login with valid credentials
2. In Burp, send `POST /verify_mfa.php` to **Intruder**
3. Set payload position on `code` parameter
4. Use **Numbers** payload (000000-999999)
5. Test rate limiting and lockout

### Scenario 4: Test Approval Bypass

1. Register new user (pending approval)
2. Capture `POST /login.php` request
3. Try to modify session or bypass approval check
4. Should be blocked with "pending approval" message

### Scenario 5: Test Session Hijacking

1. Login as user A
2. Capture session cookie: `PHPSESSID=...`
3. Open different browser
4. Set same session cookie
5. Try accessing dashboard

## Troubleshooting

### Issue 1: "Proxy Server Refusing Connections"

**Solution**: Make sure Burp Suite proxy listener is running
- Go to **Proxy** → **Options**
- Check listener is enabled (checkbox ticked)

### Issue 2: "This site can't be reached"

**Solutions**:
1. Verify PHP server is running: `netstat -tlnp | grep 8080`
2. Check browser proxy settings point to correct Burp port
3. Try disabling Burp intercept: **Intercept is off**

### Issue 3: No Requests Appearing in Burp

**Solutions**:
1. Check browser proxy settings are correct
2. Verify Burp is listening on correct port
3. Clear browser cache and try again
4. Check **Proxy** → **Options** → **Intercept Client Requests** rules

### Issue 4: HTTPS Certificate Errors

**Solution**: Import Burp CA certificate
1. With proxy enabled, visit: `http://burp`
2. Click **CA Certificate** (top-right)
3. Import certificate to browser

## Quick Start Commands

### Terminal 1: Start PHP Server
```bash
cd /home/vaishnavu/fastlan-mfa-webapp
php -S localhost:8080
```

### Terminal 2: Start MailHog
```bash
cd /home/vaishnavu/fastlan-mfa-webapp/mailhog
docker-compose up -d
```

### Terminal 3: Launch Browser with Proxy
```bash
# Firefox
firefox --profile /tmp/burp-profile http://localhost:8080

# Chrome
google-chrome --proxy-server="127.0.0.1:8081" http://localhost:8080
```

### Access Points
- **Web App**: http://localhost:8080
- **MailHog**: http://localhost:8025
- **Burp Proxy**: 127.0.0.1:8081

## Security Testing Checklist

Once traffic is captured in Burp Suite, test:

- [ ] SQL Injection in login form
- [ ] XSS in registration fields
- [ ] CSRF token validation
- [ ] Session fixation attacks
- [ ] MFA code enumeration
- [ ] Rate limiting on MFA attempts
- [ ] Authorization bypass (user → admin)
- [ ] Approval status manipulation
- [ ] Password reset vulnerabilities
- [ ] Directory traversal
- [ ] File upload vulnerabilities (if any)

## Burp Suite Extensions (Optional)

Recommended extensions for better testing:

1. **Logger++** - Enhanced logging
2. **Autorize** - Authorization testing
3. **Turbo Intruder** - Fast brute forcing
4. **JSON Web Tokens** - JWT analysis
5. **Active Scan++** - Additional scan checks

Install via: **Extender** → **BApp Store**

---

**Happy Security Testing!**
