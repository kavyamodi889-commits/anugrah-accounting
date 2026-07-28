<?php
session_start();
require_once 'db_config.php';

// Log the logout activity
if (isset($_SESSION['admin_id'])) {
    logActivity($conn, null, 'LOGOUT', 'Admin logged out');
}

// Destroy all session data
session_unset();
session_destroy();

// Redirect to login page
header('Location: user_login.php');
exit();
?>