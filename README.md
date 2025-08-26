# Njumbi High School Past Papers Repository

## Overview
This web application provides a platform for Njumbi High School teachers to upload, manage, and share past paper resources. The system allows for proper categorization of papers by departments and subjects, making it easy for teachers to find relevant academic resources for exam preparation.

## Features

### General Features
- Responsive homepage with school branding and welcome message
- Intuitive navigation system
- Mobile-friendly design for access on any device

### Authentication System
- Secure login for teachers
- Admin dashboard for system management
- User activity logging for security monitoring

### File Management
- Upload past papers with proper categorization
- Download papers shared by other teachers
- Filter papers by year and term
- Search functionality to quickly find specific papers

### Admin Features
- User management (create, edit, delete teacher accounts)
- Paper approval system
- Department and subject management
- Activity logs monitoring

## Technical Details

### Technology Stack
- **Frontend**: HTML, CSS (Bootstrap 4), JavaScript
- **Backend**: PHP
- **Database**: MySQL
- **File Storage**: Local server storage

### Directory Structure
```
PastPaper Web/
├── admin/                  # Admin panel files
│   ├── index.php           # Admin dashboard
│   ├── users.php           # User management
│   ├── papers.php          # Papers management
│   ├── departments.php     # Department & subject management
│   └── logs.php            # Activity logs
├── assets/                 # Static assets
│   ├── css/                # CSS files
│   ├── js/                 # JavaScript files
│   └── images/             # Images and icons
├── config/                 # Configuration files
│   ├── database.php        # Database connection
│   └── database.sql        # Database schema
├── includes/               # Reusable components
│   ├── header.php          # Page header
│   └── footer.php          # Page footer
├── uploads/                # Uploaded past papers
├── index.php               # Homepage
├── login.php               # Login page
├── logout.php              # Logout handler
├── departments.php         # Departments listing
├── papers.php              # Papers listing
├── upload.php              # Paper upload form
├── download.php            # Paper download handler
├── error.php               # Error handling page
└── README.md               # Project documentation
```

## Setup Instructions

### Prerequisites
- Web server (Apache/Nginx)
- PHP 7.2 or higher
- MySQL 5.7 or higher
- XAMPP/WAMP/MAMP (for local development)

### Installation Steps

1. **Clone or download the repository**
   - Place the files in your web server's document root (e.g., `htdocs` for XAMPP)

2. **Create the database**
   - Create a new MySQL database named `njumbi_papers`
   - Import the database schema from `config/database.sql`

3. **Configure database connection**
   - Open `config/database.php`
   - Update the database credentials if needed:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root'); // Change if needed
     define('DB_PASS', '');     // Change if needed
     define('DB_NAME', 'njumbi_papers');
     ```

4. **Set up file permissions**
   - Ensure the `uploads` directory is writable by the web server

5. **Access the application**
   - Open your web browser and navigate to the application URL
   - For local development: `http://localhost/PastPaper Web/`

### Default Admin Account
- **Email**: admin@njumbi.ac.ke
- **Password**: admin123

> **Important**: Change the default admin password after first login for security reasons.

## Usage Guide

### For Teachers
1. Log in using your provided credentials
2. Browse departments and subjects to find papers
3. Upload new past papers using the upload form
4. Download papers shared by other teachers

### For Administrators
1. Log in using admin credentials
2. Access the admin panel from the user menu
3. Manage users, papers, departments, and subjects
4. Review and approve uploaded papers
5. Monitor system activity through logs

## Security Considerations
- All passwords are securely hashed
- Input validation is implemented to prevent SQL injection
- File uploads are validated for type and size
- Activity logging for security monitoring

## Maintenance
- Regularly backup the database
- Monitor disk space usage in the uploads directory
- Update PHP and MySQL to the latest secure versions

## Support
For technical support or questions, please contact the system administrator.