<?php
require_once 'config.php';

startSecureSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logActivity('REGISTER_ATTEMPT', 'POST data received');

    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $department = sanitizeInput($_POST['department'] ?? '');
    $position = sanitizeInput($_POST['position'] ?? '');

    // Log the registration attempt details
    logActivity('REGISTER_VALIDATION', "Email: $email, Name: $full_name");

    // Validation
    if (empty($email) || empty($password) || empty($full_name)) {
        $error = "Please fill in all required fields.";
        logActivity('REGISTER_FAILED', "Validation error: Missing fields");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
        logActivity('REGISTER_FAILED', "Invalid email: $email");
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
        logActivity('REGISTER_FAILED', "Password too short");
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
        logActivity('REGISTER_FAILED', "Password mismatch");
    } else {
        $conn = getDBConnection();

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Email already registered. Please login.";
            logActivity('REGISTER_FAILED', "Email already exists: $email");
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user with approved=FALSE (pending admin approval)
            $stmt = $conn->prepare("INSERT INTO users (email, password, full_name, department, position, role, approved) VALUES (?, ?, ?, ?, ?, 'user', FALSE)");
            $stmt->bind_param("sssss", $email, $hashed_password, $full_name, $department, $position);

            if ($stmt->execute()) {
                $success = "Registration successful! Please wait for admin approval before logging in.";
                logActivity('REGISTER_SUCCESS', "New user registered (pending approval): $email");
            } else {
                $error = "Registration failed. Please try again.";
                logActivity('REGISTER_FAILED', "Database error: " . $stmt->error);
            }
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
    <title>Register - FastLAN Employee Portal</title>
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
            max-width: 500px;
            margin: 30px auto;
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
        input[type="text"], input[type="email"], input[type="password"], select {
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
        .success {
            background: #ddffdd;
            border: 1px solid #0f0;
            padding: 10px;
            margin-bottom: 15px;
        }
        a {
            color: #2196F3;
            text-decoration: none;
        }
        .required {
            color: red;
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
            <td><h2>Register - FastLAN Employee Portal</h2></td>
        </tr>
        <?php if ($error): ?>
        <tr>
            <td><div class="error"><?php echo $error; ?></div></td>
        </tr>
        <?php endif; ?>
        <?php if ($success): ?>
        <tr>
            <td><div class="success"><?php echo $success; ?></div></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td>
                <form method="POST" action="register.php">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">

                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="full_name" required value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">

                    <label>Department</label>
                    <select name="department">
                        <option value="">Select Department</option>
                        <option value="Engineering">Engineering</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Sales">Sales</option>
                        <option value="HR">Human Resources</option>
                        <option value="Finance">Finance</option>
                        <option value="IT">IT</option>
                        <option value="Operations">Operations</option>
                    </select>

                    <label>Position</label>
                    <input type="text" name="position" value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>">

                    <label>Password <span class="required">*</span></label>
                    <input type="password" name="password" required>

                    <label>Confirm Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" required>

                    <input type="submit" value="Register">
                </form>
            </td>
        </tr>
        <tr>
            <td style="text-align: center; padding-top: 10px;">
                <a href="login.php">Already have an account? Login here</a>
            </td>
        </tr>
    </table>
</body>
</html>
