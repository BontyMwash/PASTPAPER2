<?php
// search.php – search results page
require_once 'includes/auth.php';
requireLogin();
require_once __DIR__ . '/config/database.php';

// Get query string
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    header('Location: index.php');
    exit();
}

$conn = getDbConnection();
if (!$conn) {
    die('Database error');
}

// Use wildcard for LIKE search
$like = '%' . $conn->real_escape_string($q) . '%';
$sql = "SELECT p.id, p.title, p.description, p.year, p.term, p.download_count,
               s.name AS subject_name, d.name AS department_name
        FROM papers p
        JOIN subjects s    ON p.subject_id = s.id
        JOIN departments d ON p.department_id = d.id
        WHERE p.status = 'approved' AND (p.title LIKE ? OR p.description LIKE ?)
        ORDER BY p.year DESC, p.title ASC
        LIMIT 50";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
closeDbConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search – <?php echo htmlspecialchars($q); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<main class="container my-5">
    <h1 class="mb-4"><i class="fas fa-search me-2"></i>Search Results for "<?php echo htmlspecialchars($q); ?>"</h1>

    <?php if (empty($results)): ?>
        <div class="alert alert-warning">No papers found matching your search.</div>
    <?php else: ?>
        <div class="list-group shadow-sm">
            <?php foreach ($results as $paper): ?>
            <div class="list-group-item list-group-item-action flex-column align-items-start">
                <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1"><?php echo htmlspecialchars($paper['title']); ?></h5>
                    <small><?php echo $paper['year'] ?: 'Year ?'; ?><?php echo $paper['term'] ? ' Term ' . $paper['term'] : ''; ?></small>
                </div>
                <p class="mb-1 text-truncate"><?php echo htmlspecialchars($paper['description']); ?></p>
                <small class="text-muted">Subject: <?php echo htmlspecialchars($paper['subject_name']); ?> | Department: <?php echo htmlspecialchars($paper['department_name']); ?> | Downloads: <?php echo $paper['download_count']; ?></small>
                <div class="mt-2">
                    <a href="download.php?id=<?php echo $paper['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-download me-1"></i>Download</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
