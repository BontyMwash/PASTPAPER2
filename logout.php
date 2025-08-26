<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/database.php';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Get database connection
    $conn = getDbConnection();
    
    if ($conn) {
        // Log activity
        $userId = $_SESSION['user_id'];
        $action = 'Logout';
        $description = 'User logged out';
        $ip = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        
        $logSql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
        $logStmt = $conn->prepare($logSql);
        $logStmt->bind_param('issss', $userId, $action, $description, $ip, $userAgent);
        $logStmt->execute();
        $logStmt->close();
        
        closeDbConnection($conn);
    }
}

// Unset all session variables
$_SESSION = [];

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;