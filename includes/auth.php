<?php
/**
 * Authentication & Authorization helper functions for multi-school architecture.
 * Usage:
 *   require_once 'includes/auth.php';
 *   requireLogin();
 *   if (!isSchoolAdmin()) { header('HTTP/1.1 403 Forbidden'); exit; }
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------------- Basic helpers ------------------------- //
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function currentUserRole(): ?string {
    return $_SESSION['role'] ?? null; // 'super_admin','school_admin','teacher','student'
}

function currentSchoolId(): ?int {
    return $_SESSION['school_id'] ?? null;
}

function currentSchoolName(): ?string {
    return $_SESSION['school_name'] ?? null;
}

// ------------------------- Role checks -------------------------- //
function isSuperAdmin(): bool { return currentUserRole() === 'super_admin'; }
function isSchoolAdmin(): bool { return currentUserRole() === 'school_admin'; }
function isTeacher(): bool { return currentUserRole() === 'teacher'; }
function isStudent(): bool { return currentUserRole() === 'student'; }

// ------------------------- Enforcement -------------------------- //
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * requireRole(['super_admin','school_admin'])
 */
function requireRole(array $roles): void {
    if (!in_array(currentUserRole(), $roles, true)) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied.';
        exit;
    }
}
?>
