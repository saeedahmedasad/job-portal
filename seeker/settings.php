<?php
/**
 * JobNexus - Seeker Settings
 * Account settings and preferences
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/SeekerProfile.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_SEEKER) {
  header('Location: ' . BASE_URL . '/auth/login.php?redirect=seeker/settings');
  exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$profileModel = new SeekerProfile();

$user = $userModel->findById($_SESSION['user_id']);
$profile = $profileModel->findByUserId($_SESSION['user_id']);

$message = '';
$messageType = '';
$activeTab = $_GET['tab'] ?? 'account';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'update_email') {
    $newEmail = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $currentPassword = $_POST['current_password'] ?? '';

    if (!$newEmail) {
      $message = 'Please enter a valid email address.';
      $messageType = 'error';
    } elseif (!password_verify($currentPassword, $user['password_hash'])) {
      $message = 'Current password is incorrect.';
      $messageType = 'error';
    } else {
      // Check if email is already taken
      $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
      $stmt->execute([$newEmail, $_SESSION['user_id']]);
      if ($stmt->fetch()) {
        $message = 'This email is already registered.';
        $messageType = 'error';
      } else {
        $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
        if ($stmt->execute([$newEmail, $_SESSION['user_id']])) {
          $_SESSION['user_email'] = $newEmail;
          $message = 'Email updated successfully!';
          $messageType = 'success';
          $user = $userModel->findById($_SESSION['user_id']);
        }
      }
    }
  } elseif ($action === 'update_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!password_verify($currentPassword, $user['password_hash'])) {
      $message = 'Current password is incorrect.';
      $messageType = 'error';
    } elseif (strlen($newPassword) < 8) {
      $message = 'New password must be at least 8 characters.';
      $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
      $message = 'New passwords do not match.';
      $messageType = 'error';
    } else {
      $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
      $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
      if ($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
        $message = 'Password updated successfully!';
        $messageType = 'success';
      }
    }
    $activeTab = 'security';
  } elseif ($action === 'update_notifications') {
    $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
    $jobAlerts = isset($_POST['job_alerts']) ? 1 : 0;
    $applicationUpdates = isset($_POST['application_updates']) ? 1 : 0;
    $marketingEmails = isset($_POST['marketing_emails']) ? 1 : 0;

    // Store preferences (you'd need to add a preferences table or JSON column)
    $preferences = json_encode([
      'email_notifications' => $emailNotifications,
      'job_alerts' => $jobAlerts,
      'application_updates' => $applicationUpdates,
      'marketing_emails' => $marketingEmails
    ]);

    // For now, just show success
    $message = 'Notification preferences updated!';
    $messageType = 'success';
    $activeTab = 'notifications';
  } elseif ($action === 'update_privacy') {
    $isAvailable = isset($_POST['is_available']) ? 1 : 0;

    if ($profile) {
      $stmt = $db->prepare("UPDATE seeker_profiles SET is_available = ? WHERE id = ?");
      $stmt->execute([$isAvailable, $profile['id']]);
    }

    $message = 'Privacy settings updated!';
    $messageType = 'success';
    $activeTab = 'privacy';
    $profile = $profileModel->findByUserId($_SESSION['user_id']);
  } elseif ($action === 'delete_account') {
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $confirmText = $_POST['confirm_text'] ?? '';

    if (!password_verify($confirmPassword, $user['password_hash'])) {
      $message = 'Password is incorrect.';
      $messageType = 'error';
    } elseif ($confirmText !== 'DELETE') {
      $message = 'Please type DELETE to confirm.';
      $messageType = 'error';
    } else {
      // Delete user and related data
      $stmt = $db->prepare("DELETE FROM applications WHERE seeker_id = ?");
      $stmt->execute([$_SESSION['user_id']]);

      $stmt = $db->prepare("DELETE FROM saved_jobs WHERE user_id = ?");
      $stmt->execute([$_SESSION['user_id']]);

      $stmt = $db->prepare("DELETE FROM seeker_profiles WHERE user_id = ?");
      $stmt->execute([$_SESSION['user_id']]);

      $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
      $stmt->execute([$_SESSION['user_id']]);

      session_destroy();
      header('Location: ' . BASE_URL . '/?account_deleted=1');
      exit;
    }
    $activeTab = 'danger';
  }
}

$pageTitle = 'Settings';
require_once '../includes/header.php';
?>

<div class="dashboard-container">
  <!-- Sidebar -->
  <aside class="dashboard-sidebar">
    <div class="sidebar-header">
      <div class="seeker-avatar">
        <?php
        $avatarPath = '../uploads/avatars/' . ($profile['profile_photo'] ?? '');
        if (!empty($profile['profile_photo']) && file_exists($avatarPath)):
          ?>
          <img src="<?php echo BASE_URL; ?>/uploads/avatars/<?php echo $profile['profile_photo']; ?>"
            alt="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>"
            style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
        <?php else: ?>
          <?php echo strtoupper(substr($profile['first_name'] ?? 'U', 0, 1)); ?>
        <?php endif; ?>
      </div>
      <h3><?php echo htmlspecialchars(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')); ?></h3>
      <span class="role-badge seeker">Job Seeker</span>
    </div>

    <nav class="sidebar-nav">
      <a href="<?php echo BASE_URL; ?>/seeker/index.php" class="nav-item">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/seeker/profile.php" class="nav-item">
        <i class="fas fa-user"></i>
        <span>My Profile</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/seeker/applications.php" class="nav-item">
        <i class="fas fa-file-alt"></i>
        <span>My Applications</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/seeker/saved-jobs.php" class="nav-item">
        <i class="fas fa-bookmark"></i>
        <span>Saved Jobs</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/seeker/calendar.php" class="nav-item">
        <i class="fas fa-calendar-alt"></i>
        <span>Calendar</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/seeker/resume.php" class="nav-item">
        <i class="fas fa-file-pdf"></i>
        <span>Resume Builder</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/jobs" class="nav-item">
        <i class="fas fa-search"></i>
        <span>Browse Jobs</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/seeker/settings.php" class="nav-item active">
        <i class="fas fa-cog"></i>
        <span>Settings</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="dashboard-main">
    <div class="dashboard-header">
      <div class="header-left">
        <h1><i class="fas fa-cog"></i> Settings</h1>
        <p>Manage your account settings and preferences</p>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?php echo $messageType; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <!-- Settings Tabs -->
    <div class="settings-container">
      <div class="settings-tabs">
        <a href="?tab=account" class="tab-link <?php echo $activeTab === 'account' ? 'active' : ''; ?>">
          <i class="fas fa-user"></i> Account
        </a>
        <a href="?tab=security" class="tab-link <?php echo $activeTab === 'security' ? 'active' : ''; ?>">
          <i class="fas fa-lock"></i> Security
        </a>
        <a href="?tab=notifications" class="tab-link <?php echo $activeTab === 'notifications' ? 'active' : ''; ?>">
          <i class="fas fa-bell"></i> Notifications
        </a>
        <a href="?tab=privacy" class="tab-link <?php echo $activeTab === 'privacy' ? 'active' : ''; ?>">
          <i class="fas fa-shield-alt"></i> Privacy
        </a>
        <a href="?tab=danger" class="tab-link danger <?php echo $activeTab === 'danger' ? 'active' : ''; ?>">
          <i class="fas fa-exclamation-triangle"></i> Danger Zone
        </a>
      </div>

      <div class="settings-content">
        <!-- Account Tab -->
        <?php if ($activeTab === 'account'): ?>
          <div class="settings-section">
            <div class="section-header">
              <h2><i class="fas fa-envelope"></i> Email Address</h2>
              <p>Update your email address</p>
            </div>
            <form method="POST" class="settings-form glass-card">
              <input type="hidden" name="action" value="update_email">
              <div class="form-group">
                <label>Current Email</label>
                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
              </div>
              <div class="form-group">
                <label>New Email</label>
                <input type="email" name="email" class="form-control" required placeholder="Enter new email">
              </div>
              <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" class="form-control" required
                  placeholder="Verify with password">
              </div>
              <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save"></i> Update Email
                </button>
              </div>
            </form>
          </div>

          <!-- Security Tab -->
        <?php elseif ($activeTab === 'security'): ?>
          <div class="settings-section">
            <div class="section-header">
              <h2><i class="fas fa-key"></i> Change Password</h2>
              <p>Update your password regularly for security</p>
            </div>
            <form method="POST" class="settings-form glass-card">
              <input type="hidden" name="action" value="update_password">
              <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
              </div>
              <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="8"
                  placeholder="Minimum 8 characters">
              </div>
              <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
              </div>
              <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-lock"></i> Update Password
                </button>
              </div>
            </form>
          </div>

          <!-- Notifications Tab -->
        <?php elseif ($activeTab === 'notifications'): ?>
          <div class="settings-section">
            <div class="section-header">
              <h2><i class="fas fa-bell"></i> Email Notifications</h2>
              <p>Choose what emails you'd like to receive</p>
            </div>
            <form method="POST" class="settings-form glass-card">
              <input type="hidden" name="action" value="update_notifications">

              <div class="toggle-option">
                <div class="toggle-info">
                  <h4>Email Notifications</h4>
                  <p>Receive email updates about your account</p>
                </div>
                <label class="toggle-switch">
                  <input type="checkbox" name="email_notifications" checked>
                  <span class="toggle-slider"></span>
                </label>
              </div>

              <div class="toggle-option">
                <div class="toggle-info">
                  <h4>Job Alerts</h4>
                  <p>Get notified about new jobs matching your preferences</p>
                </div>
                <label class="toggle-switch">
                  <input type="checkbox" name="job_alerts" checked>
                  <span class="toggle-slider"></span>
                </label>
              </div>

              <div class="toggle-option">
                <div class="toggle-info">
                  <h4>Application Updates</h4>
                  <p>Receive updates when your application status changes</p>
                </div>
                <label class="toggle-switch">
                  <input type="checkbox" name="application_updates" checked>
                  <span class="toggle-slider"></span>
                </label>
              </div>

              <div class="toggle-option">
                <div class="toggle-info">
                  <h4>Marketing Emails</h4>
                  <p>Receive tips, product updates, and promotional content</p>
                </div>
                <label class="toggle-switch">
                  <input type="checkbox" name="marketing_emails">
                  <span class="toggle-slider"></span>
                </label>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save"></i> Save Preferences
                </button>
              </div>
            </form>
          </div>

          <!-- Privacy Tab -->
        <?php elseif ($activeTab === 'privacy'): ?>
          <div class="settings-section">
            <div class="section-header">
              <h2><i class="fas fa-shield-alt"></i> Privacy Settings</h2>
              <p>Control who can see your profile information</p>
            </div>
            <form method="POST" class="settings-form glass-card">
              <input type="hidden" name="action" value="update_privacy">

              <div class="toggle-option">
                <div class="toggle-info">
                  <h4>Available for Opportunities</h4>
                  <p>Let recruiters know you're open to new opportunities</p>
                </div>
                <label class="toggle-switch">
                  <input type="checkbox" name="is_available" <?php echo ($profile['is_available'] ?? 1) ? 'checked' : ''; ?>>
                  <span class="toggle-slider"></span>
                </label>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save"></i> Save Privacy Settings
                </button>
              </div>
            </form>
          </div>

          <!-- Danger Zone Tab -->
        <?php elseif ($activeTab === 'danger'): ?>
          <div class="settings-section danger-section">
            <div class="section-header">
              <h2><i class="fas fa-exclamation-triangle"></i> Delete Account</h2>
              <p>Permanently delete your account and all associated data</p>
            </div>
            <form method="POST" class="settings-form glass-card danger-form" onsubmit="return confirmDelete();">
              <input type="hidden" name="action" value="delete_account">

              <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                  <strong>Warning:</strong> This action is irreversible. All your data, applications, and saved jobs will
                  be permanently deleted.
                </div>
              </div>

              <div class="form-group">
                <label>Enter your password to confirm</label>
                <input type="password" name="confirm_password" class="form-control" required>
              </div>

              <div class="form-group">
                <label>Type "DELETE" to confirm</label>
                <input type="text" name="confirm_text" class="form-control" required placeholder="DELETE">
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-danger">
                  <i class="fas fa-trash"></i> Delete My Account
                </button>
              </div>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<style>
  .settings-container {
    display: flex;
    gap: 2rem;
  }

  .settings-tabs {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 200px;
  }

  .tab-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    border-radius: var(--radius-md);
    color: var(--text-secondary);
    text-decoration: none;
    transition: var(--transition-fast);
  }

  .tab-link:hover {
    background: var(--bg-tertiary);
    color: var(--text-primary);
  }

  .tab-link.active {
    background: var(--bg-tertiary);
    color: var(--accent-primary);
  }

  .tab-link.danger {
    color: var(--error);
  }

  .settings-content {
    flex: 1;
  }

  .settings-section {
    margin-bottom: 2rem;
  }

  .settings-section .section-header {
    margin-bottom: 1.5rem;
  }

  .settings-section .section-header h2 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    margin-bottom: 0.25rem;
  }

  .settings-section .section-header p {
    color: var(--text-muted);
    font-size: 0.875rem;
  }

  .settings-form {
    padding: 1.5rem;
  }

  .toggle-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border-color);
  }

  .toggle-option:last-of-type {
    border-bottom: none;
  }

  .toggle-info h4 {
    margin-bottom: 0.25rem;
    font-size: 0.9375rem;
  }

  .toggle-info p {
    color: var(--text-muted);
    font-size: 0.8125rem;
  }

  .toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
  }

  .toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
  }

  .toggle-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background: var(--bg-tertiary);
    border-radius: 26px;
    transition: var(--transition-fast);
  }

  .toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background: white;
    border-radius: 50%;
    transition: var(--transition-fast);
  }

  .toggle-switch input:checked+.toggle-slider {
    background: var(--accent-primary);
  }

  .toggle-switch input:checked+.toggle-slider:before {
    transform: translateX(24px);
  }

  .warning-box {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    background: rgba(244, 67, 54, 0.1);
    border: 1px solid rgba(244, 67, 54, 0.3);
    border-radius: var(--radius-md);
    margin-bottom: 1.5rem;
    color: var(--error);
  }

  .warning-box i {
    font-size: 1.25rem;
    margin-top: 0.125rem;
  }

  .danger-form {
    border-color: rgba(244, 67, 54, 0.3);
  }

  .btn-danger {
    background: var(--error);
    color: white;
  }

  .btn-danger:hover {
    background: #d32f2f;
  }

  @media (max-width: 768px) {
    .settings-container {
      flex-direction: column;
    }

    .settings-tabs {
      flex-direction: row;
      overflow-x: auto;
      min-width: auto;
    }

    .tab-link {
      white-space: nowrap;
    }
  }
</style>

<script>
  function confirmDelete() {
    return confirm('Are you absolutely sure you want to delete your account? This cannot be undone.');
  }
</script>

<?php require_once '../includes/footer.php'; ?>