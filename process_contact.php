<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/database.php';

// Initialize variables
$response = array(
    'success' => false,
    'message' => ''
);

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $subject = isset($_POST['subject']) ? $_POST['subject'] : '';
    $message = isset($_POST['message']) ? $_POST['message'] : '';
    
    // Validate form data
    $errors = array();
    
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($subject)) {
        $errors[] = 'Subject is required';
    }
    
    if (empty($message)) {
        $errors[] = 'Message is required';
    }
    
    // If no errors, process the form
    if (empty($errors)) {
        $conn = getDbConnection();
        
        if ($conn) {
            // Sanitize inputs for database
            $name = sanitizeInput($conn, $name);
            $email = sanitizeInput($conn, $email);
            $subject = sanitizeInput($conn, $subject);
            $message = sanitizeInput($conn, $message);
            
            // Insert into database
            $sql = "INSERT INTO contact_messages (name, email, subject, message, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssss', $name, $email, $subject, $message);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Thank you for your message! We will get back to you as soon as possible.';
                
                // Send email notification to admin
                $to = 'admin@njumbihigh.ac.ke'; // Change this to the actual admin email
                $emailSubject = 'New Contact Form Submission: ' . $subject;
                $emailMessage = "You have received a new message from the contact form:\n\n";
                $emailMessage .= "Name: $name\n";
                $emailMessage .= "Email: $email\n";
                $emailMessage .= "Subject: $subject\n";
                $emailMessage .= "Message:\n$message\n";
                
                $headers = "From: $email\r\n";
                $headers .= "Reply-To: $email\r\n";
                
                // Attempt to send email
                // Note: This requires a properly configured mail server
                mail($to, $emailSubject, $emailMessage, $headers);
                
                // Store success message in session for display on redirect
                $_SESSION['contact_success'] = true;
                $_SESSION['contact_message'] = $response['message'];
            } else {
                $response['message'] = 'Error saving your message. Please try again later.';
                $_SESSION['contact_error'] = $response['message'];
            }
            
            $stmt->close();
            closeDbConnection($conn);
        } else {
            $response['message'] = 'Database connection error. Please try again later.';
            $_SESSION['contact_error'] = $response['message'];
        }
    } else {
        $response['message'] = 'Please fix the following errors: ' . implode(', ', $errors);
        $_SESSION['contact_error'] = $response['message'];
    }
    
    // If this is an AJAX request, return JSON response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        // Otherwise redirect back to contact page
        header('Location: contact.php');
        exit;
    }
} else {
    // If not a POST request, redirect to contact page
    header('Location: contact.php');
    exit;
}