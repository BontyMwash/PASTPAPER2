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

// Get stats for dashboard
$conn = getDbConnection();
$stats = array(
    'users' => 0,
    'papers' => 0,
    'departments' => 0,
    'subjects' => 0,
    'downloads' => 0,
    'pending_papers' => 0,
    'unread_messages' => 0
);

if ($conn) {
    // Get user count
    $sql = "SELECT COUNT(*) as count FROM users";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['users'] = $row['count'];
    }
    
    // Get paper count
    $sql = "SELECT COUNT(*) as count, SUM(download_count) as downloads FROM papers";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['papers'] = $row['count'];
        $stats['downloads'] = $row['downloads'] ?: 0;
    }
    
    // Get pending papers count
    $sql = "SELECT COUNT(*) as count FROM papers WHERE status = 'pending'";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['pending_papers'] = $row['count'];
    }
    
    // Get department count
    $sql = "SELECT COUNT(*) as count FROM departments";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['departments'] = $row['count'];
    }
    
    // Get subject count
    $sql = "SELECT COUNT(*) as count FROM subjects";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['subjects'] = $row['count'];
    }
    
    // Get unread messages count
    try {
        $sql = "SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0";
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $stats['unread_messages'] = $row['count'];
        }
    } catch (mysqli_sql_exception $e) {
        // Handle case where is_read column might not exist
        $stats['unread_messages'] = 0;
        
        // Check if the contact_messages table exists
        $checkTableSql = "SHOW TABLES LIKE 'contact_messages'";
        $tableResult = $conn->query($checkTableSql);
        
        if ($tableResult && $tableResult->num_rows > 0) {
            // Table exists but column might be missing, try to add it
            try {
                $alterSql = "ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS is_read BOOLEAN DEFAULT FALSE";
                $conn->query($alterSql);
            } catch (Exception $alterError) {
                // Silently fail if we can't alter the table
            }
        }
    }
    
    closeDbConnection($conn);
}

// Page title
$pageTitle = "Admin Dashboard";

// Include admin-specific header
include_once 'includes/header.php';
?>

<div class="container mt-4 fade-in">
    <div class="row mb-4">
        <div class="col-md-12 text-center">
            <h2 class="text-primary slide-in-top"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h2>
            <p class="lead fade-in" style="animation-delay: 0.2s">Welcome to the Njumbi High School Past Papers Admin Panel</p>
        </div>
    </div>
    
    <div class="row mb-4">
        <!-- Stats Cards -->
        <div class="col-md-4 mb-3">
            <div class="card bg-primary text-white h-100 shadow fade-in" style="animation-delay: 0.1s">
                <div class="card-body">
                    <div class="icon-circle mb-3">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                    <h5 class="card-title">Users</h5>
                    <h2 class="display-4"><?php echo $stats['users']; ?></h2>
                    <p class="card-text">Registered teachers</p>
                </div>
                <div class="card-footer d-flex">
                    <a href="users.php" class="text-white">View Details <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card bg-success text-white h-100 shadow fade-in" style="animation-delay: 0.2s">
                <div class="card-body">
                    <div class="icon-circle mb-3">
                        <i class="fas fa-file-pdf fa-2x"></i>
                    </div>
                    <h5 class="card-title">Papers</h5>
                    <h2 class="display-4"><?php echo $stats['papers']; ?></h2>
                    <p class="card-text">Total past papers</p>
                </div>
                <div class="card-footer d-flex">
                    <a href="papers.php" class="text-white">View Details <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card bg-warning text-white h-100 shadow fade-in" style="animation-delay: 0.3s">
                <div class="card-body">
                    <div class="icon-circle mb-3">
                        <i class="fas fa-download fa-2x"></i>
                    </div>
                    <h5 class="card-title">Downloads</h5>
                    <h2 class="display-4"><?php echo $stats['downloads']; ?></h2>
                    <p class="card-text">Total paper downloads</p>
                </div>
                <div class="card-footer d-flex">
                    <a href="papers.php" class="text-white">View Details <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card h-100 shadow-sm fade-in" style="animation-delay: 0.4s">
                <div class="card-header bg-light">
                    <h5 class="text-primary"><i class="fas fa-tasks"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="users.php?action=new" class="list-group-item list-group-item-action slide-in-left" style="animation-delay: 0.1s">
                            <i class="fas fa-user-plus text-primary"></i> Add New Teacher
                        </a>
                        <a href="papers.php?status=pending" class="list-group-item list-group-item-action slide-in-left" style="animation-delay: 0.2s">
                            <i class="fas fa-clock text-warning"></i> Review Pending Papers 
                            <span class="badge badge-warning"><?php echo $stats['pending_papers']; ?></span>
                        </a>
                        <a href="departments.php" class="list-group-item list-group-item-action slide-in-left" style="animation-delay: 0.3s">
                            <i class="fas fa-building text-success"></i> Manage Departments
                        </a>
                        <a href="subjects.php" class="list-group-item list-group-item-action slide-in-left" style="animation-delay: 0.4s">
                            <i class="fas fa-book text-info"></i> Manage Subjects
                        </a>
                        <a href="logs.php" class="list-group-item list-group-item-action slide-in-left" style="animation-delay: 0.5s">
                            <i class="fas fa-history text-secondary"></i> View Activity Logs
                        </a>
                        <a href="messages.php" class="list-group-item list-group-item-action slide-in-left" style="animation-delay: 0.6s">
                            <i class="fas fa-envelope text-primary"></i> Contact Messages
                            <?php if ($stats['unread_messages'] > 0): ?>
                            <span class="badge bg-danger float-end"><?php echo $stats['unread_messages']; ?> unread</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-3">
            <div class="card h-100 shadow-sm fade-in" style="animation-delay: 0.5s">
                <div class="card-header bg-light">
                    <h5 class="text-primary"><i class="fas fa-chart-pie"></i> System Overview</h5>
                </div>
                <div class="card-body">
                    <canvas id="statsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Setup chart data
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('statsChart').getContext('2d');
        const statsChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Users', 'Papers', 'Departments', 'Subjects'],
                datasets: [{
                    data: [
                        <?php echo $stats['users']; ?>,
                        <?php echo $stats['papers']; ?>,
                        <?php echo $stats['departments']; ?>,
                        <?php echo $stats['subjects']; ?>
                    ],
                    backgroundColor: [
                        '#007bff',
                        '#28a745',
                        '#ffc107',
                        '#17a2b8'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    });
</script>

<?php
// Include admin-specific footer
include_once 'includes/footer.php';
?>