# FastLAN Employee Portal - Quick Start Guide

## One-Command Setup

Run the automated setup script to configure everything:

```bash
bash SETUP.sh
```

This script will automatically:
- Check system requirements (PHP, MySQL)
- Start MySQL service if needed
- Create database and user
- Import all database schemas (base + MFA + user approval)
- Configure logs directory
- Test database connection
- Display all credentials and instructions

## Start the Web Application

After setup completes, simply run:

```bash
php -S localhost:8080
```

Then open your browser to: **http://localhost:8080**

## Default Login Credentials

### Admin Account
- Email: `admin@fastlan.com`
- Password: `Admin@123`

### Test User Accounts
- Email: `john.doe@fastlan.com` / Password: `User@123`
- Email: `jane.smith@fastlan.com` / Password: `User@123`

## System Requirements

- PHP 7.4+ (with php-cli, php-mysql, php-mbstring, php-xml)
- MySQL 5.7+ or MariaDB
- Linux/Ubuntu environment

### Install Requirements (if needed)

```bash
# Install PHP and extensions
sudo apt update
sudo apt install php php-cli php-mysql php-mbstring php-xml -y

# Install MySQL
sudo apt install mysql-server -y

# Start MySQL service
sudo systemctl start mysql
sudo systemctl enable mysql
```

## Database Configuration

The setup script automatically configures:
- Database: `employee_portal`
- User: `fastlan`
- Password: `fastlan123`
- Host: `localhost`

All settings are stored in `config.php`.

## Features Included

- User registration and authentication
- Multi-Factor Authentication (MFA)
- User approval system (admin approval required)
- Project management
- Role-based access control (admin/user)
- Activity logging

## Changing the Port

To run on a different port:

```bash
php -S localhost:3000
```

## Viewing Logs

Activity logs are saved to `logs/activity.log`:

```bash
tail -f logs/activity.log
```

## Troubleshooting

If setup fails, check:

1. MySQL is installed and running:
   ```bash
   sudo systemctl status mysql
   ```

2. PHP is installed:
   ```bash
   php -v
   ```

3. You have sudo privileges for database setup

## Re-running Setup

To completely reset and re-run setup:

```bash
bash SETUP.sh
```

The script will drop existing database and recreate everything.

## Security Warning

This application is for **educational and testing purposes only**.

Do NOT deploy in production without proper security hardening.
