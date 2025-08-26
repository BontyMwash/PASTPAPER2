<?php
// Start session
session_start();


// The code below is kept for reference but will not be executed due to the redirect above

// Include database connection
require_once 'config/database.php';

// Initialize variables
$email = $password = '';
$errors = [];

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to home page
    header('Location: index.php');
    exit;
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate form data
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    // If no validation errors, attempt to login
    if (empty($errors)) {
        // Get database connection
        $conn = getDbConnection();
        
        if ($conn) {
            // Sanitize input
            $email = sanitizeInput($conn, $email);
            
            // Prepare SQL statement
            $sql = "SELECT id, name, email, password, is_admin, department_id, status FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Check if user is active
                    if ($user['status'] !== 'active') {
                        $errors[] = 'Your account is not active. Please contact the administrator.';
                    } else {
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['is_admin'] = $user['is_admin'];
                        $_SESSION['role'] = $user['is_admin'] ? 'admin' : 'teacher';
                        $_SESSION['department_id'] = $user['department_id'];
                        
                        // Update last login time
                        $updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->bind_param('i', $user['id']);
                        $updateStmt->execute();
                        $updateStmt->close();
                        
                        // Log activity
                        $action = 'Login';
                        $description = 'User logged in';
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $userAgent = $_SERVER['HTTP_USER_AGENT'];
                        
                        $logSql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
                        $logStmt = $conn->prepare($logSql);
                        $logStmt->bind_param('issss', $user['id'], $action, $description, $ip, $userAgent);
                        $logStmt->execute();
                        $logStmt->close();
                        
                        // Redirect based on user role
                        if ($user['is_admin']) {
                            header('Location: admin/index.php');
                        } else {
                            header('Location: index.php');
                        }
                        exit;
                    }
                } else {
                    $errors[] = 'Invalid email or password';
                }
            } else {
                $errors[] = 'Invalid email or password';
            }
            
            $stmt->close();
            closeDbConnection($conn);
        } else {
            $errors[] = 'Database connection error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Login - Njumbi High School</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-form fade-in">
                    <div class="text-center mb-4">
                        <img src="assets/images/logo.svg" alt="Njumbi High School Logo" class="img-fluid mb-3 slide-in-top" style="max-width: 100px;">
                        <h2 class="text-primary">Teacher Login</h2>
                        <p class="text-muted">Access the Past Papers Repository</p>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="login.php" method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <script>
                                document.getElementById('togglePassword').addEventListener('click', function() {
                                    const password = document.getElementById('password');
                                    const icon = this.querySelector('i');
                                    if (password.type === 'password') {
                                        password.type = 'text';
                                        icon.classList.remove('fa-eye');
                                        icon.classList.add('fa-eye-slash');
                                    } else {
                                        password.type = 'password';
                                        icon.classList.remove('fa-eye-slash');
                                        icon.classList.add('fa-eye');
                                    }
                                });
                            </script>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                            <label class="form-check-label" for="rememberMe">Remember me</label>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg shadow-sm">Login <i class="fas fa-sign-in-alt ms-2"></i></button>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="forgot-password.php" class="text-decoration-none"><i class="fas fa-question-circle me-1"></i> Forgot Password?</a>
                        </div>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <p class="mb-0">Don't have an account? Please contact the administrator.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>