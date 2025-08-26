<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/database.php';

// Check if paper ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: departments.php');
    exit;
}

$paperId = intval($_GET['id']);
$conn = getDbConnection();

if ($conn) {
    // Get paper details
    $sql = "SELECT p.file_path, p.file_name, p.file_type, p.download_count, s.name as subject_name 
            FROM papers p 
            JOIN subjects s ON p.subject_id = s.id 
            WHERE p.id = ? AND p.status = 'approved'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $paperId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $paper = $result->fetch_assoc();
        $filePath = $paper['file_path'];
        $fileName = $paper['file_name'];
        $fileType = $paper['file_type'];
        $subjectName = $paper['subject_name'];
        $downloadCount = $paper['download_count'] + 1;
        
        // Update download count
        $updateSql = "UPDATE papers SET download_count = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('ii', $downloadCount, $paperId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Log activity if user is logged in
        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
            $action = 'Download Paper';
            $description = "Downloaded paper ID: $paperId, Subject: $subjectName";
            $ip = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            
            $logSql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
            $logStmt = $conn->prepare($logSql);
            $logStmt->bind_param('issss', $userId, $action, $description, $ip, $userAgent);
            $logStmt->execute();
            $logStmt->close();
        }
        
        // Close database connection
        $stmt->close();
        closeDbConnection($conn);
        
        // Check if file exists
        if (file_exists($filePath)) {
            // Set appropriate headers
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $fileType);
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            
            // Clear output buffer
            ob_clean();
            flush();
            
            // Read file and output to browser
            readfile($filePath);
            exit;
        } else {
            // File not found
            header('Location: error.php?error=file_not_found');
            exit;
        }
    } else {
        // Paper not found or not approved
        $stmt->close();
        closeDbConnection($conn);
        header('Location: error.php?error=paper_not_found');
        exit;
    }
} else {
    // Database connection error
    header('Location: error.php?error=db_connection');
    exit;
}