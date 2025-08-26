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
$users = array();
$editUser = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add/Edit user
    if (isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'edit')) {
        $name = sanitizeInput($conn, $_POST['name']);
        $email = sanitizeInput($conn, $_POST['email']);
        $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
        
        // Validate inputs
        if (empty($name) || empty($email)) {
            $message = 'Name and email are required fields.';
            $messageType = 'danger';
        } else {
            // Check if email already exists (for new users)
            $emailExists = false;
            if ($_POST['action'] === 'add') {
                $checkSql = "SELECT id FROM users WHERE email = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param('s', $email);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $emailExists = ($checkResult->num_rows > 0);
                $checkStmt->close();
            }
            
            if ($_POST['action'] === 'edit' && isset($_POST['user_id'])) {
                $userId = intval($_POST['user_id']);
                $checkSql = "SELECT id FROM users WHERE email = ? AND id != ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param('si', $email, $userId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $emailExists = ($checkResult->num_rows > 0);
                $checkStmt->close();
            }
            
            if ($emailExists) {
                $message = 'Email address already exists.';
                $messageType = 'danger';
            } else {
                // Process based on action
                if ($_POST['action'] === 'add') {
                    // Generate a random password
                    $passwordInput = trim($_POST['password'] ?? '');
                    if ($passwordInput === '') {
                        // auto-generate if admin left blank
                        $password = substr(md5(uniqid(mt_rand(), true)), 0, 8);
                    } else {
                        $password = $passwordInput;
                    }
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $sql = "INSERT INTO users (name, email, password, is_admin) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('sssi', $name, $email, $hashedPassword, $isAdmin);
                    
                    if ($stmt->execute()) {
                        $message = "User added successfully. Temporary password: $password";
                        $messageType = 'success';
                    } else {
                        $message = 'Error adding user: ' . $conn->error;
                        $messageType = 'danger';
                    }
                    $stmt->close();
                } else if ($_POST['action'] === 'edit' && isset($_POST['user_id'])) {
                    $userId = intval($_POST['user_id']);
                    
                    // Check if password should be reset
                    if (isset($_POST['reset_password']) && $_POST['reset_password'] === '1') {
                        $password = substr(md5(uniqid(mt_rand(), true)), 0, 8);
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        
                        $sql = "UPDATE users SET name = ?, email = ?, password = ?, is_admin = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('sssii', $name, $email, $hashedPassword, $isAdmin, $userId);
                        
                        if ($stmt->execute()) {
                            $message = "User updated successfully. New password: $password";
                            $messageType = 'success';
                        } else {
                            $message = 'Error updating user: ' . $conn->error;
                            $messageType = 'danger';
                        }
                    } else {
                        $sql = "UPDATE users SET name = ?, email = ?, is_admin = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('ssii', $name, $email, $isAdmin, $userId);
                        
                        if ($stmt->execute()) {
                            $message = 'User updated successfully.';
                            $messageType = 'success';
                        } else {
                            $message = 'Error updating user: ' . $conn->error;
                            $messageType = 'danger';
                        }
                    }
                    $stmt->close();
                }
            }
        }
    }
    
    // Delete user
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['user_id'])) {
        $userId = intval($_POST['user_id']);
        
        // Don't allow deleting self
        if ($userId === $_SESSION['user_id']) {
            $message = 'You cannot delete your own account.';
            $messageType = 'danger';
        } else {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $userId);
            
            if ($stmt->execute()) {
                $message = 'User deleted successfully.';
                $messageType = 'success';
            } else {
                $message = 'Error deleting user: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
}

// Handle edit request
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $userId = intval($_GET['id']);
    $sql = "SELECT id, name, email, is_admin FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $editUser = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get all users
$sql = "SELECT id, name, email, is_admin, created_at, last_login FROM users ORDER BY name";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

closeDbConnection($conn);

// Page title
$pageTitle = "Manage Users";

// Include admin-specific header
include_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-users"></i> Manage Users</h2>
            <p>Create, edit, and delete teacher accounts</p>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><?php echo $editUser ? 'Edit User' : 'Add New User'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" action="users.php">
                        <input type="hidden" name="action" value="<?php echo $editUser ? 'edit' : 'add'; ?>">
                        <?php if ($editUser): ?>
                        <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required 
                                value="<?php echo $editUser ? htmlspecialchars($editUser['name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                value="<?php echo $editUser ? htmlspecialchars($editUser['email']) : ''; ?>">
                        </div>
                        <?php if (!$editUser): ?>
                        <div class="form-group">
                            <label for="password">Initial Password (leave blank to auto-generate)</label>
                            <input type="text" class="form-control" id="password" name="password">
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="is_admin" name="is_admin" value="1"
                                <?php echo ($editUser && $editUser['is_admin'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_admin">Admin Privileges</label>
                        </div>
                        
                        <?php if ($editUser): ?>
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="reset_password" name="reset_password" value="1">
                            <label class="form-check-label" for="reset_password">Reset Password</label>
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $editUser ? 'Update User' : 'Add User'; ?>
                        </button>
                        
                        <?php if ($editUser): ?>
                        <a href="users.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>User List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Created</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php if ($user['is_admin'] == 1): ?>
                                                <span class="badge badge-primary">Admin</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Teacher</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <?php 
                                            if ($user['last_login']) {
                                                echo date('M d, Y H:i', strtotime($user['last_login']));
                                            } else {
                                                echo '<span class="text-muted">Never</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $user['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            
                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal<?php echo $user['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                                                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Are you sure you want to delete user <strong><?php echo htmlspecialchars($user['name']); ?></strong>?
                                                            This action cannot be undone.
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <form method="post" action="users.php">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <button type="submit" class="btn btn-danger">Delete User</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No users found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include admin-specific footer
include_once 'includes/footer.php';
?>