# Multi-School Past Papers Repository - Testing Guide

This document provides a step-by-step guide for testing the complete multi-school architecture workflow. Follow these steps to ensure all features are working correctly.

## Prerequisites

1. XAMPP installed and running (Apache and MySQL)
2. Repository files placed in `htdocs/PastPaper_Web` directory
3. Installation completed using `install.php`

## Testing Workflow

### 1. Installation

- [ ] Navigate to `http://localhost/PastPaper_Web/install.php`
- [ ] Complete the database configuration step
- [ ] Import the database schema
- [ ] Create a super admin account
- [ ] Verify successful installation

### 2. Super Admin Functionality

- [ ] Login as super admin at `http://localhost/PastPaper_Web/multi_school_login.php`
- [ ] Verify redirection to super admin dashboard
- [ ] Create a new school
  - [ ] Add school details (name, email, phone, address, website)
  - [ ] Verify school appears in the schools list
- [ ] Create a school admin for the new school
  - [ ] Add admin details (name, email, password)
  - [ ] Assign admin to the school
- [ ] View school details
  - [ ] Check statistics (users, papers)
  - [ ] Verify admin assignment

### 3. School Admin Functionality

- [ ] Login as school admin
- [ ] Verify redirection to school admin dashboard
- [ ] Manage departments
  - [ ] Add a new department
  - [ ] Edit an existing department
  - [ ] Delete a department (if not associated with subjects)
- [ ] Manage subjects
  - [ ] Add a new subject
  - [ ] Edit an existing subject
  - [ ] Delete a subject (if not associated with papers)
- [ ] Manage teachers
  - [ ] Add a new teacher
  - [ ] Edit teacher details
  - [ ] Reset teacher password
  - [ ] Delete a teacher
- [ ] Manage students
  - [ ] Add a new student
  - [ ] Edit student details
  - [ ] Reset student password
  - [ ] Delete a student
- [ ] Manage papers
  - [ ] Upload a new paper
  - [ ] Edit paper details
  - [ ] Delete a paper
- [ ] View reports
  - [ ] Activity logs
  - [ ] Paper statistics
  - [ ] User statistics
- [ ] Update settings
  - [ ] School information
  - [ ] Profile information
  - [ ] Change password

### 4. Student Functionality

- [ ] Login as student
- [ ] Verify redirection to student dashboard
- [ ] Browse papers
  - [ ] View recent papers
  - [ ] Search for papers
  - [ ] Filter papers by subject and year
- [ ] View papers by subject
  - [ ] Navigate through departments and subjects
  - [ ] View papers for a specific subject
- [ ] Manage favorites
  - [ ] Add papers to favorites
  - [ ] Remove papers from favorites
  - [ ] View favorite papers
- [ ] Update profile
  - [ ] Edit profile information
  - [ ] Change password

### 5. Cross-Functional Testing

- [ ] Verify session isolation between schools
  - [ ] Confirm users from one school cannot access another school's data
- [ ] Test access control
  - [ ] Verify role-based access restrictions
  - [ ] Test URL manipulation security
- [ ] Test responsive design
  - [ ] Check UI on different screen sizes
  - [ ] Verify mobile-friendly layout

## Reporting Issues

If you encounter any issues during testing, please document the following:

1. Step where the issue occurred
2. Expected behavior
3. Actual behavior
4. Screenshots (if applicable)
5. Error messages (if any)

## Successful Completion

Once all tests have passed, the multi-school architecture implementation is complete and ready for deployment.