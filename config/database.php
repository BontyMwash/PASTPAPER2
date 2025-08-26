<?php
/**
 * Database Configuration File
 * 
 * This file contains the database connection settings for the Njumbi High School Past Papers Repository.
 */

// Database credentials
define('DB_HOST', 'localhost');     // Database host
define('DB_USER', 'root');          // Database username
define('DB_PASS', '');              // Database password
define('DB_NAME', 'njumbi_papers'); // Database name

/**
 * Establishes a connection to the database
 * 
 * @return mysqli|false Returns a mysqli connection object or false on failure
 */
function getDbConnection() {
    // Create connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        // Log error (in a production environment, you would log this to a file)
        error_log('Database connection failed: ' . $conn->connect_error);
        return false;
    }
    
    // Set charset to ensure proper encoding
    $conn->set_charset('utf8mb4');
    
    return $conn;
}

/**
 * Closes the database connection
 * 
 * @param mysqli $conn The database connection to close
 * @return void
 */
function closeDbConnection($conn) {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}

/**
 * Sanitizes user input to prevent SQL injection
 * 
 * @param mysqli $conn The database connection
 * @param string $input The input to sanitize
 * @return string The sanitized input
 */
function sanitizeInput($conn, $input) {
    if ($conn instanceof mysqli) {
        return $conn->real_escape_string(trim($input));
    }
    
    // Fallback if no connection is available
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}