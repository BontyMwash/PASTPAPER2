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
$departments = array();
$editDepartment = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add/Edit department
    if (isset($_POST['action']) && ($_POST['action'] === 'add_department' || $_POST['action'] === 'edit_department')) {
        $name = sanitizeInput($conn, $_POST['name']);
        
        // Validate inputs
        if (empty($name)) {
            $message = 'Department name is required.';
            $messageType = 'danger';
        } else {
            // Check if department already exists
            $nameExists = false;
            if ($_POST['action'] === 'add_department') {
                $checkSql = "SELECT id FROM departments WHERE name = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param('s', $name);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $nameExists = ($checkResult->num_rows > 0);
                $checkStmt->close();
            }
            
            if ($_POST['action'] === 'edit_department' && isset($_POST['department_id'])) {
                $departmentId = intval($_POST['department_id']);
                $checkSql = "SELECT id FROM departments WHERE name = ? AND id != ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param('si', $name, $departmentId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $nameExists = ($checkResult->num_rows > 0);
                $checkStmt->close();
            }
            
            if ($nameExists) {
                $message = 'Department name already exists.';
                $messageType = 'danger';
            } else {
                // Process based on action
                if ($_POST['action'] === 'add_department') {
                    $sql = "INSERT INTO departments (name) VALUES (?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('s', $name);
                    
                    if ($stmt->execute()) {
                        $message = 'Department added successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Error adding department: ' . $conn->error;
                        $messageType = 'danger';
                    }
                    $stmt->close();
                } else if ($_POST['action'] === 'edit_department' && isset($_POST['department_id'])) {
                    $departmentId = intval($_POST['department_id']);
                    
                    $sql = "UPDATE departments SET name = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('si', $name, $departmentId);
                    
                    if ($stmt->execute()) {
                        $message = 'Department updated successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Error updating department: ' . $conn->error;
                        $messageType = 'danger';
                    }
                    $stmt->close();
                }
            }
        }
    }
    
    // Delete department
    if (isset($_POST['action']) && $_POST['action'] === 'delete_department' && isset($_POST['department_id'])) {
        $departmentId = intval($_POST['department_id']);
        
        // Check if department has subjects
        $checkSql = "SELECT COUNT(*) as count FROM subjects WHERE department_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('i', $departmentId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $row = $checkResult->fetch_assoc();
        $hasSubjects = ($row['count'] > 0);
        $checkStmt->close();
        
        if ($hasSubjects) {
            $message = 'Cannot delete department that has subjects. Remove all subjects first.';
            $messageType = 'danger';
        } else {
            $sql = "DELETE FROM departments WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $departmentId);
            
            if ($stmt->execute()) {
                $message = 'Department deleted successfully.';
                $messageType = 'success';
            } else {
                $message = 'Error deleting department: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
    
    // Add/Edit subject
    if (isset($_POST['action']) && ($_POST['action'] === 'add_subject' || $_POST['action'] === 'edit_subject')) {
        $name = sanitizeInput($conn, $_POST['subject_name']);
        $departmentId = intval($_POST['department_id']);
        
        // Validate inputs
        if (empty($name) || $departmentId <= 0) {
            $message = 'Subject name and department are required.';
            $messageType = 'danger';
        } else {
            // Check if subject already exists in this department
            $nameExists = false;
            if ($_POST['action'] === 'add_subject') {
                $checkSql = "SELECT id FROM subjects WHERE name = ? AND department_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param('si', $name, $departmentId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $nameExists = ($checkResult->num_rows > 0);
                $checkStmt->close();
            }
            
            if ($_POST['action'] === 'edit_subject' && isset($_POST['subject_id'])) {
                $subjectId = intval($_POST['subject_id']);
                $checkSql = "SELECT id FROM subjects WHERE name = ? AND department_id = ? AND id != ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param('sii', $name, $departmentId, $subjectId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $nameExists = ($checkResult->num_rows > 0);
                $checkStmt->close();
            }
            
            if ($nameExists) {
                $message = 'Subject name already exists in this department.';
                $messageType = 'danger';
            } else {
                // Process based on action
                if ($_POST['action'] === 'add_subject') {
                    $sql = "INSERT INTO subjects (name, department_id) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('si', $name, $departmentId);
                    
                    if ($stmt->execute()) {
                        $message = 'Subject added successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Error adding subject: ' . $conn->error;
                        $messageType = 'danger';
                    }
                    $stmt->close();
                } else if ($_POST['action'] === 'edit_subject' && isset($_POST['subject_id'])) {
                    $subjectId = intval($_POST['subject_id']);
                    
                    $sql = "UPDATE subjects SET name = ?, department_id = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('sii', $name, $departmentId, $subjectId);
                    
                    if ($stmt->execute()) {
                        $message = 'Subject updated successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Error updating subject: ' . $conn->error;
                        $messageType = 'danger';
                    }
                    $stmt->close();
                }
            }
        }
    }
    
    // Delete subject
    if (isset($_POST['action']) && $_POST['action'] === 'delete_subject' && isset($_POST['subject_id'])) {
        $subjectId = intval($_POST['subject_id']);
        
        // Check if subject has papers
        $checkSql = "SELECT COUNT(*) as count FROM papers WHERE subject_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('i', $subjectId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $row = $checkResult->fetch_assoc();
        $hasPapers = ($row['count'] > 0);
        $checkStmt->close();
        
        if ($hasPapers) {
            $message = 'Cannot delete subject that has papers. Remove all papers first.';
            $messageType = 'danger';
        } else {
            $sql = "DELETE FROM subjects WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $subjectId);
            
            if ($stmt->execute()) {
                $message = 'Subject deleted successfully.';
                $messageType = 'success';
            } else {
                $message = 'Error deleting subject: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
}

// Handle edit request for department
if (isset($_GET['action']) && $_GET['action'] === 'edit_department' && isset($_GET['id'])) {
    $departmentId = intval($_GET['id']);
    $sql = "SELECT id, name FROM departments WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $editDepartment = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get all departments with their subjects
$sql = "SELECT d.id, d.name, 
               (SELECT COUNT(*) FROM subjects WHERE department_id = d.id) as subject_count 
        FROM departments d 
        ORDER BY d.name";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departmentId = $row['id'];
        $department = $row;
        $department['subjects'] = array();
        
        // Get subjects for this department
        $subjectSql = "SELECT s.id, s.name, 
                        (SELECT COUNT(*) FROM papers WHERE subject_id = s.id) as paper_count 
                 FROM subjects s 
                 WHERE s.department_id = ? 
                 ORDER BY s.name";
        $subjectStmt = $conn->prepare($subjectSql);
        $subjectStmt->bind_param('i', $departmentId);
        $subjectStmt->execute();
        $subjectResult = $subjectStmt->get_result();
        
        if ($subjectResult) {
            while ($subjectRow = $subjectResult->fetch_assoc()) {
                $department['subjects'][] = $subjectRow;
            }
        }
        $subjectStmt->close();
        
        $departments[] = $department;
    }
}

closeDbConnection($conn);

// Page title
$pageTitle = "Manage Departments";

// Include admin-specific header
include_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-building"></i> Manage Departments & Subjects</h2>
            <p>Create, edit, and organize academic departments and subjects</p>
            
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
    
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><?php echo $editDepartment ? 'Edit Department' : 'Add New Department'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" action="departments.php">
                        <input type="hidden" name="action" value="<?php echo $editDepartment ? 'edit_department' : 'add_department'; ?>">
                        <?php if ($editDepartment): ?>
                        <input type="hidden" name="department_id" value="<?php echo $editDepartment['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="name">Department Name</label>
                            <input type="text" class="form-control" id="name" name="name" required 
                                value="<?php echo $editDepartment ? htmlspecialchars($editDepartment['name']) : ''; ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $editDepartment ? 'Update Department' : 'Add Department'; ?>
                        </button>
                        
                        <?php if ($editDepartment): ?>
                        <a href="departments.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5>Add New Subject</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="departments.php">
                        <input type="hidden" name="action" value="add_subject">
                        
                        <div class="form-group">
                            <label for="department_id">Department</label>
                            <select class="form-control" id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                <option value="<?php echo $department['id']; ?>">
                                    <?php echo htmlspecialchars($department['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject_name">Subject Name</label>
                            <input type="text" class="form-control" id="subject_name" name="subject_name" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Subject
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="accordion" id="departmentsAccordion">
                <?php if (count($departments) > 0): ?>
                    <?php foreach ($departments as $index => $department): ?>
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center" id="heading<?php echo $department['id']; ?>">
                            <h5 class="mb-0">
                                <button class="btn btn-link" type="button" data-toggle="collapse" 
                                        data-target="#collapse<?php echo $department['id']; ?>" 
                                        aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" 
                                        aria-controls="collapse<?php echo $department['id']; ?>">
                                    <?php echo htmlspecialchars($department['name']); ?>
                                    <span class="badge badge-primary ml-2"><?php echo $department['subject_count']; ?> Subjects</span>
                                </button>
                            </h5>
                            <div>
                                <a href="departments.php?action=edit_department&id=<?php echo $department['id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <?php if ($department['subject_count'] == 0): ?>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        data-toggle="modal" data-target="#deleteDeptModal<?php echo $department['id']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                                
                                <!-- Delete Department Modal -->
                                <div class="modal fade" id="deleteDeptModal<?php echo $department['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="deleteDeptModalLabel" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteDeptModalLabel">Confirm Delete</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                Are you sure you want to delete the department <strong><?php echo htmlspecialchars($department['name']); ?></strong>?
                                                This action cannot be undone.
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                <form method="post" action="departments.php">
                                                    <input type="hidden" name="action" value="delete_department">
                                                    <input type="hidden" name="department_id" value="<?php echo $department['id']; ?>">
                                                    <button type="submit" class="btn btn-danger">Delete Department</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div id="collapse<?php echo $department['id']; ?>" class="collapse <?php echo $index === 0 ? 'show' : ''; ?>" 
                             aria-labelledby="heading<?php echo $department['id']; ?>" 
                             data-parent="#departmentsAccordion">
                            <div class="card-body">
                                <?php if (count($department['subjects']) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Subject Name</th>
                                                <th>Papers</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($department['subjects'] as $subject): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                                <td><?php echo $subject['paper_count']; ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            data-toggle="modal" data-target="#editSubjectModal<?php echo $subject['id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    
                                                    <?php if ($subject['paper_count'] == 0): ?>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            data-toggle="modal" data-target="#deleteSubjectModal<?php echo $subject['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Edit Subject Modal -->
                                                    <div class="modal fade" id="editSubjectModal<?php echo $subject['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editSubjectModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog" role="document">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="editSubjectModalLabel">Edit Subject</h5>
                                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <form method="post" action="departments.php" id="editSubjectForm<?php echo $subject['id']; ?>">
                                                                        <input type="hidden" name="action" value="edit_subject">
                                                                        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                                        
                                                                        <div class="form-group">
                                                                            <label for="edit_department_id<?php echo $subject['id']; ?>">Department</label>
                                                                            <select class="form-control" id="edit_department_id<?php echo $subject['id']; ?>" name="department_id" required>
                                                                                <?php foreach ($departments as $dept): ?>
                                                                                <option value="<?php echo $dept['id']; ?>" <?php echo ($dept['id'] == $department['id']) ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($dept['name']); ?>
                                                                                </option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="form-group">
                                                                            <label for="edit_subject_name<?php echo $subject['id']; ?>">Subject Name</label>
                                                                            <input type="text" class="form-control" id="edit_subject_name<?php echo $subject['id']; ?>" 
                                                                                   name="subject_name" required value="<?php echo htmlspecialchars($subject['name']); ?>">
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                                    <button type="submit" form="editSubjectForm<?php echo $subject['id']; ?>" class="btn btn-primary">Update Subject</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Delete Subject Modal -->
                                                    <?php if ($subject['paper_count'] == 0): ?>
                                                    <div class="modal fade" id="deleteSubjectModal<?php echo $subject['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="deleteSubjectModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog" role="document">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deleteSubjectModalLabel">Confirm Delete</h5>
                                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    Are you sure you want to delete the subject <strong><?php echo htmlspecialchars($subject['name']); ?></strong>?
                                                                    This action cannot be undone.
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                                    <form method="post" action="departments.php">
                                                                        <input type="hidden" name="action" value="delete_subject">
                                                                        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                                        <button type="submit" class="btn btn-danger">Delete Subject</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No subjects found in this department. Add a subject using the form.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        No departments found. Add a department using the form.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include admin-specific footer
include_once 'includes/footer.php';
?>