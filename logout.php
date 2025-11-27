<?php
require_once 'config.php';

startSecureSession();

// Log the logout activity before destroying session
if (isset($_SESSION['user_id'])) {
    logActivity('LOGOUT', 'User logged out');
}

// Destroy all session data
session_unset();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
