# MFA Fixed Prefix Input (FAST-XXXX)

## Overview

The MFA verification page now has a **fixed prefix "FAST-"** in the input field. Users only need to enter the **4 digits**.

## How It Works

### Visual Input Field

The input field is split into two parts:

```
┌──────────┬─────────────┐
│ FAST-    │  XXXX       │
│ (fixed)  │  (input)    │
└──────────┴─────────────┘
```

**Left side**: `FAST-` (gray background, not editable)
**Right side**: 4-digit input field (white background, editable)

### User Experience

1. **Email shows**: `FAST-1234`
2. **User sees**:
   ```
   Enter Verification Code:
   ┌──────────┬─────────────┐
   │ FAST-    │  ____       │
   └──────────┴─────────────┘
   ```
3. **User types**: Only the 4 digits: `1234`
4. **Field displays**:
   ```
   ┌──────────┬─────────────┐
   │ FAST-    │  1234       │
   └──────────┴─────────────┘
   ```

### Input Behavior

- **Prefix**: `FAST-` is always visible (cannot be edited or deleted)
- **Input**: Only accepts 4 digits (0-9)
- **Maxlength**: 4 characters
- **Pattern**: `[0-9]{4}` (numeric only)
- **Input mode**: Numeric keyboard on mobile devices
- **Auto-uppercase**: Not needed (only digits)

## Technical Implementation

### HTML Structure

```html
<div class="code-input-container">
    <span class="code-prefix">FAST-</span>
    <input type="text"
           name="code"
           class="code-input"
           maxlength="4"
           pattern="[0-9]{4}"
           placeholder="XXXX"
           inputmode="numeric"
           required
           autofocus>
</div>
```

**Key attributes**:
- `maxlength="4"` - Only 4 characters allowed
- `pattern="[0-9]{4}"` - HTML5 validation for 4 digits
- `inputmode="numeric"` - Shows numeric keyboard on mobile
- `placeholder="XXXX"` - Shows expected format

### CSS Styling

```css
.code-input-container {
    display: flex;              /* Side-by-side layout */
    align-items: center;
    justify-content: center;
}

.code-prefix {
    font-size: 24px;
    background: #e0e0e0;        /* Gray background */
    padding: 15px 10px;
    border: 2px solid #4CAF50;
    border-right: none;         /* Merge with input */
}

.code-input {
    font-size: 24px;
    letter-spacing: 8px;        /* Space between digits */
    width: 150px;
    border: 2px solid #4CAF50;
    border-left: none;          /* Merge with prefix */
}
```

### Validation Logic

```php
$code = sanitizeInput($_POST['code']);

// Validate: Must be exactly 4 digits
if (!preg_match('/^\d{4}$/', $code)) {
    $error = "Invalid code format. Please enter 4 digits.";
} else {
    // Verify code
    if (verifyMFACode($userId, $code)) {
        // Success
    }
}
```

**Validation**:
- ✅ `1234` - Valid
- ✅ `0001` - Valid
- ✅ `9999` - Valid
- ❌ `123` - Invalid (only 3 digits)
- ❌ `12345` - Invalid (5 digits, prevented by maxlength)
- ❌ `ABCD` - Invalid (letters, prevented by pattern)
- ❌ `12-34` - Invalid (hyphen, prevented by pattern)

## Testing Scenarios

### Test 1: Normal Entry

1. Login with valid credentials
2. Check MailHog - code shown as `FAST-1234`
3. On verification page, see:
   ```
   FAST- [____]
   ```
4. Type only: `1234`
5. Field shows: `FAST- [1234]`
6. Click "Verify & Login"
7. Should successfully login

**Expected**: ✅ Login successful

---

### Test 2: Leading Zeros

1. Login with valid credentials
2. Code in email: `FAST-0123`
3. Type: `0123`
4. Submit and verify

**Expected**: ✅ Code accepted (leading zeros preserved)

---

### Test 3: Try Typing Prefix

1. On verification page
2. Try typing: `FAST-1234`
3. Input field should only show: `1234`
4. Prefix remains separate

**Expected**: ✅ Only digits entered, prefix stays fixed

---

### Test 4: Invalid Length

1. On verification page
2. Try typing only 3 digits: `123`
3. Try to submit
4. HTML5 validation should prevent submission

**Expected**: ❌ Browser validation error: "Please match the requested format"

---

### Test 5: Non-Numeric Input

1. On verification page
2. Try typing letters: `ABCD`
3. Input field should not accept letters

**Expected**: ❌ Only numeric input accepted

---

### Test 6: Mobile Keyboard

1. Open verification page on mobile device
2. Tap input field
3. Should show **numeric keyboard** (not full keyboard)

**Expected**: ✅ Numeric keyboard appears

---

### Test 7: Copy-Paste Full Code

1. Copy from email: `FAST-1234`
2. Paste into input field
3. Should extract only: `1234`
4. Submit and verify

**Note**: May need JavaScript to handle this properly.

---

### Test 8: Burp Suite Capture

1. Enter code: `1234`
2. Submit form
3. In Burp Suite, you should see:

```http
POST /verify_mfa.php HTTP/1.1
Host: localhost:8080
Content-Type: application/x-www-form-urlencoded

code=1234
```

**Expected**: Only the 4 digits sent, not "FAST-1234"

---

## Visual Design

### Desktop View

```
┌─────────────────────────────────────────┐
│   Verify Your Login                     │
├─────────────────────────────────────────┤
│ Code sent to: admin@fastlan.com         │
│                                         │
│ Enter Verification Code:                │
│                                         │
│  ┌──────────┬──────────────────┐        │
│  │ FAST-    │  X X X X         │        │
│  │ (gray)   │  (white, input)  │        │
│  └──────────┴──────────────────┘        │
│                                         │
│  Enter the 4-digit code from your email │
│                                         │
│  [     Verify & Login     ]             │
│                                         │
│  Code expires in: 9:45                  │
└─────────────────────────────────────────┘
```

### Mobile View (Responsive)

```
┌────────────────────────┐
│ Verify Your Login      │
├────────────────────────┤
│ Code sent to:          │
│ admin@fastlan.com      │
│                        │
│ Enter Code:            │
│                        │
│ ┌─────┬──────────┐     │
│ │FAST-│ XXXX     │     │
│ └─────┴──────────┘     │
│                        │
│ [Verify & Login]       │
│                        │
│ Expires: 9:45          │
└────────────────────────┘
```

## Browser Compatibility

### HTML5 Pattern Validation

```html
pattern="[0-9]{4}"
```

**Supported**:
- ✅ Chrome 10+
- ✅ Firefox 4+
- ✅ Safari 5+
- ✅ Edge (all versions)
- ✅ Mobile browsers

### Input Mode Numeric

```html
inputmode="numeric"
```

**Behavior**:
- Mobile devices: Shows numeric keyboard
- Desktop: No effect (regular keyboard)

**Supported**:
- ✅ iOS Safari 12.2+
- ✅ Android Chrome 66+
- ✅ Samsung Internet 9+

## Advantages

### User Experience
1. **Clearer input**: Users know exactly what to enter (just 4 digits)
2. **No confusion**: Can't accidentally enter "FAST-" or wrong format
3. **Mobile friendly**: Numeric keyboard automatically shown
4. **Visual consistency**: Input matches email format

### Security
1. **Format validation**: HTML5 pattern prevents non-numeric input
2. **Length validation**: Maxlength prevents too many digits
3. **Server validation**: Backend still validates 4-digit format

### Development
1. **Simpler validation**: Only check for 4 digits
2. **No parsing**: Don't need to extract digits from "FAST-XXXX"
3. **Cleaner code**: Less regex complexity

## Burp Suite Testing

### Form Submission

**Request**:
```http
POST /verify_mfa.php HTTP/1.1
Host: localhost:8080
Cookie: PHPSESSID=...
Content-Type: application/x-www-form-urlencoded
Content-Length: 9

code=1234
```

**Note**: Only the 4 digits are sent, not the prefix.

### Test Invalid Input

Using Burp Repeater, try sending:

1. **Less than 4 digits**:
   ```
   code=123
   ```
   **Expected**: "Invalid code format. Please enter 4 digits."

2. **More than 4 digits**:
   ```
   code=12345
   ```
   **Expected**: "Invalid code format. Please enter 4 digits."

3. **Non-numeric**:
   ```
   code=ABCD
   ```
   **Expected**: "Invalid code format. Please enter 4 digits."

4. **With prefix**:
   ```
   code=FAST-1234
   ```
   **Expected**: "Invalid code format. Please enter 4 digits."

## Files Modified

1. **verify_mfa.php**
   - Changed input field to split design (prefix + input)
   - Added `.code-input-container` and `.code-prefix` CSS
   - Updated validation to accept only 4 digits
   - Added `inputmode="numeric"` for mobile
   - Updated responsive styling

## Migration from Old Format

### Old Format (Accepted multiple formats)
```html
<input type="text" placeholder="FAST-XXXX" maxlength="9">
```
- Accepted: FAST-1234, FAST1234, 1234, fast-1234
- Required parsing logic

### New Format (Fixed prefix)
```html
<span>FAST-</span>
<input type="text" placeholder="XXXX" maxlength="4" pattern="[0-9]{4}">
```
- Accepts: Only 4 digits (1234)
- No parsing needed

## CSS Breakdown

```css
/* Container - holds both prefix and input */
.code-input-container {
    display: flex;           /* Side-by-side layout */
    align-items: center;     /* Vertical center */
    justify-content: center; /* Horizontal center */
}

/* Fixed prefix "FAST-" */
.code-prefix {
    font-size: 24px;
    background: #e0e0e0;     /* Gray to indicate non-editable */
    padding: 15px 10px;
    border: 2px solid #4CAF50;
    border-right: none;      /* Seamless join with input */
    letter-spacing: 2px;
}

/* Input field for 4 digits */
.code-input {
    font-size: 24px;
    letter-spacing: 8px;     /* Space between digits */
    width: 150px;            /* Fits 4 digits nicely */
    border: 2px solid #4CAF50;
    border-left: none;       /* Seamless join with prefix */
    text-align: center;
}
```

## Troubleshooting

### Issue 1: Prefix not showing
**Check**: CSS loaded correctly, `.code-prefix` class applied

### Issue 2: Can type more than 4 digits
**Check**: `maxlength="4"` attribute present

### Issue 3: Can type letters
**Check**: `pattern="[0-9]{4}"` attribute present

### Issue 4: Numeric keyboard not showing on mobile
**Check**: `inputmode="numeric"` attribute present

### Issue 5: Validation accepting wrong format
**Check**: Backend validation regex: `/^\d{4}$/`

---

**Fixed Prefix Input Successfully Implemented!**

Users now see a clear visual separation:
- **FAST-** (fixed, gray background)
- **____** (input field for 4 digits)

This improves usability and reduces input errors!
