<?php
require_once 'includes/auth.php';
requireLogin();
// Include database connection
require_once 'config/database.php';

// Get all departments with their subjects
$departments = [];
$conn = getDbConnection();

if ($conn) {
    // Get departments
    $deptSql = "SELECT id, name, description FROM departments ORDER BY name";
    $deptResult = $conn->query($deptSql);
    
    if ($deptResult && $deptResult->num_rows > 0) {
        while ($dept = $deptResult->fetch_assoc()) {
            $dept['subjects'] = [];
            $departments[$dept['id']] = $dept;
        }
        
        // Get subjects for each department
        $subjectSql = "SELECT id, department_id, name, description FROM subjects ORDER BY name";
        $subjectResult = $conn->query($subjectSql);
        
        if ($subjectResult && $subjectResult->num_rows > 0) {
            while ($subject = $subjectResult->fetch_assoc()) {
                if (isset($departments[$subject['department_id']])) {
                    $departments[$subject['department_id']]['subjects'][] = $subject;
                }
            }
        }
    }
    
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - Njumbi High School</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container my-5">
        <div class="text-center mb-5 fade-in">
            <h1 class="display-4 mb-3">Academic Departments</h1>
            <div class="row mb-4">
                <div class="col-md-8 mx-auto">
                    <p class="lead">Browse our academic departments and access past papers organized by subject.</p>
                </div>
            </div>
            <div class="col-md-6 mx-auto">
                <div class="bg-light p-2 rounded-pill mb-5">
                    <div class="d-flex justify-content-center">
                        <span class="badge bg-primary rounded-pill mx-2 px-3 py-2"><i class="fas fa-book me-1"></i> Subjects</span>
                        <span class="badge bg-success rounded-pill mx-2 px-3 py-2"><i class="fas fa-file-alt me-1"></i> Papers</span>
                        <span class="badge bg-info rounded-pill mx-2 px-3 py-2"><i class="fas fa-download me-1"></i> Downloads</span>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (empty($departments)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No departments found. Please check back later.
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php $delay = 0; foreach ($departments as $dept): ?>
                    <div class="col-md-6 fade-in" style="animation-delay: <?php echo $delay; ?>s;">
                        <div class="department-card">
                            <div class="card-header">
                                <h3 class="h5 mb-0">
                                    <i class="fas fa-graduation-cap me-2"></i>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($dept['description'])): ?>
                                    <p class="card-text"><?php echo htmlspecialchars($dept['description']); ?></p>
                                <?php endif; ?>
                                
                                <h4 class="h6 mt-4 mb-3 d-flex align-items-center">
                                    <span class="badge bg-primary me-2"><i class="fas fa-book"></i></span>
                                    Subjects:
                                </h4>
                                <?php if (empty($dept['subjects'])): ?>
                                    <div class="alert alert-light text-center py-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <span class="text-muted">No subjects found for this department.</span>
                                    </div>
                                <?php else: ?>
                                    <ul class="list-group">
                                        <?php foreach ($dept['subjects'] as $subject): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span>
                                                    <i class="fas fa-book-open text-primary me-2"></i>
                                                    <?php echo htmlspecialchars($subject['name']); ?>
                                                </span>
                                                <a href="papers.php?subject=<?php echo $subject['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-file-alt me-1"></i> View Papers
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php $delay += 0.2; endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>