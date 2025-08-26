<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../login.php');
    exit;
}

// Include database connection
require_once '../config/database.php';

// Initialize variables
$conn = getDbConnection();
$message = '';
$messageType = '';
$messages = array();
$viewMessage = null;

// Handle actions (mark as read/unread, delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $messageId = intval($_GET['id']);
    
    // Check if is_read column exists
    $columnExists = false;
    try {
        $checkColumnSql = "SHOW COLUMNS FROM contact_messages LIKE 'is_read'";
        $columnResult = $conn->query($checkColumnSql);
        $columnExists = ($columnResult && $columnResult->num_rows > 0);
        
        // Add column if it doesn't exist
        if (!$columnExists) {
            $alterSql = "ALTER TABLE contact_messages ADD COLUMN is_read BOOLEAN DEFAULT FALSE";
            $conn->query($alterSql);
            $columnExists = true;
        }
    } catch (Exception $e) {
        $message = 'Database error: ' . $e->getMessage();
        $messageType = 'danger';
    }
    
    // Mark as read/unread
    if ($_GET['action'] === 'toggle_read' && $conn && $columnExists) {
        $sql = "SELECT is_read FROM contact_messages WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $messageId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            $newStatus = $row['is_read'] ? 0 : 1;
            $updateSql = "UPDATE contact_messages SET is_read = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param('ii', $newStatus, $messageId);
            
            if ($updateStmt->execute()) {
                $message = 'Message status updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Error updating message status.';
                $messageType = 'danger';
            }
            $updateStmt->close();
        }
        $stmt->close();
    }
    
    // Delete message
    if ($_GET['action'] === 'delete' && $conn) {
        $sql = "DELETE FROM contact_messages WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $messageId);
        
        if ($stmt->execute()) {
            $message = 'Message deleted successfully.';
            $messageType = 'success';
        } else {
            $message = 'Error deleting message.';
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    // View message details
    if ($_GET['action'] === 'view' && $conn) {
        $sql = "SELECT * FROM contact_messages WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $messageId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $viewMessage = $result->fetch_assoc();
            
            // Mark as read if not already
            if (isset($viewMessage['is_read']) && !$viewMessage['is_read']) {
                try {
                    $updateSql = "UPDATE contact_messages SET is_read = 1 WHERE id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param('i', $messageId);
                    $updateStmt->execute();
                    $updateStmt->close();
                    $viewMessage['is_read'] = 1;
                } catch (Exception $e) {
                    // Silently fail if we can't update
                }
            }
        }
        $stmt->close();
    }
}

// Get all messages
if ($conn) {
    try {
        $sql = "SELECT * FROM contact_messages ORDER BY created_at DESC";
        $result = $conn->query($sql);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }
        }
    } catch (Exception $e) {
        $message = 'Error retrieving messages: ' . $e->getMessage();
        $messageType = 'danger';
    }
    
    closeDbConnection($conn);
}

// Count unread messages
$unreadCount = 0;
foreach ($messages as $msg) {
    if (!$msg['is_read']) {
        $unreadCount++;
    }
}

// Page title
$pageTitle = "Contact Messages";

// Include admin-specific header
include_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-envelope"></i> Contact Messages</h2>
            <p>View and manage messages from the contact form</p>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <!-- Message List -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Messages</h5>
                    <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-primary"><?php echo $unreadCount; ?> unread</span>
                    <?php endif; ?>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($messages)): ?>
                    <div class="list-group-item text-center text-muted">
                        <i class="fas fa-inbox fa-2x my-3"></i>
                        <p>No messages found</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                    <a href="?action=view&id=<?php echo $msg['id']; ?>" class="list-group-item list-group-item-action <?php echo (!$msg['is_read']) ? 'fw-bold' : ''; ?> <?php echo (isset($viewMessage) && $viewMessage['id'] == $msg['id']) ? 'active' : ''; ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($msg['name']); ?></h6>
                            <small><?php echo date('M d, Y', strtotime($msg['created_at'])); ?></small>
                        </div>
                        <p class="mb-1 text-truncate"><?php echo htmlspecialchars($msg['subject']); ?></p>
                        <small class="text-muted text-truncate d-block"><?php echo htmlspecialchars(substr($msg['message'], 0, 50)) . (strlen($msg['message']) > 50 ? '...' : ''); ?></small>
                        <?php if (!$msg['is_read']): ?>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-primary rounded-circle">
                            <span class="visually-hidden">New message</span>
                        </span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Message Details -->
        <div class="col-md-8">
            <?php if ($viewMessage): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?php echo htmlspecialchars($viewMessage['subject']); ?></h5>
                    <div>
                        <a href="?action=toggle_read&id=<?php echo $viewMessage['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                            <i class="fas <?php echo $viewMessage['is_read'] ? 'fa-envelope' : 'fa-envelope-open'; ?>"></i>
                            <?php echo $viewMessage['is_read'] ? 'Mark as Unread' : 'Mark as Read'; ?>
                        </a>
                        <a href="?action=delete&id=<?php echo $viewMessage['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this message?');">
                            <i class="fas fa-trash-alt"></i> Delete
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>From:</strong> <?php echo htmlspecialchars($viewMessage['name']); ?> &lt;<?php echo htmlspecialchars($viewMessage['email']); ?>&gt;
                    </div>
                    <div class="mb-3">
                        <strong>Date:</strong> <?php echo date('F d, Y \a\t h:i A', strtotime($viewMessage['created_at'])); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Subject:</strong> <?php echo htmlspecialchars($viewMessage['subject']); ?>
                    </div>
                    <hr>
                    <div class="message-content">
                        <?php echo nl2br(htmlspecialchars($viewMessage['message'])); ?>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="mailto:<?php echo htmlspecialchars($viewMessage['email']); ?>?subject=Re: <?php echo htmlspecialchars($viewMessage['subject']); ?>" class="btn btn-primary">
                            <i class="fas fa-reply"></i> Reply
                        </a>
                        <div>
                            <a href="?action=toggle_read&id=<?php echo $viewMessage['id']; ?>" class="btn btn-outline-primary me-1">
                                <i class="fas <?php echo $viewMessage['is_read'] ? 'fa-envelope' : 'fa-envelope-open'; ?>"></i>
                                <?php echo $viewMessage['is_read'] ? 'Mark as Unread' : 'Mark as Read'; ?>
                            </a>
                            <a href="?action=delete&id=<?php echo $viewMessage['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this message?');">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-envelope-open-text fa-4x text-muted mb-3"></i>
                    <h5>Select a message to view</h5>
                    <p class="text-muted">Click on a message from the list to view its details</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include admin-specific footer
include_once 'includes/footer.php';
?>