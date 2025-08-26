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
$departmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$messageType = '';
$departments = [];
$departmentData = [
    'id' => '',
    'department_name' => '',
    'description' => '',
    'status' => 'active'
];

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_department']) || isset($_POST['update_department'])) {
        // Get form data
        $departmentName = sanitizeInput($_POST['department_name']);
        $description = isset($_POST['description']) ? sanitizeInput($_POST['description']) : '';
        $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : 'active';
        
        // Validate form data
        $errors = [];
        
        if (empty($departmentName)) {
            $errors[] = "Department name is required";
        }
        
        // Check if department name already exists
        if (isset($_POST['add_department']) || (isset($_POST['update_department']) && isset($_POST['original_name']) && $_POST['original_name'] != $departmentName)) {
            $sql = "SELECT id FROM departments WHERE department_name = ?";
            if (isset($_POST['update_department']) && isset($_POST['id'])) {
                $sql .= " AND id != ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('si', $departmentName, $_POST['id']);
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('s', $departmentName);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $errors[] = "Department name already exists";
            }
            $stmt->close();
        }
        
        // If no errors, proceed with adding/updating department
        if (empty($errors)) {
            if (isset($_POST['add_department'])) {
                // Insert new department
                $sql = "INSERT INTO departments (department_name, description, status) 
                        VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sss', $departmentName, $description, $status);
                
                if ($stmt->execute()) {
                    $message = "Department added successfully";
                    $messageType = "success";
                    
                    // Log activity
                    $activityType = "add_department";
                    $activityDescription = "Added new department: $departmentName";
                    logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
                    
                    // Redirect to departments list
                    header('Location: departments.php?message=' . urlencode($message) . '&type=' . urlencode($messageType));
                    exit;
                } else {
                    $message = "Error adding department: " . $conn->error;
                    $messageType = "danger";
                }
                $stmt->close();
            } elseif (isset($_POST['update_department']) && isset($_POST['id'])) {
                $departmentId = intval($_POST['id']);
                
                // Update department
                $sql = "UPDATE departments SET department_name = ?, description = ?, status = ? 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sssi', $departmentName, $description, $status, $departmentId);
                
                if ($stmt->execute()) {
                    $message = "Department updated successfully";
                    $messageType = "success";
                    
                    // Log activity
                    $activityType = "update_department";
                    $activityDescription = "Updated department: $departmentName";
                    logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
                    
                    // Redirect to departments list
                    header('Location: departments.php?message=' . urlencode($message) . '&type=' . urlencode($messageType));
                    exit;
                } else {
                    $message = "Error updating department: " . $conn->error;
                    $messageType = "danger";
                }
                $stmt->close();
            }
        } else {
            $message = "Please fix the following errors: <ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
            $messageType = "danger";
            
            // Preserve form data
            $departmentData = [
                'id' => isset($_POST['id']) ? $_POST['id'] : '',
                'department_name' => $departmentName,
                'description' => $description,
                'status' => $status
            ];
        }
    } elseif (isset($_POST['delete_department']) && isset($_POST['id'])) {
        $departmentId = intval($_POST['id']);
        
        // Check if department has subjects
        $sql = "SELECT COUNT(*) as count FROM subjects WHERE department_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $departmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $subjectCount = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;
        $stmt->close();
        
        if ($subjectCount > 0) {
            $message = "Cannot delete department because it has $subjectCount subject(s) associated with it";
            $messageType = "danger";
        } else {
            // Get department name for activity log
            $sql = "SELECT department_name FROM departments WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $departmentId);
            $stmt->execute();
            $result = $stmt->get_result();
            $departmentName = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['department_name'] : 'Unknown';
            $stmt->close();
            
            // Delete department
            $sql = "DELETE FROM departments WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $departmentId);
            
            if ($stmt->execute()) {
                $message = "Department deleted successfully";
                $messageType = "success";
                
                // Log activity
                $activityType = "delete_department";
                $activityDescription = "Deleted department: $departmentName";
                logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
            } else {
                $message = "Error deleting department: " . $conn->error;
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

// Get departments
if ($conn) {
    // Get departments with subject counts
    $sql = "SELECT d.id, d.department_name, d.description, d.status, 
            COUNT(s.id) as subject_count 
            FROM departments d 
            LEFT JOIN subjects s ON d.id = s.department_id 
            GROUP BY d.id 
            ORDER BY d.department_name";
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
    }
    
    // Get department data for edit
    if ($action === 'edit' && $departmentId > 0) {
        $sql = "SELECT id, department_name, description, status 
                FROM departments 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $departmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $departmentData = $result->fetch_assoc();
        } else {
            $message = "Department not found";
            $messageType = "danger";
            $action = ''; // Reset action
        }
        $stmt->close();
    }
    
    closeDbConnection($conn);
}

// Set page title
$pageTitle = ($action === 'add' || $action === 'edit') ? ($action === 'add' ? "Add Department" : "Edit Department") : "Departments";

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
                    <li class="breadcrumb-item"><a href="departments.php">Departments</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $action === 'add' ? 'Add Department' : 'Edit Department'; ?></li>
                    <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">Departments</li>
                    <?php endif; ?>
                </ol>
            </nav>
        </div>
        <?php if ($action === ''): ?>
        <div class="col-md-6 text-end">
            <a href="departments.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i> Add Department
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
    <!-- Add/Edit Department Form -->
    <div class="card">
        <div class="card-body">
            <form method="post" action="departments.php" class="needs-validation" novalidate>
                <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo $departmentData['id']; ?>">
                <input type="hidden" name="original_name" value="<?php echo $departmentData['department_name']; ?>">
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="department_name" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="department_name" name="department_name" value="<?php echo htmlspecialchars($departmentData['department_name']); ?>" required>
                        <div class="invalid-feedback">Please enter a department name</div>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo ($departmentData['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($departmentData['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($departmentData['description']); ?></textarea>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="departments.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Cancel
                    </a>
                    <button type="submit" name="<?php echo ($action === 'add') ? 'add_department' : 'update_department'; ?>" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> <?php echo ($action === 'add') ? 'Add Department' : 'Update Department'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <!-- Departments List -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($departments)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="fas fa-building fa-4x text-muted"></i>
                </div>
                <h4>No Departments Found</h4>
                <p class="text-muted">Start by adding departments to organize subjects</p>
                <a href="departments.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i> Add Department
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Department Name</th>
                            <th>Description</th>
                            <th>Subjects</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $department): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($department['department_name']); ?></td>
                            <td>
                                <?php if (!empty($department['description'])): ?>
                                <?php echo htmlspecialchars(substr($department['description'], 0, 50)) . (strlen($department['description']) > 50 ? '...' : ''); ?>
                                <?php else: ?>
                                <span class="text-muted">No description</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $department['subject_count']; ?></span>
                            </td>
                            <td>
                                <?php if ($department['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="departments.php?action=edit&id=<?php echo $department['id']; ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteDepartmentModal<?php echo $department['id']; ?>" title="Delete" <?php echo ($department['subject_count'] > 0) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                
                                <!-- Delete Department Modal -->
                                <div class="modal fade" id="deleteDepartmentModal<?php echo $department['id']; ?>" tabindex="-1" aria-labelledby="deleteDepartmentModalLabel<?php echo $department['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteDepartmentModalLabel<?php echo $department['id']; ?>">Delete Department</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($department['department_name']); ?></strong>?</p>
                                                <p class="text-danger">This action cannot be undone.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form method="post" action="departments.php">
                                                    <input type="hidden" name="id" value="<?php echo $department['id']; ?>">
                                                    <button type="submit" name="delete_department" class="btn btn-danger">Delete Department</button>
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