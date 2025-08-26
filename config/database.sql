-- Database schema for Njumbi High School Past Papers Repository

-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS njumbi_papers;

-- Use the database
USE njumbi_papers;

-- Create departments table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create subjects table
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    department_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Create papers table
CREATE TABLE IF NOT EXISTS papers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    subject_id INT NOT NULL,
    department_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    year INT,
    term ENUM('1', '2', '3') NULL,
    download_count INT DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Create activity_logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default departments
INSERT INTO departments (name, description) VALUES
('Technical Department', 'Includes Home Science, Computer, Agriculture, Business Studies, and French'),
('Sciences Department', 'Includes Chemistry, Biology, and Physics'),
('Mathematics Department', 'Includes all Mathematics subjects'),
('Humanities Department', 'Includes C.R.E, History, and Geography'),
('Languages Department', 'Includes Kiswahili and English');

-- Insert default subjects
INSERT INTO subjects (department_id, name, description) VALUES
-- Technical Department subjects
(1, 'Home Science', 'Home Science subject for Technical Department'),
(1, 'Computer', 'Computer Studies subject for Technical Department'),
(1, 'Agriculture', 'Agriculture subject for Technical Department'),
(1, 'Business Studies', 'Business Studies subject for Technical Department'),
(1, 'French', 'French language subject for Technical Department'),

-- Sciences Department subjects
(2, 'Chemistry', 'Chemistry subject for Sciences Department'),
(2, 'Biology', 'Biology subject for Sciences Department'),
(2, 'Physics', 'Physics subject for Sciences Department'),

-- Mathematics Department subjects
(3, 'Mathematics', 'Mathematics subject'),

-- Humanities Department subjects
(4, 'C.R.E', 'Christian Religious Education subject for Humanities Department'),
(4, 'History', 'History subject for Humanities Department'),
(4, 'Geography', 'Geography subject for Humanities Department'),

-- Languages Department subjects
(5, 'Kiswahili', 'Kiswahili language subject for Languages Department'),
(5, 'English', 'English language subject for Languages Department');

-- Insert default admin user (password: admin123)
INSERT INTO users (name, email, password, is_admin) VALUES
('Admin User', 'admin@njumbi.ac.ke', '$2y$10$Ixu0UZXgzUWQHBMvCwYfYuqIBVWkRy5pQlOhzf8mjFYSqLQXlhnVK', TRUE);