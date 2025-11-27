-- Create a new MySQL user for the FastLAN application
-- Run this as MySQL root: sudo mysql < CREATE_USER.sql

-- Create the database
CREATE DATABASE IF NOT EXISTS employee_portal;

-- Create user 'fastlan' with password 'fastlan123'
CREATE USER IF NOT EXISTS 'fastlan'@'localhost' IDENTIFIED BY 'fastlan123';

-- Grant all privileges on the employee_portal database
GRANT ALL PRIVILEGES ON employee_portal.* TO 'fastlan'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Show success message
SELECT 'User created successfully!' AS Status;
SELECT 'Username: fastlan' AS '';
SELECT 'Password: fastlan123' AS '';
SELECT 'Database: employee_portal' AS '';
