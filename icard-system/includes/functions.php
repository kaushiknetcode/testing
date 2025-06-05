<?php
/**
 * Common Functions
 * 
 * This file contains helper functions used throughout the application.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Sanitize user input
 * 
 * @param string $data The input string to sanitize
 * @return string Sanitized string
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Redirect to a specific URL
 * 
 * @param string $url The URL to redirect to
 * @return void
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has a specific role
 * 
 * @param string $role The role to check for
 * @return bool True if user has the role, false otherwise
 */
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Generate a random string
 * 
 * @param int $length Length of the random string
 * @return string Random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * Simple logging function (writes to file instead of database for now)
 * 
 * @param string $action The action performed
 * @param string $table The database table affected
 * @param string $recordId The ID of the record affected
 * @param array $oldValues Old values (for updates)
 * @param array $newValues New values (for updates)
 * @return bool True on success, false on failure
 */
function logAction($action, $table, $recordId, $oldValues = null, $newValues = null) {
    // Simple file logging for now (we'll add database logging later)
    $logMessage = date('Y-m-d H:i:s') . " - User: " . ($_SESSION['user_id'] ?? 'unknown') . 
                  " - Action: $action - Table: $table - Record: $recordId\n";
    
    $logFile = __DIR__ . '/../logs/application.log';
    
    // Create logs directory if it doesn't exist
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    return file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * Test database connection
 * 
 * @return bool True if connection successful, false otherwise
 */
function testDbConnection() {
    try {
        $conn = getDbConnection();
        $result = $conn->query("SELECT 1");
        $conn->close();
        return $result !== false;
    } catch (Exception $e) {
        error_log("Database connection test failed: " . $e->getMessage());
        return false;
    }
}