<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$success = '';
$error = '';
$projectId = isset($_GET['id']) ? intval($_GET['id']) : null;

// Handle project update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logActivity('UPDATE_PROJECT_ATTEMPT', 'POST data received');

    $projectId = intval($_POST['project_id']);
    $project_name = sanitizeInput($_POST['project_name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'pending');
    $priority = sanitizeInput($_POST['priority'] ?? 'medium');
    $start_date = $_POST['start_date'] ?? null;
    $due_date = $_POST['due_date'] ?? null;

    if (empty($project_name)) {
        $error = "Project name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE projects SET project_name = ?, description = ?, status = ?, priority = ?, start_date = ?, due_date = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $project_name, $description, $status, $priority, $start_date, $due_date, $projectId);

        if ($stmt->execute()) {
            $success = "Project updated successfully!";
            logActivity('PROJECT_UPDATED', "Project ID: $projectId");
        } else {
            $error = "Failed to update project.";
        }
        $stmt->close();
    }
}

// Get project information
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->bind_param("i", $projectId);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    header("Location: manage_projects.php");
    exit();
}

$stmt->close();
$conn->close();

logActivity('VIEW_EDIT_PROJECT', "Admin editing project ID: $projectId");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project - FastLAN Admin</title>
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
            max-width: 800px;
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
            min-height: 100px;
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
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s;
            margin-right: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
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
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">FastLAN Admin</div>
        <div class="navbar-menu">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_projects.php">Manage Projects</a>
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
            <h1>Edit Project</h1>
            <p>Update project details</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="section">
            <form method="POST" action="edit_project.php">
                <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Project Name</label>
                        <input type="text" name="project_name" value="<?php echo htmlspecialchars($project['project_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="low" <?php echo $project['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $project['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $project['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="critical" <?php echo $project['priority'] === 'critical' ? 'selected' : ''; ?>>Critical</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="pending" <?php echo $project['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $project['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $project['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="on_hold" <?php echo $project['status'] === 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($project['start_date'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date" value="<?php echo htmlspecialchars($project['due_date'] ?? ''); ?>">
                    </div>

                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn">Update Project</button>
                <a href="manage_projects.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>
