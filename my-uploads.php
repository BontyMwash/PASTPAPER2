<?php
// my-uploads.php – list of papers uploaded by the logged-in user
session_start();
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
$sql = "SELECT p.id, p.title, p.file_name, p.created_at, p.status, p.download_count,
               s.name  AS subject_name,
               d.name  AS department_name,
               p.year, p.term
        FROM papers p
        JOIN subjects s    ON p.subject_id = s.id
        JOIN departments d ON p.department_id = d.id
        WHERE p.uploaded_by = ?
        ORDER BY p.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$uploads = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
closeDbConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Uploads</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<main class="container my-5">
    <h1 class="mb-4"><i class="fas fa-file-upload me-2"></i>My Uploads</h1>

    <?php if (empty($uploads)): ?>
        <div class="alert alert-info">
            You haven't uploaded any papers yet.
        </div>
    <?php else: ?>
        <div class="table-responsive shadow-sm">
            <table class="table table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>Title</th>
                        <th>Subject</th>
                        <th>Department</th>
                        <th>Year</th>
                        <th>Term</th>
                        <th>Status</th>
                        <th>Downloads</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uploads as $paper): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($paper['title']); ?></td>
                            <td><?php echo htmlspecialchars($paper['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($paper['department_name']); ?></td>
                            <td><?php echo $paper['year'] ?: '—'; ?></td>
                            <td><?php echo $paper['term'] ?: '—'; ?></td>
                            <td><span class="badge bg-<?php echo $paper['status'] === 'approved' ? 'success' : ($paper['status'] === 'pending' ? 'warning text-dark' : 'danger'); ?>">
                                <?php echo ucfirst($paper['status']); ?></span></td>
                            <td><?php echo $paper['download_count']; ?></td>
                            <td>
                                <a href="download.php?id=<?php echo $paper['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
