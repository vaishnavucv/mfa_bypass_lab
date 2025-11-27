# MFA Code Resend Feature

## Overview

The MFA verification page now includes automatic expiration detection with a resend button that appears when the verification code expires.

## How It Works

### User Experience Flow

1. **User logs in** with email/password
2. **MFA code is sent** to email (expires in 10 minutes)
3. **User redirected** to verification page
4. **Timer counts down** from 10:00 to 0:00
5. **When timer expires**:
   - Timer shows "EXPIRED" in red
   - Yellow warning box appears
   - "Resend New Code" button is displayed
6. **User clicks "Resend New Code"**:
   - New 6-digit code is generated
   - Old code is invalidated
   - New code is sent to MailHog
   - Timer resets to 10:00
   - Success message displayed

## Technical Implementation

### Backend Functions (mfa_helper.php)

#### `isMFACodeExpired($userId)`
Checks if the current MFA code has expired for a user.

```php
$isExpired = isMFACodeExpired($userId);
// Returns true if expired or no code found
```

#### `getMFAExpiryTime($userId)`
Gets the UNIX timestamp when the code expires.

```php
$expiryTime = getMFAExpiryTime($userId);
// Returns timestamp or null if no code
```

#### `resendMFACode($userId, $email, $userName)`
Generates new code, stores it, and sends email.

```php
if (resendMFACode($userId, $email, $userName)) {
    // Success - new code sent
}
```

### Frontend Features (verify_mfa.php)

#### Real-time Countdown Timer
JavaScript timer that:
- Shows remaining time (MM:SS format)
- Updates every second
- Uses actual database expiry timestamp
- Not client-side manipulatable

#### Automatic Resend Button Display
- Hidden by default
- Shows when timer reaches 0:00
- Uses POST method for security
- Prevents CSRF attacks

#### Visual Indicators
- **Timer Color**: Green → Orange → Red as it expires
- **Expired State**: Timer shows "EXPIRED" in red
- **Warning Box**: Yellow background with "code expired" message
- **Resend Button**: Orange button with hover effect

## Testing Scenarios

### Scenario 1: Normal Login (Code Valid)

1. Login with valid credentials
2. Check MailHog for code
3. Enter code within 10 minutes
4. Successfully login

**Expected**: ✅ Login successful, timer not expired

### Scenario 2: Code Expires Before Entry

1. Login with valid credentials
2. **Wait 10+ minutes** (or manually expire in database)
3. Try entering code
4. See error: "Invalid or expired code"
5. Timer shows "EXPIRED"
6. Resend button appears

**Expected**: ✅ Code rejected, resend option shown

### Scenario 3: Resend New Code

1. Login with valid credentials
2. Wait for timer to expire (or simulate)
3. Click "Resend New Code" button
4. Check MailHog for new code
5. Enter new code
6. Successfully login

**Expected**: ✅ New code received, old code invalidated, login successful

### Scenario 4: Multiple Resend Attempts

1. Login with valid credentials
2. Click "Resend New Code" (1st resend)
3. Wait and click "Resend New Code" again (2nd resend)
4. Check MailHog - should have 2 new emails
5. Enter the **latest** code
6. Successfully login

**Expected**: ✅ Only the latest code works, previous codes invalidated

### Scenario 5: Try Old Code After Resend

1. Login with valid credentials
2. Note the first code from MailHog
3. Click "Resend New Code"
4. Try entering the **old** code
5. Should fail

**Expected**: ✅ Old code rejected, must use new code

## Manual Testing Commands

### Test 1: Check Current MFA Code
```sql
SELECT user_id, code, created_at, expires_at, is_used
FROM mfa_codes
WHERE user_id = 1
ORDER BY created_at DESC
LIMIT 1;
```

### Test 2: Manually Expire Code
```sql
UPDATE mfa_codes
SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE)
WHERE user_id = 1 AND is_used = FALSE;
```

### Test 3: Check Resend Activity Logs
```bash
grep "MFA_RESENT\|MFA_RESEND" logs/activity.log
```

Expected output:
```
[2025-11-27 XX:XX:XX] User: guest | Action: MFA_RESEND_ATTEMPT | Details: Resend code requested
[2025-11-27 XX:XX:XX] User: guest | Action: MFA_RESENT | Details: MFA code resent to: user@fastlan.com
```

### Test 4: View All MFA Codes
```sql
SELECT u.email, m.code, m.created_at, m.expires_at, m.is_used,
       CASE WHEN m.expires_at > NOW() THEN 'Valid' ELSE 'Expired' END as status
FROM mfa_codes m
JOIN users u ON m.user_id = u.id
ORDER BY m.created_at DESC;
```

## Security Considerations

### Resend Rate Limiting
**Current Implementation**: None (intentional for lab testing)

**Production Recommendation**: Add rate limiting to prevent spam
```php
// Example: Max 3 resends per 30 minutes
if (getResendCount($userId) > 3) {
    $error = "Too many resend attempts. Please try again later.";
}
```

### Code Invalidation
✅ **Implemented**: Old codes are deleted when new code is generated
- Prevents code reuse attacks
- Only one valid code per user at a time

### Expiration Enforcement
✅ **Implemented**: Database-side expiration check
- `expires_at > NOW()` in SQL query
- Cannot be bypassed by client manipulation

### CSRF Protection
✅ **Implemented**: POST method for resend
- Not vulnerable to GET-based CSRF
- Session-based authentication

## Burp Suite Testing

### Capture Resend Request

1. Login and wait for expiration
2. Click "Resend New Code"
3. In Burp, you'll see:

```http
POST /verify_mfa.php HTTP/1.1
Host: localhost:8080
Cookie: PHPSESSID=...
Content-Type: application/x-www-form-urlencoded

resend_code=1
```

### Test Resend Abuse

1. Send resend request to Intruder
2. Set payload: None (simple repeater)
3. Attack type: Sniper
4. Run 100 times rapidly
5. Check MailHog - should have 100 emails (no rate limit for testing)

### Test Session Hijacking

1. Capture valid session cookie
2. Try resending code from different IP
3. Should work (session-based, not IP-restricted)

## UI States

### Before Expiration
```
┌─────────────────────────────┐
│   Verify Your Login         │
├─────────────────────────────┤
│ Code sent to: user@test.com │
│                             │
│ [______] (6-digit input)    │
│ [Verify & Login]            │
│                             │
│ Code expires in: 09:45      │
└─────────────────────────────┘
```

### After Expiration
```
┌─────────────────────────────┐
│   Verify Your Login         │
├─────────────────────────────┤
│ Code sent to: user@test.com │
│                             │
│ [______] (6-digit input)    │
│ [Verify & Login]            │
│                             │
│ Code expires in: EXPIRED    │
│ ┌───────────────────────┐   │
│ │ ⚠ Code has expired!   │   │
│ │ [Resend New Code]     │   │
│ └───────────────────────┘   │
└─────────────────────────────┘
```

### After Resend Success
```
┌─────────────────────────────┐
│   Verify Your Login         │
├─────────────────────────────┤
│ ✅ New code sent!            │
│                             │
│ [______] (6-digit input)    │
│ [Verify & Login]            │
│                             │
│ Code expires in: 10:00      │
└─────────────────────────────┘
```

## Files Modified

1. **mfa_helper.php**
   - Added `isMFACodeExpired()` function
   - Added `resendMFACode()` function
   - Added logging for resend attempts

2. **verify_mfa.php**
   - Added resend request handler (POST)
   - Added client-side countdown timer
   - Added automatic resend button display
   - Improved error messages

## Database Behavior

### On Resend:
```sql
-- Step 1: Delete old codes
DELETE FROM mfa_codes WHERE user_id = ?

-- Step 2: Insert new code
INSERT INTO mfa_codes (user_id, code, expires_at)
VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
```

### Result:
- Old code: Deleted ❌
- New code: Active ✅
- Timer: Reset to 10:00

## Activity Log Examples

```log
[2025-11-27 10:00:00] User: guest | Action: LOGIN_ATTEMPT
[2025-11-27 10:00:01] User: guest | Action: MFA_SENT | Details: MFA code sent to: user@test.com
[2025-11-27 10:10:05] User: guest | Action: MFA_VERIFY_ATTEMPT
[2025-11-27 10:10:05] User: guest | Action: MFA_FAILED | Details: Invalid code (expired)
[2025-11-27 10:10:10] User: guest | Action: MFA_RESEND_ATTEMPT | Details: Resend code requested
[2025-11-27 10:10:11] User: guest | Action: MFA_RESENT | Details: MFA code resent to: user@test.com
[2025-11-27 10:10:20] User: guest | Action: MFA_SUCCESS | Details: User verified: user@test.com
```

## Troubleshooting

### Issue: Timer shows incorrect time
**Solution**: Timer uses server-side expiry timestamp from database

### Issue: Resend button doesn't appear
**Solution**: Check JavaScript console, ensure timer script is running

### Issue: Old code still works after resend
**Solution**: Check `storeMFACode()` function - should delete old codes

### Issue: Multiple codes in database
**Solution**: Run cleanup:
```sql
DELETE FROM mfa_codes WHERE user_id = ? AND is_used = FALSE;
```

---

**Resend MFA Code Feature Successfully Implemented!**
