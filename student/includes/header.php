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

// Set default page title if not set
if (!isset($pageTitle)) {
    $pageTitle = "Student Dashboard";
}

// Get user information
$userName = $_SESSION['name'] ?? 'Student';
$userEmail = $_SESSION['email'] ?? '';
$schoolId = $_SESSION['school_id'] ?? 0;
$schoolName = $_SESSION['school_name'] ?? 'School';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | <?php echo htmlspecialchars($schoolName); ?> Past Papers</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/student.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3>Student Portal</h3>
                <div class="sidebar-toggle-mobile d-md-none">
                    <i class="fas fa-times"></i>
                </div>
            </div>
            
            <ul class="list-unstyled components">
                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                    <a href="index.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'papers.php') ? 'active' : ''; ?>">
                    <a href="papers.php">
                        <i class="fas fa-file-alt"></i> Past Papers
                    </a>
                </li>
                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'subjects.php') ? 'active' : ''; ?>">
                    <a href="subjects.php">
                        <i class="fas fa-book"></i> Subjects
                    </a>
                </li>
                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'favorites.php') ? 'active' : ''; ?>">
                    <a href="favorites.php">
                        <i class="fas fa-star"></i> Favorites
                    </a>
                </li>
                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>">
                    <a href="profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </li>
                <li>
                    <a href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-footer">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($schoolName); ?></p>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div id="content">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <div class="ms-auto d-flex align-items-center">
                        <!-- Search Form -->
                        <form class="d-none d-md-flex me-4" action="papers.php" method="get">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Search papers..." name="search">
                                <button class="btn btn-outline-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                        
                        <!-- User Dropdown -->
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="d-none d-md-inline me-2"><?php echo htmlspecialchars($userName); ?></span>
                                <i class="fas fa-user-circle fa-lg"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><h6 class="dropdown-header"><?php echo htmlspecialchars($userEmail); ?></h6></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a></li>
                                <li><a class="dropdown-item" href="favorites.php"><i class="fas fa-star me-2"></i> Favorites</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="main-content">