-- Add MFA codes table for Two-Factor Authentication
USE employee_portal;

-- Create MFA codes table
CREATE TABLE IF NOT EXISTS mfa_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_code (user_id, code),
    INDEX idx_expires (expires_at)
);

-- Clean up expired codes (optional - for maintenance)
DELETE FROM mfa_codes WHERE expires_at < NOW() OR is_used = TRUE;

SELECT 'MFA table created successfully!' AS Status;
