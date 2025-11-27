<?php
require_once 'config.php';
require_once 'mfa_helper.php';

startSecureSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logActivity('LOGIN_ATTEMPT', 'POST data received');

    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    logActivity('LOGIN_VALIDATION', "Email: $email");

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
        logActivity('LOGIN_FAILED', "Missing credentials");
    } else {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT id, email, password, full_name, role, approved FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            logActivity('LOGIN_USER_FOUND', "User ID: " . $user['id']);

            // Check if user is approved
            if (!$user['approved']) {
                $error = "Your account is pending admin approval. Please wait for approval before logging in.";
                logActivity('LOGIN_FAILED', "Account not approved: $email");
            } elseif (password_verify($password, $user['password'])) {
                // Password correct - Generate and send MFA code
                $mfaCode = generateMFACode();

                // Store MFA code
                if (storeMFACode($user['id'], $mfaCode)) {
                    // Send MFA code via email
                    if (sendMFACode($user['email'], $mfaCode, $user['full_name'])) {
                        // Set MFA pending session
                        $_SESSION['mfa_user_id'] = $user['id'];
                        $_SESSION['mfa_email'] = $user['email'];
                        $_SESSION['mfa_name'] = $user['full_name'];
                        $_SESSION['mfa_role'] = $user['role'];
                        $_SESSION['mfa_pending'] = true;

                        logActivity('MFA_SENT', "MFA code sent to: " . $user['email']);

                        // Redirect to MFA verification page
                        header("Location: verify_mfa.php");
                        exit();
                    } else {
                        $error = "Failed to send verification code. Please try again.";
                        logActivity('MFA_EMAIL_FAILED', "Failed to send MFA to: " . $user['email']);
                    }
                } else {
                    $error = "System error. Please try again.";
                    logActivity('MFA_STORE_FAILED', "Failed to store MFA code");
                }
            } else {
                $error = "Invalid email or password.";
                logActivity('LOGIN_FAILED', "Invalid password for: $email");
            }
        } else {
            $error = "Invalid email or password.";
            logActivity('LOGIN_FAILED', "User not found: $email");
        }

        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FastLAN Employee Portal</title>
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
            max-width: 400px;
            margin: 50px auto;
            background: white;
            border: 1px solid #ccc;
            padding: 20px;
        }
        h2 {
            text-align: center;
            margin: 0 0 20px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }
        input[type="submit"] {
            width: 100%;
            padding: 10px;
            background: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        .error {
            background: #ffdddd;
            border: 1px solid #f00;
            padding: 10px;
            margin-bottom: 15px;
        }
        a {
            color: #2196F3;
            text-decoration: none;
        }
        @media (max-width: 600px) {
            table {
                width: 95%;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                <h2>FastLAN Employee Portal</h2>
            </td>
        </tr>
        <?php if ($error): ?>
        <tr>
            <td>
                <div class="error"><?php echo $error; ?></div>
            </td>
        </tr>
        <?php endif; ?>
        <tr>
            <td>
                <form method="POST" action="login.php">
                    <label>Email:</label>
                    <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">

                    <label>Password:</label>
                    <input type="password" name="password" required>

                    <input type="submit" value="Login">
                </form>
            </td>
        </tr>
        <tr>
            <td style="text-align: center; padding-top: 10px;">
                <a href="register.php">Register New Account</a>
            </td>
        </tr>
    </table>
</body>
</html>
