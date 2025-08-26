<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true;
$userName = $isLoggedIn ? $_SESSION['user_name'] : '';

// Get page title if set, otherwise use default
$pageTitle = isset($pageTitle) ? $pageTitle . ' - Njumbi High School' : 'Admin Panel - Njumbi High School';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <img src="../assets/images/logo.svg" alt="Njumbi High School Logo" height="40" class="me-2 slide-in-left">
                <span class="slide-in-right">Njumbi High School</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">
                            <i class="fas fa-home me-1"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../departments.php">
                            <i class="fas fa-book me-1"></i> Departments
                        </a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../upload.php">
                            <i class="fas fa-upload me-1"></i> Upload Papers
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" href="index.php">
                            <i class="fas fa-user-shield me-1"></i> Admin Panel
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../contact.php">
                            <i class="fas fa-envelope me-1"></i> Contact
                        </a>
                    </li>
                    <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'messages.php') ? 'active' : ''; ?>" href="messages.php">
                            <i class="fas fa-inbox me-1"></i> Messages
                            <?php 
                            // Get unread messages count for the badge
                            $unreadCount = 0;
                            $msgConn = getDbConnection();
                            if ($msgConn) {
                                $msgSql = "SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0";
                                $msgResult = $msgConn->query($msgSql);
                                if ($msgResult && $row = $msgResult->fetch_assoc()) {
                                    $unreadCount = $row['count'];
                                }
                                closeDbConnection($msgConn);
                            }
                            if ($unreadCount > 0): 
                            ?>
                            <span class="badge rounded-pill bg-danger"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <!-- User Menu -->
                <div class="nav-item dropdown">
                    <?php if ($isLoggedIn): ?>
                        <a class="nav-link dropdown-toggle text-white" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($userName); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="../my-uploads.php"><i class="fas fa-file-upload me-2"></i>My Uploads</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    <?php else: ?>
                        <a class="btn btn-outline-light" href="../login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>