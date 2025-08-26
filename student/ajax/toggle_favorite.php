<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check required parameters
if (!isset($_POST['paper_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Include database connection
require_once '../../config/multi_school_database.php';

// Initialize variables
$conn = getDbConnection();
$userId = $_SESSION['user_id'];
$schoolId = $_SESSION['school_id'];
$paperId = (int)$_POST['paper_id'];
$action = $_POST['action']; // 'add' or 'remove'

// Validate paper exists and belongs to the user's school
if ($conn) {
    $sql = "SELECT id FROM papers WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $paperId, $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Paper not found']);
        $stmt->close();
        closeDbConnection($conn);
        exit;
    }
    $stmt->close();
    
    // Process the action
    if ($action === 'add') {
        // Check if already a favorite
        $checkSql = "SELECT id FROM favorites WHERE user_id = ? AND paper_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('ii', $userId, $paperId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Already in favorites']);
            $checkStmt->close();
            closeDbConnection($conn);
            exit;
        }
        $checkStmt->close();
        
        // Add to favorites
        $addSql = "INSERT INTO favorites (user_id, paper_id, date_added) VALUES (?, ?, NOW())";
        $addStmt = $conn->prepare($addSql);
        $addStmt->bind_param('ii', $userId, $paperId);
        $result = $addStmt->execute();
        
        if ($result) {
            // Log activity
            $activityType = 'add_favorite';
            $description = "Student added paper ID $paperId to favorites";
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            
            $logSql = "INSERT INTO activity_logs (user_id, school_id, activity_type, description, ip_address) 
                      VALUES (?, ?, ?, ?, ?)";
            $logStmt = $conn->prepare($logSql);
            $logStmt->bind_param('iisss', $userId, $schoolId, $activityType, $description, $ipAddress);
            $logStmt->execute();
            $logStmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Added to favorites']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add to favorites']);
        }
        $addStmt->close();
    } elseif ($action === 'remove') {
        // Remove from favorites
        $removeSql = "DELETE FROM favorites WHERE user_id = ? AND paper_id = ?";
        $removeStmt = $conn->prepare($removeSql);
        $removeStmt->bind_param('ii', $userId, $paperId);
        $result = $removeStmt->execute();
        
        if ($result) {
            // Log activity
            $activityType = 'remove_favorite';
            $description = "Student removed paper ID $paperId from favorites";
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            
            $logSql = "INSERT INTO activity_logs (user_id, school_id, activity_type, description, ip_address) 
                      VALUES (?, ?, ?, ?, ?)";
            $logStmt = $conn->prepare($logSql);
            $logStmt->bind_param('iisss', $userId, $schoolId, $activityType, $description, $ipAddress);
            $logStmt->execute();
            $logStmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Removed from favorites']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove from favorites']);
        }
        $removeStmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
    closeDbConnection($conn);
} else {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
}