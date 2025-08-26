<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get error type from URL parameter
$errorType = isset($_GET['error']) ? $_GET['error'] : 'unknown';

// Define error messages
$errorMessages = [
    'file_not_found' => [
        'title' => 'File Not Found',
        'message' => 'The requested file could not be found on the server.',
        'icon' => 'fas fa-file-excel',
        'color' => 'warning'
    ],
    'paper_not_found' => [
        'title' => 'Paper Not Found',
        'message' => 'The requested past paper does not exist or has not been approved yet.',
        'icon' => 'fas fa-file-pdf',
        'color' => 'warning'
    ],
    'db_connection' => [
        'title' => 'Database Connection Error',
        'message' => 'Unable to connect to the database. Please try again later or contact the administrator.',
        'icon' => 'fas fa-database',
        'color' => 'danger'
    ],
    'access_denied' => [
        'title' => 'Access Denied',
        'message' => 'You do not have permission to access this resource.',
        'icon' => 'fas fa-lock',
        'color' => 'danger'
    ],
    'upload_error' => [
        'title' => 'Upload Error',
        'message' => 'There was an error uploading your file. Please try again.',
        'icon' => 'fas fa-cloud-upload-alt',
        'color' => 'danger'
    ],
    'unknown' => [
        'title' => 'Unknown Error',
        'message' => 'An unknown error occurred. Please try again or contact the administrator.',
        'icon' => 'fas fa-exclamation-triangle',
        'color' => 'danger'
    ]
];

// Get error details
$error = isset($errorMessages[$errorType]) ? $errorMessages[$errorType] : $errorMessages['unknown'];

// Page title
$pageTitle = $error['title'];

// Include header
include_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-<?php echo $error['color']; ?> mb-4">
                <div class="card-header bg-<?php echo $error['color']; ?> text-white">
                    <h4><i class="<?php echo $error['icon']; ?> mr-2"></i> <?php echo $error['title']; ?></h4>
                </div>
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="<?php echo $error['icon']; ?> fa-4x text-<?php echo $error['color']; ?> mb-3"></i>
                        <h5 class="mb-3"><?php echo $error['message']; ?></h5>
                    </div>
                    
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary mr-2">
                            <i class="fas fa-home"></i> Go to Homepage
                        </a>
                        <?php if (isset($_SERVER['HTTP_REFERER'])): ?>
                        <a href="<?php echo $_SERVER['HTTP_REFERER']; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Go Back
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer text-muted">
                    <small>If you continue to experience issues, please contact the administrator.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>