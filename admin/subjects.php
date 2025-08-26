<?php
// admin/subjects.php – manage subjects
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../config/database.php';
$conn = getDbConnection();
if (!$conn) {
    die('DB error');
}

$message = '';
$messageType = '';
$editSubject = null;

// fetch departments list for dropdown
$deptResult = $conn->query('SELECT id, name FROM departments ORDER BY name');
$departments = $deptResult ? $deptResult->fetch_all(MYSQLI_ASSOC) : [];

// handle form posts
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add_subject', 'edit_subject'])) {
        $name = sanitizeInput($conn, $_POST['subject_name'] ?? '');
        $departmentId = intval($_POST['department_id'] ?? 0);
        if ($name === '' || $departmentId <= 0) {
            $message = 'Subject name and department are required.';
            $messageType = 'danger';
        } else {
            // uniqueness check
            $query = $action === 'add_subject'
                ? 'SELECT id FROM subjects WHERE name=? AND department_id=?'
                : 'SELECT id FROM subjects WHERE name=? AND department_id=? AND id != ?';
            $stmt = $conn->prepare($query);
            if ($action === 'add_subject') {
                $stmt->bind_param('si', $name, $departmentId);
            } else {
                $subjectId = intval($_POST['subject_id']);
                $stmt->bind_param('sii', $name, $departmentId, $subjectId);
            }
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if ($exists) {
                $message = 'Subject already exists in that department.';
                $messageType = 'danger';
            } else {
                if ($action === 'add_subject') {
                    $stmt = $conn->prepare('INSERT INTO subjects (name, department_id) VALUES (?,?)');
                    $stmt->bind_param('si', $name, $departmentId);
                    $stmt->execute();
                    $message = $stmt->affected_rows ? 'Subject added.' : 'Error adding subject.';
                    $messageType = $stmt->affected_rows ? 'success' : 'danger';
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare('UPDATE subjects SET name=?, department_id=? WHERE id=?');
                    $stmt->bind_param('sii', $name, $departmentId, $subjectId);
                    $stmt->execute();
                    $message = $stmt->affected_rows ? 'Subject updated.' : 'No changes made.';
                    $messageType = $stmt->affected_rows ? 'success' : 'info';
                    $stmt->close();
                }
            }
        }
    } elseif ($action === 'delete_subject' && isset($_POST['subject_id'])) {
        $subjectId = intval($_POST['subject_id']);
        // ensure no papers depend
        $stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM papers WHERE subject_id=?');
        $stmt->bind_param('i', $subjectId);
        $stmt->execute();
        $cnt = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        if ($cnt > 0) {
            $message = 'Cannot delete subject that has papers.';
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare('DELETE FROM subjects WHERE id=?');
            $stmt->bind_param('i', $subjectId);
            $stmt->execute();
            $message = $stmt->affected_rows ? 'Subject deleted.' : 'Error deleting subject.';
            $messageType = $stmt->affected_rows ? 'success' : 'danger';
            $stmt->close();
        }
    }
}

// handle edit request
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $stmt = $conn->prepare('SELECT id,name,department_id FROM subjects WHERE id=?');
    $stmt->bind_param('i', $_GET['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $editSubject = $res && $res->num_rows ? $res->fetch_assoc() : null;
    $stmt->close();
}

// fetch all subjects with department
$subSql = 'SELECT s.id, s.name, d.name AS dept_name,
                  (SELECT COUNT(*) FROM papers WHERE subject_id=s.id) AS paper_count
           FROM subjects s JOIN departments d ON s.department_id=d.id
           ORDER BY d.name, s.name';
$subs = $conn->query($subSql)->fetch_all(MYSQLI_ASSOC);

closeDbConnection($conn);
$pageTitle = 'Manage Subjects';
include_once 'includes/header.php';
?>
<div class="container mt-4">
    <h2><i class="fas fa-book"></i> Manage Subjects</h2>
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><?= $editSubject ? 'Edit Subject' : 'Add Subject' ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" action="subjects.php">
                        <input type="hidden" name="action" value="<?= $editSubject ? 'edit_subject' : 'add_subject' ?>">
                        <?php if ($editSubject): ?>
                        <input type="hidden" name="subject_id" value="<?= $editSubject['id'] ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Subject Name</label>
                            <input type="text" name="subject_name" class="form-control" value="<?= htmlspecialchars($editSubject['name'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select name="department_id" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>" <?= isset($editSubject) && $editSubject['department_id']==$dept['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><?= $editSubject ? 'Update' : 'Add' ?></button>
                        <?php if ($editSubject): ?>
                        <a href="subjects.php" class="btn btn-secondary ms-2">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>All Subjects</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Subject</th>
                                <th>Department</th>
                                <th>Papers</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subs)): ?>
                                <tr><td colspan="4" class="text-center">No subjects found.</td></tr>
                            <?php else: foreach ($subs as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['name']) ?></td>
                                    <td><?= htmlspecialchars($s['dept_name']) ?></td>
                                    <td><?= $s['paper_count'] ?></td>
                                    <td>
                                        <a href="subjects.php?action=edit&id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                        <form method="post" action="subjects.php" class="d-inline" onsubmit="return confirm('Delete this subject?');">
                                            <input type="hidden" name="action" value="delete_subject">
                                            <input type="hidden" name="subject_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
