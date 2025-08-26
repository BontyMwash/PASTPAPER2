<?php
// forgot-password.php – request password reset
session_start();
require_once __DIR__ . '/config/database.php';

$emailSent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } else {
        $conn = getDbConnection();
        if (!$conn) {
            $errors[] = 'Database error';
        } else {
            $emailEsc = sanitizeInput($conn, $email);
            $userResult = $conn->query("SELECT id,email FROM users WHERE email='".$emailEsc."' LIMIT 1");
            if ($userResult && $userResult->num_rows) {
                // ensure columns exist
                $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL, ADD COLUMN IF NOT EXISTS reset_expires DATETIME NULL");
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600);
                $stmt = $conn->prepare('UPDATE users SET reset_token=?, reset_expires=? WHERE email=?');
                $stmt->bind_param('sss', $token, $expires, $emailEsc);
                $stmt->execute();
                $stmt->close();

                // build reset link
                $link = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http').
                         '://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/reset-password.php?token='.$token;

                // send email (basic mail function)
                $subject = 'Password Reset Request';
                $message = "Hello,\n\nWe received a request to reset your password. " .
                           "Please click the link below or copy it into your browser to reset your password (valid for 1 hour):\n\n".$link."\n\nIf you didn't request this, please ignore this email.";
                @mail($email, $subject, $message, 'From: no-reply@njumbi.ac.ke');
                $emailSent = true;
            }
            closeDbConnection($conn);
            // For security, do not reveal if email exists or not
            $emailSent = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password</title>
<link rel="stylesheet" href="assets/css/style.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/header.php'; ?>
<main class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">Reset Your Password</div>
                <div class="card-body">
                    <?php if ($emailSent): ?>
                        <div class="alert alert-success">If the email is registered, a reset link has been sent.</div>
                    <?php else: ?>
                        <?php if ($errors): ?>
                            <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
                        <?php endif; ?>
                        <form method="post" action="forgot-password.php">
                            <div class="mb-3">
                                <label class="form-label">Email address</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Send Reset Link</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
