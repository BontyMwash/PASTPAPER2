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
$papers = array();
$statusFilter = isset($_GET['status']) ? sanitizeInput($conn, $_GET['status']) : 'all';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['paper_id'])) {
        $paperId = intval($_POST['paper_id']);
        
        // Approve paper
        if ($_POST['action'] === 'approve') {
            $sql = "UPDATE papers SET status = 'approved' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $paperId);
            
            if ($stmt->execute()) {
                $message = 'Paper approved successfully.';
                $messageType = 'success';
                
                // Log activity
                $userId = $_SESSION['user_id'];
                $action = 'Approve Paper';
                $description = "Approved paper ID: $paperId";
                $ip = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                
                $logSql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
                $logStmt = $conn->prepare($logSql);
                $logStmt->bind_param('issss', $userId, $action, $description, $ip, $userAgent);
                $logStmt->execute();
                $logStmt->close();
            } else {
                $message = 'Error approving paper: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
        
        // Reject/Delete paper
        if ($_POST['action'] === 'delete') {
            // First get the file path to delete the file
            $sql = "SELECT file_path FROM papers WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $paperId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $paper = $result->fetch_assoc();
                $filePath = $paper['file_path'];
                
                // Delete from database
                $deleteSql = "DELETE FROM papers WHERE id = ?";
                $deleteStmt = $conn->prepare($deleteSql);
                $deleteStmt->bind_param('i', $paperId);
                
                if ($deleteStmt->execute()) {
                    // Try to delete the file
                    if (file_exists($filePath) && unlink($filePath)) {
                        $message = 'Paper deleted successfully.';
                    } else {
                        $message = 'Paper deleted from database, but file could not be deleted.';
                    }
                    $messageType = 'success';
                    
                    // Log activity
                    $userId = $_SESSION['user_id'];
                    $action = 'Delete Paper';
                    $description = "Deleted paper ID: $paperId";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $userAgent = $_SERVER['HTTP_USER_AGENT'];
                    
                    $logSql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
                    $logStmt = $conn->prepare($logSql);
                    $logStmt->bind_param('issss', $userId, $action, $description, $ip, $userAgent);
                    $logStmt->execute();
                    $logStmt->close();
                } else {
                    $message = 'Error deleting paper: ' . $conn->error;
                    $messageType = 'danger';
                }
                $deleteStmt->close();
            }
            $stmt->close();
        }
    }
}

// Get papers based on filter
$sql = "SELECT p.id, p.title, p.description, p.file_name, p.file_type, p.file_size, p.year, p.term, 
               p.status, p.created_at, p.download_count, p.file_path,
               u.name as uploaded_by, s.name as subject_name, d.name as department_name
        FROM papers p
        JOIN users u ON p.uploaded_by = u.id
        JOIN subjects s ON p.subject_id = s.id
        JOIN departments d ON s.department_id = d.id";

if ($statusFilter !== 'all') {
    $sql .= " WHERE p.status = ?";
}

$sql .= " ORDER BY p.created_at DESC";

if ($statusFilter !== 'all') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $statusFilter);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $papers[] = $row;
    }
}

closeDbConnection($conn);

// Page title
$pageTitle = "Manage Papers";

// Include admin-specific header
include_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-file-pdf"></i> Manage Papers</h2>
            <p>Review, approve, and manage uploaded past papers</p>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $statusFilter === 'all' ? 'active' : ''; ?>" href="papers.php">
                                All Papers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>" href="papers.php?status=pending">
                                Pending Approval
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $statusFilter === 'approved' ? 'active' : ''; ?>" href="papers.php?status=approved">
                                Approved
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Subject</th>
                                    <th>Department</th>
                                    <th>Year/Term</th>
                                    <th>Uploaded By</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Downloads</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($papers) > 0): ?>
                                    <?php foreach ($papers as $paper): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo $paper['file_path']; ?>" target="_blank" title="View File">
                                                <?php echo htmlspecialchars($paper['title']); ?>
                                            </a>
                                            <small class="d-block text-muted"><?php echo htmlspecialchars($paper['file_name']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($paper['subject_name']); ?></td>
                                        <td><?php echo htmlspecialchars($paper['department_name']); ?></td>
                                        <td>
                                            Year: <?php echo htmlspecialchars($paper['year']); ?><br>
                                            Term: <?php echo htmlspecialchars($paper['term']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($paper['uploaded_by']); ?></td>
                                        // Fix the date display around line 212
                                        <td><?php echo date('M d, Y', strtotime($paper['created_at'])); ?></td>
                                        <td>
                                            <?php if ($paper['status'] === 'approved'): ?>
                                                <span class="badge badge-success">Approved</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $paper['download_count']; ?></td>
                                        <td>
                                            <?php if ($paper['status'] === 'pending'): ?>
                                            <form method="post" action="papers.php?status=<?php echo $statusFilter; ?>" style="display:inline;">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="paper_id" value="<?php echo $paper['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    data-toggle="modal" data-target="#deleteModal<?php echo $paper['id']; ?>" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            
                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal<?php echo $paper['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Are you sure you want to delete the paper <strong><?php echo htmlspecialchars($paper['title']); ?></strong>?
                                                            This action cannot be undone.
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                            <form method="post" action="papers.php?status=<?php echo $statusFilter; ?>">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="paper_id" value="<?php echo $paper['id']; ?>">
                                                                <button type="submit" class="btn btn-danger">Delete Paper</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">
                                            <?php 
                                            if ($statusFilter === 'pending') {
                                                echo 'No pending papers found.';
                                            } elseif ($statusFilter === 'approved') {
                                                echo 'No approved papers found.';
                                            } else {
                                                echo 'No papers found.';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include admin-specific footer
include_once 'includes/footer.php';
?>