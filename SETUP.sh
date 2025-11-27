#!/bin/bash

echo "=========================================="
echo "FastLAN Employee Portal - Database Setup"
echo "=========================================="
echo ""

# Step 1: Create MySQL user and database
echo "Step 1: Creating MySQL user and database..."
echo "You will be prompted for your MySQL root password (if you have one)"
echo ""

sudo mysql << 'EOF'
-- Create the database
CREATE DATABASE IF NOT EXISTS employee_portal;

-- Create user 'fastlan' with password 'fastlan123'
DROP USER IF EXISTS 'fastlan'@'localhost';
CREATE USER 'fastlan'@'localhost' IDENTIFIED BY 'fastlan123';

-- Grant all privileges
GRANT ALL PRIVILEGES ON employee_portal.* TO 'fastlan'@'localhost';
FLUSH PRIVILEGES;

SELECT 'MySQL user created successfully!' AS Status;
EOF

if [ $? -eq 0 ]; then
    echo "✓ MySQL user created successfully"
else
    echo "✗ Failed to create MySQL user"
    echo "Try running: sudo mysql < CREATE_USER.sql"
    exit 1
fi

echo ""
echo "Step 2: Importing database schema and data..."

mysql -u fastlan -pfastlan123 employee_portal < reset_database.sql

if [ $? -eq 0 ]; then
    echo "✓ Database schema imported successfully"
else
    echo "✗ Failed to import database"
    exit 1
fi

echo ""
echo "Step 3: Creating logs directory..."
mkdir -p logs
chmod 777 logs
echo "✓ Logs directory created"

echo ""
echo "=========================================="
echo "✅ Setup Complete!"
echo "=========================================="
echo ""
echo "Database Credentials:"
echo "  Host: localhost"
echo "  User: fastlan"
echo "  Pass: fastlan123"
echo "  DB:   employee_portal"
echo ""
echo "Login Credentials:"
echo "  Admin: admin@fastlan.com / Admin@123"
echo "  User:  john.doe@fastlan.com / User@123"
echo ""
echo "Start the server:"
echo "  php -S localhost:8080"
echo ""
echo "Then open: http://localhost:8080"
echo "=========================================="
