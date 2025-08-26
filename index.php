<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Njumbi High School - Past Papers Repository</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container my-5">
        <section class="hero-section text-center py-5">
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <img src="assets/images/logo.svg" alt="Njumbi High School Logo" class="img-fluid mb-4 slide-in-top" style="max-width: 200px;">
                    <h1 class="display-4 text-primary fade-in">Welcome to Njumbi High School</h1>
                    <h2 class="h3 text-secondary fade-in" style="animation-delay: 0.3s;">Past Papers Repository</h2>
                    <p class="lead mt-4 fade-in" style="animation-delay: 0.6s;">A collaborative platform for teachers and students to share and access academic resources.</p>
                    <div class="mt-5 fade-in" style="animation-delay: 0.9s;">
                        <a href="login.php" class="btn btn-primary btn-lg me-3 shadow-sm"><i class="fas fa-sign-in-alt me-2"></i>Teacher Login</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="features-section py-5">
            <h2 class="text-center mb-5 fade-in">Our Platform <span class="text-primary">Features</span></h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm fade-in" style="animation-delay: 0.2s;">
                        <div class="card-body text-center p-4">
                            <div class="icon-circle mb-3">
                                <i class="fas fa-file-pdf text-primary fa-2x"></i>
                            </div>
                            <h3 class="card-title h5">Organized Resources</h3>
                            <p class="card-text">Access past papers categorized by departments and subjects for easy navigation.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm fade-in" style="animation-delay: 0.4s;">
                        <div class="card-body text-center p-4">
                            <div class="icon-circle mb-3">
                                <i class="fas fa-users text-primary fa-2x"></i>
                            </div>
                            <h3 class="card-title h5">Teacher Collaboration</h3>
                            <p class="card-text">Share and access resources uploaded by other teachers to enhance learning.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm fade-in" style="animation-delay: 0.6s;">
                        <div class="card-body text-center p-4">
                            <div class="icon-circle mb-3">
                                <i class="fas fa-search text-primary fa-2x"></i>
                            </div>
                            <h3 class="card-title h5">Easy Search</h3>
                            <p class="card-text">Find specific past papers quickly using our search and filter functionality.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>