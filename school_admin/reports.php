<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a school admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 'school_admin') {
    header('Location: ../multi_school_login.php');
    exit;
}

// Include database connection
require_once '../config/multi_school_database.php';

// Initialize variables
$conn = getDbConnection();
$schoolId = $_SESSION['school_id'];
$userId = $_SESSION['user_id'];
$reportType = isset($_GET['type']) ? $_GET['type'] : 'activity';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$activityData = [];
$paperData = [];
$userData = [];
$departmentData = [];
$subjectData = [];

// Get report data based on type
if ($conn) {
    switch ($reportType) {
        case 'activity':
            // Get activity logs
            $sql = "SELECT al.*, u.name, u.email, u.role 
                    FROM activity_logs al 
                    LEFT JOIN users u ON al.user_id = u.id 
                    WHERE al.school_id = ? 
                    AND DATE(al.timestamp) BETWEEN ? AND ? 
                    ORDER BY al.timestamp DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iss', $schoolId, $startDate, $endDate);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $activityData[] = $row;
                }
            }
            $stmt->close();
            break;
            
        case 'papers':
            // Get paper upload statistics by date
            $sql = "SELECT DATE(date_uploaded) as upload_date, COUNT(*) as paper_count 
                    FROM papers 
                    WHERE school_id = ? 
                    AND DATE(date_uploaded) BETWEEN ? AND ? 
                    GROUP BY DATE(date_uploaded) 
                    ORDER BY upload_date";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iss', $schoolId, $startDate, $endDate);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $paperData['dates'][] = $row['upload_date'];
                    $paperData['counts'][] = $row['paper_count'];
                }
            }
            $stmt->close();
            
            // Get paper statistics by subject
            $sql = "SELECT s.subject_name, COUNT(p.id) as paper_count 
                    FROM papers p 
                    JOIN subjects s ON p.subject_id = s.id 
                    WHERE p.school_id = ? 
                    AND DATE(p.date_uploaded) BETWEEN ? AND ? 
                    GROUP BY s.id 
                    ORDER BY paper_count DESC 
                    LIMIT 10";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iss', $schoolId, $startDate, $endDate);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $subjectData['labels'][] = $row['subject_name'];
                    $subjectData['counts'][] = $row['paper_count'];
                }
            }
            $stmt->close();
            
            // Get paper statistics by department
            $sql = "SELECT d.department_name, COUNT(p.id) as paper_count 
                    FROM papers p 
                    JOIN subjects s ON p.subject_id = s.id 
                    JOIN departments d ON s.department_id = d.id 
                    WHERE p.school_id = ? 
                    AND DATE(p.date_uploaded) BETWEEN ? AND ? 
                    GROUP BY d.id 
                    ORDER BY paper_count DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iss', $schoolId, $startDate, $endDate);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $departmentData['labels'][] = $row['department_name'];
                    $departmentData['counts'][] = $row['paper_count'];
                }
            }
            $stmt->close();
            break;
            
        case 'users':
            // Get user registration statistics by date
            $sql = "SELECT DATE(created_at) as reg_date, COUNT(*) as user_count 
                    FROM users 
                    WHERE school_id = ? 
                    AND DATE(created_at) BETWEEN ? AND ? 
                    GROUP BY DATE(created_at) 
                    ORDER BY reg_date";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iss', $schoolId, $startDate, $endDate);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $userData['dates'][] = $row['reg_date'];
                    $userData['counts'][] = $row['user_count'];
                }
            }
            $stmt->close();
            
            // Get user statistics by role
            $sql = "SELECT role, COUNT(*) as user_count 
                    FROM users 
                    WHERE school_id = ? 
                    AND DATE(created_at) BETWEEN ? AND ? 
                    GROUP BY role";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iss', $schoolId, $startDate, $endDate);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $userData['roles'][] = ucfirst($row['role']);
                    $userData['role_counts'][] = $row['user_count'];
                }
            }
            $stmt->close();
            
            // Get user login statistics
            $sql = "SELECT DATE(al.timestamp) as login_date, COUNT(DISTINCT al.user_id) as login_count 
                    FROM activity_logs al 
                    WHERE al.school_id = ? 
                    AND al.activity_type = 'login' 
                    AND DATE(al.timestamp) BETWEEN ? AND ? 
                    GROUP BY DATE(al.timestamp) 
                    ORDER BY login_date";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iss', $schoolId, $startDate, $endDate);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $userData['login_dates'][] = $row['login_date'];
                    $userData['login_counts'][] = $row['login_count'];
                }
            }
            $stmt->close();
            break;
    }
    
    closeDbConnection($conn);
}

// Set page title
$pageTitle = "Reports";

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Reports</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Reports</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <!-- Report Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="reports.php" class="row g-3">
                <div class="col-md-3">
                    <label for="type" class="form-label">Report Type</label>
                    <select class="form-select" id="type" name="type" onchange="this.form.submit()">
                        <option value="activity" <?php echo ($reportType === 'activity') ? 'selected' : ''; ?>>Activity Logs</option>
                        <option value="papers" <?php echo ($reportType === 'papers') ? 'selected' : ''; ?>>Paper Statistics</option>
                        <option value="users" <?php echo ($reportType === 'users') ? 'selected' : ''; ?>>User Statistics</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($reportType === 'activity'): ?>
    <!-- Activity Logs Report -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Activity Logs</h5>
            <div>
                <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToCSV('activity_logs.csv')">
                    <i class="fas fa-download me-2"></i> Export CSV
                </button>
                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="window.print()">
                    <i class="fas fa-print me-2"></i> Print
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($activityData)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="fas fa-chart-line fa-4x text-muted"></i>
                </div>
                <h4>No Activity Data Found</h4>
                <p class="text-muted">Try adjusting your date range or check back later</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="activityTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Activity Type</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activityData as $activity): ?>
                        <tr>
                            <td><?php echo date('M d, Y g:i A', strtotime($activity['timestamp'])); ?></td>
                            <td>
                                <?php if (!empty($activity['name'])): ?>
                                <?php echo htmlspecialchars($activity['name']); ?>
                                <div class="small text-muted"><?php echo htmlspecialchars($activity['email']); ?></div>
                                <?php else: ?>
                                <span class="text-muted">System</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($activity['role'])): ?>
                                <span class="badge bg-<?php echo ($activity['role'] === 'school_admin') ? 'primary' : (($activity['role'] === 'teacher') ? 'success' : 'info'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $activity['role'])); ?>
                                </span>
                                <?php else: ?>
                                <span class="badge bg-secondary">System</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $activityTypeClass = 'secondary';
                                switch ($activity['activity_type']) {
                                    case 'login':
                                        $activityTypeClass = 'success';
                                        break;
                                    case 'logout':
                                        $activityTypeClass = 'warning';
                                        break;
                                    case 'add_paper':
                                    case 'add_subject':
                                    case 'add_department':
                                    case 'add_user':
                                        $activityTypeClass = 'primary';
                                        break;
                                    case 'update_paper':
                                    case 'update_subject':
                                    case 'update_department':
                                    case 'update_user':
                                    case 'update_profile':
                                    case 'update_password':
                                    case 'update_school':
                                        $activityTypeClass = 'info';
                                        break;
                                    case 'delete_paper':
                                    case 'delete_subject':
                                    case 'delete_department':
                                    case 'delete_user':
                                        $activityTypeClass = 'danger';
                                        break;
                                }
                                ?>
                                <span class="badge bg-<?php echo $activityTypeClass; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $activity['activity_type'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($activity['description']); ?></td>
                            <td><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php elseif ($reportType === 'papers'): ?>
    <!-- Paper Statistics Report -->
    <div class="row">
        <!-- Paper Uploads Over Time -->
        <div class="col-md-12 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Paper Uploads Over Time</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="downloadChart('timeChart', 'paper_uploads_over_time.png')">
                            <i class="fas fa-download me-2"></i> Download
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($paperData)): ?>
                    <div class="text-center py-5">
                        <div class="mb-3">
                            <i class="fas fa-chart-line fa-4x text-muted"></i>
                        </div>
                        <h4>No Paper Upload Data Found</h4>
                        <p class="text-muted">Try adjusting your date range or check back later</p>
                    </div>
                    <?php else: ?>
                    <canvas id="timeChart" height="300"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Papers by Department -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Papers by Department</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="downloadChart('departmentChart', 'papers_by_department.png')">
                            <i class="fas fa-download me-2"></i> Download
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($departmentData)): ?>
                    <div class="text-center py-5">
                        <div class="mb-3">
                            <i class="fas fa-chart-pie fa-4x text-muted"></i>
                        </div>
                        <h4>No Department Data Found</h4>
                        <p class="text-muted">Try adjusting your date range or check back later</p>
                    </div>
                    <?php else: ?>
                    <canvas id="departmentChart" height="300"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Top Subjects -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Top Subjects</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="downloadChart('subjectChart', 'top_subjects.png')">
                            <i class="fas fa-download me-2"></i> Download
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($subjectData)): ?>
                    <div class="text-center py-5">
                        <div class="mb-3">
                            <i class="fas fa-chart-bar fa-4x text-muted"></i>
                        </div>
                        <h4>No Subject Data Found</h4>
                        <p class="text-muted">Try adjusting your date range or check back later</p>
                    </div>
                    <?php else: ?>
                    <canvas id="subjectChart" height="300"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($reportType === 'users'): ?>
    <!-- User Statistics Report -->
    <div class="row">
        <!-- User Registrations Over Time -->
        <div class="col-md-12 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">User Registrations Over Time</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="downloadChart('registrationChart', 'user_registrations.png')">
                            <i class="fas fa-download me-2"></i> Download
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($userData['dates'])): ?>
                    <div class="text-center py-5">
                        <div class="mb-3">
                            <i class="fas fa-chart-line fa-4x text-muted"></i>
                        </div>
                        <h4>No User Registration Data Found</h4>
                        <p class="text-muted">Try adjusting your date range or check back later</p>
                    </div>
                    <?php else: ?>
                    <canvas id="registrationChart" height="300"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- User Logins Over Time -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">User Logins Over Time</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="downloadChart('loginChart', 'user_logins.png')">
                            <i class="fas fa-download me-2"></i> Download
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($userData['login_dates'])): ?>
                    <div class="text-center py-5">
                        <div class="mb-3">
                            <i class="fas fa-chart-line fa-4x text-muted"></i>
                        </div>
                        <h4>No User Login Data Found</h4>
                        <p class="text-muted">Try adjusting your date range or check back later</p>
                    </div>
                    <?php else: ?>
                    <canvas id="loginChart" height="300"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Users by Role -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Users by Role</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="downloadChart('roleChart', 'users_by_role.png')">
                            <i class="fas fa-download me-2"></i> Download
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($userData['roles'])): ?>
                    <div class="text-center py-5">
                        <div class="mb-3">
                            <i class="fas fa-chart-pie fa-4x text-muted"></i>
                        </div>
                        <h4>No User Role Data Found</h4>
                        <p class="text-muted">Try adjusting your date range or check back later</p>
                    </div>
                    <?php else: ?>
                    <canvas id="roleChart" height="300"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Function to export table to CSV
    function exportTableToCSV(filename) {
        const table = document.getElementById('activityTable');
        if (!table) return;
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                // Replace HTML entities and remove line breaks
                let data = cols[j].innerText.replace(/"/g, '""');
                data = data.replace(/\r?\n/g, ' ');
                row.push('"' + data + '"');
            }
            
            csv.push(row.join(','));
        }
        
        // Download CSV file
        downloadCSV(csv.join('\n'), filename);
    }
    
    function downloadCSV(csv, filename) {
        const csvFile = new Blob([csv], {type: 'text/csv'});
        const downloadLink = document.createElement('a');
        
        // File name
        downloadLink.download = filename;
        
        // Create a link to the file
        downloadLink.href = window.URL.createObjectURL(csvFile);
        
        // Hide download link
        downloadLink.style.display = 'none';
        
        // Add the link to DOM
        document.body.appendChild(downloadLink);
        
        // Click download link
        downloadLink.click();
        
        // Clean up
        document.body.removeChild(downloadLink);
    }
    
    // Function to download chart as image
    function downloadChart(chartId, filename) {
        const canvas = document.getElementById(chartId);
        if (!canvas) return;
        
        const image = canvas.toDataURL('image/png', 1.0);
        const downloadLink = document.createElement('a');
        
        // File name
        downloadLink.download = filename;
        
        // Create a link to the file
        downloadLink.href = image;
        
        // Click download link
        downloadLink.click();
    }
    
    // Initialize charts when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($reportType === 'papers' && !empty($paperData)): ?>
        // Paper uploads over time chart
        const timeCtx = document.getElementById('timeChart');
        if (timeCtx) {
            new Chart(timeCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($paperData['dates'] ?? []); ?>,
                    datasets: [{
                        label: 'Paper Uploads',
                        data: <?php echo json_encode($paperData['counts'] ?? []); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
        
        // Papers by department chart
        const deptCtx = document.getElementById('departmentChart');
        if (deptCtx) {
            new Chart(deptCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($departmentData['labels'] ?? []); ?>,
                    datasets: [{
                        data: <?php echo json_encode($departmentData['counts'] ?? []); ?>,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)',
                            'rgba(255, 159, 64, 0.7)',
                            'rgba(199, 199, 199, 0.7)',
                            'rgba(83, 102, 255, 0.7)',
                            'rgba(40, 159, 64, 0.7)',
                            'rgba(210, 199, 199, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        }
        
        // Top subjects chart
        const subjectCtx = document.getElementById('subjectChart');
        if (subjectCtx) {
            new Chart(subjectCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($subjectData['labels'] ?? []); ?>,
                    datasets: [{
                        label: 'Number of Papers',
                        data: <?php echo json_encode($subjectData['counts'] ?? []); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
        <?php endif; ?>
        
        <?php if ($reportType === 'users'): ?>
        // User registrations chart
        const regCtx = document.getElementById('registrationChart');
        if (regCtx) {
            new Chart(regCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($userData['dates'] ?? []); ?>,
                    datasets: [{
                        label: 'User Registrations',
                        data: <?php echo json_encode($userData['counts'] ?? []); ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
        
        // User logins chart
        const loginCtx = document.getElementById('loginChart');
        if (loginCtx) {
            new Chart(loginCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($userData['login_dates'] ?? []); ?>,
                    datasets: [{
                        label: 'User Logins',
                        data: <?php echo json_encode($userData['login_counts'] ?? []); ?>,
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 2,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
        
        // Users by role chart
        const roleCtx = document.getElementById('roleChart');
        if (roleCtx) {
            new Chart(roleCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($userData['roles'] ?? []); ?>,
                    datasets: [{
                        data: <?php echo json_encode($userData['role_counts'] ?? []); ?>,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        }
        <?php endif; ?>
    });
</script>

<?php include_once 'includes/footer.php'; ?>