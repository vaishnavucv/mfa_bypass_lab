<?php
// Database Configuration for Docker
// This file uses environment variables or defaults to Docker service names

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_USER', getenv('DB_USER') ?: 'fastlan');
define('DB_PASS', getenv('DB_PASS') ?: 'fastlan123');
define('DB_NAME', getenv('DB_NAME') ?: 'employee_portal');

// Application Configuration
define('BASE_URL', 'http://localhost');
define('SESSION_TIMEOUT', 3600); // 1 hour

// Database Connection
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            error_log("Database Connection Failed: " . $conn->connect_error);
            die("Connection failed. Please try again later.");
        }

        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        error_log("Database Error: " . $e->getMessage());
        die("Database error occurred.");
    }
}

// Session Management
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        session_start();
    }
}

// Authentication Check
function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']) && isset($_SESSION['email']);
}

// Check if user is admin
function isAdmin() {
    startSecureSession();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: dashboard.php");
        exit();
    }
}

// Sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Log activity (useful for tracking requests)
function logActivity($action, $details = '') {
    $logFile = __DIR__ . '/logs/activity.log';
    $logDir = dirname($logFile);

    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $userId = $_SESSION['user_id'] ?? 'guest';
    $email = $_SESSION['email'] ?? 'anonymous';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $logEntry = sprintf(
        "[%s] User: %s (%s) | IP: %s | Action: %s | Details: %s\n",
        $timestamp,
        $userId,
        $email,
        $ip,
        $action,
        $details
    );

    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
?>
