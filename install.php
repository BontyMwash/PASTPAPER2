<?php
// Installation script for Multi-School Past Papers Repository

// Start session
session_start();

// Define constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'multi_school_papers');

// Initialize variables
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$message = '';
$messageType = '';
$installComplete = false;

// Function to check database connection
function checkDbConnection($host, $user, $pass) {
    try {
        $conn = new mysqli($host, $user, $pass);
        if ($conn->connect_error) {
            return false;
        }
        $conn->close();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Function to check if database exists
function databaseExists($host, $user, $pass, $dbName) {
    try {
        $conn = new mysqli($host, $user, $pass);
        if ($conn->connect_error) {
            return false;
        }
        
        $result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'");
        $exists = ($result->num_rows > 0);
        
        $conn->close();
        return $exists;
    } catch (Exception $e) {
        return false;
    }
}

// Function to create database
function createDatabase($host, $user, $pass, $dbName) {
    try {
        $conn = new mysqli($host, $user, $pass);
        if ($conn->connect_error) {
            return false;
        }
        
        $result = $conn->query("CREATE DATABASE IF NOT EXISTS $dbName");
        $conn->close();
        return $result;
    } catch (Exception $e) {
        return false;
    }
}

// Function to import SQL file
function importSqlFile($host, $user, $pass, $dbName, $sqlFile) {
    try {
        // Check if file exists
        if (!file_exists($sqlFile)) {
            return "SQL file not found: $sqlFile";
        }
        
        // Read SQL file
        $sql = file_get_contents($sqlFile);
        if (!$sql) {
            return "Could not read SQL file: $sqlFile";
        }
        
        // Connect to database
        $conn = new mysqli($host, $user, $pass, $dbName);
        if ($conn->connect_error) {
            return "Database connection failed: " . $conn->connect_error;
        }
        
        // Execute SQL queries
        if ($conn->multi_query($sql)) {
            // Process all result sets
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
        }
        
        // Check for errors
        if ($conn->error) {
            return "SQL execution error: " . $conn->error;
        }
        
        $conn->close();
        return true;
    } catch (Exception $e) {
        return "Exception: " . $e->getMessage();
    }
}

// Function to create config file
function createConfigFile($host, $user, $pass, $dbName, $configFile) {
    try {
        $config = "<?php\n/**\n * Multi-School Database Configuration File\n * \n * This file contains the database connection settings for the Multi-School Past Papers Repository.\n */\n\n// Database credentials\ndefine('DB_HOST', '$host');              // Database host\ndefine('DB_USER', '$user');                   // Database username\ndefine('DB_PASS', '$pass');                       // Database password\ndefine('DB_NAME', '$dbName');    // Database name\n\n// Google Drive API credentials\ndefine('GOOGLE_CLIENT_ID', '');              // Google API Client ID\ndefine('GOOGLE_CLIENT_SECRET', '');          // Google API Client Secret\ndefine('GOOGLE_REDIRECT_URI', '');           // Google API Redirect URI\n\n/**\n * Establishes a connection to the database\n * \n * @return mysqli|false Returns a mysqli connection object or false on failure\n */\nfunction getDbConnection() {\n    // Create connection\n    \$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);\n    \n    // Check connection\n    if (\$conn->connect_error) {\n        // Log error (in a production environment, you would log this to a file)\n        error_log('Database connection failed: ' . \$conn->connect_error);\n        return false;\n    }\n    \n    // Set charset to ensure proper encoding\n    \$conn->set_charset('utf8mb4');\n    \n    return \$conn;\n}\n\n/**\n * Closes a database connection\n * \n * @param mysqli \$conn The database connection to close\n */\nfunction closeDbConnection(\$conn) {\n    if (\$conn) {\n        \$conn->close();\n    }\n}\n\n/**\n * Sanitizes user input to prevent SQL injection\n * \n * @param mysqli \$conn The database connection\n * @param string \$input The input to sanitize\n * @return string The sanitized input\n */\nfunction sanitizeInput(\$conn, \$input) {\n    if (is_array(\$input)) {\n        return array_map(function(\$item) use (\$conn) {\n            return sanitizeInput(\$conn, \$item);\n        }, \$input);\n    }\n    return \$conn ? \$conn->real_escape_string(\$input) : htmlspecialchars(\$input);\n}\n\n/**\n * Creates a new folder in Google Drive for a school\n * \n * @param string \$schoolName The name of the school\n * @return string The ID of the created folder\n */\nfunction createSchoolDriveFolder(\$schoolName) {\n    // This is a placeholder function. In a real implementation, you would use the Google Drive API\n    // to create a folder and return its ID.\n    return 'placeholder_folder_id';\n}\n";
        
        // Write config file
        if (file_put_contents($configFile, $config) === false) {
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 1: Database Configuration
    if ($step === 1 && isset($_POST['db_host'])) {
        $dbHost = trim($_POST['db_host']);
        $dbUser = trim($_POST['db_user']);
        $dbPass = $_POST['db_pass']; // Don't trim password as it might contain spaces
        $dbName = trim($_POST['db_name']);
        
        // Validate inputs
        if (empty($dbHost) || empty($dbUser) || empty($dbName)) {
            $message = 'All fields except password are required.';
            $messageType = 'danger';
        } else {
            // Check database connection
            if (!checkDbConnection($dbHost, $dbUser, $dbPass)) {
                $message = 'Could not connect to database server. Please check your credentials.';
                $messageType = 'danger';
            } else {
                // Check if database exists
                $dbExists = databaseExists($dbHost, $dbUser, $dbPass, $dbName);
                
                if (!$dbExists) {
                    // Create database
                    if (!createDatabase($dbHost, $dbUser, $dbPass, $dbName)) {
                        $message = 'Could not create database. Please check your permissions.';
                        $messageType = 'danger';
                    } else {
                        $message = 'Database created successfully.';
                        $messageType = 'success';
                    }
                } else {
                    $message = 'Database already exists.';
                    $messageType = 'warning';
                }
                
                // Store database configuration in session
                $_SESSION['db_host'] = $dbHost;
                $_SESSION['db_user'] = $dbUser;
                $_SESSION['db_pass'] = $dbPass;
                $_SESSION['db_name'] = $dbName;
                
                // Move to next step
                $step = 2;
            }
        }
    }
    
    // Step 2: Database Import
    if ($step === 2 && isset($_POST['import_db'])) {
        // Get database configuration from session
        $dbHost = $_SESSION['db_host'] ?? DB_HOST;
        $dbUser = $_SESSION['db_user'] ?? DB_USER;
        $dbPass = $_SESSION['db_pass'] ?? DB_PASS;
        $dbName = $_SESSION['db_name'] ?? DB_NAME;
        
        // Import SQL file
        $sqlFile = __DIR__ . '/config/multi_school_database.sql';
        $importResult = importSqlFile($dbHost, $dbUser, $dbPass, $dbName, $sqlFile);
        
        if ($importResult === true) {
            $message = 'Database imported successfully.';
            $messageType = 'success';
            
            // Create config file
            $configFile = __DIR__ . '/config/multi_school_database.php';
            if (createConfigFile($dbHost, $dbUser, $dbPass, $dbName, $configFile)) {
                $message .= ' Configuration file created successfully.';
                
                // Move to next step
                $step = 3;
            } else {
                $message .= ' Could not create configuration file. Please check file permissions.';
                $messageType = 'warning';
            }
        } else {
            $message = 'Error importing database: ' . $importResult;
            $messageType = 'danger';
        }
    }
    
    // Step 3: Create Super Admin
    if ($step === 3 && isset($_POST['admin_email'])) {
        $adminName = trim($_POST['admin_name']);
        $adminEmail = trim($_POST['admin_email']);
        $adminPassword = $_POST['admin_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($adminName) || empty($adminEmail) || empty($adminPassword)) {
            $message = 'All fields are required.';
            $messageType = 'danger';
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid email format.';
            $messageType = 'danger';
        } elseif ($adminPassword !== $confirmPassword) {
            $message = 'Passwords do not match.';
            $messageType = 'danger';
        } else {
            // Get database configuration from session
            $dbHost = $_SESSION['db_host'] ?? DB_HOST;
            $dbUser = $_SESSION['db_user'] ?? DB_USER;
            $dbPass = $_SESSION['db_pass'] ?? DB_PASS;
            $dbName = $_SESSION['db_name'] ?? DB_NAME;
            
            try {
                // Connect to database
                $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
                if ($conn->connect_error) {
                    throw new Exception("Database connection failed: " . $conn->connect_error);
                }
                
                // Hash password
                $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
                
                // Update super admin user
                $sql = "UPDATE users SET name = ?, email = ?, password = ? WHERE id = 1 AND role = 'super_admin'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sss', $adminName, $adminEmail, $hashedPassword);
                
                if ($stmt->execute()) {
                    $message = 'Super admin account updated successfully.';
                    $messageType = 'success';
                    $installComplete = true;
                    
                    // Move to next step
                    $step = 4;
                } else {
                    throw new Exception("Error updating super admin account: " . $stmt->error);
                }
                
                $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                $message = $e->getMessage();
                $messageType = 'danger';
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
    <title>Installation - Multi-School Past Papers Repository</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .install-container {
            max-width: 800px;
            margin: 50px auto;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
        }
        .step:not(:last-child):after {
            content: '';
            position: absolute;
            top: 50%;
            right: -10%;
            width: 20%;
            height: 2px;
            background-color: #dee2e6;
        }
        .step.active {
            font-weight: bold;
            color: #0d6efd;
        }
        .step.active:not(:last-child):after {
            background-color: #0d6efd;
        }
        .step.completed {
            color: #198754;
        }
        .step.completed:not(:last-child):after {
            background-color: #198754;
        }
        .step-icon {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            border-radius: 50%;
            background-color: #dee2e6;
            color: #fff;
            margin-bottom: 10px;
        }
        .step.active .step-icon {
            background-color: #0d6efd;
        }
        .step.completed .step-icon {
            background-color: #198754;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="install-container">
            <h1 class="text-center mb-4">Multi-School Past Papers Repository</h1>
            <h2 class="text-center mb-4">Installation Wizard</h2>
            
            <div class="step-indicator">
                <div class="step <?php echo ($step >= 1) ? 'active' : ''; ?> <?php echo ($step > 1) ? 'completed' : ''; ?>">
                    <div class="step-icon">
                        <?php if ($step > 1): ?>
                            <i class="fas fa-check"></i>
                        <?php else: ?>
                            1
                        <?php endif; ?>
                    </div>
                    <div>Database Configuration</div>
                </div>
                <div class="step <?php echo ($step >= 2) ? 'active' : ''; ?> <?php echo ($step > 2) ? 'completed' : ''; ?>">
                    <div class="step-icon">
                        <?php if ($step > 2): ?>
                            <i class="fas fa-check"></i>
                        <?php else: ?>
                            2
                        <?php endif; ?>
                    </div>
                    <div>Database Import</div>
                </div>
                <div class="step <?php echo ($step >= 3) ? 'active' : ''; ?> <?php echo ($step > 3) ? 'completed' : ''; ?>">
                    <div class="step-icon">
                        <?php if ($step > 3): ?>
                            <i class="fas fa-check"></i>
                        <?php else: ?>
                            3
                        <?php endif; ?>
                    </div>
                    <div>Super Admin Setup</div>
                </div>
                <div class="step <?php echo ($step >= 4) ? 'active' : ''; ?>">
                    <div class="step-icon">
                        <?php if ($step > 4): ?>
                            <i class="fas fa-check"></i>
                        <?php else: ?>
                            4
                        <?php endif; ?>
                    </div>
                    <div>Complete</div>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
                <!-- Step 1: Database Configuration -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Step 1: Database Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="install.php?step=1">
                            <div class="mb-3">
                                <label for="db_host" class="form-label">Database Host</label>
                                <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                            </div>
                            <div class="mb-3">
                                <label for="db_user" class="form-label">Database Username</label>
                                <input type="text" class="form-control" id="db_user" name="db_user" value="root" required>
                            </div>
                            <div class="mb-3">
                                <label for="db_pass" class="form-label">Database Password</label>
                                <input type="password" class="form-control" id="db_pass" name="db_pass">
                            </div>
                            <div class="mb-3">
                                <label for="db_name" class="form-label">Database Name</label>
                                <input type="text" class="form-control" id="db_name" name="db_name" value="multi_school_papers" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Next <i class="fas fa-arrow-right"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($step === 2): ?>
                <!-- Step 2: Database Import -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Step 2: Database Import</h5>
                    </div>
                    <div class="card-body">
                        <p>The installation wizard will now import the database schema and initial data.</p>
                        <p>This may take a few moments. Please do not close or refresh the page.</p>
                        <form method="POST" action="install.php?step=2">
                            <div class="d-grid gap-2">
                                <button type="submit" name="import_db" class="btn btn-primary">Import Database <i class="fas fa-database"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($step === 3): ?>
                <!-- Step 3: Super Admin Setup -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Step 3: Super Admin Setup</h5>
                    </div>
                    <div class="card-body">
                        <p>Create your super admin account. This account will have full access to the system.</p>
                        <form method="POST" action="install.php?step=3">
                            <div class="mb-3">
                                <label for="admin_name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="admin_name" name="admin_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="admin_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="admin_email" name="admin_email" required>
                            </div>
                            <div class="mb-3">
                                <label for="admin_password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Create Super Admin <i class="fas fa-user-shield"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($step === 4): ?>
                <!-- Step 4: Complete -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Installation Complete</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="fas fa-check-circle text-success" style="font-size: 64px;"></i>
                        </div>
                        <h4 class="text-center mb-4">Congratulations! The installation is complete.</h4>
                        <p class="text-center">You can now log in to the system using your super admin credentials.</p>
                        <div class="d-grid gap-2">
                            <a href="multi_school_login.php" class="btn btn-primary">Go to Login <i class="fas fa-sign-in-alt"></i></a>
                        </div>
                        <div class="alert alert-warning mt-4">
                            <p><strong>Important:</strong> For security reasons, please delete this installation file (install.php) after you have completed the installation.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>