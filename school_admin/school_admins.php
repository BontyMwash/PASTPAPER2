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

// Get school ID from session
$schoolId = $_SESSION['school_id'];
$schoolName = $_SESSION['school_name'] ?? 'School';

// Set page title
$pageTitle = 'School Admins';

// Process form submissions
$message = '';
$messageType = '';

// Handle admin creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create' || $_POST['action'] === 'update') {
        // Get form data
        $adminId = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : 0;
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $status = isset($_POST['status']) ? 'active' : 'inactive';
        
        // Validate form data
        if (empty($name) || empty($email)) {
            $message = 'Name and email are required.';
            $messageType = 'danger';
        } else {
            // Check if email already exists (for new admin)
            if ($_POST['action'] === 'create') {
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND school_id = ? AND role = 'school_admin'");
                $stmt->bind_param("sis", $email, $adminId, $schoolId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $message = 'Email already exists.';
                    $messageType = 'danger';
                } else {
                    // Create new admin
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, status, school_id, created_at) VALUES (?, ?, ?, ?, 'school_admin', ?, ?, NOW())");
                    $stmt->bind_param("sssssi", $name, $email, $phone, $hashedPassword, $status, $schoolId);
                    
                    if ($stmt->execute()) {
                        $message = 'School admin created successfully.';
                        $messageType = 'success';
                        
                        // Log activity
                        $adminId = $conn->insert_id;
                        $activityDescription = "Created new school admin: $name";
                        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'admin_create', ?, ?, ?, NOW())");
                        $stmt->bind_param("isss", $_SESSION['user_id'], $activityDescription, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                        $stmt->execute();
                    } else {
                        $message = 'Failed to create school admin.';
                        $messageType = 'danger';
                    }
                }
            } else {
                // Update existing admin
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND school_id = ? AND role = 'school_admin'");
                $stmt->bind_param("sis", $email, $adminId, $schoolId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $message = 'Email already exists.';
                    $messageType = 'danger';
                } else {
                    if (!empty($password)) {
                        // Update with new password
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ?, status = ?, updated_at = NOW() WHERE id = ? AND school_id = ? AND role = 'school_admin'");
                        $stmt->bind_param("sssssii", $name, $email, $phone, $hashedPassword, $status, $adminId, $schoolId);
                    } else {
                        // Update without changing password
                        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, status = ?, updated_at = NOW() WHERE id = ? AND school_id = ? AND role = 'school_admin'");
                        $stmt->bind_param("sssii", $name, $email, $phone, $status, $adminId, $schoolId);
                    }
                    
                    if ($stmt->execute()) {
                        $message = 'School admin updated successfully.';
                        $messageType = 'success';
                        
                        // Log activity
                        $activityDescription = "Updated school admin: $name";
                        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'admin_update', ?, ?, ?, NOW())");
                        $stmt->bind_param("isss", $_SESSION['user_id'], $activityDescription, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                        $stmt->execute();
                    } else {
                        $message = 'Failed to update school admin.';
                        $messageType = 'danger';
                    }
                }
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['admin_id'])) {
        // Delete admin
        $adminId = intval($_POST['admin_id']);
        
        // Get admin name for activity log
        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ? AND school_id = ? AND role = 'school_admin'");
        $stmt->bind_param("ii", $adminId, $schoolId);
        $stmt->execute();
        $result = $stmt->get_result();
        $adminName = $result->fetch_assoc()['name'] ?? 'Unknown';
        
        // Delete the admin
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND school_id = ? AND role = 'school_admin'");
        $stmt->bind_param("ii", $adminId, $schoolId);
        
        if ($stmt->execute()) {
            $message = 'School admin deleted successfully.';
            $messageType = 'success';
            
            // Log activity
            $activityDescription = "Deleted school admin: $adminName";
            $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'admin_delete', ?, ?, ?, NOW())");
            $stmt->bind_param("isss", $_SESSION['user_id'], $activityDescription, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
            $stmt->execute();
        } else {
            $message = 'Failed to delete school admin.';
            $messageType = 'danger';
        }
    }
}

// Get all school admins for this school
$stmt = $conn->prepare("SELECT id, name, email, phone, status, created_at FROM users WHERE school_id = ? AND role = 'school_admin' ORDER BY name ASC");
$stmt->bind_param("i", $schoolId);
$stmt->execute();
$result = $stmt->get_result();
$admins = $result->fetch_all(MYSQLI_ASSOC);

// Include header
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">School Administrators</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adminModal">
            <i class="fas fa-plus"></i> Add New Admin
        </button>
    </div>
    
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($admins) > 0): ?>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($admin['name']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $admin['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($admin['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info edit-admin" data-id="<?php echo $admin['id']; ?>" data-name="<?php echo htmlspecialchars($admin['name']); ?>" data-email="<?php echo htmlspecialchars($admin['email']); ?>" data-phone="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>" data-status="<?php echo $admin['status']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger delete-admin" data-id="<?php echo $admin['id']; ?>" data-name="<?php echo htmlspecialchars($admin['name']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No administrators found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Admin Modal -->
<div class="modal fade" id="adminModal" tabindex="-1" aria-labelledby="adminModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="adminForm" method="post" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="adminModalLabel">Add New Administrator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="adminAction" value="create">
                    <input type="hidden" name="admin_id" id="adminId" value="">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="invalid-feedback">Please enter a name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="invalid-feedback">Please enter a valid email.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                    
                    <div class="mb-3 password-field">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password">
                        <div class="form-text password-help">Leave blank to keep current password (when editing).</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="status" name="status" checked>
                        <label class="form-check-label" for="status">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="admin_id" id="deleteAdminId" value="">
                    <p>Are you sure you want to delete the administrator <strong id="deleteAdminName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Edit admin
        document.querySelectorAll('.edit-admin').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const email = this.getAttribute('data-email');
                const phone = this.getAttribute('data-phone');
                const status = this.getAttribute('data-status');
                
                document.getElementById('adminModalLabel').textContent = 'Edit Administrator';
                document.getElementById('adminAction').value = 'update';
                document.getElementById('adminId').value = id;
                document.getElementById('name').value = name;
                document.getElementById('email').value = email;
                document.getElementById('phone').value = phone;
                document.getElementById('status').checked = status === 'active';
                document.getElementById('password').value = '';
                document.querySelector('.password-help').style.display = 'block';
                
                const adminModal = new bootstrap.Modal(document.getElementById('adminModal'));
                adminModal.show();
            });
        });
        
        // Delete admin
        document.querySelectorAll('.delete-admin').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('deleteAdminId').value = id;
                document.getElementById('deleteAdminName').textContent = name;
                
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                deleteModal.show();
            });
        });
        
        // Reset form when modal is closed
        document.getElementById('adminModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('adminForm').reset();
            document.getElementById('adminModalLabel').textContent = 'Add New Administrator';
            document.getElementById('adminAction').value = 'create';
            document.getElementById('adminId').value = '';
            document.querySelector('.password-help').style.display = 'none';
        });
    });
</script>

<?php
// Include footer
include 'includes/footer.php';
?>