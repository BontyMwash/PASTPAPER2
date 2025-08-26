<?php
// profile.php - User profile page
session_start();

// Redirect non-logged in users to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();
if (!$conn) {
    die('Database connection error');
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT name, email, is_admin, department_id, created_at, last_login, status FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
closeDbConnection($conn);

if (!$user) {
    die('User record not found');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile – <?php echo htmlspecialchars($user['name']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<main class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Your Profile</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Name</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($user['name']); ?></dd>

                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($user['email']); ?></dd>

                        <dt class="col-sm-4">Role</dt>
                        <dd class="col-sm-8"><?php echo $user['is_admin'] ? 'Administrator' : 'Teacher'; ?></dd>

                        <dt class="col-sm-4">Account Status</dt>
                        <dd class="col-sm-8"><?php echo ucfirst($user['status']); ?></dd>

                        <dt class="col-sm-4">Member Since</dt>
                        <dd class="col-sm-8"><?php echo date('d M Y', strtotime($user['created_at'])); ?></dd>

                        <dt class="col-sm-4">Last Login</dt>
                        <dd class="col-sm-8"><?php echo $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : '—'; ?></dd>
                    </dl>
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
