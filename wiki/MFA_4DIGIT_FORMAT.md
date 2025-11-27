# MFA 4-Digit Code Format (FAST-XXXX)

## Overview

The MFA system has been updated to use **4-digit codes** with the format **FAST-XXXX** instead of the previous 6-digit codes.

## Changes Made

### 1. Code Generation
- **Old**: 6-digit code (000000-999999)
- **New**: 4-digit code (0000-9999)
- **Function**: `generateMFACode()` in `mfa_helper.php`

```php
// Generates codes like: 0123, 1234, 9876, etc.
function generateMFACode() {
    return sprintf("%04d", mt_rand(0, 9999));
}
```

### 2. Database Schema
- **Column**: `mfa_codes.code`
- **Old**: `VARCHAR(6)`
- **New**: `VARCHAR(4)`

```sql
ALTER TABLE mfa_codes MODIFY COLUMN code VARCHAR(4) NOT NULL;
```

### 3. Email Format
Emails now display the code as **FAST-XXXX**:

```
┌─────────────────────────────┐
│ Your Verification Code:     │
│                             │
│      FAST-1234              │
│                             │
│ Enter the 4-digit code in   │
│ the format: FAST-XXXX       │
└─────────────────────────────┘
```

### 4. Input Field Format
The verification page now accepts multiple formats:

**Accepted Formats**:
- `FAST-1234` (recommended)
- `FAST1234` (without hyphen)
- `1234` (just digits)
- `fast-1234` (lowercase, will be converted)

**Input Field**:
```html
<input type="text" name="code" placeholder="FAST-XXXX" maxlength="9">
```

### 5. Validation Logic

The system now validates codes using regex patterns:

```php
// Accept: FAST-1234, FAST1234, or 1234
if (preg_match('/^FAST-?(\d{4})$/i', $code, $matches)) {
    $extractedCode = $matches[1]; // Extract 1234
}
```

**Validation Rules**:
- Must be exactly 4 digits
- Can have FAST- prefix (optional)
- Case insensitive (FAST, fast, FaSt all work)
- Hyphen is optional

## User Experience

### Login Flow

1. **User logs in** with email/password
2. **MFA code generated**: 4-digit random number (e.g., 7531)
3. **Email sent** with format: **FAST-7531**
4. **Verification page** shows input field with placeholder: `FAST-XXXX`
5. **User enters code**: Can type `FAST-7531`, `FAST7531`, or just `7531`
6. **Code validated** and user logged in

### Example Codes

```
FAST-0001
FAST-0123
FAST-1234
FAST-5678
FAST-9999
```

## Testing Scenarios

### Test 1: Full Format (FAST-XXXX)

1. Login with valid credentials
2. Check MailHog - code shown as `FAST-1234`
3. Enter code as `FAST-1234` on verification page
4. Should successfully login

**Expected**: ✅ Code accepted

### Test 2: Without Hyphen (FASTXXXX)

1. Login with valid credentials
2. Code in email: `FAST-5678`
3. Enter code as `FAST5678` (no hyphen)
4. Should successfully login

**Expected**: ✅ Code accepted

### Test 3: Digits Only (XXXX)

1. Login with valid credentials
2. Code in email: `FAST-9876`
3. Enter code as `9876` (just digits)
4. Should successfully login

**Expected**: ✅ Code accepted

### Test 4: Lowercase Format

1. Login with valid credentials
2. Code in email: `FAST-4321`
3. Enter code as `fast-4321` (lowercase)
4. Should successfully login (converted to uppercase)

**Expected**: ✅ Code accepted

### Test 5: Invalid Format

Try these invalid formats:

| Input | Expected Result |
|-------|----------------|
| `FAST-12` | ❌ Error: "Invalid code format" (only 2 digits) |
| `FAST-12345` | ❌ Error: "Invalid code format" (5 digits) |
| `TEST-1234` | ❌ Error: "Invalid code format" (wrong prefix) |
| `FAST-ABCD` | ❌ Error: "Invalid code format" (letters) |
| `12345` | ❌ Error: "Invalid code format" (5 digits) |

### Test 6: Code Reuse

1. Login and get code: `FAST-1111`
2. Use code successfully → Login complete
3. Logout
4. Try using same code `FAST-1111` again
5. Should fail (code marked as used)

**Expected**: ❌ Error: "Invalid or expired code"

### Test 7: Expired Code

1. Login and get code: `FAST-2222`
2. Wait 10+ minutes (or manually expire in database)
3. Try using code `FAST-2222`
4. Should fail (code expired)

**Expected**: ❌ Error: "Invalid or expired code"

### Test 8: Resend Code

1. Login and wait for code to expire
2. Click "Resend New Code"
3. New code sent: `FAST-3333`
4. Enter new code `FAST-3333`
5. Should successfully login

**Expected**: ✅ New code works, old code invalidated

## Database Queries

### Check Current Codes
```sql
SELECT user_id, code, created_at, expires_at, is_used
FROM mfa_codes
ORDER BY created_at DESC
LIMIT 10;
```

Example output:
```
user_id | code | created_at          | expires_at          | is_used
--------|------|---------------------|---------------------|--------
1       | 1234 | 2025-11-27 10:00:00 | 2025-11-27 10:10:00 | 0
2       | 5678 | 2025-11-27 09:55:00 | 2025-11-27 10:05:00 | 1
```

### Generate Test Code Manually
```sql
INSERT INTO mfa_codes (user_id, code, expires_at)
VALUES (1, '9999', DATE_ADD(NOW(), INTERVAL 10 MINUTE));
```

### Check Code Format
```sql
SELECT code, LENGTH(code) as length
FROM mfa_codes;
```

Expected: All codes have `length = 4`

## Security Considerations

### Code Space Reduction
- **6-digit codes**: 1,000,000 possible combinations (10^6)
- **4-digit codes**: 10,000 possible combinations (10^4)
- **Reduction**: 100x fewer combinations

**Implications**:
- Easier to brute force (10,000 attempts vs 1,000,000)
- Still acceptable with rate limiting and expiration
- Good for lab/testing environment

**Mitigation Strategies** (Production):
1. Add rate limiting (3 attempts per minute)
2. Lock account after 5 failed attempts
3. Add CAPTCHA after 2 failed attempts
4. Use longer expiry (5 minutes instead of 10)
5. Consider 6-digit codes for production

### Brute Force Calculation

**Without rate limiting**:
- 10,000 codes to try
- Average success: 5,000 attempts
- At 100 attempts/second: 50 seconds to crack

**With rate limiting** (3 attempts/min):
- 10,000 codes to try
- At 3 attempts/min: 55 hours to try all codes
- At 3 attempts/min: 27 hours average to crack

## Burp Suite Testing

### Capture Code Submission

```http
POST /verify_mfa.php HTTP/1.1
Host: localhost:8080
Cookie: PHPSESSID=...
Content-Type: application/x-www-form-urlencoded

code=FAST-1234
```

### Test Different Formats

**Test 1**: Submit with full format
```
code=FAST-1234
```

**Test 2**: Submit without hyphen
```
code=FAST1234
```

**Test 3**: Submit digits only
```
code=1234
```

**Test 4**: Submit lowercase
```
code=fast-1234
```

All should be accepted and extract `1234` for verification.

### Brute Force Attack (Testing)

1. Send code submission to Intruder
2. Set payload position: `code=§FAST-0000§`
3. Payload type: Numbers
4. From: 0000, To: 9999, Step: 1
5. Format: `FAST-%04d`
6. Start attack

**Expected**:
- 10,000 requests
- One will succeed (correct code)
- No rate limiting in lab environment

## Activity Log Examples

```log
[2025-11-27 10:00:00] Action: MFA_SENT | Details: MFA code sent to: user@test.com
[2025-11-27 10:00:15] Action: MFA_VERIFY_ATTEMPT | Details: POST data received
[2025-11-27 10:00:15] Action: MFA_FAILED | Details: Invalid code format: FAST-12
[2025-11-27 10:00:20] Action: MFA_VERIFY_ATTEMPT | Details: POST data received
[2025-11-27 10:00:20] Action: MFA_SUCCESS | Details: User verified: user@test.com
```

## Files Modified

1. **mfa_helper.php**
   - `generateMFACode()` - Changed to 4 digits
   - `storeMFACode()` - Updated comment
   - `sendMFACode()` - Updated email template to show FAST-XXXX

2. **verify_mfa.php**
   - Input field placeholder changed to "FAST-XXXX"
   - Added regex validation for FAST-XXXX format
   - Added hint text for users
   - Updated maxlength to 9 characters

3. **Database**
   - `mfa_codes.code` column changed from VARCHAR(6) to VARCHAR(4)

## Migration Steps

If upgrading from 6-digit to 4-digit:

```sql
-- Step 1: Clear old codes
DELETE FROM mfa_codes;

-- Step 2: Modify column
ALTER TABLE mfa_codes MODIFY COLUMN code VARCHAR(4) NOT NULL;

-- Step 3: Verify
DESCRIBE mfa_codes;
```

## UI Display

### Email Template
```
FastLAN Security Verification

Hello John Doe,

A login attempt was detected for your account.
Please enter the code below in the verification page:

         FAST-1234

This code will expire in 10 minutes.

Enter the 4-digit code in the format: FAST-XXXX
```

### Verification Page
```
┌─────────────────────────────────────┐
│   Verify Your Login                 │
├─────────────────────────────────────┤
│ Code sent to: admin@fastlan.com     │
│                                     │
│ [ FAST-XXXX ]  ← Input field        │
│ Enter code in format: FAST-1234     │
│ or just 1234                        │
│                                     │
│ [Verify & Login]                    │
│                                     │
│ Code expires in: 9:45               │
└─────────────────────────────────────┘
```

## Troubleshooting

### Issue 1: Code still shows 6 digits in email
**Solution**: Clear browser cache, generate new code

### Issue 2: "Invalid code format" error for valid code
**Solution**: Check if code is exactly 4 digits, check regex pattern

### Issue 3: Old 6-digit codes still in database
**Solution**: Run `DELETE FROM mfa_codes;` to clear

### Issue 4: Column too small error
**Solution**: Run `ALTER TABLE mfa_codes MODIFY COLUMN code VARCHAR(4);`

---

**4-Digit MFA Code Format Successfully Implemented!**

Now using **FAST-XXXX** format for easier entry and better branding.
