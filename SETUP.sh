#!/bin/bash

# FastLAN Employee Portal - Automated Setup Script
# This script automates all configuration and installation
# After running this script, simply use: php -S localhost:8080

set -e  # Exit on error

echo "=========================================="
echo "FastLAN Employee Portal - Auto Setup"
echo "=========================================="
echo ""

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${YELLOW}➜${NC} $1"
}

# Check if script is run with bash
if [ -z "$BASH_VERSION" ]; then
    echo "Please run this script with bash: bash SETUP.sh"
    exit 1
fi

# Step 1: Check for required software
echo "Step 1: Checking system requirements..."
echo ""

# Check PHP
if ! command -v php &> /dev/null; then
    print_error "PHP is not installed"
    echo "  Install with: sudo apt install php php-cli php-mysql php-mbstring php-xml -y"
    exit 1
fi
print_success "PHP found: $(php -v | head -n1)"

# Check MySQL
if ! command -v mysql &> /dev/null; then
    print_error "MySQL is not installed"
    echo "  Install with: sudo apt install mysql-server -y"
    exit 1
fi
print_success "MySQL found: $(mysql --version | head -n1)"

echo ""

# Step 2: Check if MySQL is running
echo "Step 2: Checking MySQL service..."
if ! systemctl is-active --quiet mysql 2>/dev/null && ! systemctl is-active --quiet mariadb 2>/dev/null; then
    print_info "MySQL service not running, attempting to start..."
    sudo systemctl start mysql 2>/dev/null || sudo systemctl start mariadb 2>/dev/null || true
    sleep 2
fi

if systemctl is-active --quiet mysql 2>/dev/null || systemctl is-active --quiet mariadb 2>/dev/null; then
    print_success "MySQL service is running"
else
    print_info "MySQL service status unknown (this may be okay in some environments)"
fi

echo ""

# Step 3: Create MySQL user and database
echo "Step 3: Setting up database and user..."
print_info "Creating database 'employee_portal' and user 'fastlan'..."

# Detect which validate_password variables exist (handles both plugin and component names)
PASSWORD_POLICY_VAR=$(sudo mysql -Nse "SHOW VARIABLES LIKE 'validate_password%policy%';" 2>/dev/null | awk '{print $1}' | head -n1)
PASSWORD_LENGTH_VAR=$(sudo mysql -Nse "SHOW VARIABLES LIKE 'validate_password%length%';" 2>/dev/null | awk '{print $1}' | head -n1)

if [ -n "$PASSWORD_POLICY_VAR" ] && [ -n "$PASSWORD_LENGTH_VAR" ]; then
    print_info "Temporarily relaxing password policy to allow default credentials..."
    PW_POLICY_ADJUSTMENTS="SET @old_validate_password_policy = @@GLOBAL.${PASSWORD_POLICY_VAR};
SET @old_validate_password_length = @@GLOBAL.${PASSWORD_LENGTH_VAR};
SET GLOBAL ${PASSWORD_POLICY_VAR} = 'LOW';
SET GLOBAL ${PASSWORD_LENGTH_VAR} = 6;"
    PW_POLICY_RESTORE="SET GLOBAL ${PASSWORD_POLICY_VAR} = @old_validate_password_policy;
SET GLOBAL ${PASSWORD_LENGTH_VAR} = @old_validate_password_length;"
else
    print_info "validate_password plugin not available; skipping password policy changes"
    PW_POLICY_ADJUSTMENTS="SELECT 'password policy plugin not available';"
    PW_POLICY_RESTORE=""
fi

sudo mysql <<EOF
$PW_POLICY_ADJUSTMENTS

DROP DATABASE IF EXISTS employee_portal;
CREATE DATABASE employee_portal;

DROP USER IF EXISTS 'fastlan'@'localhost';
CREATE USER 'fastlan'@'localhost' IDENTIFIED BY 'fastlan123';

GRANT ALL PRIVILEGES ON employee_portal.* TO 'fastlan'@'localhost';
FLUSH PRIVILEGES;

$PW_POLICY_RESTORE
EOF

if [ $? -eq 0 ]; then
    print_success "Database and user created successfully"
else
    print_error "Failed to create database and user"
    echo "  You may need to run: sudo mysql < CREATE_USER.sql"
    exit 1
fi

echo ""

# Step 4: Import base database schema
echo "Step 4: Importing database schema..."
mysql -u fastlan -pfastlan123 employee_portal < reset_database.sql 2>/dev/null

if [ $? -eq 0 ]; then
    print_success "Base schema and default data imported"
else
    print_error "Failed to import base schema"
    exit 1
fi

# Step 5: Import MFA table
echo "Step 5: Setting up MFA (Multi-Factor Authentication)..."
mysql -u fastlan -pfastlan123 employee_portal < add_mfa_table.sql 2>/dev/null

if [ $? -eq 0 ]; then
    print_success "MFA table created"
else
    print_error "Failed to create MFA table"
    exit 1
fi

# Step 6: Import user approval system
echo "Step 6: Setting up user approval system..."
mysql -u fastlan -pfastlan123 employee_portal < add_user_approval.sql 2>/dev/null

if [ $? -eq 0 ]; then
    print_success "User approval system configured"
else
    print_error "Failed to configure user approval system"
    exit 1
fi

echo ""

# Step 7: Create and configure logs directory
echo "Step 7: Setting up logs directory..."
mkdir -p logs
chmod 777 logs

if [ -d "logs" ]; then
    print_success "Logs directory created and configured"
else
    print_error "Failed to create logs directory"
    exit 1
fi

echo ""

# Step 8: Verify config.php exists
echo "Step 8: Verifying configuration files..."
if [ -f "config.php" ]; then
    print_success "Configuration file found"
else
    print_error "config.php not found"
    exit 1
fi

echo ""

# Step 9: Test database connection
echo "Step 9: Testing database connection..."
php -r "
\$conn = @new mysqli('localhost', 'fastlan', 'fastlan123', 'employee_portal');
if (\$conn->connect_error) {
    echo 'FAILED';
    exit(1);
}
echo 'SUCCESS';
\$conn->close();
" 2>/dev/null

if [ $? -eq 0 ]; then
    print_success "Database connection test passed"
else
    print_error "Database connection test failed"
    exit 1
fi

echo ""

# Step 10: Display completion message
echo "=========================================="
echo -e "${GREEN}✅ Setup Complete!${NC}"
echo "=========================================="
echo ""
echo "📊 Database Configuration:"
echo "  Host:     localhost"
echo "  User:     fastlan"
echo "  Password: fastlan123"
echo "  Database: employee_portal"
echo ""
echo "🔐 Default Login Credentials:"
echo "  Admin: admin@fastlan.com / Admin@123"
echo "  User:  john.doe@fastlan.com / User@123"
echo "  User:  jane.smith@fastlan.com / User@123"
echo ""
echo "🚀 To start the web application:"
echo -e "  ${GREEN}php -S localhost:8080${NC}"
echo ""
echo "🌐 Then open in your browser:"
echo "  http://localhost:8080"
echo ""
echo "📝 Activity logs will be saved to:"
echo "  ./logs/activity.log"
echo ""
echo "🔧 To change the port (e.g., 3000):"
echo "  php -S localhost:3000"
echo ""
echo "⚠️  Security Note:"
echo "  This is for educational/testing purposes only."
echo "  Do NOT use in production without proper hardening."
echo ""
echo "=========================================="
echo ""
print_info "You can now run: ${GREEN}php -S localhost:8080${NC}"
echo ""
