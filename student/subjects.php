<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 'student') {
    header('Location: ../multi_school_login.php');
    exit;
}

// Include database connection
require_once '../config/multi_school_database.php';

// Initialize variables
$conn = getDbConnection();
$schoolId = $_SESSION['school_id'];
$userId = $_SESSION['user_id'];
$departments = [];

// Get departments and subjects with paper counts
if ($conn) {
    $sql = "SELECT d.*, 
            (SELECT COUNT(*) FROM subjects s WHERE s.department_id = d.id) as subject_count,
            (SELECT COUNT(*) FROM papers p JOIN subjects s ON p.subject_id = s.id WHERE s.department_id = d.id) as paper_count
            FROM departments d 
            WHERE d.school_id = ? 
            ORDER BY d.department_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Get subjects for this department
            $subjectSql = "SELECT s.*, 
                          (SELECT COUNT(*) FROM papers p WHERE p.subject_id = s.id) as paper_count 
                          FROM subjects s 
                          WHERE s.department_id = ? 
                          ORDER BY s.subject_name";
            $subjectStmt = $conn->prepare($subjectSql);
            $subjectStmt->bind_param('i', $row['id']);
            $subjectStmt->execute();
            $subjectResult = $subjectStmt->get_result();
            
            $subjects = [];
            if ($subjectResult) {
                while ($subjectRow = $subjectResult->fetch_assoc()) {
                    $subjects[] = $subjectRow;
                }
            }
            $subjectStmt->close();
            
            $row['subjects'] = $subjects;
            $departments[] = $row;
        }
    }
    $stmt->close();
    
    // Log this activity
    $activityType = 'view_subjects';
    $description = "Student viewed subjects list";
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    $sql = "INSERT INTO activity_logs (user_id, school_id, activity_type, description, ip_address) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iisss', $userId, $schoolId, $activityType, $description, $ipAddress);
    $stmt->execute();
    $stmt->close();
    
    closeDbConnection($conn);
}

// Set page title
$pageTitle = "Subjects";

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Subjects</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Subjects</li>
                </ol>
            </nav>
        </div>
        <div class="col-md-6 text-end">
            <form id="searchForm" action="papers.php" method="get" class="d-flex">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search papers..." name="search">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if (empty($departments)): ?>
    <div class="text-center py-5">
        <div class="mb-3">
            <i class="fas fa-building fa-4x text-muted"></i>
        </div>
        <h4>No Departments Found</h4>
        <p class="text-muted">There are no departments set up in your school yet</p>
    </div>
    <?php else: ?>
    <div class="row">
        <?php foreach ($departments as $department): ?>
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-building me-2"></i>
                        <?php echo htmlspecialchars($department['department_name']); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="department-stats mb-3">
                        <span class="badge bg-primary me-2">
                            <i class="fas fa-book me-1"></i> <?php echo $department['subject_count']; ?> Subjects
                        </span>
                        <span class="badge bg-secondary">
                            <i class="fas fa-file-alt me-1"></i> <?php echo $department['paper_count']; ?> Papers
                        </span>
                    </div>
                    
                    <?php if (empty($department['subjects'])): ?>
                    <p class="text-muted">No subjects available in this department</p>
                    <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($department['subjects'] as $subject): ?>
                        <a href="papers.php?subject_id=<?php echo $subject['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-book me-2"></i>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </div>
                            <span class="badge bg-primary rounded-pill">
                                <?php echo $subject['paper_count']; ?> Papers
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="papers.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-search me-1"></i> Browse All Papers
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include_once 'includes/footer.php'; ?>