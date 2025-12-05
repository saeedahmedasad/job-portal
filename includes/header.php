<?php
/**
 * JobNexus - Header Include
 */

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

// Get current user info if logged in
$currentUser = null;
$currentUserName = 'Guest';
$notificationCount = 0;

if (isLoggedIn()) {
  require_once __DIR__ . '/../classes/User.php';
  require_once __DIR__ . '/../classes/SeekerProfile.php';
  require_once __DIR__ . '/../classes/Company.php';

  $userModel = new User();
  $currentUser = $userModel->findById(getCurrentUserId());

  if ($currentUser) {
    if ($currentUser['role'] === ROLE_SEEKER) {
      $profileModel = new SeekerProfile();
      $profile = $profileModel->findByUserId($currentUser['id']);
      if ($profile) {
        $currentUserName = $profile['first_name'] . ' ' . $profile['last_name'];
        $currentUserProfilePhoto = $profile['profile_photo'] ?? null;
      }
    } elseif ($currentUser['role'] === ROLE_HR) {
      $companyModel = new Company();
      $company = $companyModel->findByHRUserId($currentUser['id']);
      if ($company) {
        $currentUserName = $company['company_name'];
        $currentUserCompanyLogo = $company['logo'] ?? null;
      }
    } else {
      $currentUserName = 'Admin';
    }
  }

  // Get unread notification count and notifications
  $db = Database::getInstance()->getConnection();
  $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
  $stmt->execute([getCurrentUserId()]);
  $notificationCount = (int) $stmt->fetchColumn();

  // Get recent notifications
  $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
  $stmt->execute([getCurrentUserId()]);
  $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get flash message
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description"
    content="<?php echo SITE_TAGLINE; ?> - Find the best remote and on-site jobs from top companies worldwide.">
  <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME . ' - ' . SITE_TAGLINE; ?></title>

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?php echo BASE_URL; ?>/assets/images/favicon.svg">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Staatliches&family=Inter:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Main Stylesheet -->
  <link rel="stylesheet" href="<?php echo CSS_URL; ?>style.css?v=<?php echo time(); ?>">

  <!-- Dashboard Stylesheet -->
  <link rel="stylesheet" href="<?php echo CSS_URL; ?>dashboard.css?v=<?php echo time(); ?>">

  <?php if (isset($additionalCSS)): ?>
    <?php foreach ($additionalCSS as $css): ?>
      <link rel="stylesheet" href="<?php echo $css; ?>">
    <?php endforeach; ?>
  <?php endif; ?>
</head>

<body>
  <!-- Navigation -->
  <nav class="navbar">
    <div class="navbar-container">
      <a href="<?php echo BASE_URL; ?>" class="navbar-brand">
        <span class="navbar-logo">Job<span>Nexus</span></span>
      </a>

      <ul class="navbar-menu">
        <li><a href="<?php echo BASE_URL; ?>"
            class="navbar-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['REQUEST_URI'], '/jobs') === false && strpos($_SERVER['REQUEST_URI'], '/companies') === false && strpos($_SERVER['REQUEST_URI'], '/admin') === false && strpos($_SERVER['REQUEST_URI'], '/hr') === false && strpos($_SERVER['REQUEST_URI'], '/seeker') === false ? 'active' : ''; ?>">Home</a>
        </li>
        <li><a href="<?php echo BASE_URL; ?>/jobs/"
            class="navbar-link <?php echo strpos($_SERVER['REQUEST_URI'], '/jobs') !== false ? 'active' : ''; ?>">Find
            Jobs</a></li>
        <li><a href="<?php echo BASE_URL; ?>/companies/"
            class="navbar-link <?php echo strpos($_SERVER['REQUEST_URI'], '/companies') !== false ? 'active' : ''; ?>">Companies</a>
        </li>
        <?php if (isLoggedIn() && hasRole(ROLE_HR)): ?>
          <li><a href="<?php echo BASE_URL; ?>/hr/post-job.php" class="navbar-link">Post a Job</a></li>
        <?php endif; ?>
      </ul>

      <div class="navbar-actions">
        <?php if (isLoggedIn()): ?>
          <!-- Notifications -->
          <div class="nav-notification-wrapper">
            <button class="btn btn-icon btn-ghost nav-notification" title="Notifications" id="notificationToggle">
              <i class="far fa-bell"></i>
              <?php if ($notificationCount > 0): ?>
                <span class="notification-badge"
                  id="notificationBadge"><?php echo $notificationCount > 99 ? '99+' : $notificationCount; ?></span>
              <?php endif; ?>
            </button>

            <!-- Notification Dropdown -->
            <div class="notification-dropdown" id="notificationDropdown">
              <div class="notification-header">
                <h4>Notifications</h4>
                <?php if ($notificationCount > 0): ?>
                  <a href="<?php echo BASE_URL; ?>/notifications.php?action=mark_all_read" class="btn-text">Mark all
                    read</a>
                <?php endif; ?>
              </div>
              <div class="notification-list" id="notificationList">
                <?php if (empty($notifications)): ?>
                  <div class="notification-empty">
                    <i class="far fa-bell-slash"></i>
                    <p>No notifications yet</p>
                  </div>
                <?php else: ?>
                  <?php foreach ($notifications as $notification):
                    $iconClass = 'fas fa-info-circle';
                    if ($notification['type'] === 'application')
                      $iconClass = 'fas fa-file-alt';
                    elseif ($notification['type'] === 'interview')
                      $iconClass = 'fas fa-calendar-check';
                    elseif ($notification['type'] === 'message')
                      $iconClass = 'fas fa-envelope';
                    elseif ($notification['type'] === 'job')
                      $iconClass = 'fas fa-briefcase';
                    $unreadClass = $notification['is_read'] == 0 ? 'unread' : '';
                    $link = $notification['link'] ? BASE_URL . $notification['link'] : '#';
                    ?>
                    <a href="<?php echo $link; ?>" class="notification-item <?php echo $unreadClass; ?>">
                      <div class="notification-icon type-<?php echo $notification['type']; ?>">
                        <i class="<?php echo $iconClass; ?>"></i>
                      </div>
                      <div class="notification-content">
                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                        <div class="notification-time"><?php echo timeAgo($notification['created_at']); ?></div>
                      </div>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <div class="notification-footer">
                <a href="<?php echo BASE_URL; ?>/notifications.php">View All Notifications</a>
              </div>
            </div>
          </div>

          <!-- User Dropdown -->
          <div class="nav-dropdown">
            <button class="nav-dropdown-toggle">
              <div class="avatar avatar-sm">
                <?php
                // Check for seeker profile photo
                $avatarPath = __DIR__ . '/../uploads/avatars/' . ($currentUserProfilePhoto ?? '');
                // Check for HR company logo
                $logoPath = __DIR__ . '/../uploads/logos/' . ($currentUserCompanyLogo ?? '');
                
                if (isset($currentUserProfilePhoto) && $currentUserProfilePhoto && file_exists($avatarPath)):
                ?>
                  <img src="<?php echo BASE_URL; ?>/uploads/avatars/<?php echo $currentUserProfilePhoto; ?>"
                    alt="<?php echo sanitize($currentUserName); ?>"
                    style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                <?php elseif (isset($currentUserCompanyLogo) && $currentUserCompanyLogo && file_exists($logoPath)): ?>
                  <img src="<?php echo BASE_URL; ?>/uploads/logos/<?php echo $currentUserCompanyLogo; ?>"
                    alt="<?php echo sanitize($currentUserName); ?>"
                    style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                <?php else: ?>
                  <span class="initials"><?php echo getInitials($currentUserName); ?></span>
                <?php endif; ?>
              </div>
              <span class="nav-user-name"><?php echo sanitize($currentUserName); ?></span>
              <i class="fas fa-chevron-down"></i>
            </button>
            <div class="nav-dropdown-menu">
              <?php if (hasRole(ROLE_ADMIN)): ?>
                <a href="<?php echo BASE_URL; ?>/admin/" class="nav-dropdown-item">
                  <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/users.php" class="nav-dropdown-item">
                  <i class="fas fa-users"></i> Manage Users
                </a>
              <?php elseif (hasRole(ROLE_HR)): ?>
                <a href="<?php echo BASE_URL; ?>/hr/" class="nav-dropdown-item">
                  <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="<?php echo BASE_URL; ?>/hr/company.php" class="nav-dropdown-item">
                  <i class="fas fa-building"></i> Company Profile
                </a>
                <a href="<?php echo BASE_URL; ?>/hr/applications.php" class="nav-dropdown-item">
                  <i class="fas fa-file-alt"></i> Applications
                </a>
              <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/seeker/" class="nav-dropdown-item">
                  <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="<?php echo BASE_URL; ?>/seeker/profile.php" class="nav-dropdown-item">
                  <i class="fas fa-user"></i> My Profile
                </a>
                <a href="<?php echo BASE_URL; ?>/seeker/applications.php" class="nav-dropdown-item">
                  <i class="fas fa-file-alt"></i> My Applications
                </a>
                <a href="<?php echo BASE_URL; ?>/seeker/saved-jobs.php" class="nav-dropdown-item">
                  <i class="fas fa-heart"></i> Saved Jobs
                </a>
              <?php endif; ?>
              <div class="nav-dropdown-divider"></div>
              <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="nav-dropdown-item text-error">
                <i class="fas fa-sign-out-alt"></i> Logout
              </a>
            </div>
          </div>
        <?php else: ?>
          <a href="<?php echo BASE_URL; ?>/auth/login.php" class="btn btn-ghost">Sign In</a>
          <a href="<?php echo BASE_URL; ?>/auth/register.php" class="btn btn-primary">Get Started</a>
        <?php endif; ?>

        <button class="mobile-toggle">
          <i class="fas fa-bars"></i>
        </button>
      </div>
    </div>
  </nav>

  <!-- Toast Container -->
  <div class="toast-container">
    <?php if ($flash): ?>
      <div class="toast toast-<?php echo $flash['type']; ?> show">
        <i
          class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
        <span><?php echo sanitize($flash['message']); ?></span>
      </div>
    <?php endif; ?>
  </div>

  <!-- Main Content Wrapper -->
  <main>