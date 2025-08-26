<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize variables for form data
$name = $email = $subject = $message = '';

// Check for success or error messages
$success_message = '';
$error_message = '';

if (isset($_SESSION['contact_success'])) {
    $success_message = $_SESSION['contact_success'];
    unset($_SESSION['contact_success']);
}

if (isset($_SESSION['contact_error'])) {
    $error_message = $_SESSION['contact_error'];
    unset($_SESSION['contact_error']);
}

// Page title
$pageTitle = "Contact Us";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Njumbi High School - Contact Us</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<main class="container my-5">
    <div class="text-center mb-5 fade-in">
        <h1 class="display-4 mb-3">Contact Us</h1>
        <div class="row mb-4">
            <div class="col-md-8 mx-auto">
                <p class="lead">Have questions about our past papers repository? We're here to help!</p>
            </div>
        </div>
    </div>
    
    <div class="row">
            
        <div class="col-lg-8 mx-auto">
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card border-0 shadow-sm fade-in" style="animation-delay: 0.3s; border-top: 4px solid var(--primary-color) !important;">
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-5 mb-4 mb-md-0">
                            <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-info-circle me-2 text-primary"></i>Get In Touch</h5>
                            
                            <div class="mt-4">
                                <div class="d-flex mb-3 hover-effect p-2 rounded">
                                    <div class="contact-icon me-3">
                                        <i class="fas fa-map-marker-alt text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold">Address</h6>
                                        <p class="mb-0 text-muted">P.O. Box 123, Njumbi</p>
                                    </div>
                                </div>
                                
                                <div class="d-flex mb-3 hover-effect p-2 rounded">
                                    <div class="contact-icon me-3">
                                        <i class="fas fa-phone text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold">Phone</h6>
                                        <p class="mb-0 text-muted">+254 123 456 789</p>
                                    </div>
                                </div>
                                
                                <div class="d-flex hover-effect p-2 rounded">
                                    <div class="contact-icon me-3">
                                        <i class="fas fa-envelope text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold">Email</h6>
                                        <p class="mb-0 text-muted">info@njumbihigh.ac.ke</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-7">
                            <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-paper-plane me-2 text-primary"></i>Send Us a Message</h5>
                            <form method="POST" action="process_contact.php" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="name" class="form-label fw-medium">Your Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-primary text-white"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="name" name="name" placeholder="Enter your name" required>
                                    </div>
                                    <div class="invalid-feedback">Please enter your name.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label fw-medium">Your Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-primary text-white"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                                    </div>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label fw-medium">Subject</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-primary text-white"><i class="fas fa-heading"></i></span>
                                        <input type="text" class="form-control" id="subject" name="subject" placeholder="Enter subject" required>
                                    </div>
                                    <div class="invalid-feedback">Please enter a subject.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="message" class="form-label fw-medium">Message</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-primary text-white"><i class="fas fa-comment"></i></span>
                                        <textarea class="form-control" id="message" name="message" rows="5" placeholder="Enter your message" required></textarea>
                                    </div>
                                    <div class="invalid-feedback">Please enter your message.</div>
                                </div>
                                
                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i>Send Message
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <script>
                        // Form validation script
                        (function() {
                            'use strict';
                            
                            // Fetch all forms that need validation
                            var forms = document.querySelectorAll('.needs-validation');
                            
                            // Loop over them and prevent submission
                            Array.prototype.slice.call(forms).forEach(function(form) {
                                form.addEventListener('submit', function(event) {
                                    if (!form.checkValidity()) {
                                        event.preventDefault();
                                        event.stopPropagation();
                                    }
                                    
                                    form.classList.add('was-validated');
                                }, false);
                            });
                        })();
                        </script>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    <!-- FAQ Section -->
    <div class="row mt-5">
        <div class="col-lg-8 mx-auto">
            <div class="text-center mb-4 fade-in" style="animation-delay: 0.6s;">
                <h2 class="display-5 mb-3">Frequently Asked Questions</h2>
                <p class="lead">Find answers to common questions about our past papers repository.</p>
            </div>
            <div class="accordion fade-in shadow-sm" id="faqAccordion" style="animation-delay: 0.7s;">
                <div class="accordion-item border-0 mb-3 shadow-sm">
                    <h2 class="accordion-header" id="headingOne">
                        <button class="accordion-button rounded" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                            <i class="fas fa-question-circle text-primary me-2"></i> How do I access past papers?
                        </button>
                    </h2>
                    <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                        <div class="accordion-body bg-light rounded-bottom">
                            <p>You can access past papers by navigating to the <strong>Departments</strong> section, selecting your subject of interest, and browsing through the available papers.</p>
                            <div class="bg-white p-3 rounded border-start border-primary border-3">
                                <p class="mb-0"><i class="fas fa-lightbulb text-warning me-2"></i> <em>If you're a registered user, you can also upload your own papers to contribute to our repository.</em></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="accordion-item border-0 mb-3 shadow-sm">
                    <h2 class="accordion-header" id="headingTwo">
                        <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                            <i class="fas fa-user-plus text-primary me-2"></i> How do I create an account?
                        </button>
                    </h2>
                    <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                        <div class="accordion-body bg-light rounded-bottom">
                            <p>Teacher accounts are created by the school administration. If you're a teacher at Njumbi High School and need an account, please contact the ICT department or the school administrator to set up your credentials.</p>
                            <div class="bg-white p-3 rounded border-start border-secondary border-3">
                                <p class="mb-0"><i class="fas fa-info-circle text-info me-2"></i> <em>For account-related inquiries, you can also use the contact form above to reach our administrative team.</em></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="accordion-item border-0 mb-3 shadow-sm">
                    <h2 class="accordion-header" id="headingThree">
                        <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                            <i class="fas fa-upload text-primary me-2"></i> Can I upload my own papers?
                        </button>
                    </h2>
                    <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                        <div class="accordion-body bg-light rounded-bottom">
                            <p>Yes, registered teachers can upload past papers. After logging in, navigate to the <strong>Upload Papers</strong> section, fill in the required details, and upload your PDF file.</p>
                            <div class="bg-white p-3 rounded border-start border-success border-3">
                                <p class="mb-0"><i class="fas fa-check-circle text-success me-2"></i> <em>Your contribution will help build our repository for the benefit of all students.</em></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="accordion-item border-0 mb-3 shadow-sm">
                    <h2 class="accordion-header" id="headingFour">
                        <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                            <i class="fas fa-search text-primary me-2"></i> How do I find specific papers?
                        </button>
                    </h2>
                    <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#faqAccordion">
                        <div class="accordion-body bg-light rounded-bottom">
                            <p>You can use the search bar at the top of the page to find specific papers by keywords, subject, or year. Additionally, you can browse through departments and subjects to find the papers you need.</p>
                            <div class="bg-white p-3 rounded border-start border-warning border-3">
                                <p class="mb-0"><i class="fas fa-lightbulb text-warning me-2"></i> <em>Try using specific keywords like subject codes or exam years for more accurate search results.</em></p>
                            </div>
                        </div>
                    </div>
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