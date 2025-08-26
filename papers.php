<?php
// Include database connection
require_once 'config/database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if subject ID is provided
if (!isset($_GET['subject']) || !is_numeric($_GET['subject'])) {
    header('Location: departments.php');
    exit;
}

$subjectId = intval($_GET['subject']);
$subject = null;
$department = null;
$papers = [];

// Get current page for pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10; // Items per page
$offset = ($page - 1) * $limit;

// Get filter values
$yearFilter = isset($_GET['year']) && is_numeric($_GET['year']) ? intval($_GET['year']) : 0;
$termFilter = isset($_GET['term']) && in_array($_GET['term'], ['1', '2', '3']) ? $_GET['term'] : '';

// Get database connection
$conn = getDbConnection();

if ($conn) {
    // Get subject details
    $subjectSql = "SELECT s.id, s.name, s.description, d.id as department_id, d.name as department_name 
                   FROM subjects s 
                   JOIN departments d ON s.department_id = d.id 
                   WHERE s.id = ?";
    $subjectStmt = $conn->prepare($subjectSql);
    $subjectStmt->bind_param('i', $subjectId);
    $subjectStmt->execute();
    $subjectResult = $subjectStmt->get_result();
    
    if ($subjectResult && $subjectResult->num_rows > 0) {
        $row = $subjectResult->fetch_assoc();
        $subject = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description']
        ];
        $department = [
            'id' => $row['department_id'],
            'name' => $row['department_name']
        ];
        
        // Build query for papers
        $papersSql = "SELECT p.id, p.title, p.description, p.file_name, p.file_path, p.file_size, 
                      p.year, p.term, p.download_count, p.created_at, u.name as uploaded_by 
                      FROM papers p 
                      JOIN users u ON p.uploaded_by = u.id 
                      WHERE p.subject_id = ? AND p.status = 'approved'";
        $params = [$subjectId];
        $types = 'i';
        
        // Add filters if set
        if ($yearFilter > 0) {
            $papersSql .= " AND p.year = ?";
            $params[] = $yearFilter;
            $types .= 'i';
        }
        
        if (!empty($termFilter)) {
            $papersSql .= " AND p.term = ?";
            $params[] = $termFilter;
            $types .= 's';
        }
        
        // Count total papers for pagination
        $countSql = str_replace('SELECT p.id, p.title, p.description, p.file_name, p.file_path, p.file_size, 
                      p.year, p.term, p.download_count, p.created_at, u.name as uploaded_by', 'SELECT COUNT(*)', $papersSql);
        $countStmt = $conn->prepare($countSql);
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalPapers = $countResult->fetch_row()[0];
        $totalPages = ceil($totalPapers / $limit);
        
        // Add pagination to query
        $papersSql .= " ORDER BY p.year DESC, p.term DESC, p.created_at DESC LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= 'ii';
        
        // Get papers
        $papersStmt = $conn->prepare($papersSql);
        $papersStmt->bind_param($types, ...$params);
        $papersStmt->execute();
        $papersResult = $papersStmt->get_result();
        
        if ($papersResult && $papersResult->num_rows > 0) {
            while ($paper = $papersResult->fetch_assoc()) {
                $papers[] = $paper;
            }
        }
        
        $papersStmt->close();
        $countStmt->close();
    }
    
    $subjectStmt->close();
}

// Get available years for filter
$years = [];
foreach ($papers as $paper) {
    if (!in_array($paper['year'], $years)) {
        $years[] = $paper['year'];
    }
}
rsort($years);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $subject ? htmlspecialchars($subject['name']) : 'Subject'; ?> Past Papers - Njumbi High School</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container my-5">
        <?php if (!$subject): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> Subject not found.
            </div>
            <div class="text-center mt-4">
                <a href="departments.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Departments
                </a>
            </div>
        <?php else: ?>
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="departments.php">Departments</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($subject['name']); ?></li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
                <div>
                    <h1 class="text-primary"><?php echo htmlspecialchars($subject['name']); ?> Past Papers</h1>
                    <p class="text-muted"><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($department['name']); ?> Department</p>
                </div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="upload.php" class="btn btn-primary btn-lg shadow-sm">
                        <i class="fas fa-upload me-2"></i> Upload Paper
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($subject['description'])): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">About this Subject</h5>
                        <p class="card-text"><?php echo htmlspecialchars($subject['description']); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="card mb-4 shadow-sm slide-in-left">
                <div class="card-body">
                    <h5 class="card-title text-primary"><i class="fas fa-filter me-2"></i>Filter Papers</h5>
                    <form action="papers.php" method="GET" class="row g-3">
                        <input type="hidden" name="subject" value="<?php echo $subjectId; ?>">
                        
                        <div class="col-md-4">
                            <label for="year" class="form-label">Year</label>
                            <select class="form-select" id="year" name="year">
                                <option value="">All Years</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo ($yearFilter == $year) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="term" class="form-label">Term</label>
                            <select class="form-select" id="term" name="term">
                                <option value="">All Terms</option>
                                <option value="1" <?php echo ($termFilter == '1') ? 'selected' : ''; ?>>Term 1</option>
                                <option value="2" <?php echo ($termFilter == '2') ? 'selected' : ''; ?>>Term 2</option>
                                <option value="3" <?php echo ($termFilter == '3') ? 'selected' : ''; ?>>Term 3</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                            
                            <?php if ($yearFilter || $termFilter): ?>
                                <a href="papers.php?subject=<?php echo $subjectId; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i> Clear Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Papers List -->
            <?php if (empty($papers)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No past papers found for this subject.
                    <?php if ($yearFilter || $termFilter): ?>
                        Try clearing your filters or check back later.
                    <?php else: ?>
                        Check back later or upload a paper if you're a teacher.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php $delay = 0; foreach ($papers as $paper): $delay += 0.1; ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="file-card fade-in" style="animation-delay: <?php echo $delay; ?>s;">
                                <div class="p-4 text-center">
                                    <?php 
                                    $fileExt = strtolower(pathinfo($paper['file_name'], PATHINFO_EXTENSION));
                                    $iconClass = 'fa-file-alt text-secondary';
                                    
                                    if ($fileExt === 'pdf') {
                                        $iconClass = 'fa-file-pdf text-danger';
                                    } elseif (in_array($fileExt, ['doc', 'docx'])) {
                                        $iconClass = 'fa-file-word text-primary';
                                    } elseif (in_array($fileExt, ['xls', 'xlsx'])) {
                                        $iconClass = 'fa-file-excel text-success';
                                    } elseif (in_array($fileExt, ['ppt', 'pptx'])) {
                                        $iconClass = 'fa-file-powerpoint text-warning';
                                    }
                                    ?>
                                    <i class="fas <?php echo $iconClass; ?> file-icon mb-3"></i>
                                    <h3 class="h5"><?php echo htmlspecialchars($paper['title']); ?></h3>
                                </div>
                                
                                <div class="file-info">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><i class="fas fa-calendar-alt me-2"></i> Year: <?php echo $paper['year']; ?></span>
                                        <span><i class="fas fa-list-ol me-2"></i> Term: <?php echo $paper['term']; ?></span>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><i class="fas fa-user me-2"></i> By: <?php echo htmlspecialchars($paper['uploaded_by']); ?></span>
                                        <span><i class="fas fa-download me-2"></i> <?php echo $paper['download_count']; ?> downloads</span>
                                    </div>
                                    
                                    <?php if (!empty($paper['description'])): ?>
                                        <p class="text-muted small mt-2"><?php echo htmlspecialchars($paper['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="file-actions">
                                    <div class="d-grid">
                                        <a href="download.php?id=<?php echo $paper['id']; ?>" class="btn btn-success shadow-sm">
                                            <i class="fas fa-download me-2"></i> Download Paper
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-5 fade-in">
                        <ul class="pagination pagination-lg justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="papers.php?subject=<?php echo $subjectId; ?>&page=<?php echo $page - 1; ?><?php echo $yearFilter ? '&year=' . $yearFilter : ''; ?><?php echo $termFilter ? '&term=' . $termFilter : ''; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="papers.php?subject=<?php echo $subjectId; ?>&page=<?php echo $i; ?><?php echo $yearFilter ? '&year=' . $yearFilter : ''; ?><?php echo $termFilter ? '&term=' . $termFilter : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="papers.php?subject=<?php echo $subjectId; ?>&page=<?php echo $page + 1; ?><?php echo $yearFilter ? '&year=' . $yearFilter : ''; ?><?php echo $termFilter ? '&term=' . $termFilter : ''; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
<?php
// Close database connection after all includes have been processed
if (isset($conn)) {
    closeDbConnection($conn);
}
?>