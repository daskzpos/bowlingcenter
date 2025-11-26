<?php
// Suppress all error output - errors should be logged, not displayed
ini_set('display_errors', '0');
error_reporting(0); // Turn off all error reporting

// Database configuration
define('DB_HOST', 'mysql');
define('DB_USER', 'root');
define('DB_PASS', 'password');
define('DB_NAME', 'bowlingcenter');

// Create connection
function getDBConnection() {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        // Log error instead of displaying it
        error_log("Database connection failed: " . $conn->connect_error);
        throw new Exception("Database connection failed");
    }
    
    return $conn;
}

// Start session if not already started (only if headers not sent)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
?>
