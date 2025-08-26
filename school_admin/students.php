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
$studentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$messageType = '';
$students = [];
$studentData = [
    'id' => '',
    'name' => '',
    'email' => '',
    'student_id' => '',
    'grade' => '',
    'status' => 'active'
];

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_student']) || isset($_POST['update_student'])) {
        // Get form data
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $studentIdNumber = sanitizeInput($_POST['student_id']);
        $grade = sanitizeInput($_POST['grade']);
        $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : 'active';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
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
        
        if (empty($studentIdNumber)) {
            $errors[] = "Student ID is required";
        }
        
        // Check if email already exists (for new students or when changing email)
        if (isset($_POST['add_student']) || (isset($_POST['update_student']) && isset($_POST['original_email']) && $_POST['original_email'] != $email)) {
            $sql = "SELECT id FROM users WHERE email = ? AND school_id = ? AND role = 'student'";
            if (isset($_POST['update_student']) && isset($_POST['id'])) {
                $sql .= " AND id != ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sii', $email, $schoolId, $_POST['id']);
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('si', $email, $schoolId);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $errors[] = "Email already exists";
            }
            $stmt->close();
        }
        
        // Check if student ID already exists (for new students or when changing student ID)
        if (isset($_POST['add_student']) || (isset($_POST['update_student']) && isset($_POST['original_student_id']) && $_POST['original_student_id'] != $studentIdNumber)) {
            $sql = "SELECT id FROM users WHERE student_id = ? AND school_id = ? AND role = 'student'";
            if (isset($_POST['update_student']) && isset($_POST['id'])) {
                $sql .= " AND id != ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sii', $studentIdNumber, $schoolId, $_POST['id']);
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('si', $studentIdNumber, $schoolId);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $errors[] = "Student ID already exists";
            }
            $stmt->close();
        }
        
        // Validate password for new students
        if (isset($_POST['add_student'])) {
            if (empty($password)) {
                $errors[] = "Password is required";
            } elseif (strlen($password) < 6) {
                $errors[] = "Password must be at least 6 characters";
            } elseif ($password !== $confirmPassword) {
                $errors[] = "Passwords do not match";
            }
        } elseif (isset($_POST['update_student']) && !empty($password)) {
            // Validate password only if provided for existing students
            if (strlen($password) < 6) {
                $errors[] = "Password must be at least 6 characters";
            } elseif ($password !== $confirmPassword) {
                $errors[] = "Passwords do not match";
            }
        }
        
        // If no errors, proceed with adding/updating student
        if (empty($errors)) {
            if (isset($_POST['add_student'])) {
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new student
                $sql = "INSERT INTO users (school_id, name, email, password, student_id, grade, role, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 'student', ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('issssss', $schoolId, $name, $email, $hashedPassword, $studentIdNumber, $grade, $status);
                
                if ($stmt->execute()) {
                    $message = "Student added successfully";
                    $messageType = "success";
                    
                    // Log activity
                    $activityType = "add_student";
                    $activityDescription = "Added new student: $name";
                    logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
                    
                    // Redirect to students list
                    header('Location: students.php?message=' . urlencode($message) . '&type=' . urlencode($messageType));
                    exit;
                } else {
                    $message = "Error adding student: " . $conn->error;
                    $messageType = "danger";
                }
                $stmt->close();
            } elseif (isset($_POST['update_student']) && isset($_POST['id'])) {
                $studentId = intval($_POST['id']);
                
                // Update student
                if (!empty($password)) {
                    // Update with new password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET name = ?, email = ?, password = ?, student_id = ?, grade = ?, status = ? 
                            WHERE id = ? AND school_id = ? AND role = 'student'";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('sssssiii', $name, $email, $hashedPassword, $studentIdNumber, $grade, $status, $studentId, $schoolId);
                } else {
                    // Update without changing password
                    $sql = "UPDATE users SET name = ?, email = ?, student_id = ?, grade = ?, status = ? 
                            WHERE id = ? AND school_id = ? AND role = 'student'";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ssssiii', $name, $email, $studentIdNumber, $grade, $status, $studentId, $schoolId);
                }
                
                if ($stmt->execute()) {
                    $message = "Student updated successfully";
                    $messageType = "success";
                    
                    // Log activity
                    $activityType = "update_student";
                    $activityDescription = "Updated student: $name";
                    logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
                    
                    // Redirect to students list
                    header('Location: students.php?message=' . urlencode($message) . '&type=' . urlencode($messageType));
                    exit;
                } else {
                    $message = "Error updating student: " . $conn->error;
                    $messageType = "danger";
                }
                $stmt->close();
            }
        } else {
            $message = "Please fix the following errors: <ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
            $messageType = "danger";
            
            // Preserve form data
            $studentData = [
                'id' => isset($_POST['id']) ? $_POST['id'] : '',
                'name' => $name,
                'email' => $email,
                'student_id' => $studentIdNumber,
                'grade' => $grade,
                'status' => $status
            ];
        }
    } elseif (isset($_POST['delete_student']) && isset($_POST['id'])) {
        $studentId = intval($_POST['id']);
        
        // Get student name for activity log
        $sql = "SELECT name FROM users WHERE id = ? AND school_id = ? AND role = 'student'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $studentId, $schoolId);
        $stmt->execute();
        $result = $stmt->get_result();
        $studentName = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['name'] : 'Unknown';
        $stmt->close();
        
        // Delete student
        $sql = "DELETE FROM users WHERE id = ? AND school_id = ? AND role = 'student'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $studentId, $schoolId);
        
        if ($stmt->execute()) {
            $message = "Student deleted successfully";
            $messageType = "success";
            
            // Log activity
            $activityType = "delete_student";
            $activityDescription = "Deleted student: $studentName";
            logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
        } else {
            $message = "Error deleting student: " . $conn->error;
            $messageType = "danger";
        }
        $stmt->close();
    } elseif (isset($_POST['reset_password']) && isset($_POST['id'])) {
        $studentId = intval($_POST['id']);
        $newPassword = sanitizeInput($_POST['new_password']);
        $confirmPassword = sanitizeInput($_POST['confirm_password']);
        
        // Validate passwords
        $errors = [];
        
        if (empty($newPassword)) {
            $errors[] = "Password is required";
        } elseif (strlen($newPassword) < 6) {
            $errors[] = "Password must be at least 6 characters";
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = "Passwords do not match";
        }
        
        if (empty($errors)) {
            // Get student name for activity log
            $sql = "SELECT name FROM users WHERE id = ? AND school_id = ? AND role = 'student'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $studentId, $schoolId);
            $stmt->execute();
            $result = $stmt->get_result();
            $studentName = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['name'] : 'Unknown';
            $stmt->close();
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ? WHERE id = ? AND school_id = ? AND role = 'student'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sii', $hashedPassword, $studentId, $schoolId);
            
            if ($stmt->execute()) {
                $message = "Password reset successfully";
                $messageType = "success";
                
                // Log activity
                $activityType = "reset_password";
                $activityDescription = "Reset password for student: $studentName";
                logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
            } else {
                $message = "Error resetting password: " . $conn->error;
                $messageType = "danger";
            }
            $stmt->close();
        } else {
            $message = "Please fix the following errors: <ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
            $messageType = "danger";
        }
    }
}

// Get message from URL if redirected
if (empty($message) && isset($_GET['message'])) {
    $message = $_GET['message'];
    $messageType = isset($_GET['type']) ? $_GET['type'] : 'info';
}

// Get students
if ($conn) {
    // Get students
    $sql = "SELECT id, name, email, student_id, grade, status, created_at, last_login 
            FROM users 
            WHERE school_id = ? AND role = 'student' 
            ORDER BY name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }
    $stmt->close();
    
    // Get student data for edit
    if ($action === 'edit' && $studentId > 0) {
        $sql = "SELECT id, name, email, student_id, grade, status 
                FROM users 
                WHERE id = ? AND school_id = ? AND role = 'student'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $studentId, $schoolId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $studentData = $result->fetch_assoc();
        } else {
            $message = "Student not found";
            $messageType = "danger";
            $action = ''; // Reset action
        }
        $stmt->close();
    }
    
    closeDbConnection($conn);
}

// Set page title
$pageTitle = ($action === 'add' || $action === 'edit') ? ($action === 'add' ? "Add Student" : "Edit Student") : "Students";

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
                    <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $action === 'add' ? 'Add Student' : 'Edit Student'; ?></li>
                    <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">Students</li>
                    <?php endif; ?>
                </ol>
            </nav>
        </div>
        <?php if ($action === ''): ?>
        <div class="col-md-6 text-end">
            <a href="students.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i> Add Student
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
    <!-- Add/Edit Student Form -->
    <div class="card">
        <div class="card-body">
            <form method="post" action="students.php" class="needs-validation" novalidate>
                <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo $studentData['id']; ?>">
                <input type="hidden" name="original_email" value="<?php echo $studentData['email']; ?>">
                <input type="hidden" name="original_student_id" value="<?php echo $studentData['student_id']; ?>">
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($studentData['name']); ?>" required>
                        <div class="invalid-feedback">Please enter a name</div>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($studentData['email']); ?>" required>
                        <div class="invalid-feedback">Please enter a valid email</div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="student_id" class="form-label">Student ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="student_id" name="student_id" value="<?php echo htmlspecialchars($studentData['student_id']); ?>" required>
                        <div class="invalid-feedback">Please enter a student ID</div>
                    </div>
                    <div class="col-md-6">
                        <label for="grade" class="form-label">Grade/Class</label>
                        <input type="text" class="form-control" id="grade" name="grade" value="<?php echo htmlspecialchars($studentData['grade']); ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo ($studentData['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($studentData['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="password" class="form-label"><?php echo ($action === 'add') ? 'Password <span class="text-danger">*</span>' : 'Password (leave blank to keep current)'; ?></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" <?php echo ($action === 'add') ? 'required' : ''; ?> minlength="6">
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Password must be at least 6 characters</div>
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label"><?php echo ($action === 'add') ? 'Confirm Password <span class="text-danger">*</span>' : 'Confirm Password'; ?></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" <?php echo ($action === 'add') ? 'required' : ''; ?>>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="students.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Cancel
                    </a>
                    <button type="submit" name="<?php echo ($action === 'add') ? 'add_student' : 'update_student'; ?>" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> <?php echo ($action === 'add') ? 'Add Student' : 'Update Student'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <!-- Students List -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($students)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="fas fa-user-graduate fa-4x text-muted"></i>
                </div>
                <h4>No Students Found</h4>
                <p class="text-muted">Start by adding students to your school</p>
                <a href="students.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i> Add Student
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Student ID</th>
                            <th>Grade/Class</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($student['grade']); ?></td>
                            <td>
                                <?php if ($student['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student['last_login']): ?>
                                <?php echo date('M d, Y H:i', strtotime($student['last_login'])); ?>
                                <?php else: ?>
                                <span class="text-muted">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="students.php?action=edit&id=<?php echo $student['id']; ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetPasswordModal<?php echo $student['id']; ?>" title="Reset Password">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteStudentModal<?php echo $student['id']; ?>" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                
                                <!-- Reset Password Modal -->
                                <div class="modal fade" id="resetPasswordModal<?php echo $student['id']; ?>" tabindex="-1" aria-labelledby="resetPasswordModalLabel<?php echo $student['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="resetPasswordModalLabel<?php echo $student['id']; ?>">Reset Password</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="post" action="students.php" class="needs-validation" novalidate>
                                                <div class="modal-body">
                                                    <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
                                                    <p>Reset password for <strong><?php echo htmlspecialchars($student['name']); ?></strong></p>
                                                    
                                                    <div class="mb-3">
                                                        <label for="new_password<?php echo $student['id']; ?>" class="form-label">New Password</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" id="new_password<?php echo $student['id']; ?>" name="new_password" required minlength="6">
                                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password<?php echo $student['id']; ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                        <div class="form-text">Password must be at least 6 characters</div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="confirm_password<?php echo $student['id']; ?>" class="form-label">Confirm Password</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" id="confirm_password<?php echo $student['id']; ?>" name="confirm_password" required>
                                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password<?php echo $student['id']; ?>">
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
                                
                                <!-- Delete Student Modal -->
                                <div class="modal fade" id="deleteStudentModal<?php echo $student['id']; ?>" tabindex="-1" aria-labelledby="deleteStudentModalLabel<?php echo $student['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteStudentModalLabel<?php echo $student['id']; ?>">Delete Student</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($student['name']); ?></strong>?</p>
                                                <p class="text-danger">This action cannot be undone.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form method="post" action="students.php">
                                                    <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
                                                    <button type="submit" name="delete_student" class="btn btn-danger">Delete Student</button>
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