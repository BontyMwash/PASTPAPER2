<?php
// Include database connection
require_once 'config/database.php';

// Generate a new password hash
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Admin Password Fix</h2>";
echo "<p>Generated hash for 'admin123': {$hash}</p>";

// Update the admin user password
$conn = getDbConnection();

if ($conn) {
    // Check if admin user exists
    $check_sql = "SELECT id FROM users WHERE email = 'admin@njumbi.ac.ke'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        // Update existing admin user
        $user = $check_result->fetch_assoc();
        $update_sql = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('si', $hash, $user['id']);
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>Admin password updated successfully!</p>";
            echo "<p>You can now log in with:</p>";
            echo "<p>Email: admin@njumbi.ac.ke</p>";
            echo "<p>Password: admin123</p>";
        } else {
            echo "<p style='color: red;'>Failed to update admin password: {$stmt->error}</p>";
        }
        
        $stmt->close();
    } else {
        // Create new admin user if it doesn't exist
        $insert_sql = "INSERT INTO users (name, email, password, is_admin) VALUES (?, ?, ?, 1)";
        $stmt = $conn->prepare($insert_sql);
        $name = "Admin User";
        $email = "admin@njumbi.ac.ke";
        $stmt->bind_param('sss', $name, $email, $hash);
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>Admin user created successfully!</p>";
            echo "<p>You can now log in with:</p>";
            echo "<p>Email: admin@njumbi.ac.ke</p>";
            echo "<p>Password: admin123</p>";
        } else {
            echo "<p style='color: red;'>Failed to create admin user: {$stmt->error}</p>";
        }
        
        $stmt->close();
    }
    
    closeDbConnection($conn);
} else {
    echo "<p style='color: red;'>Database connection failed!</p>";
    echo "<p>Please check your database settings in config/database.php and make sure the database exists.</p>";
}

echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>