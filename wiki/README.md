# FastLAN Employee Portal

A PHP-based employee portal with user authentication, project management, and role-based access control.

## Features

### User Features
- **Registration**: Self-service account registration with email and password
- **Login**: Secure authentication system
- **Dashboard**: View assigned projects with status and priority
- **Profile Management**: Update personal information and change password
- **Project Tracking**: Monitor project status, deadlines, and assignments

### Admin Features
- **User Management**: View all registered users and their details
- **Project Management**: Create, edit, and delete projects
- **Project Assignment**: Assign projects to team members
- **Admin Dashboard**: Overview of users, projects, and assignments
- **Activity Monitoring**: All requests and responses are logged for analysis

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Git (optional, for cloning)
- Burp Suite (optional, for security testing)

### Step-by-Step Setup on Ubuntu

#### 1. Install Required Software

```bash
# Update package list
sudo apt update

# Install PHP and required extensions
sudo apt install php php-cli php-mysql php-mbstring php-xml -y

# Install MySQL Server
sudo apt install mysql-server -y

# Start and enable MySQL service
sudo systemctl start mysql
sudo systemctl enable mysql

# Verify installations
php -v
mysql --version
```

#### 2. Configure MySQL Database

```bash
# Secure MySQL installation (optional but recommended)
sudo mysql_secure_installation

# Login to MySQL as root
sudo mysql -u root -p
```

Inside MySQL console, create the database:
```sql
CREATE DATABASE employee_portal;
EXIT;
```

#### 3. Clone or Download the Project

```bash
# Navigate to your desired directory
cd ~

# If you have the project files, navigate to them
cd /path/to/fastlan-mfa-webapp

# Or clone from repository if available
# git clone <repository-url>
# cd fastlan-mfa-webapp
```

#### 4. Configure Database Connection

Edit the `config.php` file and update database credentials:
```bash
nano config.php
```

Update these lines if needed:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_mysql_password');
define('DB_NAME', 'employee_portal');
```

Save and exit (Ctrl+X, then Y, then Enter)

#### 5. Import Database Schema

```bash
# Import the database.sql file
mysql -u root -p employee_portal < database.sql

# If you get permission errors, try with sudo
sudo mysql -u root employee_portal < database.sql
```

#### 6. Set Permissions

```bash
# Make sure logs directory exists and is writable
mkdir -p logs
chmod 755 -R .
chmod 777 logs/
```

#### 7. Run the Application

Start PHP's built-in web server:
```bash
php -S localhost:8080
```

You should see output like:
```
[Mon Dec  9 10:30:00 2025] PHP 8.1.2 Development Server (http://localhost:8080) started
```

#### 8. Access the Application

Open your web browser and navigate to:
```
http://localhost:8080
```

You will be redirected to the login page.

#### 9. Login with Default Admin Account

Use these credentials for first login:
- **Email**: `admin@fastlan.com`
- **Password**: `Admin@123`

**IMPORTANT**: Change the admin password immediately after first login!

### Running the Application

Every time you want to run the application:

```bash
# Navigate to project directory
cd /path/to/fastlan-mfa-webapp

# Start PHP server
php -S localhost:8080
```

To run on a different port:
```bash
php -S localhost:3000
```

To stop the server, press `Ctrl+C` in the terminal.

## Default Accounts

### Admin Account
- Email: `admin@fastlan.com`
- Password: `Admin@123`

### Test User Accounts
- Email: `john.doe@fastlan.com`
- Password: `User@123`

- Email: `jane.smith@fastlan.com`
- Password: `User@123`

## Application Structure

```
fastlan-mfa-webapp/
├── config.php              # Database and application configuration
├── database.sql            # Database schema and sample data
├── index.php              # Entry point (redirects to login/dashboard)
├── login.php              # User login page
├── register.php           # User registration page
├── logout.php             # Session termination
├── dashboard.php          # User dashboard
├── profile.php            # User profile management
├── admin_dashboard.php    # Admin dashboard
├── manage_projects.php    # Admin project management
├── assign_project.php     # Admin project assignment
├── edit_project.php       # Admin project editing
└── logs/
    └── activity.log       # Activity and request logs
```

## Security Testing with Burp Suite

### Configuring Burp Suite

1. **Start Burp Suite**
   - Open Burp Suite Community/Professional
   - Go to Proxy → Options
   - Ensure proxy is listening on `127.0.0.1:8080`

2. **Configure Browser**
   - Set browser proxy to `127.0.0.1:8080`
   - Or use Firefox with FoxyProxy extension

3. **Intercept Traffic**
   - Turn on "Intercept" in Burp Suite Proxy tab
   - Navigate to the application
   - All HTTP requests/responses will be visible in Burp

### Testing Scenarios

#### 1. Authentication Analysis
- **Login Request**: Analyze POST data to `/login.php`
  - Observe email and password transmission
  - Check for session cookie creation
  - View server responses

- **Registration Request**: Monitor POST to `/register.php`
  - Analyze password policy enforcement
  - Check validation mechanisms

#### 2. Session Management
- **Session Cookie**: Examine `PHPSESSID` cookie
  - Test session fixation
  - Check cookie flags (HttpOnly)
  - Session timeout testing

#### 3. Authorization Testing
- **Horizontal Privilege Escalation**: Try accessing other users' data
- **Vertical Privilege Escalation**: Try accessing admin pages as regular user
- **Parameter Tampering**: Modify `user_id`, `project_id` in requests

#### 4. Request/Response Analysis
All actions are logged in `logs/activity.log`:
```
[2025-01-26 10:30:45] User: 2 (john.doe@fastlan.com) | IP: 127.0.0.1 | Action: LOGIN_SUCCESS
[2025-01-26 10:31:12] User: 2 (john.doe@fastlan.com) | IP: 127.0.0.1 | Action: VIEW_DASHBOARD
```

### Common Attack Vectors to Test

1. **SQL Injection**
   - Test login fields with `' OR '1'='1`
   - Modify project_id parameters

2. **Cross-Site Scripting (XSS)**
   - Try injecting scripts in project descriptions
   - Test profile fields with HTML/JavaScript

3. **CSRF (Cross-Site Request Forgery)**
   - Craft forms that submit to admin endpoints
   - Test without proper tokens

4. **Brute Force**
   - Use Burp Intruder to test login attempts
   - Monitor account lockout mechanisms

## Database Schema

### Users Table
- `id`: Primary key
- `email`: Unique email address
- `password`: Hashed password
- `full_name`: User's full name
- `department`: Department name
- `position`: Job position
- `role`: user/admin
- `created_at`: Registration timestamp
- `last_login`: Last login timestamp

### Projects Table
- `id`: Primary key
- `project_name`: Project name
- `description`: Project description
- `status`: pending/in_progress/completed/on_hold
- `priority`: low/medium/high/critical
- `start_date`: Project start date
- `due_date`: Project deadline
- `created_by`: Admin who created the project

### Project Assignments Table
- `id`: Primary key
- `project_id`: Reference to projects
- `user_id`: Reference to users
- `assigned_by`: Admin who made the assignment
- `assigned_at`: Assignment timestamp

## Activity Logging

All user activities are logged to `logs/activity.log` including:
- Login attempts (successful/failed)
- Registration attempts
- Profile updates
- Password changes
- Project assignments
- Admin actions

View logs in real-time:
```bash
tail -f logs/activity.log
```

## API Endpoints (for Testing)

All endpoints use POST method for form submissions:

### Authentication
- `POST /login.php` - User login
- `POST /register.php` - New user registration
- `GET /logout.php` - Session termination

### User Actions
- `GET /dashboard.php` - View dashboard
- `GET /profile.php` - View profile
- `POST /profile.php` - Update profile

### Admin Actions
- `GET /admin_dashboard.php` - Admin overview
- `POST /manage_projects.php` - Create project
- `GET /manage_projects.php?delete={id}` - Delete project
- `POST /edit_project.php` - Update project
- `POST /assign_project.php` - Assign projects to user

## Lab Exercises

### Exercise 1: Basic Authentication Analysis
1. Intercept login request in Burp Suite
2. Analyze request parameters
3. View session cookie creation
4. Document authentication flow

### Exercise 2: Session Hijacking
1. Capture valid session cookie
2. Try using it in different browser
3. Test session timeout
4. Document findings

### Exercise 3: Authorization Bypass
1. Login as regular user
2. Try accessing `/admin_dashboard.php`
3. Modify request parameters
4. Document access controls

### Exercise 4: Input Validation
1. Test XSS in project descriptions
2. Test SQL injection in login forms
3. Try malicious file uploads (if implemented)
4. Document vulnerabilities found

## Troubleshooting

### Database Connection Error
- Check MySQL service is running
- Verify credentials in `config.php`
- Ensure database exists

### Permission Denied on logs/
```bash
mkdir logs
chmod 777 logs
```

### Session Not Persisting
- Check PHP session configuration
- Ensure cookies are enabled in browser
- Verify server has write access to session directory

## Security Notes

This application is intentionally designed for security testing and education:
- Basic password hashing using PHP's `password_hash()`
- Simple session management
- No CSRF tokens (for testing)
- Minimal input sanitization (allows testing)
- Activity logging for forensic analysis

**WARNING**: This application is for educational purposes only. Do not deploy in production without proper security hardening.

## Support

For issues or questions about the lab setup, check the activity logs:
```bash
cat logs/activity.log
```

## License

This is an educational project for cybersecurity training purposes.
