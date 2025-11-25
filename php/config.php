<?php
// Database configuration
define('DB_HOST', 'mysql');
define('DB_USER', 'root');
define('DB_PASS', 'password');
define('DB_NAME', 'bowlingcenter');

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
