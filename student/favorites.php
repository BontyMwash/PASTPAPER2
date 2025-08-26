<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 'student') {
    header('Location: ../multi_school_login.php');
    exit;
}

// Include database connection
require_once '../config/multi_school_database.php';

// Initialize variables
$conn = getDbConnection();
$schoolId = $_SESSION['school_id'];
$userId = $_SESSION['user_id'];
$favorites = [];
$totalFavorites = 0;
$limit = 12; // Papers per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get filter parameters
$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$year = isset($_GET['year']) ? $_GET['year'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query conditions
$conditions = ["p.school_id = ? AND f.user_id = ?"];
$params = [$schoolId, $userId];
$types = "ii";

if ($subjectId > 0) {
    $conditions[] = "p.subject_id = ?";
    $params[] = $subjectId;
    $types .= "i";
}

if (!empty($year)) {
    $conditions[] = "p.year = ?";
    $params[] = $year;
    $types .= "s";
}

if (!empty($search)) {
    $conditions[] = "(p.title LIKE ? OR p.description LIKE ? OR s.subject_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

$whereClause = implode(" AND ", $conditions);

// Handle AJAX favorite removal
if (isset($_POST['action']) && $_POST['action'] == 'remove_favorite' && isset($_POST['paper_id'])) {
    $paperId = (int)$_POST['paper_id'];
    
    if ($conn) {
        $sql = "DELETE FROM favorites WHERE user_id = ? AND paper_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $userId, $paperId);
        $result = $stmt->execute();
        
        if ($result) {
            // Log activity
            $activityType = 'remove_favorite';
            $description = "Student removed paper ID $paperId from favorites";
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            
            $sql = "INSERT INTO activity_logs (user_id, school_id, activity_type, description, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iisss', $userId, $schoolId, $activityType, $description, $ipAddress);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove favorite']);
        }
        exit;
    }
}

// Get favorite papers with pagination
if ($conn) {
    // Get total favorites count for pagination
    $countSql = "SELECT COUNT(*) as total 
                FROM favorites f 
                JOIN papers p ON f.paper_id = p.id 
                JOIN subjects s ON p.subject_id = s.id 
                WHERE $whereClause";
    $stmt = $conn->prepare($countSql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $totalFavorites = $row['total'];
    }
    $stmt->close();
    
    // Get favorite papers for current page
    $sql = "SELECT p.*, s.subject_name, d.department_name, u.name as uploaded_by, f.date_added 
            FROM favorites f 
            JOIN papers p ON f.paper_id = p.id 
            JOIN subjects s ON p.subject_id = s.id 
            JOIN departments d ON s.department_id = d.id 
            JOIN users u ON p.uploaded_by = u.id 
            WHERE $whereClause 
            ORDER BY f.date_added DESC 
            LIMIT ?, ?";
    
    $stmt = $conn->prepare($sql);
    $params[] = $offset;
    $params[] = $limit;
    $types .= "ii";
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $favorites[] = $row;
        }
    }
    $stmt->close();
    
    // Get all subjects for filter
    $subjects = [];
    $subjectSql = "SELECT s.*, d.department_name 
                  FROM subjects s 
                  JOIN departments d ON s.department_id = d.id 
                  WHERE s.school_id = ? 
                  ORDER BY d.department_name, s.subject_name";
    $stmt = $conn->prepare($subjectSql);
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
    }
    $stmt->close();
    
    // Log this activity
    $activityType = 'view_favorites';
    $description = "Student viewed favorite papers";
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    $sql = "INSERT INTO activity_logs (user_id, school_id, activity_type, description, ip_address) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iisss', $userId, $schoolId, $activityType, $description, $ipAddress);
    $stmt->execute();
    $stmt->close();
    
    closeDbConnection($conn);
}

// Calculate pagination
$totalPages = ceil($totalFavorites / $limit);
$prevPage = ($page > 1) ? $page - 1 : 1;
$nextPage = ($page < $totalPages) ? $page + 1 : $totalPages;

// Build pagination URL
$paginationUrl = 'favorites.php?';
if ($subjectId > 0) $paginationUrl .= "subject_id=$subjectId&";
if (!empty($year)) $paginationUrl .= "year=$year&";
if (!empty($search)) $paginationUrl .= "search=$search&";

// Set page title
$pageTitle = "My Favorites";

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>My Favorites</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">My Favorites</li>
                </ol>
            </nav>
        </div>
        <div class="col-md-6 text-end">
            <form id="searchForm" action="favorites.php" method="get" class="d-flex">
                <?php if ($subjectId > 0): ?>
                <input type="hidden" name="subject_id" value="<?php echo $subjectId; ?>">
                <?php endif; ?>
                <?php if (!empty($year)): ?>
                <input type="hidden" name="year" value="<?php echo htmlspecialchars($year); ?>">
                <?php endif; ?>
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search favorites..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <?php if (!empty($search)): ?>
                    <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="favoriteFilterForm" action="favorites.php" method="get" class="row g-3">
                <?php if (!empty($search)): ?>
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                <div class="col-md-4">
                    <label for="subject_id" class="form-label">Subject</label>
                    <select class="form-select" id="subject_id" name="subject_id">
                        <option value="">All Subjects</option>
                        <?php 
                        $currentDepartment = '';
                        foreach ($subjects as $subject):
                            if ($currentDepartment != $subject['department_name']):
                                if ($currentDepartment != ''):
                                    echo '</optgroup>';
                                endif;
                                $currentDepartment = $subject['department_name'];
                                echo '<optgroup label="' . htmlspecialchars($currentDepartment) . '">';
                            endif;
                        ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo ($subjectId == $subject['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php 
                        endforeach;
                        if ($currentDepartment != ''):
                            echo '</optgroup>';
                        endif;
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="year" class="form-label">Year</label>
                    <select class="form-select" id="year" name="year">
                        <option value="">All Years</option>
                        <?php 
                        $currentYear = date('Y');
                        for ($i = $currentYear; $i >= $currentYear - 10; $i--): 
                        ?>
                            <option value="<?php echo $i; ?>" <?php echo ($year == $i) ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                    <a href="favorites.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Favorites List -->
    <?php if (empty($favorites)): ?>
    <div class="text-center py-5">
        <div class="mb-3">
            <i class="far fa-star fa-4x text-muted"></i>
        </div>
        <h4>No Favorites Found</h4>
        <p class="text-muted">You haven't added any papers to your favorites yet</p>
        <a href="papers.php" class="btn btn-primary mt-3">
            <i class="fas fa-search me-2"></i> Browse Papers
        </a>
    </div>
    <?php else: ?>
    <div class="row" id="favorites-container">
        <?php foreach ($favorites as $paper): ?>
        <div class="col-md-4 col-lg-3 mb-4 favorite-item" data-paper-id="<?php echo $paper['id']; ?>">
            <div class="card paper-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="badge bg-primary"><?php echo htmlspecialchars($paper['year']); ?></span>
                    <button class="btn-favorite active" data-paper-id="<?php echo $paper['id']; ?>" data-bs-toggle="tooltip" title="Remove from favorites">
                        <i class="fas fa-star"></i>
                    </button>
                </div>
                <div class="card-body">
                    <h5 class="paper-title"><?php echo htmlspecialchars($paper['title']); ?></h5>
                    <div class="paper-meta">
                        <div class="mb-2">
                            <i class="fas fa-book me-1"></i>
                            <span class="paper-subject"><?php echo htmlspecialchars($paper['subject_name']); ?></span>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-building me-1"></i>
                            <span><?php echo htmlspecialchars($paper['department_name']); ?></span>
                        </div>
                        <div>
                            <i class="fas fa-calendar-alt me-1"></i>
                            <span><?php echo date('M d, Y', strtotime($paper['date_uploaded'])); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($paper['description'])): ?>
                    <p class="card-text"><?php echo htmlspecialchars(substr($paper['description'], 0, 100)) . (strlen($paper['description']) > 100 ? '...' : ''); ?></p>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="<?php echo htmlspecialchars($paper['drive_link']); ?>" target="_blank" class="btn btn-primary w-100">
                        <i class="fas fa-download me-2"></i> Download
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $paginationUrl; ?>page=1" aria-label="First">
                    <span aria-hidden="true">&laquo;&laquo;</span>
                </a>
            </li>
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $paginationUrl; ?>page=<?php echo $prevPage; ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            
            <?php 
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            if ($startPage > 1) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            
            for ($i = $startPage; $i <= $endPage; $i++): 
            ?>
            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo $paginationUrl; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; 
            
            if ($endPage < $totalPages) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            ?>
            
            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $paginationUrl; ?>page=<?php echo $nextPage; ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $paginationUrl; ?>page=<?php echo $totalPages; ?>" aria-label="Last">
                    <span aria-hidden="true">&raquo;&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Handle favorite removal
$(document).ready(function() {
    $('.btn-favorite').on('click', function() {
        const paperId = $(this).data('paper-id');
        const $favoriteItem = $(this).closest('.favorite-item');
        
        $.ajax({
            url: 'favorites.php',
            type: 'POST',
            data: {
                action: 'remove_favorite',
                paper_id: paperId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Remove the item with animation
                    $favoriteItem.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if there are no more favorites
                        if ($('#favorites-container').children().length === 0) {
                            location.reload(); // Reload to show empty state
                        }
                    });
                    
                    // Show success toast
                    showToast('Paper removed from favorites', 'success');
                } else {
                    showToast('Failed to remove from favorites', 'danger');
                }
            },
            error: function() {
                showToast('An error occurred', 'danger');
            }
        });
    });
    
    // Clear search
    $('#clearSearch').on('click', function() {
        const url = new URL(window.location.href);
        url.searchParams.delete('search');
        window.location.href = url.toString();
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>