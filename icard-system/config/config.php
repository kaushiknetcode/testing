<?php
/**
 * Application Configuration
 * 
 * This file contains the main configuration settings for the Eastern Railway I-Card System.
 */

// Application Settings
define('APP_NAME', 'Eastern Railway I-Card System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/icard-system');

// File Upload Settings
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);

// Paths
define('BASE_PATH', dirname(dirname(__FILE__)));
define('UPLOAD_PATH', BASE_PATH . '/uploads/');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
session_start();

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 0 in production

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Include database configuration
require_once 'database.php';
