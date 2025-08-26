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
$subjectId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$messageType = '';
$subjects = [];
$departments = [];
$subjectData = [
    'id' => '',
    'subject_name' => '',
    'department_id' => '',
    'description' => '',
    'status' => 'active'
];

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_subject']) || isset($_POST['update_subject'])) {
        // Get form data
        $subjectName = sanitizeInput($_POST['subject_name']);
        $departmentId = isset($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $description = isset($_POST['description']) ? sanitizeInput($_POST['description']) : '';
        $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : 'active';
        
        // Validate form data
        $errors = [];
        
        if (empty($subjectName)) {
            $errors[] = "Subject name is required";
        }
        
        if (empty($departmentId)) {
            $errors[] = "Department is required";
        }
        
        // Check if subject name already exists in the same department
        if (isset($_POST['add_subject']) || (isset($_POST['update_subject']) && isset($_POST['original_name']) && $_POST['original_name'] != $subjectName)) {
            $sql = "SELECT id FROM subjects WHERE subject_name = ? AND department_id = ?";
            if (isset($_POST['update_subject']) && isset($_POST['id'])) {
                $sql .= " AND id != ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sii', $subjectName, $departmentId, $_POST['id']);
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('si', $subjectName, $departmentId);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $errors[] = "Subject name already exists in this department";
            }
            $stmt->close();
        }
        
        // If no errors, proceed with adding/updating subject
        if (empty($errors)) {
            if (isset($_POST['add_subject'])) {
                // Insert new subject
                $sql = "INSERT INTO subjects (subject_name, department_id, description, status) 
                        VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('siss', $subjectName, $departmentId, $description, $status);
                
                if ($stmt->execute()) {
                    $message = "Subject added successfully";
                    $messageType = "success";
                    
                    // Log activity
                    $activityType = "add_subject";
                    $activityDescription = "Added new subject: $subjectName";
                    logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
                    
                    // Redirect to subjects list
                    header('Location: subjects.php?message=' . urlencode($message) . '&type=' . urlencode($messageType));
                    exit;
                } else {
                    $message = "Error adding subject: " . $conn->error;
                    $messageType = "danger";
                }
                $stmt->close();
            } elseif (isset($_POST['update_subject']) && isset($_POST['id'])) {
                $subjectId = intval($_POST['id']);
                
                // Update subject
                $sql = "UPDATE subjects SET subject_name = ?, department_id = ?, description = ?, status = ? 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sissi', $subjectName, $departmentId, $description, $status, $subjectId);
                
                if ($stmt->execute()) {
                    $message = "Subject updated successfully";
                    $messageType = "success";
                    
                    // Log activity
                    $activityType = "update_subject";
                    $activityDescription = "Updated subject: $subjectName";
                    logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
                    
                    // Redirect to subjects list
                    header('Location: subjects.php?message=' . urlencode($message) . '&type=' . urlencode($messageType));
                    exit;
                } else {
                    $message = "Error updating subject: " . $conn->error;
                    $messageType = "danger";
                }
                $stmt->close();
            }
        } else {
            $message = "Please fix the following errors: <ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
            $messageType = "danger";
            
            // Preserve form data
            $subjectData = [
                'id' => isset($_POST['id']) ? $_POST['id'] : '',
                'subject_name' => $subjectName,
                'department_id' => $departmentId,
                'description' => $description,
                'status' => $status
            ];
        }
    } elseif (isset($_POST['delete_subject']) && isset($_POST['id'])) {
        $subjectId = intval($_POST['id']);
        
        // Check if subject has papers
        $sql = "SELECT COUNT(*) as count FROM papers WHERE subject_id = ? AND school_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $subjectId, $schoolId);
        $stmt->execute();
        $result = $stmt->get_result();
        $paperCount = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;
        $stmt->close();
        
        if ($paperCount > 0) {
            $message = "Cannot delete subject because it has $paperCount paper(s) associated with it";
            $messageType = "danger";
        } else {
            // Get subject name for activity log
            $sql = "SELECT subject_name FROM subjects WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $subjectId);
            $stmt->execute();
            $result = $stmt->get_result();
            $subjectName = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['subject_name'] : 'Unknown';
            $stmt->close();
            
            // Delete subject
            $sql = "DELETE FROM subjects WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $subjectId);
            
            if ($stmt->execute()) {
                $message = "Subject deleted successfully";
                $messageType = "success";
                
                // Log activity
                $activityType = "delete_subject";
                $activityDescription = "Deleted subject: $subjectName";
                logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
            } else {
                $message = "Error deleting subject: " . $conn->error;
                $messageType = "danger";
            }
            $stmt->close();
        }
    }
}

// Get message from URL if redirected
if (empty($message) && isset($_GET['message'])) {
    $message = $_GET['message'];
    $messageType = isset($_GET['type']) ? $_GET['type'] : 'info';
}

// Get departments and subjects
if ($conn) {
    // Get departments
    $sql = "SELECT id, department_name, status FROM departments WHERE status = 'active' ORDER BY department_name";
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
    }
    
    // Get subjects with department names and paper counts
    $sql = "SELECT s.id, s.subject_name, s.description, s.status, 
            d.department_name, d.id as department_id, 
            COUNT(p.id) as paper_count 
            FROM subjects s 
            JOIN departments d ON s.department_id = d.id 
            LEFT JOIN papers p ON s.id = p.subject_id AND p.school_id = ? 
            GROUP BY s.id 
            ORDER BY d.department_name, s.subject_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
    }
    $stmt->close();
    
    // Get subject data for edit
    if ($action === 'edit' && $subjectId > 0) {
        $sql = "SELECT id, subject_name, department_id, description, status 
                FROM subjects 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $subjectId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $subjectData = $result->fetch_assoc();
        } else {
            $message = "Subject not found";
            $messageType = "danger";
            $action = ''; // Reset action
        }
        $stmt->close();
    }
    
    closeDbConnection($conn);
}

// Set page title
$pageTitle = ($action === 'add' || $action === 'edit') ? ($action === 'add' ? "Add Subject" : "Edit Subject") : "Subjects";

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
                    <li class="breadcrumb-item"><a href="subjects.php">Subjects</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $action === 'add' ? 'Add Subject' : 'Edit Subject'; ?></li>
                    <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">Subjects</li>
                    <?php endif; ?>
                </ol>
            </nav>
        </div>
        <?php if ($action === ''): ?>
        <div class="col-md-6 text-end">
            <a href="subjects.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i> Add Subject
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
    <!-- Add/Edit Subject Form -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($departments)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i> You need to add departments before adding subjects.
                <a href="departments.php?action=add" class="alert-link">Add a department</a>
            </div>
            <?php else: ?>
            <form method="post" action="subjects.php" class="needs-validation" novalidate>
                <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo $subjectData['id']; ?>">
                <input type="hidden" name="original_name" value="<?php echo $subjectData['subject_name']; ?>">
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="subject_name" class="form-label">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="subject_name" name="subject_name" value="<?php echo htmlspecialchars($subjectData['subject_name']); ?>" required>
                        <div class="invalid-feedback">Please enter a subject name</div>
                    </div>
                    <div class="col-md-6">
                        <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                        <select class="form-select" id="department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $department): ?>
                            <option value="<?php echo $department['id']; ?>" <?php echo ($subjectData['department_id'] == $department['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($department['department_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a department</div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo ($subjectData['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($subjectData['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($subjectData['description']); ?></textarea>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="subjects.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Cancel
                    </a>
                    <button type="submit" name="<?php echo ($action === 'add') ? 'add_subject' : 'update_subject'; ?>" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> <?php echo ($action === 'add') ? 'Add Subject' : 'Update Subject'; ?>
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <!-- Subjects List -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($subjects)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="fas fa-book fa-4x text-muted"></i>
                </div>
                <h4>No Subjects Found</h4>
                <p class="text-muted">Start by adding subjects to organize past papers</p>
                <a href="subjects.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i> Add Subject
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Subject Name</th>
                            <th>Department</th>
                            <th>Description</th>
                            <th>Papers</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $currentDept = '';
                        foreach ($subjects as $subject): 
                            if ($currentDept != $subject['department_name']) {
                                $currentDept = $subject['department_name'];
                                echo '<tr class="table-light">';
                                echo '<td colspan="6"><strong>' . htmlspecialchars($currentDept) . '</strong></td>';
                                echo '</tr>';
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($subject['department_name']); ?></td>
                            <td>
                                <?php if (!empty($subject['description'])): ?>
                                <?php echo htmlspecialchars(substr($subject['description'], 0, 50)) . (strlen($subject['description']) > 50 ? '...' : ''); ?>
                                <?php else: ?>
                                <span class="text-muted">No description</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $subject['paper_count']; ?></span>
                            </td>
                            <td>
                                <?php if ($subject['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="subjects.php?action=edit&id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteSubjectModal<?php echo $subject['id']; ?>" title="Delete" <?php echo ($subject['paper_count'] > 0) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                
                                <!-- Delete Subject Modal -->
                                <div class="modal fade" id="deleteSubjectModal<?php echo $subject['id']; ?>" tabindex="-1" aria-labelledby="deleteSubjectModalLabel<?php echo $subject['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteSubjectModalLabel<?php echo $subject['id']; ?>">Delete Subject</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>?</p>
                                                <p class="text-danger">This action cannot be undone.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form method="post" action="subjects.php">
                                                    <input type="hidden" name="id" value="<?php echo $subject['id']; ?>">
                                                    <button type="submit" name="delete_subject" class="btn btn-danger">Delete Subject</button>
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