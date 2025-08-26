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
$userName = $_SESSION['name'];
$recentPapers = [];
$departments = [];
$schoolInfo = [];

// Get school information
if ($conn) {
    // Get school information
    $sql = "SELECT * FROM schools WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $schoolInfo = $result->fetch_assoc();
    }
    $stmt->close();
    
    // Get recent papers
    $sql = "SELECT p.*, s.subject_name, u.name as uploaded_by 
            FROM papers p 
            JOIN subjects s ON p.subject_id = s.id 
            JOIN users u ON p.uploaded_by = u.id 
            WHERE p.school_id = ? 
            ORDER BY p.date_uploaded DESC 
            LIMIT 10";
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
    
    // Get departments with subjects
    $sql = "SELECT d.*, 
            (SELECT COUNT(*) FROM subjects s WHERE s.department_id = d.id) as subject_count 
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
    $activityType = 'login';
    $description = "Student accessed dashboard";
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
$pageTitle = "Student Dashboard";

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Welcome Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card welcome-card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <?php if (!empty($schoolInfo['logo'])): ?>
                                <img src="../uploads/school_logos/<?php echo htmlspecialchars($schoolInfo['logo']); ?>" alt="School Logo" class="img-fluid school-logo mb-3 mb-md-0" style="max-height: 100px;">
                            <?php else: ?>
                                <i class="fas fa-school fa-4x text-primary mb-3 mb-md-0"></i>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-10">
                            <h2 class="welcome-title">Welcome to <?php echo htmlspecialchars($schoolInfo['name'] ?? 'School'); ?> Past Papers</h2>
                            <p class="welcome-text">Browse and download past papers to help with your exam preparation.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="row mb-4">
        <!-- Recently Added Papers -->
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recently Added Papers</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentPapers)): ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-file-alt fa-4x text-muted"></i>
                            </div>
                            <h4>No Papers Available Yet</h4>
                            <p class="text-muted">Check back soon for new past papers</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Subject</th>
                                        <th>Year</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentPapers as $paper): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($paper['title']); ?></td>
                                            <td><?php echo htmlspecialchars($paper['subject_name']); ?></td>
                                            <td><?php echo htmlspecialchars($paper['year']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($paper['date_uploaded'])); ?></td>
                                            <td>
                                                <a href="<?php echo htmlspecialchars($paper['drive_link']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-end">
                    <a href="papers.php" class="btn btn-outline-primary btn-sm">View All Papers</a>
                </div>
            </div>
        </div>
        
        <!-- Departments Quick Access -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Departments</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($departments)): ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-building fa-4x text-muted"></i>
                            </div>
                            <h4>No Departments Available</h4>
                            <p class="text-muted">Departments will appear here once added</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($departments as $department): ?>
                                <a href="#department-<?php echo $department['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" role="button" aria-expanded="false">
                                    <span>
                                        <i class="fas fa-building me-2"></i>
                                        <?php echo htmlspecialchars($department['department_name']); ?>
                                    </span>
                                    <span class="badge bg-primary rounded-pill"><?php echo $department['subject_count']; ?></span>
                                </a>
                                <div class="collapse" id="department-<?php echo $department['id']; ?>">
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($department['subjects'] as $subject): ?>
                                            <a href="papers.php?subject_id=<?php echo $subject['id']; ?>" class="list-group-item list-group-item-action ps-5 d-flex justify-content-between align-items-center">
                                                <span>
                                                    <i class="fas fa-book me-2"></i>
                                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                                </span>
                                                <span class="badge bg-info rounded-pill"><?php echo $subject['paper_count']; ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Search Past Papers</h5>
                </div>
                <div class="card-body">
                    <form action="papers.php" method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="subject" class="form-label">Subject</label>
                            <select class="form-select" id="subject" name="subject_id">
                                <option value="">All Subjects</option>
                                <?php foreach ($departments as $department): ?>
                                    <optgroup label="<?php echo htmlspecialchars($department['department_name']); ?>">
                                        <?php foreach ($department['subjects'] as $subject): ?>
                                            <option value="<?php echo $subject['id']; ?>">
                                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="year" class="form-label">Year</label>
                            <select class="form-select" id="year" name="year">
                                <option value="">All Years</option>
                                <?php 
                                $currentYear = date('Y');
                                for ($i = $currentYear; $i >= $currentYear - 10; $i--) {
                                    echo "<option value=\"$i\">$i</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search Term</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Enter keywords...">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Study Tips Section -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Study Tips</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-4 mb-md-0">
                            <div class="study-tip">
                                <div class="study-tip-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <h5>Create a Study Schedule</h5>
                                <p>Plan your study sessions in advance. Allocate specific time slots for different subjects based on your strengths and weaknesses.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4 mb-md-0">
                            <div class="study-tip">
                                <div class="study-tip-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h5>Practice Past Papers</h5>
                                <p>Regularly practice with past papers under timed conditions to familiarize yourself with the exam format and improve time management.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="study-tip">
                                <div class="study-tip-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h5>Form Study Groups</h5>
                                <p>Collaborate with classmates to discuss difficult concepts, share notes, and quiz each other on important topics.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>