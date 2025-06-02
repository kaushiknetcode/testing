<?php
session_start();

// Log the logout action before destroying session
if (isset($_SESSION['user_type']) && isset($_SESSION['user_id'])) {
    $log_entry = date('Y-m-d H:i:s') . " - Logout: " . $_SESSION['user_type'] . " - " . $_SESSION['user_id'] . "\n";
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    file_put_contents($log_dir . '/logout.log', $log_entry, FILE_APPEND | LOCK_EX);
}

// Destroy all session data
session_unset();
session_destroy();

// Start a new session for flash messages
session_start();
$_SESSION['logout_success'] = 'You have been successfully logged out.';

// Redirect to main page
header('Location: index.php');
exit();
?>