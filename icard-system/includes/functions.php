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
 * Log an action to the audit log
 * 
 * @param string $action The action performed
 * @param string $table The database table affected
 * @param string $recordId The ID of the record affected
 * @param array $oldValues Old values (for updates)
 * @param array $newValues New values (for updates)
 * @return bool True on success, false on failure
 */
function logAction($action, $table, $recordId, $oldValues = null, $newValues = null) {
    $conn = getDbConnection();
    
    $userId = $_SESSION['user_id'] ?? null;
    $userType = $_SESSION['user_type'] ?? 'system';
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $oldValuesJson = $oldValues ? json_encode($oldValues) : null;
    $newValuesJson = $newValues ? json_encode($newValues) : null;
    
    $sql = "INSERT INTO audit_logs (
        user_id, 
        user_type, 
        action, 
        table_name, 
        record_id, 
        old_values, 
        new_values, 
        ip_address, 
        user_agent
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'issssssss', 
        $userId, 
        $userType, 
        $action, 
        $table, 
        $recordId, 
        $oldValuesJson, 
        $newValuesJson, 
        $ipAddress, 
        $userAgent
    );
    
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}
