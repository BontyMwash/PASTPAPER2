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
$schoolInfo = [];
$teacherCount = 0;
$studentCount = 0;
$paperCount = 0;
$recentPapers = [];
$recentUsers = [];

// Get school information
if ($conn) {
    // Get school details
    $sql = "SELECT * FROM schools WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $schoolInfo = $result->fetch_assoc();
    }
    $stmt->close();
    
    // Get teacher count
    $sql = "SELECT COUNT(*) as count FROM users WHERE school_id = ? AND role = 'teacher'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $teacherCount = $row['count'];
    }
    $stmt->close();
    
    // Get student count
    $sql = "SELECT COUNT(*) as count FROM users WHERE school_id = ? AND role = 'student'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $studentCount = $row['count'];
    }
    $stmt->close();
    
    // Get paper count
    $sql = "SELECT COUNT(*) as count FROM papers WHERE school_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $paperCount = $row['count'];
    }
    $stmt->close();
    
    // Get recent papers
    $sql = "SELECT p.id, p.title, p.year, p.uploaded_at, s.subject_name, u.name as uploaded_by 
           FROM papers p 
           JOIN subjects s ON p.subject_id = s.id 
           JOIN users u ON p.uploaded_by = u.id 
           WHERE p.school_id = ? 
           ORDER BY p.uploaded_at DESC 
           LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentPapers[] = $row;
        }
    }
    $stmt->close();
    
    // Get recent users
    $sql = "SELECT id, name, email, role, created_at 
           FROM users 
           WHERE school_id = ? AND id != ? 
           ORDER BY created_at DESC 
           LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $schoolId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentUsers[] = $row;
        }
    }
    $stmt->close();
    
    closeDbConnection($conn);
}

// Page title
$pageTitle = "School Admin Dashboard";

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2>Welcome to <?php echo htmlspecialchars($schoolInfo['name']); ?> Dashboard</h2>
            <p class="text-muted">Manage your school's resources, users, and past papers.</p>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Teachers</h6>
                            <h2 class="mb-0"><?php echo $teacherCount; ?></h2>
                        </div>
                        <div>
                            <i class="fas fa-chalkboard-teacher fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="teachers.php" class="text-white">Manage Teachers <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Students</h6>
                            <h2 class="mb-0"><?php echo $studentCount; ?></h2>
                        </div>
                        <div>
                            <i class="fas fa-user-graduate fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="students.php" class="text-white">Manage Students <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Past Papers</h6>
                            <h2 class="mb-0"><?php echo $paperCount; ?></h2>
                        </div>
                        <div>
                            <i class="fas fa-file-alt fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="papers.php" class="text-white">Manage Papers <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Papers -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Papers</h5>
                    <a href="papers.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentPapers)): ?>
                    <div class="text-center py-3">
                        <div class="mb-3">
                            <i class="fas fa-file-alt fa-3x text-muted"></i>
                        </div>
                        <p class="mb-0">No papers uploaded yet</p>
                        <a href="papers.php?action=add" class="btn btn-sm btn-primary mt-2">Upload Paper</a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Subject</th>
                                    <th>Year</th>
                                    <th>Uploaded By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPapers as $paper): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($paper['title']); ?></td>
                                    <td><?php echo htmlspecialchars($paper['subject_name']); ?></td>
                                    <td><?php echo $paper['year']; ?></td>
                                    <td><?php echo htmlspecialchars($paper['uploaded_by']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($paper['uploaded_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Users -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Users</h5>
                    <div>
                        <a href="teachers.php" class="btn btn-sm btn-outline-primary me-2">Teachers</a>
                        <a href="students.php" class="btn btn-sm btn-outline-success">Students</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($recentUsers)): ?>
                    <div class="text-center py-3">
                        <div class="mb-3">
                            <i class="fas fa-users fa-3x text-muted"></i>
                        </div>
                        <p class="mb-0">No users added yet</p>
                        <div class="mt-2">
                            <a href="teachers.php?action=add" class="btn btn-sm btn-primary me-2">Add Teacher</a>
                            <a href="students.php?action=add" class="btn btn-sm btn-success">Add Student</a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php if ($user['role'] === 'teacher'): ?>
                                        <span class="badge bg-primary">Teacher</span>
                                        <?php elseif ($user['role'] === 'student'): ?>
                                        <span class="badge bg-success">Student</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- School Information -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">School Information</h5>
                    <a href="profile.php" class="btn btn-sm btn-outline-primary">Edit Profile</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table">
                                <tbody>
                                    <tr>
                                        <th width="30%">School Name:</th>
                                        <td><?php echo htmlspecialchars($schoolInfo['name']); ?></td>
                                    </tr>
                                    <?php if (!empty($schoolInfo['email'])): ?>
                                    <tr>
                                        <th>Email:</th>
                                        <td><?php echo htmlspecialchars($schoolInfo['email']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($schoolInfo['phone'])): ?>
                                    <tr>
                                        <th>Phone:</th>
                                        <td><?php echo htmlspecialchars($schoolInfo['phone']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table">
                                <tbody>
                                    <?php if (!empty($schoolInfo['address'])): ?>
                                    <tr>
                                        <th width="30%">Address:</th>
                                        <td><?php echo htmlspecialchars($schoolInfo['address']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($schoolInfo['website'])): ?>
                                    <tr>
                                        <th>Website:</th>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($schoolInfo['website']); ?>" target="_blank">
                                                <?php echo htmlspecialchars($schoolInfo['website']); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <?php if ($schoolInfo['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                            <?php elseif ($schoolInfo['status'] === 'inactive'): ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">Suspended</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>