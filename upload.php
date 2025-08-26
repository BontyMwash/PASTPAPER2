<?php
// Start session
session_start();

// Include database connection
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php');
    exit;
}

// Initialize variables
$title = $description = $year = $term = '';
$subjectId = $departmentId = 0;
$errors = [];
$success = false;

// Get departments and subjects
$departments = [];
$subjects = [];
$conn = getDbConnection();

if ($conn) {
    // Get departments
    $deptSql = "SELECT id, name FROM departments ORDER BY name";
    $deptResult = $conn->query($deptSql);
    
    if ($deptResult && $deptResult->num_rows > 0) {
        while ($dept = $deptResult->fetch_assoc()) {
            $departments[] = $dept;
        }
    }
    
    // Get subjects
    $subjectSql = "SELECT id, department_id, name FROM subjects ORDER BY name";
    $subjectResult = $conn->query($subjectSql);
    
    if ($subjectResult && $subjectResult->num_rows > 0) {
        while ($subject = $subjectResult->fetch_assoc()) {
            $subjects[] = $subject;
        }
    }
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get form data
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $subjectId = intval($_POST['subject_id'] ?? 0);
        $departmentId = intval($_POST['department_id'] ?? 0);
        $year = intval($_POST['year'] ?? 0);
        $term = $_POST['term'] ?? '';
        
        // Validate form data
        if (empty($title)) {
            $errors[] = 'Title is required';
        }
        
        if ($subjectId <= 0) {
            $errors[] = 'Subject is required';
        }
        
        if ($departmentId <= 0) {
            $errors[] = 'Department is required';
        }
        
        if ($year <= 0) {
            $errors[] = 'Year is required';
        }
        
        if (empty($term)) {
            $errors[] = 'Term is required';
        }
        
        // Validate file upload
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload is required';
        } else {
            $file = $_FILES['file'];
            $fileName = $file['name'];
            $fileSize = $file['size'];
            $fileTmpName = $file['tmp_name'];
            $fileType = $file['type'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Check file size (limit to 10MB)
            if ($fileSize > 10 * 1024 * 1024) {
                $errors[] = 'File size should not exceed 10MB';
            }
            
            // Check file extension
            $allowedExts = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];
            if (!in_array($fileExt, $allowedExts)) {
                $errors[] = 'Only PDF, DOC, DOCX, PPT, PPTX, XLS, and XLSX files are allowed';
            }
        }
        
        // If no validation errors, upload file and save to database
        if (empty($errors)) {
            // Create uploads directory if it doesn't exist
            $uploadDir = 'uploads/' . $departmentId . '/' . $subjectId . '/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generate unique file name
            $newFileName = uniqid() . '_' . $fileName;
            $filePath = $uploadDir . $newFileName;
            
            // Move uploaded file
            if (move_uploaded_file($fileTmpName, $filePath)) {
                // Sanitize input
                $title = sanitizeInput($conn, $title);
                $description = sanitizeInput($conn, $description);
                $userId = $_SESSION['user_id'];
                
                // Prepare SQL statement
                $sql = "INSERT INTO papers (title, description, file_name, file_path, file_size, file_type, subject_id, department_id, uploaded_by, year, term) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssssissiiis', $title, $description, $fileName, $filePath, $fileSize, $fileType, $subjectId, $departmentId, $userId, $year, $term);
                
                if ($stmt->execute()) {
                    // Log activity
                    $action = 'Upload Paper';
                    $activityDesc = "Uploaded paper: $title";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $userAgent = $_SERVER['HTTP_USER_AGENT'];
                    
                    $logSql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
                    $logStmt = $conn->prepare($logSql);
                    $logStmt->bind_param('issss', $userId, $action, $activityDesc, $ip, $userAgent);
                    $logStmt->execute();
                    $logStmt->close();
                    
                    $success = true;
                    $title = $description = $year = $term = '';
                    $subjectId = $departmentId = 0;
                } else {
                    $errors[] = 'Failed to save paper details to database';
                }
                
                $stmt->close();
            } else {
                $errors[] = 'Failed to upload file';
            }
        }
    }
    
    closeDbConnection($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Past Paper - Njumbi High School</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container my-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h2 class="h4 mb-0">Upload Past Paper</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i> Past paper uploaded successfully! It will be reviewed by an administrator before being published.
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form action="upload.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">Paper Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                                <div class="form-text">E.g., "Form 3 Chemistry End Term 2 Exam 2023"</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                                <div class="form-text">Optional: Add any additional information about this paper</div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                                    <select class="form-select" id="department_id" name="department_id" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['id']; ?>" <?php echo ($departmentId == $dept['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="subject_id" class="form-label">Subject <span class="text-danger">*</span></label>
                                    <select class="form-select" id="subject_id" name="subject_id" required>
                                        <option value="">Select Subject</option>
                                        <?php foreach ($subjects as $subject): ?>
                                            <option value="<?php echo $subject['id']; ?>" data-department="<?php echo $subject['department_id']; ?>" <?php echo ($subjectId == $subject['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($subject['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="year" class="form-label">Year <span class="text-danger">*</span></label>
                                    <select class="form-select" id="year" name="year" required>
                                        <option value="">Select Year</option>
                                        <?php for ($y = date('Y'); $y >= 2010; $y--): ?>
                                            <option value="<?php echo $y; ?>" <?php echo ($year == $y) ? 'selected' : ''; ?>>
                                                <?php echo $y; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="term" class="form-label">Term <span class="text-danger">*</span></label>
                                    <select class="form-select" id="term" name="term" required>
                                        <option value="">Select Term</option>
                                        <option value="1" <?php echo ($term == '1') ? 'selected' : ''; ?>>Term 1</option>
                                        <option value="2" <?php echo ($term == '2') ? 'selected' : ''; ?>>Term 2</option>
                                        <option value="3" <?php echo ($term == '3') ? 'selected' : ''; ?>>Term 3</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="fileUpload" class="form-label">Upload File <span class="text-danger">*</span></label>
                                <div class="upload-area p-4 text-center">
                                    <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-primary"></i>
                                    <h5>Drag & Drop or Click to Upload</h5>
                                    <p class="text-muted">Supported formats: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX (Max 10MB)</p>
                                    <input type="file" class="form-control" id="fileUpload" name="file" required>
                                </div>
                                
                                <div id="filePreview" class="mt-3 d-none">
                                    <div class="card">
                                        <div class="card-body d-flex align-items-center">
                                            <i id="fileIcon" class="fas fa-file fa-2x me-3 text-primary"></i>
                                            <div>
                                                <h6 id="fileName" class="mb-1">filename.pdf</h6>
                                                <small id="fileSize" class="text-muted">File size</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i> Upload Past Paper
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Filter subjects based on selected department
        document.addEventListener('DOMContentLoaded', function() {
            const departmentSelect = document.getElementById('department_id');
            const subjectSelect = document.getElementById('subject_id');
            const subjectOptions = Array.from(subjectSelect.options);
            
            function filterSubjects() {
                const selectedDepartment = departmentSelect.value;
                
                // Reset subject dropdown
                subjectSelect.innerHTML = '<option value="">Select Subject</option>';
                
                if (selectedDepartment) {
                    // Add subjects for selected department
                    subjectOptions.forEach(option => {
                        if (option.dataset.department === selectedDepartment || option.value === '') {
                            subjectSelect.appendChild(option.cloneNode(true));
                        }
                    });
                } else {
                    // Add all subjects if no department selected
                    subjectOptions.forEach(option => {
                        subjectSelect.appendChild(option.cloneNode(true));
                    });
                }
            }
            
            departmentSelect.addEventListener('change', filterSubjects);
            
            // Initial filter on page load
            filterSubjects();
        });
    </script>
</body>
</html>