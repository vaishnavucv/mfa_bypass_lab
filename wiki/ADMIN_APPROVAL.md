# Admin Approval System

## Overview

The FastLAN Employee Portal now includes an admin approval system where new user registrations require admin approval before users can login.

## How It Works

### User Registration Flow

1. **User Registers** (`register.php`)
   - User fills out registration form
   - Account is created with `approved = FALSE`
   - User sees message: "Registration successful! Please wait for admin approval before logging in."

2. **User Tries to Login** (`login.php`)
   - If account is NOT approved: Shows error message
   - Message: "Your account is pending admin approval. Please wait for approval before logging in."
   - Login is blocked until admin approves

3. **Admin Approves** (`approve_users.php`)
   - Admin logs in and sees pending approvals count on dashboard
   - Admin navigates to "User Approvals" page
   - Admin can:
     - **Approve**: Sets `approved = TRUE`, user can now login
     - **Reject**: Deletes the user account permanently

4. **User Can Login** (after approval)
   - User enters credentials
   - MFA code is sent via email
   - User completes login successfully

## Database Schema

### New Columns Added to `users` Table:

```sql
approved BOOLEAN DEFAULT FALSE         -- Approval status
approved_at TIMESTAMP NULL             -- When user was approved
approved_by INT NULL                   -- Which admin approved (user_id)
```

### Migration

Run this SQL file to add approval system:
```bash
mysql -u fastlan -pfastlan123 employee_portal < add_user_approval.sql
```

## Admin Interface

### Admin Dashboard (`admin_dashboard.php`)
- Shows "Pending Approvals" stat box
- Highlighted in orange if there are pending users
- Link to "View Pending" if count > 0
- New navigation link: "User Approvals"

### User Approvals Page (`approve_users.php`)
- **Pending Users Section**:
  - Lists all users with `approved = FALSE`
  - Shows: Email, Name, Department, Position, Registration Date
  - Actions: Approve or Reject buttons

- **Approved Users Section**:
  - Lists all approved users (role = 'user')
  - Shows: Email, Name, Department, Position, Approval Date, Approved By

## Testing Scenarios

### Test 1: New User Registration
1. Navigate to `http://localhost:8080/register.php`
2. Register with:
   - Email: `test@fastlan.com`
   - Name: `Test User`
   - Password: `Test@123`
3. See success message about waiting for approval
4. Try logging in → Should be blocked with pending message

### Test 2: Admin Approval Process
1. Login as admin: `admin@fastlan.com` / `Admin@123`
2. Complete MFA verification
3. See "Pending Approvals: 1" on dashboard (orange highlighted)
4. Click "User Approvals" in navigation
5. See test user in "Pending Users" section
6. Click "Approve" button
7. User moves to "Approved Users" section

### Test 3: Approved User Login
1. Logout from admin
2. Login as approved user: `test@fastlan.com` / `Test@123`
3. Receive MFA code via MailHog
4. Complete MFA verification
5. Successfully access dashboard

### Test 4: Admin Rejection
1. Register another test user
2. Login as admin
3. Go to User Approvals
4. Click "Reject" for the user
5. User account is deleted permanently
6. User cannot login (invalid credentials)

## Security Testing

### Approval Bypass Attempts
1. **Direct Dashboard Access**: Try accessing `dashboard.php` without approval
2. **Session Manipulation**: Try modifying session variables
3. **MFA Bypass**: Try skipping MFA after password verification

### SQL Injection Tests
1. Test approval forms with SQL injection payloads
2. Test user_id parameter manipulation
3. Test action parameter tampering

## Activity Logging

All approval activities are logged in `logs/activity.log`:

```
[2025-11-27 XX:XX:XX] User: guest | Action: REGISTER_SUCCESS | Details: New user registered (pending approval): test@fastlan.com
[2025-11-27 XX:XX:XX] User: guest | Action: LOGIN_FAILED | Details: Account not approved: test@fastlan.com
[2025-11-27 XX:XX:XX] User: 1 (admin) | Action: VIEW_USER_APPROVALS | Details: Admin accessed user approval page
[2025-11-27 XX:XX:XX] User: 1 (admin) | Action: USER_APPROVED | Details: Admin approved user ID: X
[2025-11-27 XX:XX:XX] User: 1 (admin) | Action: USER_REJECTED | Details: Admin rejected user ID: X
```

## Database Queries

### Check Pending Users
```sql
SELECT email, full_name, created_at
FROM users
WHERE approved = FALSE
ORDER BY created_at DESC;
```

### Check Approved Users
```sql
SELECT u.email, u.full_name, u.approved_at, a.full_name as approved_by
FROM users u
LEFT JOIN users a ON u.approved_by = a.id
WHERE u.approved = TRUE
ORDER BY u.approved_at DESC;
```

### Manually Approve User
```sql
UPDATE users SET approved = TRUE, approved_at = NOW() WHERE email = 'test@fastlan.com';
```

## Default Users

All existing users are automatically approved during migration:
- `admin@fastlan.com` - Admin account
- `john.doe@fastlan.com` - Test user 1
- `jane.smith@fastlan.com` - Test user 2

New registrations will require approval.

## Files Modified/Created

### New Files:
- `add_user_approval.sql` - Database migration
- `approve_users.php` - Admin approval interface
- `ADMIN_APPROVAL.md` - This documentation

### Modified Files:
- `register.php` - Sets approved=FALSE on registration
- `login.php` - Checks approved status before allowing login
- `admin_dashboard.php` - Added pending approvals stat and navigation link

## Troubleshooting

### Issue: All users blocked from login
**Solution**: Check if migration ran successfully. Existing users should be approved:
```sql
UPDATE users SET approved = TRUE WHERE email IN ('admin@fastlan.com', 'john.doe@fastlan.com');
```

### Issue: Pending count not showing
**Solution**: Clear browser cache and refresh admin dashboard

### Issue: Approval not working
**Solution**: Check logs/activity.log for errors. Verify database permissions.

---

**Admin Approval System Successfully Implemented!**
