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
$message = '';
$messageType = '';
$schoolData = [];
$adminData = [];

// Get school data
if ($conn) {
    // Get school information
    $sql = "SELECT s.*, COUNT(u.id) as total_users 
            FROM schools s 
            LEFT JOIN users u ON s.id = u.school_id 
            WHERE s.id = ? 
            GROUP BY s.id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $schoolData = $result->fetch_assoc();
    }
    $stmt->close();
    
    // Get school admin information
    $sql = "SELECT u.* 
            FROM users u 
            JOIN schools s ON u.id = s.admin_id 
            WHERE s.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $adminData = $result->fetch_assoc();
    }
    $stmt->close();
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_school'])) {
        // Get form data
        $schoolName = sanitizeInput($_POST['school_name']);
        $schoolEmail = sanitizeInput($_POST['school_email']);
        $schoolPhone = sanitizeInput($_POST['school_phone']);
        $schoolAddress = sanitizeInput($_POST['school_address']);
        $schoolWebsite = sanitizeInput($_POST['school_website']);
        $schoolDescription = sanitizeInput($_POST['school_description']);
        
        // Validate form data
        $errors = [];
        
        if (empty($schoolName)) {
            $errors[] = "School name is required";
        }
        
        if (empty($schoolEmail)) {
            $errors[] = "School email is required";
        } elseif (!filter_var($schoolEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check if school name already exists
        if ($schoolName !== $schoolData['school_name']) {
            $sql = "SELECT id FROM schools WHERE school_name = ? AND id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $schoolName, $schoolId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $errors[] = "School name already exists";
            }
            $stmt->close();
        }
        
        // Check if school email already exists
        if ($schoolEmail !== $schoolData['email']) {
            $sql = "SELECT id FROM schools WHERE email = ? AND id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $schoolEmail, $schoolId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $errors[] = "School email already exists";
            }
            $stmt->close();
        }
        
        // Process logo upload if provided
        $logoPath = $schoolData['logo'];
        if (isset($_FILES['school_logo']) && $_FILES['school_logo']['size'] > 0) {
            $targetDir = "../uploads/school_logos/";
            
            // Create directory if it doesn't exist
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES["school_logo"]["name"], PATHINFO_EXTENSION));
            $newFileName = "school_" . $schoolId . "_" . time() . "." . $fileExtension;
            $targetFile = $targetDir . $newFileName;
            
            // Check file type
            $allowedTypes = ["jpg", "jpeg", "png", "gif"];
            if (!in_array($fileExtension, $allowedTypes)) {
                $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed for logo";
            }
            
            // Check file size (max 2MB)
            if ($_FILES["school_logo"]["size"] > 2000000) {
                $errors[] = "Logo file is too large (max 2MB)";
            }
            
            // Upload file if no errors
            if (empty($errors)) {
                if (move_uploaded_file($_FILES["school_logo"]["tmp_name"], $targetFile)) {
                    $logoPath = $targetFile;
                    
                    // Delete old logo if exists and not default
                    if (!empty($schoolData['logo']) && $schoolData['logo'] != '../assets/img/default_school.png' && file_exists($schoolData['logo'])) {
                        unlink($schoolData['logo']);
                    }
                } else {
                    $errors[] = "Failed to upload logo";
                }
            }
        }
        
        // If no errors, update school information
        if (empty($errors)) {
            $sql = "UPDATE schools SET 
                    school_name = ?, 
                    email = ?, 
                    phone = ?, 
                    address = ?, 
                    website = ?, 
                    description = ?, 
                    logo = ?, 
                    updated_at = NOW() 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssssssi', $schoolName, $schoolEmail, $schoolPhone, $schoolAddress, $schoolWebsite, $schoolDescription, $logoPath, $schoolId);
            
            if ($stmt->execute()) {
                $message = "School information updated successfully";
                $messageType = "success";
                
                // Log activity
                $activityType = "update_school";
                $activityDescription = "Updated school information";
                logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
                
                // Update school data
                $schoolData['school_name'] = $schoolName;
                $schoolData['email'] = $schoolEmail;
                $schoolData['phone'] = $schoolPhone;
                $schoolData['address'] = $schoolAddress;
                $schoolData['website'] = $schoolWebsite;
                $schoolData['description'] = $schoolDescription;
                $schoolData['logo'] = $logoPath;
            } else {
                $message = "Error updating school information: " . $conn->error;
                $messageType = "danger";
            }
            $stmt->close();
        } else {
            $message = "Please fix the following errors: <ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
            $messageType = "danger";
        }
    } elseif (isset($_POST['update_password'])) {
        // Get form data
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validate form data
        $errors = [];
        
        if (empty($currentPassword)) {
            $errors[] = "Current password is required";
        }
        
        if (empty($newPassword)) {
            $errors[] = "New password is required";
        } elseif (strlen($newPassword) < 8) {
            $errors[] = "New password must be at least 8 characters long";
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = "New password and confirm password do not match";
        }
        
        // Verify current password
        if (empty($errors)) {
            $sql = "SELECT password FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $userData = $result->fetch_assoc();
                
                if (!password_verify($currentPassword, $userData['password'])) {
                    $errors[] = "Current password is incorrect";
                }
            } else {
                $errors[] = "User not found";
            }
            $stmt->close();
        }
        
        // If no errors, update password
        if (empty($errors)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $hashedPassword, $userId);
            
            if ($stmt->execute()) {
                $message = "Password updated successfully";
                $messageType = "success";
                
                // Log activity
                $activityType = "update_password";
                $activityDescription = "Updated password";
                logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
            } else {
                $message = "Error updating password: " . $conn->error;
                $messageType = "danger";
            }
            $stmt->close();
        } else {
            $message = "Please fix the following errors: <ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
            $messageType = "danger";
        }
    } elseif (isset($_POST['update_profile'])) {
        // Get form data
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        
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
        
        // Check if email already exists
        if ($email !== $_SESSION['email']) {
            $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $email, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $errors[] = "Email already exists";
            }
            $stmt->close();
        }
        
        // Process profile picture upload if provided
        $profilePicture = $_SESSION['profile_picture'] ?? null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['size'] > 0) {
            $targetDir = "../uploads/profile_pictures/";
            
            // Create directory if it doesn't exist
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));
            $newFileName = "user_" . $userId . "_" . time() . "." . $fileExtension;
            $targetFile = $targetDir . $newFileName;
            
            // Check file type
            $allowedTypes = ["jpg", "jpeg", "png", "gif"];
            if (!in_array($fileExtension, $allowedTypes)) {
                $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed for profile picture";
            }
            
            // Check file size (max 2MB)
            if ($_FILES["profile_picture"]["size"] > 2000000) {
                $errors[] = "Profile picture file is too large (max 2MB)";
            }
            
            // Upload file if no errors
            if (empty($errors)) {
                if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFile)) {
                    $profilePicture = $targetFile;
                    
                    // Delete old profile picture if exists and not default
                    if (!empty($_SESSION['profile_picture']) && $_SESSION['profile_picture'] != '../assets/img/default_avatar.png' && file_exists($_SESSION['profile_picture'])) {
                        unlink($_SESSION['profile_picture']);
                    }
                } else {
                    $errors[] = "Failed to upload profile picture";
                }
            }
        }
        
        // If no errors, update user profile
        if (empty($errors)) {
            $sql = "UPDATE users SET name = ?, email = ?, phone = ?";
            $params = [$name, $email, $phone];
            $types = "sss";
            
            if ($profilePicture) {
                $sql .= ", profile_picture = ?";
                $params[] = $profilePicture;
                $types .= "s";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $userId;
            $types .= "i";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $message = "Profile updated successfully";
                $messageType = "success";
                
                // Update session variables
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                if ($profilePicture) {
                    $_SESSION['profile_picture'] = $profilePicture;
                }
                
                // Log activity
                $activityType = "update_profile";
                $activityDescription = "Updated profile information";
                logActivity($conn, $userId, $activityType, $activityDescription, $schoolId);
            } else {
                $message = "Error updating profile: " . $conn->error;
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

// Close database connection
if ($conn) {
    closeDbConnection($conn);
}

// Set page title
$pageTitle = "Settings";

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Settings</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Settings</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Left Column - Tabs Navigation -->
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="settings-tabs" role="tablist">
                        <a class="list-group-item list-group-item-action active" id="school-tab" data-bs-toggle="list" href="#school" role="tab" aria-controls="school">
                            <i class="fas fa-school me-2"></i> School Information
                        </a>
                        <a class="list-group-item list-group-item-action" id="profile-tab" data-bs-toggle="list" href="#profile" role="tab" aria-controls="profile">
                            <i class="fas fa-user me-2"></i> My Profile
                        </a>
                        <a class="list-group-item list-group-item-action" id="password-tab" data-bs-toggle="list" href="#password" role="tab" aria-controls="password">
                            <i class="fas fa-key me-2"></i> Change Password
                        </a>
                        <a class="list-group-item list-group-item-action" id="notifications-tab" data-bs-toggle="list" href="#notifications" role="tab" aria-controls="notifications">
                            <i class="fas fa-bell me-2"></i> Notifications
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Tab Content -->
        <div class="col-md-9">
            <div class="tab-content" id="settings-tabsContent">
                <!-- School Information Tab -->
                <div class="tab-pane fade show active" id="school" role="tabpanel" aria-labelledby="school-tab">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">School Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="settings.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <div class="text-center mb-3">
                                            <img src="<?php echo !empty($schoolData['logo']) ? $schoolData['logo'] : '../assets/img/default_school.png'; ?>" class="img-fluid rounded" style="max-height: 150px;" alt="School Logo" id="logoPreview">
                                        </div>
                                        <div class="mb-3">
                                            <label for="school_logo" class="form-label">School Logo</label>
                                            <input type="file" class="form-control" id="school_logo" name="school_logo" accept="image/*" onchange="previewLogo(this)">
                                            <div class="form-text">Max file size: 2MB. Supported formats: JPG, PNG, GIF</div>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="school_name" class="form-label">School Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="school_name" name="school_name" value="<?php echo htmlspecialchars($schoolData['school_name'] ?? ''); ?>" required>
                                                <div class="invalid-feedback">Please enter school name</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="school_email" class="form-label">School Email <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" id="school_email" name="school_email" value="<?php echo htmlspecialchars($schoolData['email'] ?? ''); ?>" required>
                                                <div class="invalid-feedback">Please enter a valid email</div>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="school_phone" class="form-label">Phone Number</label>
                                                <input type="text" class="form-control" id="school_phone" name="school_phone" value="<?php echo htmlspecialchars($schoolData['phone'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="school_website" class="form-label">Website</label>
                                                <input type="url" class="form-control" id="school_website" name="school_website" value="<?php echo htmlspecialchars($schoolData['website'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="school_address" class="form-label">Address</label>
                                            <textarea class="form-control" id="school_address" name="school_address" rows="2"><?php echo htmlspecialchars($schoolData['address'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="school_description" class="form-label">Description</label>
                                            <textarea class="form-control" id="school_description" name="school_description" rows="3"><?php echo htmlspecialchars($schoolData['description'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="update_school" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- My Profile Tab -->
                <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">My Profile</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="settings.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <div class="text-center mb-3">
                                            <img src="<?php echo !empty($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '../assets/img/default_avatar.png'; ?>" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;" alt="Profile Picture" id="profilePreview">
                                        </div>
                                        <div class="mb-3">
                                            <label for="profile_picture" class="form-label">Profile Picture</label>
                                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*" onchange="previewProfile(this)">
                                            <div class="form-text">Max file size: 2MB. Supported formats: JPG, PNG, GIF</div>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>" required>
                                                <div class="invalid-feedback">Please enter your name</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>
                                                <div class="invalid-feedback">Please enter a valid email</div>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="phone" class="form-label">Phone Number</label>
                                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($_SESSION['phone'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="role" class="form-label">Role</label>
                                                <input type="text" class="form-control" id="role" value="<?php echo ucfirst($_SESSION['role'] ?? ''); ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="created_at" class="form-label">Account Created</label>
                                                <input type="text" class="form-control" id="created_at" value="<?php echo isset($_SESSION['created_at']) ? date('F j, Y', strtotime($_SESSION['created_at'])) : ''; ?>" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="last_login" class="form-label">Last Login</label>
                                                <input type="text" class="form-control" id="last_login" value="<?php echo isset($_SESSION['last_login']) ? date('F j, Y g:i A', strtotime($_SESSION['last_login'])) : ''; ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Change Password Tab -->
                <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="settings.php" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">Please enter your current password</div>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">Password must be at least 8 characters long</div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">Please confirm your new password</div>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="update_password" class="btn btn-primary">
                                        <i class="fas fa-key me-2"></i> Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications Tab -->
                <div class="tab-pane fade" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Notification Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="settings.php">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" checked>
                                        <label class="form-check-label" for="email_notifications">Email Notifications</label>
                                    </div>
                                    <div class="form-text">Receive email notifications for important updates</div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="new_paper_notifications" name="new_paper_notifications" checked>
                                        <label class="form-check-label" for="new_paper_notifications">New Paper Uploads</label>
                                    </div>
                                    <div class="form-text">Get notified when new papers are uploaded</div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="user_activity_notifications" name="user_activity_notifications" checked>
                                        <label class="form-check-label" for="user_activity_notifications">User Activity</label>
                                    </div>
                                    <div class="form-text">Get notified about user registrations and logins</div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="system_notifications" name="system_notifications" checked>
                                        <label class="form-check-label" for="system_notifications">System Notifications</label>
                                    </div>
                                    <div class="form-text">Receive notifications about system updates and maintenance</div>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="update_notifications" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Save Preferences
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Preview uploaded logo
    function previewLogo(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('logoPreview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // Preview uploaded profile picture
    function previewProfile(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePreview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // Activate tab from URL hash
    document.addEventListener('DOMContentLoaded', function() {
        const hash = window.location.hash;
        if (hash) {
            const tab = document.querySelector(`#settings-tabs a[href="${hash}"]`);
            if (tab) {
                tab.click();
            }
        }
    });
</script>

<?php include_once 'includes/footer.php'; ?>