<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$success = '';
$error = '';
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Handle project assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logActivity('ASSIGN_PROJECT_ATTEMPT', 'POST data received');

    $userId = intval($_POST['user_id']);
    $projectIds = $_POST['project_ids'] ?? [];

    if (empty($projectIds)) {
        $error = "Please select at least one project.";
    } else {
        $successCount = 0;
        foreach ($projectIds as $projectId) {
            $stmt = $conn->prepare("INSERT IGNORE INTO project_assignments (project_id, user_id, assigned_by) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $projectId, $userId, $_SESSION['user_id']);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $successCount++;
            }
            $stmt->close();
        }

        if ($successCount > 0) {
            $success = "$successCount project(s) assigned successfully!";
            logActivity('PROJECTS_ASSIGNED', "Assigned $successCount projects to user ID: $userId");
        } else {
            $error = "Projects may already be assigned to this user.";
        }
    }
}

// Get user information
$userStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: admin_dashboard.php");
    exit();
}

// Get all projects
$projectsStmt = $conn->query("SELECT * FROM projects ORDER BY project_name ASC");

// Get user's current assignments
$assignedStmt = $conn->prepare("SELECT project_id FROM project_assignments WHERE user_id = ?");
$assignedStmt->bind_param("i", $userId);
$assignedStmt->execute();
$assignedResult = $assignedStmt->get_result();
$assignedProjects = [];
while ($row = $assignedResult->fetch_assoc()) {
    $assignedProjects[] = $row['project_id'];
}

$userStmt->close();
$assignedStmt->close();

logActivity('VIEW_ASSIGN_PROJECT', "Admin viewing project assignment for user ID: $userId");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Projects - FastLAN Admin</title>
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

        .user-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-card-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }

        .user-card-info h3 {
            color: #333;
            margin-bottom: 5px;
        }

        .user-card-info p {
            color: #666;
            font-size: 14px;
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

        .project-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .project-item {
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .project-item:hover {
            background: #f8f9fa;
        }

        .project-item input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .project-item label {
            cursor: pointer;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .project-details h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .project-details p {
            color: #666;
            font-size: 13px;
        }

        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            margin-right: 5px;
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

        .already-assigned {
            background: #e7f3ff;
            border-color: #0066cc;
        }

        .already-assigned-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #0066cc;
            color: white;
            border-radius: 3px;
            font-size: 10px;
            margin-left: 10px;
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
            <h1>Assign Projects</h1>
            <p>Assign projects to employee</p>

            <div class="user-card">
                <div class="user-card-avatar">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <div class="user-card-info">
                    <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                    <p><?php echo htmlspecialchars($user['email']); ?> | <?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></p>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="section">
            <h2 class="section-title">Select Projects</h2>

            <form method="POST" action="assign_project.php">
                <input type="hidden" name="user_id" value="<?php echo $userId; ?>">

                <div class="project-list">
                    <?php while ($project = $projectsStmt->fetch_assoc()):
                        $isAssigned = in_array($project['id'], $assignedProjects);
                    ?>
                        <div class="project-item <?php echo $isAssigned ? 'already-assigned' : ''; ?>">
                            <label>
                                <input type="checkbox" name="project_ids[]" value="<?php echo $project['id']; ?>" <?php echo $isAssigned ? '' : ''; ?>>
                                <div class="project-details">
                                    <h4>
                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                        <?php if ($isAssigned): ?>
                                            <span class="already-assigned-badge">Already Assigned</span>
                                        <?php endif; ?>
                                    </h4>
                                    <p>
                                        <span class="status-badge status-<?php echo $project['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                        </span>
                                        <?php echo htmlspecialchars(substr($project['description'] ?? 'No description', 0, 100)); ?>
                                        <?php if ($project['due_date']): ?>
                                            | Due: <?php echo date('M d, Y', strtotime($project['due_date'])); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </label>
                        </div>
                    <?php endwhile; ?>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn">Assign Selected Projects</button>
                    <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <?php $conn->close(); ?>
</body>
</html>
