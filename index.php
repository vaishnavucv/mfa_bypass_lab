<?php
require_once 'config.php';

startSecureSession();

// Redirect to appropriate page based on login status
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
} else {
    header("Location: login.php");
}

exit();
?>
