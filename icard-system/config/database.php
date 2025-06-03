<?php
/**
 * Database Configuration
 * 
 * This file contains the database connection settings for the Eastern Railway I-Card System.
 * Update these values with your Hostinger database credentials.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'u266837330_project_ic');
define('DB_USER', 'u266837330_icard_system');
define('DB_PASS', 'Bansfore1234');

/**
 * Create database connection
 */
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        die("Connection failed. Please try again later.");
    }
    
    // Set charset to utf8mb4 for proper Unicode support
    $conn->set_charset("utf8mb4");
    
    // FIXED: Set MySQL timezone to Indian Standard Time
    $conn->query("SET time_zone = '+05:30'");
    
    return $conn;
}

// Test connection (remove in production)
// $testConn = getDbConnection();
// if ($testConn) {
//     echo "Database connection successful!";
//     $testConn->close();
// }