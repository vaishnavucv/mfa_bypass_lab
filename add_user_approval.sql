-- Add user approval system to existing database
USE employee_portal;

-- Add approved column to users table (ignore if already exists)
ALTER TABLE users ADD COLUMN approved BOOLEAN DEFAULT FALSE AFTER role;

-- Add approved_at and approved_by columns for tracking
ALTER TABLE users ADD COLUMN approved_at TIMESTAMP NULL AFTER approved;
ALTER TABLE users ADD COLUMN approved_by INT NULL AFTER approved_at;

-- Keep existing admin and test users approved
UPDATE users SET approved = TRUE WHERE email IN ('admin@fastlan.com', 'john.doe@fastlan.com', 'jane.smith@fastlan.com');

-- Add index for filtering pending users
ALTER TABLE users ADD INDEX idx_approved (approved);

SELECT 'User approval system added successfully!' AS Status;
