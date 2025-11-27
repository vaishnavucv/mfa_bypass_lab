<?php
echo "Database Connection Test\n";
echo "========================\n\n";

// Test 1: Check PHP MySQL extension
echo "1. Checking PHP MySQL extension...\n";
if (extension_loaded('mysqli')) {
    echo "   ✓ MySQLi extension is loaded\n\n";
} else {
    echo "   ✗ MySQLi extension is NOT loaded\n\n";
    die("Please install php-mysqli extension\n");
}

// Test 2: Try different connection methods
$hosts = ['localhost', '127.0.0.1', 'localhost:3306'];
$users = ['root', 'YOUR_USERNAME'];
$passes = ['', 'YOUR_PASSWORD'];

echo "2. Testing database connections...\n";

foreach ($hosts as $host) {
    foreach ($users as $user) {
        foreach ($passes as $pass) {
            echo "   Trying: $user@$host with password: " . ($pass ? "YES" : "NO") . "\n";

            $conn = @new mysqli($host, $user, $pass);

            if (!$conn->connect_error) {
                echo "   ✓ CONNECTED SUCCESSFULLY!\n";
                echo "   MySQL version: " . $conn->server_info . "\n\n";

                // Test database access
                echo "3. Checking for 'employee_portal' database...\n";
                $result = $conn->query("SHOW DATABASES LIKE 'employee_portal'");
                if ($result && $result->num_rows > 0) {
                    echo "   ✓ Database 'employee_portal' exists\n\n";

                    // Select the database
                    $conn->select_db('employee_portal');

                    // Check tables
                    echo "4. Checking tables...\n";
                    $tables = ['users', 'projects', 'project_assignments'];
                    foreach ($tables as $table) {
                        $result = $conn->query("SHOW TABLES LIKE '$table'");
                        if ($result && $result->num_rows > 0) {
                            echo "   ✓ Table '$table' exists\n";

                            // Count rows
                            $count = $conn->query("SELECT COUNT(*) as cnt FROM $table")->fetch_assoc()['cnt'];
                            echo "     ($count rows)\n";
                        } else {
                            echo "   ✗ Table '$table' does NOT exist\n";
                        }
                    }

                    // Test user query
                    echo "\n5. Testing user authentication query...\n";
                    $testEmail = 'admin@fastlan.com';
                    $stmt = $conn->prepare("SELECT id, email, password, full_name, role FROM users WHERE email = ?");
                    if ($stmt) {
                        $stmt->bind_param("s", $testEmail);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            $user = $result->fetch_assoc();
                            echo "   ✓ Found admin user\n";
                            echo "     ID: " . $user['id'] . "\n";
                            echo "     Email: " . $user['email'] . "\n";
                            echo "     Name: " . $user['full_name'] . "\n";
                            echo "     Role: " . $user['role'] . "\n";

                            // Test password verification
                            $testPassword = 'Admin@123';
                            if (password_verify($testPassword, $user['password'])) {
                                echo "   ✓ Password verification works!\n";
                            } else {
                                echo "   ✗ Password verification FAILED!\n";
                                echo "     Stored hash: " . $user['password'] . "\n";
                            }
                        } else {
                            echo "   ✗ Admin user NOT found\n";
                        }
                        $stmt->close();
                    } else {
                        echo "   ✗ Failed to prepare statement: " . $conn->error . "\n";
                    }

                } else {
                    echo "   ✗ Database 'employee_portal' does NOT exist\n";
                    echo "   Available databases:\n";
                    $result = $conn->query("SHOW DATABASES");
                    while ($row = $result->fetch_assoc()) {
                        echo "     - " . $row['Database'] . "\n";
                    }
                }

                echo "\n✅ WORKING CREDENTIALS:\n";
                echo "   DB_HOST: '$host'\n";
                echo "   DB_USER: '$user'\n";
                echo "   DB_PASS: '" . ($pass ? $pass : '') . "'\n";
                echo "   DB_NAME: 'employee_portal'\n\n";
                echo "Update config.php with these credentials!\n";

                $conn->close();
                exit(0);
            } else {
                echo "   ✗ Failed: " . $conn->connect_error . "\n";
            }
        }
    }
}

echo "\n❌ Could not connect to database with any credentials.\n";
echo "Please check your MySQL installation and credentials.\n";
?>
