<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true;
$userName = $isLoggedIn ? $_SESSION['user_name'] : '';
?>

<header>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="assets/images/logo.svg" alt="Njumbi High School Logo" height="40" class="me-2 slide-in-left">
                <span class="slide-in-right">Njumbi High School</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" href="index.php">
                            <i class="fas fa-home me-1"></i> Home
                        </a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'departments.php') ? 'active' : ''; ?>" href="departments.php">
                            <i class="fas fa-book me-1"></i> Departments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'upload.php') ? 'active' : ''; ?>" href="upload.php">
                            <i class="fas fa-upload me-1"></i> Upload Papers
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin/index.php') ? 'active' : ''; ?>" href="admin/index.php">
                            <i class="fas fa-user-shield me-1"></i> Admin Panel
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'contact.php') ? 'active' : ''; ?>" href="contact.php">
                            <i class="fas fa-envelope me-1"></i> Contact
                        </a>
                    </li>
                </ul>
                
                <?php if ($isLoggedIn): ?>
                <!-- Search Form -->
                <form class="d-flex me-2 position-relative" action="search.php" method="GET">
                    <div class="input-group">
                        <input id="searchInput" class="form-control" type="search" name="q" placeholder="Search papers..." aria-label="Search">
                        <button class="btn btn-outline-light" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div id="searchResults" class="position-absolute bg-white shadow rounded w-100 mt-1 d-none" style="top: 100%; z-index: 1000;"></div>
                </form>
                <?php endif; ?>
                
                <!-- User Menu -->
                <div class="nav-item dropdown">
                    <?php if ($isLoggedIn): ?>
                        <a class="nav-link dropdown-toggle text-white" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($userName); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="my-uploads.php"><i class="fas fa-file-upload me-2"></i>My Uploads</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    <?php else: ?>
                        <a class="btn btn-outline-light" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>