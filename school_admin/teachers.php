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
$teacherId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$messageType = '';
$teachers = [];
$departments = [];
$teacherData = [
    'id' => '',
    'name' => '',
    'email' => '',
    'department_id' => '',
    'phone' => '',
    'status' => 'active'
];

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_teacher']) || isset($_POST['update_teacher'])) {
        // Get form data
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $departmentId = isset($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
        $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : 'active';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        // Validate form data
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Name is required";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check if email already exists (for new teachers)
        if (isset($_POST['add_teacher']) || (isset($_POST['update_teacher']) && isset($_POST['id']) && $_POST['email'] != $_POST['original_email'])) {
            $checkEmailSql = "SELECT id FROM users WHERE email = ? AND school_id = ? AND id != ?";
            $checkEmailStmt = $conn->prepare($checkEmailSql);
            $teacherId = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $checkEmailStmt->bind_param('sii', $email, $schoolId, $teacherId);
            $checkEmailStmt->execute();
            $checkEmailResult = $checkEmailStmt->get_result();
            
            if ($checkEmailResult && $checkEmailResult->num_rows > 0) {
                $errors[] = "Email already exists";
            }
            $checkEmailStmt->close();
        }
        
        // If adding a new teacher, password is required
        if (isset($_POST['add_teacher']) && empty($password)) {
            $errors[] = "Password is required";
        }
        
        // If no errors, proceed with adding/updating teacher
        if (empty($errors)) {
            if (isset($_POST['add_teacher'])) {
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new teacher
                $sql = "INSERT INTO users (school_id, name, email, password, role, department_id, phone, status, created_at) 
                        VALUES (?, ?, ?, ?, 'teacher', ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('issssss', $schoolId, $name, $email, $hashedPassword, $departmentId, $phone, $status);
                
                if ($stmt->execute()) {
                    $message = "Teacher added successfully";
                    $messageType = "success";
                    
                    // Log activity
                    $activityType = "add_teacher";
                    $activityDescription = "Added new teacher: $name";
                    logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
                    
                    // Redirect to teachers list
                    header('Location: teachers.php?message=' . urlencode($message) . '&type=' . urlencode($messageType));
                    exit;
                } else {
                    $message = "Error adding teacher: " . $conn->error;
                    $messageType = "danger";
                }
                $stmt->close();
            } elseif (isset($_POST['update_teacher']) && isset($_POST['id'])) {
                $teacherId = intval($_POST['id']);
                
                // Update teacher
                if (!empty($password)) {
                    // Update with new password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET name = ?, email = ?, password = ?, department_id = ?, phone = ?, status = ? 
                            WHERE id = ? AND school_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('sssssii', $name, $email, $hashedPassword, $departmentId, $phone, $status, $teacherId, $schoolId);
                } else {
                    // Update without changing password
                    $sql = "UPDATE users SET name = ?, email = ?, department_id = ?, phone = ?, status = ? 
                            WHERE id = ? AND school_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ssssii', $name, $email, $departmentId, $phone, $status, $teacherId, $schoolId);
                }
                
                if ($stmt->execute()) {
                    $message = "Teacher updated successfully";
                    $messageType = "success";
                    
                    // Log activity
                    $activityType = "update_teacher";
                    $activityDescription = "Updated teacher: $name";
                    logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
                    
                    // Redirect to teachers list
                    header('Location: teachers.php?message=' . urlencode($message) . '&type=' . urlencode($messageType));
                    exit;
                } else {
                    $message = "Error updating teacher: " . $conn->error;
                    $messageType = "danger";
                }
                $stmt->close();
            }
        } else {
            $message = "Please fix the following errors: <ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
            $messageType = "danger";
            
            // Preserve form data
            $teacherData = [
                'id' => isset($_POST['id']) ? $_POST['id'] : '',
                'name' => $name,
                'email' => $email,
                'department_id' => $departmentId,
                'phone' => $phone,
                'status' => $status
            ];
        }
    } elseif (isset($_POST['reset_password']) && isset($_POST['id'])) {
        $teacherId = intval($_POST['id']);
        $newPassword = $_POST['new_password'];
        
        if (empty($newPassword)) {
            $message = "Password cannot be empty";
            $messageType = "danger";
        } else {
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $sql = "UPDATE users SET password = ? WHERE id = ? AND school_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sii', $hashedPassword, $teacherId, $schoolId);
            
            if ($stmt->execute()) {
                $message = "Password reset successfully";
                $messageType = "success";
                
                // Log activity
                $activityType = "reset_password";
                $activityDescription = "Reset password for teacher ID: $teacherId";
                logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
            } else {
                $message = "Error resetting password: " . $conn->error;
                $messageType = "danger";
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete_teacher']) && isset($_POST['id'])) {
        $teacherId = intval($_POST['id']);
        
        // Get teacher name for activity log
        $sql = "SELECT name FROM users WHERE id = ? AND school_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $teacherId, $schoolId);
        $stmt->execute();
        $result = $stmt->get_result();
        $teacherName = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['name'] : "Unknown";
        $stmt->close();
        
        // Delete teacher
        $sql = "DELETE FROM users WHERE id = ? AND school_id = ? AND role = 'teacher'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $teacherId, $schoolId);
        
        if ($stmt->execute()) {
            $message = "Teacher deleted successfully";
            $messageType = "success";
            
            // Log activity
            $activityType = "delete_teacher";
            $activityDescription = "Deleted teacher: $teacherName";
            logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
        } else {
            $message = "Error deleting teacher: " . $conn->error;
            $messageType = "danger";
        }
        $stmt->close();
    }
}

// Get message from URL if redirected
if (empty($message) && isset($_GET['message'])) {
    $message = $_GET['message'];
    $messageType = isset($_GET['type']) ? $_GET['type'] : 'info';
}

// Get departments for dropdown
if ($conn) {
    $sql = "SELECT id, department_name FROM departments";
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
    }
    
    // Get teachers
    $sql = "SELECT u.id, u.name, u.email, u.department_id, u.phone, u.status, u.created_at, 
            d.department_name, COUNT(p.id) as paper_count 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.id 
            LEFT JOIN papers p ON u.id = p.uploaded_by 
            WHERE u.school_id = ? AND u.role = 'teacher' 
            GROUP BY u.id 
            ORDER BY u.name ASC";
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
    
    // Get teacher data for edit
    if ($action === 'edit' && $teacherId > 0) {
        $sql = "SELECT id, name, email, department_id, phone, status 
                FROM users 
                WHERE id = ? AND school_id = ? AND role = 'teacher'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $teacherId, $schoolId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $teacherData = $result->fetch_assoc();
        } else {
            $message = "Teacher not found";
            $messageType = "danger";
            $action = ''; // Reset action
        }
        $stmt->close();
    }
    
    closeDbConnection($conn);
}

// Set page title
$pageTitle = ($action === 'add' || $action === 'edit') ? ($action === 'add' ? "Add Teacher" : "Edit Teacher") : "Teachers";

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
                    <li class="breadcrumb-item"><a href="teachers.php">Teachers</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $action === 'add' ? 'Add Teacher' : 'Edit Teacher'; ?></li>
                    <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">Teachers</li>
                    <?php endif; ?>
                </ol>
            </nav>
        </div>
        <?php if ($action === ''): ?>
        <div class="col-md-6 text-end">
            <a href="teachers.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i> Add Teacher
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
    <!-- Add/Edit Teacher Form -->
    <div class="card">
        <div class="card-body">
            <form method="post" action="teachers.php" class="needs-validation" novalidate>
                <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo $teacherData['id']; ?>">
                <input type="hidden" name="original_email" value="<?php echo $teacherData['email']; ?>">
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($teacherData['name']); ?>" required>
                        <div class="invalid-feedback">Please enter a name</div>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($teacherData['email']); ?>" required>
                        <div class="invalid-feedback">Please enter a valid email</div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="department_id" class="form-label">Department</label>
                        <select class="form-select" id="department_id" name="department_id">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $department): ?>
                            <option value="<?php echo $department['id']; ?>" <?php echo ($teacherData['department_id'] == $department['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($department['department_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($teacherData['phone']); ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="password" class="form-label"><?php echo ($action === 'add') ? 'Password <span class="text-danger">*</span>' : 'Password (leave blank to keep current)'; ?></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" <?php echo ($action === 'add') ? 'required' : ''; ?>>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Please enter a password</div>
                        <div class="form-text">Password should be at least 8 characters</div>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo ($teacherData['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($teacherData['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="teachers.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Cancel
                    </a>
                    <button type="submit" name="<?php echo ($action === 'add') ? 'add_teacher' : 'update_teacher'; ?>" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> <?php echo ($action === 'add') ? 'Add Teacher' : 'Update Teacher'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <!-- Teachers List -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($teachers)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="fas fa-chalkboard-teacher fa-4x text-muted"></i>
                </div>
                <h4>No Teachers Found</h4>
                <p class="text-muted">Start by adding a teacher to your school</p>
                <a href="teachers.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i> Add Teacher
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Papers</th>
                            <th>Status</th>
                            <th>Added On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['department_name'] ?? 'Not Assigned'); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo $teacher['paper_count']; ?></span>
                            </td>
                            <td>
                                <?php if ($teacher['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="teachers.php?action=edit&id=<?php echo $teacher['id']; ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetPasswordModal<?php echo $teacher['id']; ?>" title="Reset Password">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteTeacherModal<?php echo $teacher['id']; ?>" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                
                                <!-- Reset Password Modal -->
                                <div class="modal fade" id="resetPasswordModal<?php echo $teacher['id']; ?>" tabindex="-1" aria-labelledby="resetPasswordModalLabel<?php echo $teacher['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="resetPasswordModalLabel<?php echo $teacher['id']; ?>">Reset Password</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="post" action="teachers.php">
                                                <div class="modal-body">
                                                    <input type="hidden" name="id" value="<?php echo $teacher['id']; ?>">
                                                    <p>Reset password for <strong><?php echo htmlspecialchars($teacher['name']); ?></strong></p>
                                                    <div class="mb-3">
                                                        <label for="new_password<?php echo $teacher['id']; ?>" class="form-label">New Password</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" id="new_password<?php echo $teacher['id']; ?>" name="new_password" required>
                                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password<?php echo $teacher['id']; ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="reset_password" class="btn btn-warning">Reset Password</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Delete Teacher Modal -->
                                <div class="modal fade" id="deleteTeacherModal<?php echo $teacher['id']; ?>" tabindex="-1" aria-labelledby="deleteTeacherModalLabel<?php echo $teacher['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteTeacherModalLabel<?php echo $teacher['id']; ?>">Delete Teacher</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($teacher['name']); ?></strong>?</p>
                                                <p class="text-danger">This action cannot be undone. All associated data will be permanently deleted.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form method="post" action="teachers.php">
                                                    <input type="hidden" name="id" value="<?php echo $teacher['id']; ?>">
                                                    <button type="submit" name="delete_teacher" class="btn btn-danger">Delete Teacher</button>
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

<script>
    // Toggle password visibility
    document.addEventListener('DOMContentLoaded', function() {
        const togglePassword = document.getElementById('togglePassword');
        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const password = document.getElementById('password');
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }
        
        // Toggle password visibility in modals
        document.querySelectorAll('.toggle-password').forEach(function(button) {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordField = document.getElementById(targetId);
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        });
    });
</script>

<?php include_once 'includes/footer.php'; ?>