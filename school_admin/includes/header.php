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

// Get user information
$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? 'Admin';
$schoolId = $_SESSION['school_id'];
$schoolName = $_SESSION['school_name'] ?? 'School';

// Set default page title if not set
if (!isset($pageTitle)) {
    $pageTitle = 'School Admin Dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | <?php echo htmlspecialchars($schoolName); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/school-admin.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="active">
            <div class="sidebar-header">
                <h3><?php echo htmlspecialchars($schoolName); ?></h3>
                <div class="sidebar-toggle-btn">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
            
            <ul class="list-unstyled components">
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <a href="index.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'school_admins.php' ? 'active' : ''; ?>">
                    <a href="school_admins.php">
                        <i class="fas fa-user-shield"></i> School Admins
                    </a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'teachers.php' ? 'active' : ''; ?>">
                    <a href="teachers.php">
                        <i class="fas fa-chalkboard-teacher"></i> Teachers
                    </a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>">
                    <a href="students.php">
                        <i class="fas fa-user-graduate"></i> Students
                    </a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'departments.php' ? 'active' : ''; ?>">
                    <a href="departments.php">
                        <i class="fas fa-building"></i> Departments
                    </a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'subjects.php' ? 'active' : ''; ?>">
                    <a href="subjects.php">
                        <i class="fas fa-book"></i> Subjects
                    </a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'papers.php' ? 'active' : ''; ?>">
                    <a href="papers.php">
                        <i class="fas fa-file-alt"></i> Past Papers
                    </a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <a href="reports.php">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <a href="settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <li>
                    <a href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Page Content -->
        <div id="content">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-outline-primary sidebar-toggle-btn">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <div class="ms-auto d-flex align-items-center">
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($userName); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="p-4">