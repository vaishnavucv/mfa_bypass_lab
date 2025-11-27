<?php
require_once 'config.php';

startSecureSession();

// Check if user is logged in and is admin
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$success = '';
$error = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($userId > 0 && in_array($action, ['approve', 'reject'])) {
        $conn = getDBConnection();

        if ($action === 'approve') {
            // Approve user
            $stmt = $conn->prepare("UPDATE users SET approved = TRUE, approved_at = NOW(), approved_by = ? WHERE id = ?");
            $stmt->bind_param("ii", $_SESSION['user_id'], $userId);

            if ($stmt->execute()) {
                $success = "User approved successfully!";
                logActivity('USER_APPROVED', "Admin approved user ID: $userId");
            } else {
                $error = "Failed to approve user.";
                logActivity('USER_APPROVE_FAILED', "Failed to approve user ID: $userId");
            }
            $stmt->close();
        } else {
            // Reject user (delete account)
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);

            if ($stmt->execute()) {
                $success = "User rejected and removed successfully!";
                logActivity('USER_REJECTED', "Admin rejected user ID: $userId");
            } else {
                $error = "Failed to reject user.";
                logActivity('USER_REJECT_FAILED', "Failed to reject user ID: $userId");
            }
            $stmt->close();
        }

        $conn->close();
    }
}

// Fetch all users with approval status
$conn = getDBConnection();

// Get pending users
$pendingStmt = $conn->query("SELECT id, email, full_name, department, position, created_at FROM users WHERE approved = FALSE ORDER BY created_at DESC");
$pendingUsers = $pendingStmt->fetch_all(MYSQLI_ASSOC);

// Get approved users
$approvedStmt = $conn->query("SELECT u.id, u.email, u.full_name, u.department, u.position, u.approved_at, a.full_name as approved_by_name
    FROM users u
    LEFT JOIN users a ON u.approved_by = a.id
    WHERE u.approved = TRUE AND u.role = 'user'
    ORDER BY u.approved_at DESC");
$approvedUsers = $approvedStmt->fetch_all(MYSQLI_ASSOC);

$conn->close();

logActivity('VIEW_USER_APPROVALS', 'Admin accessed user approval page');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Approvals - FastLAN Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: #333;
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 30px;
        }
        .header h1 {
            font-size: 20px;
            margin: 0;
        }
        .header a {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
        }
        .header a:hover {
            background: #555;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .section {
            background: white;
            border: 1px solid #ccc;
            padding: 20px;
            margin-bottom: 20px;
        }
        h2 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }
        .success {
            background: #ddffdd;
            border: 1px solid #0f0;
            padding: 10px;
            margin-bottom: 15px;
        }
        .error {
            background: #ffdddd;
            border: 1px solid #f00;
            padding: 10px;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th, table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        table th {
            background: #f0f0f0;
            font-weight: bold;
        }
        .btn {
            padding: 6px 12px;
            border: none;
            cursor: pointer;
            margin-right: 5px;
            color: white;
        }
        .btn-approve {
            background: #4CAF50;
        }
        .btn-reject {
            background: #f44336;
        }
        .btn:hover {
            opacity: 0.8;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        .badge-pending {
            background: #ff9800;
            color: white;
        }
        .badge-approved {
            background: #4CAF50;
            color: white;
        }
        .empty-message {
            text-align: center;
            color: #666;
            padding: 20px;
        }
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            .header-left {
                flex-direction: column;
                gap: 10px;
                width: 100%;
            }
            table {
                font-size: 14px;
            }
            .btn {
                padding: 4px 8px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1>FastLAN Admin Portal</h1>
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_projects.php">Projects</a>
            <a href="approve_users.php">User Approvals</a>
        </div>
        <a href="logout.php">Logout</a>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Pending Users Section -->
        <div class="section">
            <h2>Pending User Approvals (<?php echo count($pendingUsers); ?>)</h2>

            <?php if (count($pendingUsers) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Full Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Registered</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingUsers as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['department'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($user['position'] ?: 'N/A'); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Approve this user?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-approve">Approve</button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Reject and delete this user?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-reject">Reject</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty-message">No pending user approvals.</p>
            <?php endif; ?>
        </div>

        <!-- Approved Users Section -->
        <div class="section">
            <h2>Approved Users (<?php echo count($approvedUsers); ?>)</h2>

            <?php if (count($approvedUsers) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Full Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Approved At</th>
                            <th>Approved By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvedUsers as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['department'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($user['position'] ?: 'N/A'); ?></td>
                            <td><?php echo $user['approved_at'] ? date('Y-m-d H:i', strtotime($user['approved_at'])) : 'N/A'; ?></td>
                            <td><?php echo htmlspecialchars($user['approved_by_name'] ?: 'System'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty-message">No approved users yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
