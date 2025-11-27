<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$userId = $_SESSION['user_id'];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logActivity('PROFILE_UPDATE_ATTEMPT', 'POST data received');

    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $department = sanitizeInput($_POST['department'] ?? '');
    $position = sanitizeInput($_POST['position'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($full_name)) {
        $error = "Full name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, department = ?, position = ? WHERE id = ?");
        $stmt->bind_param("sssi", $full_name, $department, $position, $userId);

        if ($stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            $success = "Profile updated successfully!";
            logActivity('PROFILE_UPDATED', "Updated profile information");

            if (!empty($current_password) && !empty($new_password)) {
                $passStmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $passStmt->bind_param("i", $userId);
                $passStmt->execute();
                $result = $passStmt->get_result();
                $user = $result->fetch_assoc();

                if (password_verify($current_password, $user['password'])) {
                    if ($new_password === $confirm_password) {
                        if (strlen($new_password) >= 6) {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $updatePassStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $updatePassStmt->bind_param("si", $hashed_password, $userId);

                            if ($updatePassStmt->execute()) {
                                $success = "Profile and password updated successfully!";
                                logActivity('PASSWORD_CHANGED', "User changed password");
                            } else {
                                $error = "Failed to update password.";
                            }
                            $updatePassStmt->close();
                        } else {
                            $error = "New password must be at least 6 characters long.";
                        }
                    } else {
                        $error = "New passwords do not match.";
                    }
                } else {
                    $error = "Current password is incorrect.";
                    logActivity('PASSWORD_CHANGE_FAILED', "Incorrect current password");
                }
                $passStmt->close();
            }
        } else {
            $error = "Failed to update profile.";
        }
        $stmt->close();
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$stmt->close();
$conn->close();

logActivity('VIEW_PROFILE', 'User accessed profile page');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Profile - FastLAN</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .header {
            background: #333;
            color: white;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 30px;
        }
        .header strong { display: inline-block; }
        .header a { color: white; text-decoration: none; }
        .header .user-info { white-space: nowrap; }
        .container { padding: 20px; max-width: 600px; margin: 0 auto; }
        h2, h3 { margin: 20px 0 10px 0; }
        table { width: 100%; background: white; border: 1px solid #ccc; padding: 20px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], input[type="email"], input[type="password"], select {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }
        input[type="submit"] {
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        .error { background: #ffdddd; border: 1px solid #f00; padding: 10px; margin-bottom: 15px; }
        .success { background: #ddffdd; border: 1px solid #0f0; padding: 10px; margin-bottom: 15px; }
        .info-box { background: #e7f3fe; border: 1px solid #2196F3; padding: 15px; margin-bottom: 20px; }
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .header-left { flex-direction: column; align-items: flex-start; gap: 10px; }
            .container { padding: 10px; }
            table { padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <strong>FastLAN Employee Portal</strong>
            <?php if (isAdmin()): ?>
                <a href="admin_dashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="dashboard.php">Dashboard</a>
            <?php endif; ?>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </div>
        <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>

    <div class="container">
        <h2>My Profile</h2>

        <div class="info-box">
            <strong>Account Information:</strong><br>
            Email: <?php echo htmlspecialchars($user['email']); ?><br>
            Role: <?php echo strtoupper($user['role']); ?><br>
            Member Since: <?php echo date('M d, Y', strtotime($user['created_at'])); ?><br>
            Last Login: <?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="profile.php">
            <table>
                <tr>
                    <td colspan="2"><h3>Update Profile</h3></td>
                </tr>
                <tr>
                    <td colspan="2">
                        <label>Full Name:</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <label>Department:</label>
                        <select name="department">
                            <option value="">Select Department</option>
                            <option value="Engineering" <?php echo $user['department'] === 'Engineering' ? 'selected' : ''; ?>>Engineering</option>
                            <option value="Marketing" <?php echo $user['department'] === 'Marketing' ? 'selected' : ''; ?>>Marketing</option>
                            <option value="Sales" <?php echo $user['department'] === 'Sales' ? 'selected' : ''; ?>>Sales</option>
                            <option value="HR" <?php echo $user['department'] === 'HR' ? 'selected' : ''; ?>>Human Resources</option>
                            <option value="Finance" <?php echo $user['department'] === 'Finance' ? 'selected' : ''; ?>>Finance</option>
                            <option value="IT" <?php echo $user['department'] === 'IT' ? 'selected' : ''; ?>>IT</option>
                            <option value="Operations" <?php echo $user['department'] === 'Operations' ? 'selected' : ''; ?>>Operations</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <label>Position:</label>
                        <input type="text" name="position" value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>">
                    </td>
                </tr>
                <tr>
                    <td colspan="2"><h3>Change Password (Optional)</h3></td>
                </tr>
                <tr>
                    <td colspan="2">
                        <label>Current Password:</label>
                        <input type="password" name="current_password">
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <label>New Password:</label>
                        <input type="password" name="new_password">
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <label>Confirm New Password:</label>
                        <input type="password" name="confirm_password">
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <input type="submit" value="Update Profile">
                    </td>
                </tr>
            </table>
        </form>
    </div>
</body>
</html>
