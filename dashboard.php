<?php
require_once 'config.php';
requireLogin();

if (isAdmin()) {
    header("Location: admin_dashboard.php");
    exit();
}

$conn = getDBConnection();
$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT p.*, pa.assigned_at, u.full_name as assigned_by_name
    FROM projects p
    JOIN project_assignments pa ON p.id = pa.project_id
    LEFT JOIN users u ON pa.assigned_by = u.id
    WHERE pa.user_id = ?
    ORDER BY p.due_date ASC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$projects = $stmt->get_result();

$statsStmt = $conn->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN p.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM projects p
    JOIN project_assignments pa ON p.id = pa.project_id
    WHERE pa.user_id = ?
");
$statsStmt->bind_param("i", $userId);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

$stmt->close();
$statsStmt->close();
$conn->close();

logActivity('VIEW_DASHBOARD', 'User accessed dashboard');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - FastLAN</title>
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
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        h2, h3 { margin: 20px 0 10px 0; }
        table { width: 100%; background: white; border-collapse: collapse; margin-top: 20px; overflow-x: auto; display: block; }
        th { background: #4CAF50; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-box { background: white; border: 1px solid #ccc; padding: 20px; text-align: center; }
        .stat-box h3 { margin: 0 0 10px 0; font-size: 14px; color: #666; }
        .stat-box .number { font-size: 32px; font-weight: bold; color: #4CAF50; }
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .header-left { flex-direction: column; align-items: flex-start; gap: 10px; }
            .container { padding: 10px; }
            table { font-size: 12px; }
            th, td { padding: 5px; }
            .stats { grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); }
            .stat-box .number { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <strong>FastLAN Employee Portal</strong>
            <a href="dashboard.php">Dashboard</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </div>
        <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>

    <div class="container">
        <h2>My Dashboard</h2>

        <div class="stats">
            <div class="stat-box">
                <h3>Total Projects</h3>
                <div class="number"><?php echo $stats['total'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <h3>In Progress</h3>
                <div class="number"><?php echo $stats['in_progress'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <h3>Completed</h3>
                <div class="number"><?php echo $stats['completed'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <h3>Pending</h3>
                <div class="number"><?php echo $stats['pending'] ?? 0; ?></div>
            </div>
        </div>

        <h3>My Assigned Projects</h3>
        <?php if ($projects->num_rows > 0): ?>
        <table border="1">
            <tr>
                <th>Project Name</th>
                <th>Description</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Due Date</th>
                <th>Assigned By</th>
            </tr>
            <?php while ($project = $projects->fetch_assoc()): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($project['project_name']); ?></strong></td>
                <td><?php echo htmlspecialchars(substr($project['description'] ?? '', 0, 60)); ?></td>
                <td><?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?></td>
                <td><?php echo ucfirst($project['priority']); ?></td>
                <td><?php echo $project['due_date'] ? date('M d, Y', strtotime($project['due_date'])) : 'N/A'; ?></td>
                <td><?php echo htmlspecialchars($project['assigned_by_name'] ?? 'System'); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
        <?php else: ?>
        <p>No projects assigned yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>
