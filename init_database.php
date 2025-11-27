<?php
// Database initialization script
echo "FastLAN Employee Portal - Database Setup\n";
echo "=========================================\n\n";

// Database credentials
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'employee_portal';

try {
    // Connect to MySQL (without database)
    $conn = new mysqli($host, $user, $pass);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error . "\n");
    }

    echo "✓ Connected to MySQL server\n";

    // Create database
    $conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
    echo "✓ Database '$dbname' created/verified\n";

    // Select database
    $conn->select_db($dbname);

    // Drop existing tables to start fresh
    $conn->query("DROP TABLE IF EXISTS project_assignments");
    $conn->query("DROP TABLE IF EXISTS projects");
    $conn->query("DROP TABLE IF EXISTS users");
    echo "✓ Cleaned up old tables\n";

    // Create users table
    $sql = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        department VARCHAR(100),
        position VARCHAR(100),
        role ENUM('user', 'admin') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        INDEX idx_email (email)
    )";
    $conn->query($sql);
    echo "✓ Created 'users' table\n";

    // Create projects table
    $sql = "CREATE TABLE projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_name VARCHAR(255) NOT NULL,
        description TEXT,
        status ENUM('pending', 'in_progress', 'completed', 'on_hold') DEFAULT 'pending',
        priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
        start_date DATE,
        due_date DATE,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )";
    $conn->query($sql);
    echo "✓ Created 'projects' table\n";

    // Create project_assignments table
    $sql = "CREATE TABLE project_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        user_id INT NOT NULL,
        assigned_by INT,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
        UNIQUE KEY unique_assignment (project_id, user_id)
    )";
    $conn->query($sql);
    echo "✓ Created 'project_assignments' table\n";

    // Insert admin user
    $adminEmail = 'admin@fastlan.com';
    $adminPass = password_hash('Admin@123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (email, password, full_name, department, position, role) VALUES (?, ?, 'System Administrator', 'IT', 'Administrator', 'admin')");
    $stmt->bind_param("ss", $adminEmail, $adminPass);
    $stmt->execute();
    echo "✓ Created admin user (admin@fastlan.com / Admin@123)\n";

    // Insert test users
    $userPass = password_hash('User@123', PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (email, password, full_name, department, position, role) VALUES (?, ?, ?, ?, ?, 'user')");

    $email = 'john.doe@fastlan.com';
    $name = 'John Doe';
    $dept = 'Engineering';
    $pos = 'Senior Developer';
    $stmt->bind_param("sssss", $email, $userPass, $name, $dept, $pos);
    $stmt->execute();
    echo "✓ Created user (john.doe@fastlan.com / User@123)\n";

    $email = 'jane.smith@fastlan.com';
    $name = 'Jane Smith';
    $dept = 'Marketing';
    $pos = 'Marketing Manager';
    $stmt->bind_param("sssss", $email, $userPass, $name, $dept, $pos);
    $stmt->execute();
    echo "✓ Created user (jane.smith@fastlan.com / User@123)\n";

    // Insert sample projects
    $stmt = $conn->prepare("INSERT INTO projects (project_name, description, status, priority, start_date, due_date, created_by) VALUES (?, ?, ?, ?, ?, ?, 1)");

    $projects = [
        ['Website Redesign', 'Complete redesign of company website with modern UI/UX', 'in_progress', 'high', '2025-01-01', '2025-03-31'],
        ['Mobile App Development', 'Develop cross-platform mobile application', 'pending', 'critical', '2025-02-01', '2025-06-30'],
        ['Security Audit', 'Quarterly security audit and vulnerability assessment', 'in_progress', 'high', '2025-01-15', '2025-02-15'],
    ];

    foreach ($projects as $proj) {
        $stmt->bind_param("ssssss", $proj[0], $proj[1], $proj[2], $proj[3], $proj[4], $proj[5]);
        $stmt->execute();
    }
    echo "✓ Created " . count($projects) . " sample projects\n";

    // Assign projects to users
    $stmt = $conn->prepare("INSERT INTO project_assignments (project_id, user_id, assigned_by) VALUES (?, ?, 1)");

    $assignments = [
        [1, 2], // Project 1 to John
        [2, 2], // Project 2 to John
        [3, 3], // Project 3 to Jane
    ];

    foreach ($assignments as $assign) {
        $stmt->bind_param("ii", $assign[0], $assign[1]);
        $stmt->execute();
    }
    echo "✓ Created " . count($assignments) . " project assignments\n";

    // Create logs directory
    if (!file_exists('logs')) {
        mkdir('logs', 0777, true);
        echo "✓ Created logs directory\n";
    }

    $conn->close();

    echo "\n✅ Database setup completed successfully!\n";
    echo "\nYou can now login with:\n";
    echo "  Admin: admin@fastlan.com / Admin@123\n";
    echo "  User:  john.doe@fastlan.com / User@123\n";
    echo "  User:  jane.smith@fastlan.com / User@123\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}
?>
