<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$success = '';
$error = '';

// Handle project creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    logActivity('CREATE_PROJECT_ATTEMPT', 'POST data received');

    $project_name = sanitizeInput($_POST['project_name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'pending');
    $priority = sanitizeInput($_POST['priority'] ?? 'medium');
    $start_date = $_POST['start_date'] ?? null;
    $due_date = $_POST['due_date'] ?? null;

    if (empty($project_name)) {
        $error = "Project name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO projects (project_name, description, status, priority, start_date, due_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssi", $project_name, $description, $status, $priority, $start_date, $due_date, $_SESSION['user_id']);

        if ($stmt->execute()) {
            $success = "Project created successfully!";
            logActivity('PROJECT_CREATED', "Project: $project_name");
        } else {
            $error = "Failed to create project.";
        }
        $stmt->close();
    }
}

// Handle project deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $projectId = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->bind_param("i", $projectId);

    if ($stmt->execute()) {
        $success = "Project deleted successfully!";
        logActivity('PROJECT_DELETED', "Project ID: $projectId");
    } else {
        $error = "Failed to delete project.";
    }
    $stmt->close();
}

// Get all projects
$projectsStmt = $conn->query("
    SELECT p.*, u.full_name as created_by_name,
           COUNT(DISTINCT pa.user_id) as assigned_users
    FROM projects p
    LEFT JOIN users u ON p.created_by = u.id
    LEFT JOIN project_assignments pa ON p.id = pa.project_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
");

$conn->close();
logActivity('VIEW_MANAGE_PROJECTS', 'Admin accessed project management');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Projects - FastLAN Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }

        .navbar-menu {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .navbar-menu a {
            color: #333;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .navbar-menu a:hover {
            background: #f0f0f0;
        }

        .navbar-menu a.active {
            background: #667eea;
            color: white;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            color: #333;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-in_progress {
            background: #cfe2ff;
            color: #084298;
        }

        .status-completed {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-on_hold {
            background: #f8d7da;
            color: #842029;
        }

        .priority-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .priority-low {
            background: #e7f3ff;
            color: #0066cc;
        }

        .priority-medium {
            background: #fff8e1;
            color: #f57c00;
        }

        .priority-high {
            background: #ffe6e6;
            color: #c41e3a;
        }

        .priority-critical {
            background: #d32f2f;
            color: white;
        }

        .actions {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">FastLAN Admin</div>
        <div class="navbar-menu">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_projects.php" class="active">Manage Projects</a>
            <a href="profile.php">Profile</a>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Manage Projects</h1>
            <p>Create and manage project assignments</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="section">
            <h2 class="section-title">Create New Project</h2>
            <form method="POST" action="manage_projects.php">
                <input type="hidden" name="action" value="create">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Project Name</label>
                        <input type="text" name="project_name" required>
                    </div>

                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="pending" selected>Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date">
                    </div>

                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date">
                    </div>

                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" placeholder="Enter project description..."></textarea>
                    </div>
                </div>

                <button type="submit" class="btn">Create Project</button>
            </form>
        </div>

        <div class="section">
            <h2 class="section-title">All Projects</h2>
            <table>
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assigned Users</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($project = $projectsStmt->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($project['project_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($project['description'] ?? '', 0, 50)) . (strlen($project['description'] ?? '') > 50 ? '...' : ''); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $project['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="priority-badge priority-<?php echo $project['priority']; ?>">
                                    <?php echo ucfirst($project['priority']); ?>
                                </span>
                            </td>
                            <td><?php echo $project['assigned_users']; ?> user(s)</td>
                            <td><?php echo $project['due_date'] ? date('M d, Y', strtotime($project['due_date'])) : 'N/A'; ?></td>
                            <td>
                                <div class="actions">
                                    <a href="edit_project.php?id=<?php echo $project['id']; ?>" class="btn btn-small">Edit</a>
                                    <a href="manage_projects.php?delete=<?php echo $project['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure you want to delete this project?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
