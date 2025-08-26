<?php
// reset-password.php – handle token and set new password
require_once __DIR__ . '/config/database.php';

$token = $_GET['token'] ?? '';
$errors = [];
$success = false;

if ($token === '') {
    die('Invalid token.');
}

$conn = getDbConnection();
if (!$conn) {
    die('Database error');
}

$stmt = $conn->prepare('SELECT id, reset_expires FROM users WHERE reset_token=?');
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
$user = $res && $res->num_rows ? $res->fetch_assoc() : null;
$stmt->close();

if (!$user || strtotime($user['reset_expires']) < time()) {
    closeDbConnection($conn);
    die('Token expired or invalid.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['password'] ?? '';
    $pass2 = $_POST['confirm_password'] ?? '';
    if (strlen($pass1) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($pass1 !== $pass2) {
        $errors[] = 'Passwords do not match.';
    }
    if (!$errors) {
        $hash = password_hash($pass1, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?');
        $stmt->bind_param('si', $hash, $user['id']);
        $stmt->execute();
        $stmt->close();
        $success = true;
    }
}
closeDbConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password</title>
<link rel="stylesheet" href="assets/css/style.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/header.php'; ?>
<main class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">Set New Password</div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">Password updated successfully. You can now <a href="login.php">login</a>.</div>
                    <?php else: ?>
                        <?php if ($errors): ?>
                        <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
                        <?php endif; ?>
                        <form method="post" action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>">
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Reset Password</button>
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
