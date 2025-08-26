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
$action = isset($_GET['action']) ? $_GET['action'] : '';
$paperId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$messageType = '';
$papers = [];
$subjects = [];
$teachers = [];
$paperData = [
    'id' => '',
    'title' => '',
    'subject_id' => '',
    'year' => date('Y'),
    'description' => '',
    'status' => 'active'
];

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_paper']) || isset($_POST['update_paper'])) {
        // Get form data
        $title = sanitizeInput($_POST['title']);
        $subjectId = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : null;
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
        $description = isset($_POST['description']) ? sanitizeInput($_POST['description']) : '';
        $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : 'active';
        
        // Validate form data
        $errors = [];
        
        if (empty($title)) {
            $errors[] = "Title is required";
        }
        
        if (empty($subjectId)) {
            $errors[] = "Subject is required";
        }
        
        if ($year < 1900 || $year > date('Y') + 1) {
            $errors[] = "Invalid year";
        }
        
        // Check if file is uploaded (for new papers)
        $fileUploaded = false;
        $driveLink = '';
        
        if (isset($_FILES['paper_file']) && $_FILES['paper_file']['error'] === UPLOAD_ERR_OK) {
            $fileUploaded = true;
            $fileName = $_FILES['paper_file']['name'];
            $fileTmpName = $_FILES['paper_file']['tmp_name'];
            $fileSize = $_FILES['paper_file']['size'];
            $fileType = $_FILES['paper_file']['type'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Check file extension
            $allowedExts = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];
            
            if (!in_array($fileExt, $allowedExts)) {
                $errors[] = "Invalid file type. Allowed types: " . implode(', ', $allowedExts);
            }
            
            // Check file size (max 10MB)
            if ($fileSize > 10 * 1024 * 1024) {
                $errors[] = "File size exceeds the limit (10MB)";
            }
        } elseif (isset($_POST['add_paper'])) {
            $errors[] = "Please upload a file";
        }
        
        // If no errors, proceed with adding/updating paper
        if (empty($errors)) {
            // Upload file to Google Drive if a new file is uploaded
            if ($fileUploaded) {
                // Get school's Drive folder ID
                $sql = "SELECT drive_folder_id FROM schools WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $schoolId);
                $stmt->execute();
                $result = $stmt->get_result();
                $driveFolderId = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['drive_folder_id'] : null;
                $stmt->close();
                
                if ($driveFolderId) {
                    // Get subject name for folder structure
                    $sql = "SELECT s.subject_name, d.department_name 
                           FROM subjects s 
                           JOIN departments d ON s.department_id = d.id 
                           WHERE s.id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('i', $subjectId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $subjectInfo = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
                    $stmt->close();
                    
                    if ($subjectInfo) {
                        $departmentName = $subjectInfo['department_name'];
                        $subjectName = $subjectInfo['subject_name'];
                        
                        // For demonstration purposes, we'll simulate Google Drive upload
                        // In a real implementation, you would use the Google Drive API
                        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $title) . '_' . $year . '.' . $fileExt;
                        $uploadDir = '../uploads/' . $schoolId . '/' . $departmentName . '/' . $subjectName . '/';
                        
                        // Create directories if they don't exist
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        $uploadPath = $uploadDir . $safeName;
                        
                        if (move_uploaded_file($fileTmpName, $uploadPath)) {
                            // In a real implementation, this would be the Google Drive file ID
                            $driveLink = $uploadPath;
                        } else {
                            $errors[] = "Failed to upload file";
                        }
                    } else {
                        $errors[] = "Subject information not found";
                    }
                } else {
                    $errors[] = "School Drive folder not configured";
                }
            }
            
            if (empty($errors)) {
                if (isset($_POST['add_paper'])) {
                    // Insert new paper
                    $sql = "INSERT INTO papers (school_id, subject_id, title, year, description, drive_link, uploaded_by, status, uploaded_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('iissssis', $schoolId, $subjectId, $title, $year, $description, $driveLink, $userId, $status);
                    
                    if ($stmt->execute()) {
                        $message = "Paper added successfully";
                        $messageType = "success";
                        
                        // Log activity
                        $activityType = "add_paper";
                        $activityDescription = "Added new paper: $title";
                        logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
                        
                        // Redirect to papers list
                        header('Location: papers.php?message=' . urlencode($message) . '&type=' . urlencode($messageType));
                        exit;
                    } else {
                        $message = "Error adding paper: " . $conn->error;
                        $messageType = "danger";
                    }
                    $stmt->close();
                } elseif (isset($_POST['update_paper']) && isset($_POST['id'])) {
                    $paperId = intval($_POST['id']);
                    
                    // Update paper
                    if ($fileUploaded) {
                        // Update with new file
                        $sql = "UPDATE papers SET subject_id = ?, title = ?, year = ?, description = ?, drive_link = ?, status = ? 
                                WHERE id = ? AND school_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('isisssii', $subjectId, $title, $year, $description, $driveLink, $status, $paperId, $schoolId);
                    } else {
                        // Update without changing file
                        $sql = "UPDATE papers SET subject_id = ?, title = ?, year = ?, description = ?, status = ? 
                                WHERE id = ? AND school_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('isissii', $subjectId, $title, $year, $description, $status, $paperId, $schoolId);
                    }
                    
                    if ($stmt->execute()) {
                        $message = "Paper updated successfully";
                        $messageType = "success";
                        
                        // Log activity
                        $activityType = "update_paper";
                        $activityDescription = "Updated paper: $title";
                        logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
                        
                        // Redirect to papers list
                        header('Location: papers.php?message=' . urlencode($message) . '&type=' . urlencode($messageType));
                        exit;
                    } else {
                        $message = "Error updating paper: " . $conn->error;
                        $messageType = "danger";
                    }
                    $stmt->close();
                }
            } else {
                $message = "Please fix the following errors: <ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
                $messageType = "danger";
            }
        } else {
            $message = "Please fix the following errors: <ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
            $messageType = "danger";
            
            // Preserve form data
            $paperData = [
                'id' => isset($_POST['id']) ? $_POST['id'] : '',
                'title' => $title,
                'subject_id' => $subjectId,
                'year' => $year,
                'description' => $description,
                'status' => $status
            ];
        }
    } elseif (isset($_POST['delete_paper']) && isset($_POST['id'])) {
        $paperId = intval($_POST['id']);
        
        // Get paper title for activity log
        $sql = "SELECT title, drive_link FROM papers WHERE id = ? AND school_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $paperId, $schoolId);
        $stmt->execute();
        $result = $stmt->get_result();
        $paperInfo = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
        $stmt->close();
        
        if ($paperInfo) {
            // Delete file from storage if it exists
            $driveLink = $paperInfo['drive_link'];
            if (!empty($driveLink) && file_exists($driveLink)) {
                unlink($driveLink);
            }
            
            // Delete paper from database
            $sql = "DELETE FROM papers WHERE id = ? AND school_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $paperId, $schoolId);
            
            if ($stmt->execute()) {
                $message = "Paper deleted successfully";
                $messageType = "success";
                
                // Log activity
                $activityType = "delete_paper";
                $activityDescription = "Deleted paper: " . $paperInfo['title'];
                logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
            } else {
                $message = "Error deleting paper: " . $conn->error;
                $messageType = "danger";
            }
            $stmt->close();
        } else {
            $message = "Paper not found";
            $messageType = "danger";
        }
    }
}

// Get message from URL if redirected
if (empty($message) && isset($_GET['message'])) {
    $message = $_GET['message'];
    $messageType = isset($_GET['type']) ? $_GET['type'] : 'info';
}

// Get subjects and teachers for dropdowns
if ($conn) {
    // Get subjects
    $sql = "SELECT s.id, s.subject_name, d.department_name 
            FROM subjects s 
            JOIN departments d ON s.department_id = d.id 
            ORDER BY d.department_name, s.subject_name";
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
    }
    
    // Get teachers
    $sql = "SELECT id, name FROM users WHERE school_id = ? AND role = 'teacher' AND status = 'active' ORDER BY name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
    }
    $stmt->close();
    
    // Get papers
    $sql = "SELECT p.id, p.title, p.year, p.description, p.drive_link, p.status, p.uploaded_at, 
            s.subject_name, d.department_name, u.name as uploaded_by 
            FROM papers p 
            JOIN subjects s ON p.subject_id = s.id 
            JOIN departments d ON s.department_id = d.id 
            JOIN users u ON p.uploaded_by = u.id 
            WHERE p.school_id = ? 
            ORDER BY p.uploaded_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $papers[] = $row;
        }
    }
    $stmt->close();
    
    // Get paper data for edit
    if ($action === 'edit' && $paperId > 0) {
        $sql = "SELECT id, title, subject_id, year, description, status, drive_link 
                FROM papers 
                WHERE id = ? AND school_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $paperId, $schoolId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $paperData = $result->fetch_assoc();
        } else {
            $message = "Paper not found";
            $messageType = "danger";
            $action = ''; // Reset action
        }
        $stmt->close();
    }
    
    closeDbConnection($conn);
}

// Set page title
$pageTitle = ($action === 'add' || $action === 'edit') ? ($action === 'add' ? "Add Paper" : "Edit Paper") : "Past Papers";

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><?php echo $pageTitle; ?></h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <?php if ($action === 'add' || $action === 'edit'): ?>
                    <li class="breadcrumb-item"><a href="papers.php">Past Papers</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $action === 'add' ? 'Add Paper' : 'Edit Paper'; ?></li>
                    <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">Past Papers</li>
                    <?php endif; ?>
                </ol>
            </nav>
        </div>
        <?php if ($action === ''): ?>
        <div class="col-md-6 text-end">
            <a href="papers.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i> Add Paper
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Alert Messages -->
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($action === 'add' || $action === 'edit'): ?>
    <!-- Add/Edit Paper Form -->
    <div class="card">
        <div class="card-body">
            <form method="post" action="papers.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo $paperData['id']; ?>">
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($paperData['title']); ?>" required>
                        <div class="invalid-feedback">Please enter a title</div>
                    </div>
                    <div class="col-md-6">
                        <label for="subject_id" class="form-label">Subject <span class="text-danger">*</span></label>
                        <select class="form-select" id="subject_id" name="subject_id" required>
                            <option value="">Select Subject</option>
                            <?php 
                            $currentDept = '';
                            foreach ($subjects as $subject): 
                                if ($currentDept != $subject['department_name']) {
                                    if ($currentDept != '') {
                                        echo '</optgroup>';
                                    }
                                    echo '<optgroup label="' . htmlspecialchars($subject['department_name']) . '">';
                                    $currentDept = $subject['department_name'];
                                }
                            ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo ($paperData['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                            <?php endforeach; ?>
                            <?php if ($currentDept != '') echo '</optgroup>'; ?>
                        </select>
                        <div class="invalid-feedback">Please select a subject</div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="year" class="form-label">Year <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="year" name="year" value="<?php echo $paperData['year']; ?>" min="1900" max="<?php echo date('Y') + 1; ?>" required>
                        <div class="invalid-feedback">Please enter a valid year</div>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo ($paperData['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($paperData['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($paperData['description']); ?></textarea>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="paper_file" class="form-label"><?php echo ($action === 'add') ? 'Paper File <span class="text-danger">*</span>' : 'Paper File (leave blank to keep current)'; ?></label>
                        <input type="file" class="form-control" id="paper_file" name="paper_file" <?php echo ($action === 'add') ? 'required' : ''; ?>>
                        <div class="invalid-feedback">Please upload a file</div>
                        <div class="form-text">Allowed file types: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX. Max size: 10MB</div>
                        <?php if ($action === 'edit' && !empty($paperData['drive_link'])): ?>
                        <div class="mt-2">
                            <span class="text-muted">Current file:</span> 
                            <a href="<?php echo htmlspecialchars($paperData['drive_link']); ?>" target="_blank" class="ms-2">
                                <i class="fas fa-file-alt me-1"></i> View Current File
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="papers.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Cancel
                    </a>
                    <button type="submit" name="<?php echo ($action === 'add') ? 'add_paper' : 'update_paper'; ?>" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> <?php echo ($action === 'add') ? 'Add Paper' : 'Update Paper'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <!-- Papers List -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($papers)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="fas fa-file-alt fa-4x text-muted"></i>
                </div>
                <h4>No Papers Found</h4>
                <p class="text-muted">Start by adding past papers to your school</p>
                <a href="papers.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i> Add Paper
                </a>
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
                            <th>Status</th>
                            <th>Uploaded On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($papers as $paper): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($paper['title']); ?></td>
                            <td>
                                <span data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($paper['department_name']); ?>">
                                    <?php echo htmlspecialchars($paper['subject_name']); ?>
                                </span>
                            </td>
                            <td><?php echo $paper['year']; ?></td>
                            <td><?php echo htmlspecialchars($paper['uploaded_by']); ?></td>
                            <td>
                                <?php if ($paper['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($paper['uploaded_at'])); ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?php echo htmlspecialchars($paper['drive_link']); ?>" target="_blank" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="papers.php?action=edit&id=<?php echo $paper['id']; ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deletePaperModal<?php echo $paper['id']; ?>" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                
                                <!-- Delete Paper Modal -->
                                <div class="modal fade" id="deletePaperModal<?php echo $paper['id']; ?>" tabindex="-1" aria-labelledby="deletePaperModalLabel<?php echo $paper['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deletePaperModalLabel<?php echo $paper['id']; ?>">Delete Paper</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($paper['title']); ?></strong>?</p>
                                                <p class="text-danger">This action cannot be undone. The file will be permanently deleted.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form method="post" action="papers.php">
                                                    <input type="hidden" name="id" value="<?php echo $paper['id']; ?>">
                                                    <button type="submit" name="delete_paper" class="btn btn-danger">Delete Paper</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include_once 'includes/footer.php'; ?>