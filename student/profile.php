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
$userId = $_SESSION['user_id'];
$schoolId = $_SESSION['school_id'];
$user = [];
$school = [];
$message = '';
$messageType = '';

// Get user data
if ($conn) {
    // Get user information
    $sql = "SELECT u.*, s.school_name, s.logo 
            FROM users u 
            JOIN schools s ON u.school_id = s.id 
            WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $user = $row;
    }
    $stmt->close();
    
    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_profile'])) {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
            
            // Validate inputs
            $errors = [];
            
            if (empty($name)) {
                $errors[] = "Name is required";
            }
            
            if (empty($email)) {
                $errors[] = "Email is required";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format";
            } else {
                // Check if email exists for another user
                $checkSql = "SELECT id FROM users WHERE email = ? AND id != ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param('si', $email, $userId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult && $checkResult->num_rows > 0) {
                    $errors[] = "Email already in use by another account";
                }
                $checkStmt->close();
            }
            
            // Process profile picture if uploaded
            $profilePicture = $user['profile_picture'];
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $fileType = $_FILES['profile_picture']['type'];
                
                if (!in_array($fileType, $allowedTypes)) {
                    $errors[] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                } else {
                    $maxFileSize = 2 * 1024 * 1024; // 2MB
                    if ($_FILES['profile_picture']['size'] > $maxFileSize) {
                        $errors[] = "File size exceeds the limit of 2MB.";
                    } else {
                        $uploadDir = '../uploads/profile_pictures/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        $fileName = 'profile_' . $userId . '_' . time() . '_' . basename($_FILES['profile_picture']['name']);
                        $targetFile = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetFile)) {
                            $profilePicture = $fileName;
                        } else {
                            $errors[] = "Failed to upload profile picture.";
                        }
                    }
                }
            }
            
            if (empty($errors)) {
                // Update user profile
                $updateSql = "UPDATE users SET name = ?, email = ?, phone = ?, profile_picture = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param('ssssi', $name, $email, $phone, $profilePicture, $userId);
                $result = $updateStmt->execute();
                
                if ($result) {
                    $message = "Profile updated successfully!";
                    $messageType = "success";
                    
                    // Update session data
                    $_SESSION['name'] = $name;
                    
                    // Log activity
                    $activityType = 'update_profile';
                    $description = "Student updated their profile";
                    $ipAddress = $_SERVER['REMOTE_ADDR'];
                    
                    $logSql = "INSERT INTO activity_logs (user_id, school_id, activity_type, description, ip_address) 
                                VALUES (?, ?, ?, ?, ?)";
                    $logStmt = $conn->prepare($logSql);
                    $logStmt->bind_param('iisss', $userId, $schoolId, $activityType, $description, $ipAddress);
                    $logStmt->execute();
                    $logStmt->close();
                    
                    // Refresh user data
                    $sql = "SELECT u.*, s.school_name, s.logo 
                            FROM users u 
                            JOIN schools s ON u.school_id = s.id 
                            WHERE u.id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result && $row = $result->fetch_assoc()) {
                        $user = $row;
                    }
                    $stmt->close();
                } else {
                    $message = "Failed to update profile.";
                    $messageType = "danger";
                }
                $updateStmt->close();
            } else {
                $message = implode("<br>", $errors);
                $messageType = "danger";
            }
        } elseif (isset($_POST['change_password'])) {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            // Validate inputs
            $errors = [];
            
            if (empty($currentPassword)) {
                $errors[] = "Current password is required";
            }
            
            if (empty($newPassword)) {
                $errors[] = "New password is required";
            } elseif (strlen($newPassword) < 6) {
                $errors[] = "New password must be at least 6 characters long";
            }
            
            if ($newPassword !== $confirmPassword) {
                $errors[] = "New password and confirmation do not match";
            }
            
            if (empty($errors)) {
                // Verify current password
                $sql = "SELECT password FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $row = $result->fetch_assoc()) {
                    if (password_verify($currentPassword, $row['password'])) {
                        // Update password
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $updateSql = "UPDATE users SET password = ? WHERE id = ?";
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->bind_param('si', $hashedPassword, $userId);
                        $result = $updateStmt->execute();
                        
                        if ($result) {
                            $message = "Password changed successfully!";
                            $messageType = "success";
                            
                            // Log activity
                            $activityType = 'change_password';
                            $description = "Student changed their password";
                            $ipAddress = $_SERVER['REMOTE_ADDR'];
                            
                            $logSql = "INSERT INTO activity_logs (user_id, school_id, activity_type, description, ip_address) 
                                        VALUES (?, ?, ?, ?, ?)";
                            $logStmt = $conn->prepare($logSql);
                            $logStmt->bind_param('iisss', $userId, $schoolId, $activityType, $description, $ipAddress);
                            $logStmt->execute();
                            $logStmt->close();
                        } else {
                            $message = "Failed to change password.";
                            $messageType = "danger";
                        }
                        $updateStmt->close();
                    } else {
                        $message = "Current password is incorrect.";
                        $messageType = "danger";
                    }
                }
                $stmt->close();
            } else {
                $message = implode("<br>", $errors);
                $messageType = "danger";
            }
        }
    }
    
    // Log this activity
    $activityType = 'view_profile';
    $description = "Student viewed their profile";
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
$pageTitle = "My Profile";

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h2>My Profile</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">My Profile</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Profile Information -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="profile-image-container mb-3">
                        <?php if (!empty($user['profile_picture'])): ?>
                        <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture" class="profile-image">
                        <?php else: ?>
                        <div class="profile-image-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                    <p class="text-muted"><?php echo ucfirst($user['role']); ?></p>
                    <div class="school-info mt-3">
                        <div class="school-logo-small">
                            <?php if (!empty($user['logo'])): ?>
                            <img src="../uploads/school_logos/<?php echo htmlspecialchars($user['logo']); ?>" alt="School Logo">
                            <?php else: ?>
                            <i class="fas fa-school"></i>
                            <?php endif; ?>
                        </div>
                        <p class="mb-0"><?php echo htmlspecialchars($user['school_name']); ?></p>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <div>
                            <i class="fas fa-calendar-alt me-2"></i>
                            <small>Joined: <?php echo date('M d, Y', strtotime($user['date_created'])); ?></small>
                        </div>
                        <div>
                            <i class="fas fa-clock me-2"></i>
                            <small>Last Login: <?php echo date('M d, Y', strtotime($user['last_login'])); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Profile Tabs -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit" type="button" role="tab" aria-controls="edit" aria-selected="true">
                                <i class="fas fa-user-edit me-2"></i> Edit Profile
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab" aria-controls="password" aria-selected="false">
                                <i class="fas fa-key me-2"></i> Change Password
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="profileTabsContent">
                        <!-- Edit Profile Tab -->
                        <div class="tab-pane fade show active" id="edit" role="tabpanel" aria-labelledby="edit-tab">
                            <form action="profile.php" method="post" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="profile_picture" class="form-label">Profile Picture</label>
                                    <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                                    <small class="form-text text-muted">Max file size: 2MB. Allowed formats: JPG, PNG, GIF</small>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                            </form>
                        </div>
                        
                        <!-- Change Password Tab -->
                        <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                            <form action="profile.php" method="post" id="passwordForm">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                            <i class="far fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                            <i class="far fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="form-text text-muted">Password must be at least 6 characters long</small>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                            <i class="far fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-key me-2"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Password visibility toggle
    $('.toggle-password').on('click', function() {
        const targetId = $(this).data('target');
        const passwordInput = $('#' + targetId);
        const icon = $(this).find('i');
        
        if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordInput.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Password confirmation validation
    $('#passwordForm').on('submit', function(e) {
        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            showToast('New password and confirmation do not match', 'danger');
        }
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>