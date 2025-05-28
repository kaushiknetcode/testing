<?php
/**
 * Authentication Functions
 * 
 * This file contains functions related to user authentication and authorization.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

/**
 * Authenticate an employee using HRMS ID and DOB
 * 
 * @param string $hrmsId Employee's HRMS ID
 * @param string $dob Employee's date of birth (YYYY-MM-DD)
 * @return array|bool Employee data if authenticated, false otherwise
 */
function authenticateEmployee($hrmsId, $dob) {
    $conn = getDbConnection();
    
    $sql = "SELECT * FROM employees WHERE hrms_id = ? AND dob = ? AND status = 'active' LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $hrmsId, $dob);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $employee = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        // Log the login
        logAction('login', 'employees', $employee['hrms_id'], null, ['login_time' => date('Y-m-d H:i:s')]);
        
        return $employee;
    }
    
    $stmt->close();
    $conn->close();
    return false;
}

/**
 * Authenticate a system user (admin, CO, dealer, AWO)
 * 
 * @param string $username Username
 * @param string $password Plain text password
 * @return array|bool User data if authenticated, false otherwise
 */
function authenticateSystemUser($username, $password) {
    $conn = getDbConnection();
    
    $sql = "SELECT * FROM system_users WHERE username = ? AND is_active = 1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            // Update last login
            $updateSql = "UPDATE system_users SET last_login = NOW() WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param('i', $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Log the login
            logAction('login', 'system_users', $user['id'], null, ['login_time' => date('Y-m-d H:i:s')]);
            
            $conn->close();
            return $user;
        }
    }
    
    $stmt->close();
    $conn->close();
    return false;
}

/**
 * Check if the current user has permission to access a specific resource
 * 
 * @param string $requiredRole Required role to access the resource
 * @return bool True if user has permission, false otherwise
 */
function checkPermission($requiredRole) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    // Admin has access to everything
    if ($_SESSION['user_role'] === 'admin') {
        return true;
    }
    
    // Check if user has the required role
    return $_SESSION['user_role'] === $requiredRole;
}

/**
 * Require the user to be logged in
 * 
 * @param string $redirect URL to redirect to if not logged in
 * @return void
 */
function requireLogin($redirect = '/icard-system/employee/login.php') {
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'Please log in to access this page.';
        header('Location: ' . $redirect);
        exit();
    }
}

/**
 * Require the user to have a specific role
 * 
 * @param string $requiredRole Required role
 * @param string $redirect URL to redirect to if permission is denied
 * @return void
 */
function requireRole($requiredRole, $redirect = '/icard-system/employee/dashboard.php') {
    requireLogin($redirect);
    
    if (!checkPermission($requiredRole)) {
        $_SESSION['error'] = 'You do not have permission to access this page.';
        header('Location: ' . $redirect);
        exit();
    }
}
