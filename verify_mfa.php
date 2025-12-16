<?php
require_once 'config.php';
require_once 'mfa_helper.php';

startSecureSession();

// Redirect if not in MFA verification state
if (!isset($_SESSION['mfa_user_id']) || !isset($_SESSION['mfa_pending'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';
$userId = $_SESSION['mfa_user_id'];
$userEmail = $_SESSION['mfa_email'] ?? '';
$userName = $_SESSION['mfa_name'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle AJAX MFA code verification
    if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
        header('Content-Type: application/json');

        $code = sanitizeInput($_POST['code'] ?? '');
        $mfaFlag = $_POST['mfa'] ?? 'false'; // VULNERABLE: Client sends mfa flag!

        logActivity('MFA_VERIFY_ATTEMPT', "Code: $code, MFA Flag: $mfaFlag");

        // CRITICAL VULNERABILITY: Server trusts client-provided 'mfa' parameter
        // If client sends mfa=true, server assumes MFA is valid without verification!
        if ($mfaFlag === 'true') {
            // Client claims MFA is verified - trust it blindly!
            logActivity('MFA_BYPASS', "Client sent mfa=true, bypassing verification for: $userEmail");

            // Complete login WITHOUT validating the code
            $_SESSION['user_id'] = $userId;
            $_SESSION['email'] = $userEmail;
            $_SESSION['full_name'] = $userName;
            $_SESSION['role'] = $_SESSION['mfa_role'];
            $_SESSION['login_time'] = time();

            // Clean up MFA session variables
            unset($_SESSION['mfa_user_id']);
            unset($_SESSION['mfa_pending']);
            unset($_SESSION['mfa_email']);
            unset($_SESSION['mfa_name']);
            unset($_SESSION['mfa_role']);

            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'redirect' => ($_SESSION['role'] === 'admin') ? 'admin_dashboard.php' : 'dashboard.php'
            ]);
            exit();
        }

        // If mfa=false (or not true), actually verify the code
        if (empty($code)) {
            echo json_encode(['success' => false, 'message' => 'Please enter the verification code.']);
            exit();
        } elseif (!preg_match('/^\d{4}$/', $code)) {
            echo json_encode(['success' => false, 'message' => 'Invalid code format. Please enter 4 digits.']);
            exit();
        } else {
            // Verify the MFA code
            if (verifyMFACode($userId, $code)) {
                logActivity('MFA_SUCCESS', "User verified: $userEmail");

                // Complete login after valid code
                $_SESSION['user_id'] = $userId;
                $_SESSION['email'] = $userEmail;
                $_SESSION['full_name'] = $userName;
                $_SESSION['role'] = $_SESSION['mfa_role'];
                $_SESSION['login_time'] = time();

                // Clean up MFA session variables
                unset($_SESSION['mfa_user_id']);
                unset($_SESSION['mfa_pending']);
                unset($_SESSION['mfa_email']);
                unset($_SESSION['mfa_name']);
                unset($_SESSION['mfa_role']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Verification successful',
                    'redirect' => ($_SESSION['role'] === 'admin') ? 'admin_dashboard.php' : 'dashboard.php'
                ]);
                exit();
            } else {
                logActivity('MFA_FAILED', "Invalid code for user: $userEmail");
                echo json_encode(['success' => false, 'message' => 'Invalid or expired code.']);
                exit();
            }
        }
    }

    // Handle resend request
    if (isset($_POST['resend_code'])) {
        logActivity('MFA_RESEND_ATTEMPT', 'Resend code requested');

        if (resendMFACode($userId, $userEmail, $userName)) {
            $success = "A new verification code has been sent to your email. Please check MailHog.";
        } else {
            $error = "Failed to send new verification code. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Login Code - FastLAN</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        table {
            width: 90%;
            max-width: 450px;
            margin: 50px auto;
            background: white;
            border: 1px solid #ccc;
            padding: 20px;
        }
        h2 {
            text-align: center;
            margin: 0 0 10px 0;
            color: #333;
        }
        .info-box {
            background: #e7f3fe;
            border: 1px solid #2196F3;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .info-box p {
            margin: 5px 0;
            font-size: 14px;
        }
        .code-input-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 15px 0;
        }
        .code-prefix {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            background: #e0e0e0;
            padding: 15px 10px;
            border: 2px solid #4CAF50;
            border-right: none;
            letter-spacing: 2px;
        }
        .code-input {
            font-size: 24px;
            text-align: center;
            letter-spacing: 8px;
            padding: 15px;
            width: 150px;
            border: 2px solid #4CAF50;
            border-left: none;
            box-sizing: border-box;
            text-transform: uppercase;
            font-weight: bold;
        }
        .input-hint {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: -10px;
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }
        input[type="submit"] {
            width: 100%;
            padding: 12px;
            background: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        input[type="submit"]:hover {
            background: #45a049;
        }
        .error {
            background: #ffdddd;
            border: 1px solid #f00;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .success {
            background: #ddffdd;
            border: 1px solid #0f0;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        a {
            color: #2196F3;
            text-decoration: none;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
        }
        .resend-section {
            text-align: center;
            margin-top: 15px;
            padding: 15px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            display: none;
        }
        .resend-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #ff9800;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
        .resend-btn:hover {
            background: #e68900;
        }
        .timer {
            font-weight: bold;
            color: #666;
        }
        .timer.expired {
            color: #f44336;
        }
        @media (max-width: 600px) {
            table {
                width: 95%;
                padding: 15px;
            }
            .code-prefix {
                font-size: 20px;
                padding: 12px 8px;
            }
            .code-input {
                font-size: 20px;
                letter-spacing: 5px;
                width: 120px;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                <h2>Verify Your Login</h2>
            </td>
        </tr>
        <tr>
            <td>
                <div class="info-box">
                    <p><strong>A 6-digit verification code has been sent to:</strong></p>
                    <p><?php echo htmlspecialchars($userEmail); ?></p>
                    <p style="margin-top: 10px; font-size: 12px; color: #666;">
                        Check your MailHog inbox at <a href="http://localhost:8025" target="_blank">http://localhost:8025</a>
                    </p>
                </div>
            </td>
        </tr>
        <?php if ($success): ?>
        <tr>
            <td>
                <div class="success"><?php echo $success; ?></div>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($error): ?>
        <tr>
            <td>
                <div class="error"><?php echo $error; ?></div>
            </td>
        </tr>
        <?php endif; ?>
        <tr>
            <td>
                <form method="POST" action="verify_mfa.php" id="mfaForm">
                    <label>Enter Verification Code:</label>
                    <div class="code-input-container">
                        <span class="code-prefix">FAST-</span>
                        <input type="text" name="code" id="codeInput" class="code-input" maxlength="4" pattern="[0-9]{4}" placeholder="XXXX" required autofocus inputmode="numeric">
                    </div>
                    <div class="input-hint">Enter the 4-digit code from your email</div>
                    <div id="ajaxError" style="display:none; background: #ffdddd; border: 1px solid #f00; padding: 10px; margin: 10px 0; border-radius: 4px;"></div>

                    <input type="submit" value="Verify & Login">
                </form>
            </td>
        </tr>
        <tr>
            <td class="footer">
                <p>Code expires in: <span class="timer" id="timer">10:00</span></p>
                <div class="resend-section" id="resendSection">
                    <p style="color: #f44336; font-weight: bold;">Your verification code has expired!</p>
                    <form method="POST" action="verify_mfa.php" style="display: inline;">
                        <input type="hidden" name="resend_code" value="1">
                        <button type="submit" class="resend-btn" style="border: none; cursor: pointer;">Resend New Code</button>
                    </form>
                </div>
                <p style="margin-top: 10px;"><a href="login.php">Back to Login</a></p>
            </td>
        </tr>
    </table>

    <script>
        // Get the actual code expiry timestamp from database
        let expiryTime = <?php
            $expiryTimestamp = getMFAExpiryTime($userId);
            echo $expiryTimestamp ?: (time() + 600);
        ?>;

        function updateTimer() {
            const now = Math.floor(Date.now() / 1000);
            const remaining = expiryTime - now;

            if (remaining <= 0) {
                // Code expired
                document.getElementById('timer').textContent = 'EXPIRED';
                document.getElementById('timer').classList.add('expired');
                document.getElementById('resendSection').style.display = 'block';
                return;
            }

            // Calculate minutes and seconds
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;

            // Format as MM:SS
            const display = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            document.getElementById('timer').textContent = display;

            // Update every second
            setTimeout(updateTimer, 1000);
        }

        // Start the timer when page loads
        updateTimer();

        // VULNERABLE: AJAX-based MFA verification
        // The client sends mfa=false in the request by default
        // An attacker can intercept and change mfa=false to mfa=true to bypass verification!
        document.getElementById('mfaForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const code = document.getElementById('codeInput').value;
            const errorDiv = document.getElementById('ajaxError');

            // CRITICAL VULNERABILITY: Client sends 'mfa' parameter in the request
            // Server will trust this parameter! Attacker can change mfa=false to mfa=true
            const requestBody = 'ajax=1&code=' + encodeURIComponent(code) + '&mfa=false';

            // Send AJAX request to verify code
            fetch('verify_mfa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: requestBody  // Contains mfa=false - can be intercepted and changed!
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to dashboard
                    window.location.href = data.redirect;
                } else {
                    errorDiv.textContent = data.message || 'Verification failed';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.style.display = 'block';
            });
        });
    </script>
</body>
</html>
