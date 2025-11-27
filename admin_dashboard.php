<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();

$usersStmt = $conn->prepare("
    SELECT u.id, u.email, u.full_name, u.department, u.position, u.role, u.created_at, u.last_login,
           COUNT(DISTINCT pa.project_id) as project_count
    FROM users u
    LEFT JOIN project_assignments pa ON u.id = pa.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$usersStmt->execute();
$users = $usersStmt->get_result();

$projectsStmt = $conn->prepare("
    SELECT p.*, u.full_name as created_by_name,
           COUNT(DISTINCT pa.user_id) as assigned_users
    FROM projects p
    LEFT JOIN users u ON p.created_by = u.id
    LEFT JOIN project_assignments pa ON p.id = pa.project_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$projectsStmt->execute();
$projects = $projectsStmt->get_result();

$statsStmt = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users,
        (SELECT COUNT(*) FROM users WHERE approved = FALSE) as pending_approvals,
        (SELECT COUNT(*) FROM projects) as total_projects,
        (SELECT COUNT(*) FROM projects WHERE status = 'in_progress') as active_projects,
        (SELECT COUNT(*) FROM project_assignments) as total_assignments
");
$stats = $statsStmt->fetch_assoc();

$usersStmt->close();
$projectsStmt->close();
$conn->close();

logActivity('VIEW_ADMIN_DASHBOARD', 'Admin accessed dashboard');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - FastLAN</title>
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
        .container { padding: 20px; max-width: 1400px; margin: 0 auto; }
        h2, h3 { margin: 20px 0 10px 0; }
        table { width: 100%; background: white; border-collapse: collapse; margin-top: 20px; overflow-x: auto; display: block; }
        th { background: #2196F3; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; font-size: 13px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-box { background: white; border: 1px solid #ccc; padding: 20px; text-align: center; }
        .stat-box h3 { margin: 0 0 10px 0; font-size: 14px; color: #666; }
        .stat-box .number { font-size: 32px; font-weight: bold; color: #2196F3; }
        .btn { padding: 6px 12px; background: #4CAF50; color: white; border: none; cursor: pointer; text-decoration: none; font-size: 12px; }
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .header-left { flex-direction: column; align-items: flex-start; gap: 10px; }
            .container { padding: 10px; }
            table { font-size: 11px; }
            th, td { padding: 5px; }
            .stats { grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); }
            .stat-box .number { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <strong>FastLAN Admin Portal</strong>
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_projects.php">Manage Projects</a>
            <a href="approve_users.php">User Approvals</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </div>
        <span class="user-info">Admin: <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>

    <div class="container">
        <h2>Admin Dashboard</h2>

        <div class="stats">
            <div class="stat-box">
                <h3>Total Users</h3>
                <div class="number"><?php echo $stats['total_users'] ?? 0; ?></div>
            </div>
            <div class="stat-box" style="<?php echo ($stats['pending_approvals'] ?? 0) > 0 ? 'border: 2px solid #ff9800;' : ''; ?>">
                <h3>Pending Approvals</h3>
                <div class="number" style="<?php echo ($stats['pending_approvals'] ?? 0) > 0 ? 'color: #ff9800;' : ''; ?>">
                    <?php echo $stats['pending_approvals'] ?? 0; ?>
                </div>
                <?php if (($stats['pending_approvals'] ?? 0) > 0): ?>
                    <a href="approve_users.php" style="font-size: 12px; color: #ff9800;">View Pending</a>
                <?php endif; ?>
            </div>
            <div class="stat-box">
                <h3>Total Projects</h3>
                <div class="number"><?php echo $stats['total_projects'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <h3>Active Projects</h3>
                <div class="number"><?php echo $stats['active_projects'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <h3>Total Assignments</h3>
                <div class="number"><?php echo $stats['total_assignments'] ?? 0; ?></div>
            </div>
        </div>

        <h3>Users Management</h3>
        <table border="1">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Department</th>
                <th>Position</th>
                <th>Role</th>
                <th>Projects</th>
                <th>Last Login</th>
                <th>Actions</th>
            </tr>
            <?php
            $users->data_seek(0);
            while ($user = $users->fetch_assoc()):
            ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></td>
                <td><?php echo strtoupper($user['role']); ?></td>
                <td><?php echo $user['project_count']; ?></td>
                <td><?php echo $user['last_login'] ? date('M d, Y', strtotime($user['last_login'])) : 'Never'; ?></td>
                <td>
                    <a href="assign_project.php?user_id=<?php echo $user['id']; ?>" class="btn">Assign Project</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>

        <h3>Projects Management</h3>
        <p><a href="manage_projects.php" class="btn">Create New Project</a></p>
        <table border="1">
            <tr>
                <th>Project Name</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Assigned Users</th>
                <th>Due Date</th>
                <th>Created By</th>
                <th>Actions</th>
            </tr>
            <?php
            $projects->data_seek(0);
            while ($project = $projects->fetch_assoc()):
            ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($project['project_name']); ?></strong></td>
                <td><?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?></td>
                <td><?php echo ucfirst($project['priority']); ?></td>
                <td><?php echo $project['assigned_users']; ?> user(s)</td>
                <td><?php echo $project['due_date'] ? date('M d, Y', strtotime($project['due_date'])) : 'N/A'; ?></td>
                <td><?php echo htmlspecialchars($project['created_by_name'] ?? 'System'); ?></td>
                <td>
                    <a href="edit_project.php?id=<?php echo $project['id']; ?>" class="btn">Edit</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
