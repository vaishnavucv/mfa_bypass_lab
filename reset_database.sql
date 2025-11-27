-- FastLAN Employee Portal - Database Reset Script
-- Run this with: mysql -u root -p < reset_database.sql

DROP DATABASE IF EXISTS employee_portal;
CREATE DATABASE employee_portal;
USE employee_portal;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    department VARCHAR(100),
    position VARCHAR(100),
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email)
);

-- Projects table
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('pending', 'in_progress', 'completed', 'on_hold') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    start_date DATE,
    due_date DATE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Project assignments table
CREATE TABLE project_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_assignment (project_id, user_id)
);

-- Insert default admin user (password: Admin@123)
INSERT INTO users (email, password, full_name, department, position, role) VALUES
('admin@fastlan.com', '$2y$10$va6rzVYWrXyLXOWzAEkHqu4kELPTxevQtjIgu0ORpnvg9kej3pzay', 'System Administrator', 'IT', 'Administrator', 'admin');

-- Insert sample users for testing (password: User@123)
INSERT INTO users (email, password, full_name, department, position, role) VALUES
('john.doe@fastlan.com', '$2y$10$6Rr535Aqgd9pfaitLVOScO5lRy2zZh97uK6vgJus3BiAou/fRKckq', 'John Doe', 'Engineering', 'Senior Developer', 'user'),
('jane.smith@fastlan.com', '$2y$10$6Rr535Aqgd9pfaitLVOScO5lRy2zZh97uK6vgJus3BiAou/fRKckq', 'Jane Smith', 'Marketing', 'Marketing Manager', 'user');

-- Insert sample projects
INSERT INTO projects (project_name, description, status, priority, start_date, due_date, created_by) VALUES
('Website Redesign', 'Complete redesign of company website with modern UI/UX', 'in_progress', 'high', '2025-01-01', '2025-03-31', 1),
('Mobile App Development', 'Develop cross-platform mobile application', 'pending', 'critical', '2025-02-01', '2025-06-30', 1),
('Security Audit', 'Quarterly security audit and vulnerability assessment', 'in_progress', 'high', '2025-01-15', '2025-02-15', 1);

-- Assign projects to users
INSERT INTO project_assignments (project_id, user_id, assigned_by) VALUES
(1, 2, 1),
(2, 2, 1),
(3, 3, 1);

SELECT 'Database setup completed!' as Status;
SELECT 'Login credentials:' as '';
SELECT 'Admin: admin@fastlan.com / Admin@123' as '';
SELECT 'User: john.doe@fastlan.com / User@123' as '';
SELECT 'User: jane.smith@fastlan.com / User@123' as '';
