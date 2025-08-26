<?php
// Include database connection
require_once 'config/database.php';

// Test database connection
echo "<h2>Testing Database Connection</h2>";

$conn = getDbConnection();

if ($conn) {
    echo "<p style='color: green;'>Database connection successful!</p>";
    
    // Check if the database has the required tables
    $tables = ['departments', 'subjects', 'users', 'papers', 'activity_logs'];
    $missing_tables = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '{$table}'")->num_rows;
        if ($result == 0) {
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        echo "<p style='color: green;'>All required tables exist.</p>";
    } else {
        echo "<p style='color: red;'>Missing tables: " . implode(", ", $missing_tables) . "</p>";
        echo "<p>Please import the database schema from config/database.sql</p>";
    }
    
    // Check if admin user exists
    $sql = "SELECT id, name, email, password FROM users WHERE email = 'admin@njumbi.ac.ke'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "<p style='color: green;'>Admin user found: {$user['name']} ({$user['email']})</p>";
        
        // Test password verification
        $test_password = 'admin123';
        if (password_verify($test_password, $user['password'])) {
            echo "<p style='color: green;'>Password verification successful!</p>";
        } else {
            echo "<p style='color: red;'>Password verification failed. The stored hash doesn't match 'admin123'.</p>";
            echo "<p>Current password hash: {$user['password']}</p>";
            
            // Generate a new hash for admin123
            $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
            echo "<p>New hash for 'admin123': {$new_hash}</p>";
            echo "<p>You may need to update the password hash in the database.</p>";
        }
    } else {
        echo "<p style='color: red;'>Admin user not found. Please check if the database was imported correctly.</p>";
    }
    
    closeDbConnection($conn);
} else {
    echo "<p style='color: red;'>Database connection failed!</p>";
    echo "<p>Please check your database settings in config/database.php and make sure the database exists.</p>";
}
?>