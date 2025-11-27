<?php
// MFA Helper Functions

// Email configuration for MailHog
define('MAIL_HOST', 'localhost');
define('MAIL_PORT', 1025);
define('MAIL_FROM', 'noreply@fastlan.com');
define('MAIL_FROM_NAME', 'FastLAN Security');

/**
 * Generate a 4-digit MFA code
 */
function generateMFACode() {
    return sprintf("%04d", mt_rand(0, 9999));
}

/**
 * Store MFA code in database
 */
function storeMFACode($userId, $code) {
    $conn = getDBConnection();

    // Delete old codes for this user
    $deleteStmt = $conn->prepare("DELETE FROM mfa_codes WHERE user_id = ?");
    $deleteStmt->bind_param("i", $userId);
    $deleteStmt->execute();
    $deleteStmt->close();

    // Store new code (expires in 10 minutes)
    // Note: Code is now 4 digits instead of 6
    $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes
    $stmt = $conn->prepare("INSERT INTO mfa_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $code, $expiresAt);
    $result = $stmt->execute();

    $stmt->close();
    $conn->close();

    return $result;
}

/**
 * Verify MFA code
 */
function verifyMFACode($userId, $code) {
    $conn = getDBConnection();

    $stmt = $conn->prepare("
        SELECT id FROM mfa_codes
        WHERE user_id = ?
        AND code = ?
        AND expires_at > NOW()
        AND is_used = FALSE
    ");
    $stmt->bind_param("is", $userId, $code);
    $stmt->execute();
    $result = $stmt->get_result();

    $isValid = $result->num_rows > 0;

    if ($isValid) {
        // Mark code as used
        $mfaId = $result->fetch_assoc()['id'];
        $updateStmt = $conn->prepare("UPDATE mfa_codes SET is_used = TRUE WHERE id = ?");
        $updateStmt->bind_param("i", $mfaId);
        $updateStmt->execute();
        $updateStmt->close();
    }

    $stmt->close();
    $conn->close();

    return $isValid;
}

/**
 * Send email via MailHog SMTP using socket connection
 */
function sendEmail($to, $subject, $message) {
    try {
        // Connect to MailHog SMTP server
        $smtp = fsockopen(MAIL_HOST, MAIL_PORT, $errno, $errstr, 10);

        if (!$smtp) {
            logActivity('EMAIL_FAILED', "Connection failed: $errstr ($errno)");
            return false;
        }

        // Read server greeting
        $response = fgets($smtp, 515);

        // Send EHLO
        fputs($smtp, "EHLO localhost\r\n");
        $response = fgets($smtp, 515);

        // Skip additional EHLO responses
        while (substr($response, 3, 1) == '-') {
            $response = fgets($smtp, 515);
        }

        // MAIL FROM
        fputs($smtp, "MAIL FROM: <" . MAIL_FROM . ">\r\n");
        $response = fgets($smtp, 515);

        // RCPT TO
        fputs($smtp, "RCPT TO: <$to>\r\n");
        $response = fgets($smtp, 515);

        // DATA
        fputs($smtp, "DATA\r\n");
        $response = fgets($smtp, 515);

        // Email headers and body
        $emailData = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $emailData .= "To: $to\r\n";
        $emailData .= "Subject: $subject\r\n";
        $emailData .= "MIME-Version: 1.0\r\n";
        $emailData .= "Content-Type: text/html; charset=UTF-8\r\n";
        $emailData .= "\r\n";
        $emailData .= $message . "\r\n";
        $emailData .= ".\r\n";

        fputs($smtp, $emailData);
        $response = fgets($smtp, 515);

        // QUIT
        fputs($smtp, "QUIT\r\n");
        fclose($smtp);

        logActivity('EMAIL_SENT', "To: $to, Subject: $subject, Result: Success");
        return true;

    } catch (Exception $e) {
        logActivity('EMAIL_FAILED', "Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send MFA code via email
 */
function sendMFACode($email, $code, $userName) {
    $subject = "Your FastLAN Login Code";

    // Format code as FAST-XXXX
    $formattedCode = "FAST-" . $code;

    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border: 1px solid #ccc; }
            .code { font-size: 36px; font-weight: bold; color: #4CAF50; text-align: center; padding: 20px; background: #f0f0f0; margin: 20px 0; letter-spacing: 3px; }
            .code-label { text-align: center; font-size: 14px; color: #666; margin-bottom: 10px; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>FastLAN Security Verification</h2>
            <p>Hello " . htmlspecialchars($userName) . ",</p>
            <p>A login attempt was detected for your account. Please enter the code below in the verification page:</p>

            <div class='code-label'>Your Verification Code:</div>
            <div class='code'>" . htmlspecialchars($formattedCode) . "</div>

            <p><strong>This code will expire in 10 minutes.</strong></p>

            <p>Enter the 4-digit code in the format: <strong>FAST-XXXX</strong></p>

            <p>If you did not attempt to login, please ignore this email or contact your administrator.</p>

            <div class='footer'>
                <p>This is an automated message from FastLAN Employee Portal.</p>
                <p>Time: " . date('Y-m-d H:i:s') . "</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail($email, $subject, $message);
}

/**
 * Get MFA code expiry timestamp for a user
 */
function getMFAExpiryTime($userId) {
    $conn = getDBConnection();

    $stmt = $conn->prepare("
        SELECT UNIX_TIMESTAMP(expires_at) as expiry_timestamp
        FROM mfa_codes
        WHERE user_id = ?
        AND is_used = FALSE
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $expiryTimestamp = null;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $expiryTimestamp = $row['expiry_timestamp'];
    }

    $stmt->close();
    $conn->close();

    return $expiryTimestamp;
}

/**
 * Check if MFA code has expired for a user
 */
function isMFACodeExpired($userId) {
    $expiryTime = getMFAExpiryTime($userId);

    if ($expiryTime === null) {
        return true; // No code found, consider expired
    }

    return time() > $expiryTime;
}

/**
 * Resend MFA code to user
 */
function resendMFACode($userId, $email, $userName) {
    // Generate new code
    $mfaCode = generateMFACode();

    // Store new code (deletes old ones)
    if (storeMFACode($userId, $mfaCode)) {
        // Send email
        if (sendMFACode($email, $mfaCode, $userName)) {
            logActivity('MFA_RESENT', "MFA code resent to: $email");
            return true;
        } else {
            logActivity('MFA_RESEND_FAILED', "Failed to resend MFA to: $email");
            return false;
        }
    }

    return false;
}

/**
 * Clean up expired MFA codes (maintenance function)
 */
function cleanupExpiredMFACodes() {
    $conn = getDBConnection();
    $conn->query("DELETE FROM mfa_codes WHERE expires_at < NOW() OR is_used = TRUE");
    $conn->close();
}
?>
