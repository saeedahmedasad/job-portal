<?php
/**
 * JobNexus - Configuration File
 * Premium Job Portal Application
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Karachi');

// Base URL Configuration
define('BASE_URL', 'http://localhost/job-portal');
define('SITE_NAME', 'JobNexus');
define('SITE_TAGLINE', 'Find Your Dream Career');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'jobnexus');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// File Upload Paths
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('RESUME_PATH', UPLOAD_PATH . 'resumes/');
define('LOGO_PATH', UPLOAD_PATH . 'logos/');
define('PROFILE_PATH', UPLOAD_PATH . 'profiles/');

// File Upload URLs
define('UPLOAD_URL', BASE_URL . '/uploads/');
define('RESUME_URL', UPLOAD_URL . 'resumes/');
define('LOGO_URL', UPLOAD_URL . 'logos/');
define('PROFILE_URL', UPLOAD_URL . 'profiles/');

// Asset URLs
define('ASSETS_URL', BASE_URL . '/assets/');
define('CSS_URL', ASSETS_URL . 'css/');
define('JS_URL', ASSETS_URL . 'js/');
define('IMAGES_URL', ASSETS_URL . 'images/');

// File Upload Limits
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_RESUME_TYPES', ['pdf', 'doc', 'docx']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Pagination
define('JOBS_PER_PAGE', 12);
define('APPLICATIONS_PER_PAGE', 10);
define('USERS_PER_PAGE', 15);

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_HR', 'hr');
define('ROLE_SEEKER', 'seeker');

// Application Statuses
define('APP_STATUS', [
  'applied' => 'Applied',
  'viewed' => 'Viewed',
  'shortlisted' => 'Shortlisted',
  'interview' => 'Interview Scheduled',
  'offered' => 'Offered',
  'rejected' => 'Rejected',
  'hired' => 'Hired',
  'withdrawn' => 'Withdrawn'
]);

// Job Statuses
define('JOB_STATUS', [
  'draft' => 'Draft',
  'pending' => 'Pending Review',
  'active' => 'Active',
  'paused' => 'Paused',
  'closed' => 'Closed',
  'expired' => 'Expired'
]);

// Company Verification Status
define('VERIFICATION_STATUS', [
  'pending' => 'Pending Verification',
  'verified' => 'Verified',
  'rejected' => 'Rejected'
]);

// Experience Levels
define('EXPERIENCE_LEVELS', [
  'entry' => 'Entry Level',
  'mid' => 'Mid Level',
  'senior' => 'Senior Level',
  'lead' => 'Lead',
  'executive' => 'Executive'
]);

// Job Types
define('JOB_TYPES', [
  'full-time' => 'Full Time',
  'part-time' => 'Part Time',
  'contract' => 'Contract',
  'internship' => 'Internship',
  'freelance' => 'Freelance'
]);

// Location Types
define('LOCATION_TYPES', [
  'remote' => 'Remote',
  'onsite' => 'On-site',
  'hybrid' => 'Hybrid'
]);

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 86400); // 24 hours

/**
 * Generate CSRF Token
 */
function generateCSRFToken(): string
{
  if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
  }
  return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF Token
 */
function verifyCSRFToken(string $token): bool
{
  return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Sanitize Input
 */
function sanitize($data)
{
  if (is_array($data)) {
    return array_map('sanitize', $data);
  }
  return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect Helper
 */
function redirect(string $url): void
{
  header("Location: " . $url);
  exit();
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool
{
  return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function getCurrentUserId(): ?int
{
  return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole(): ?string
{
  return $_SESSION['role'] ?? null;
}

/**
 * Check user role
 */
function hasRole(string $role): bool
{
  return getCurrentUserRole() === $role;
}

/**
 * Require authentication
 */
function requireAuth(): void
{
  if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect(BASE_URL . '/auth/login.php');
  }
}

/**
 * Require specific role
 */
function requireRole(string $role): void
{
  requireAuth();
  if (!hasRole($role)) {
    redirect(BASE_URL . '/403.php');
  }
}

/**
 * Flash Messages
 */
function setFlash(string $type, string $message): void
{
  $_SESSION['flash'] = [
    'type' => $type,
    'message' => $message
  ];
}

function getFlash(): ?array
{
  if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
  }
  return null;
}

/**
 * Format Currency
 */
function formatCurrency(float $amount, string $currency = 'USD'): string
{
  return '$' . number_format($amount, 0);
}

/**
 * Format Date
 */
function formatDate(string $date, string $format = 'M d, Y'): string
{
  return date($format, strtotime($date));
}

/**
 * Time Ago
 */
function timeAgo(?string $datetime): string
{
  if (empty($datetime)) {
    return 'Recently';
  }

  $time = strtotime($datetime);
  if ($time === false) {
    return 'Recently';
  }

  $now = time();
  $diff = $now - $time;

  // Handle future dates 
  if ($diff < 0) {
    return 'Just now';
  }

  if ($diff < 60)
    return 'Just now';
  if ($diff < 3600) {
    $mins = floor($diff / 60);
    return $mins . ($mins == 1 ? ' minute ago' : ' minutes ago');
  }
  if ($diff < 86400) {
    $hours = floor($diff / 3600);
    return $hours . ($hours == 1 ? ' hour ago' : ' hours ago');
  }
  if ($diff < 604800) {
    $days = floor($diff / 86400);
    return $days . ($days == 1 ? ' day ago' : ' days ago');
  }
  if ($diff < 2592000) {
    $weeks = floor($diff / 604800);
    return $weeks . ($weeks == 1 ? ' week ago' : ' weeks ago');
  }
  if ($diff < 31536000) {
    $months = floor($diff / 2592000);
    return $months . ($months == 1 ? ' month ago' : ' months ago');
  }

  $years = floor($diff / 31536000);
  return $years . ($years == 1 ? ' year ago' : ' years ago');
}

/**
 * Generate Slug
 */
function generateSlug(string $text): string
{
  $text = preg_replace('~[^\pL\d]+~u', '-', $text);
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  $text = preg_replace('~[^-\w]+~', '', $text);
  $text = trim($text, '-');
  $text = preg_replace('~-+~', '-', $text);
  $text = strtolower($text);
  return $text ?: 'n-a';
}

/**
 * Get File Extension
 */
function getFileExtension(string $filename): string
{
  return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Validate File Upload
 */
function validateFileUpload(array $file, array $allowedTypes, int $maxSize = MAX_FILE_SIZE): array
{
  $errors = [];

  if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'File upload failed.';
    return $errors;
  }

  $ext = getFileExtension($file['name']);
  if (!in_array($ext, $allowedTypes)) {
    $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes);
  }

  if ($file['size'] > $maxSize) {
    $errors[] = 'File too large. Max size: ' . ($maxSize / 1024 / 1024) . 'MB';
  }

  return $errors;
}

/**
 * Upload File
 */
function uploadFile(array $file, string $destination, string $prefix = ''): ?string
{
  $ext = getFileExtension($file['name']);
  $filename = $prefix . uniqid() . '_' . time() . '.' . $ext;
  $filepath = $destination . $filename;

  if (move_uploaded_file($file['tmp_name'], $filepath)) {
    return $filename;
  }

  return null;
}

/**
 * Truncate Text
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string
{
  if (strlen($text) <= $length)
    return $text;
  return substr($text, 0, $length) . $suffix;
}

/**
 * Get Initials
 */
function getInitials(string $name): string
{
  $words = explode(' ', $name);
  $initials = '';
  foreach ($words as $word) {
    $initials .= strtoupper(substr($word, 0, 1));
  }
  return substr($initials, 0, 2);
}
