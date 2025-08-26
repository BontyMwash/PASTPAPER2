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
$logs = array();
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$totalLogs = 0;

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM activity_logs";
$countResult = $conn->query($countSql);
if ($countResult && $row = $countResult->fetch_assoc()) {
    $totalLogs = $row['total'];
}
$totalPages = ceil($totalLogs / $limit);

// Get logs with pagination
$sql = "SELECT l.id, l.action, l.description, l.ip_address, l.user_agent, l.created_at, u.name as user_name 
        FROM activity_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        ORDER BY l.created_at DESC 
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}
$stmt->close();
closeDbConnection($conn);

// Page title
$pageTitle = "Activity Logs";

// Include admin-specific header
include_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-history"></i> Activity Logs</h2>
            <p>View system activity and user actions</p>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>System Logs</h5>
                        </div>
                        <div class="col-md-6 text-right">
                            <span class="badge badge-primary"><?php echo $totalLogs; ?> Total Logs</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($logs) > 0): ?>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                        <td><?php echo $log['user_name'] ? htmlspecialchars($log['user_name']) : '<span class="text-muted">System</span>'; ?></td>
                                        <td>
                                            <?php 
                                            $badgeClass = 'secondary';
                                            switch ($log['action']) {
                                                case 'Login':
                                                    $badgeClass = 'success';
                                                    break;
                                                case 'Logout':
                                                    $badgeClass = 'warning';
                                                    break;
                                                case 'Upload Paper':
                                                    $badgeClass = 'info';
                                                    break;
                                                case 'Download Paper':
                                                    $badgeClass = 'primary';
                                                    break;
                                                case 'Delete Paper':
                                                case 'Failed Login':
                                                    $badgeClass = 'danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge badge-<?php echo $badgeClass; ?>">
                                                <?php echo htmlspecialchars($log['action']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['description']); ?></td>
                                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No activity logs found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Activity log pagination">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include admin-specific footer
include_once 'includes/footer.php';
?>