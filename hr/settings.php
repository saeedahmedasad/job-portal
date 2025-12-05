<?php
/**
 * JobNexus - HR Settings
 * Account settings and preferences for HR users
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Company.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_HR) {
    header('Location: ' . BASE_URL . '/auth/login.php?redirect=hr/settings');
    exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$companyModel = new Company();

$user = $userModel->findById($_SESSION['user_id']);
$company = $companyModel->findByHRUserId($_SESSION['user_id']);

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
            // Delete all related data
            if ($company) {
                // Delete applications for jobs posted by this company
                $stmt = $db->prepare("DELETE FROM applications WHERE job_id IN (SELECT id FROM jobs WHERE company_id = ?)");
                $stmt->execute([$company['id']]);

                // Delete events related to this HR user
                $stmt = $db->prepare("DELETE FROM events WHERE hr_user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);

                // Delete jobs posted by this company
                $stmt = $db->prepare("DELETE FROM jobs WHERE company_id = ?");
                $stmt->execute([$company['id']]);

                // Delete the company
                $stmt = $db->prepare("DELETE FROM companies WHERE id = ?");
                $stmt->execute([$company['id']]);
            }

            // Delete notifications
            $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);

            // Delete the user
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);

            session_destroy();
            header('Location: ' . BASE_URL . '/?account_deleted=1');
            exit;
        }
        $activeTab = 'danger';
    }
}

$pageTitle = 'Settings - JobNexus';
require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="dashboard-sidebar">
        <div class="sidebar-header">
            <div class="hr-avatar">
                <?php
                $logoPath = '../uploads/logos/' . ($company['logo'] ?? '');
                if (!empty($company['logo']) && file_exists($logoPath)):
                    ?>
                    <img src="<?php echo BASE_URL; ?>/uploads/logos/<?php echo $company['logo']; ?>"
                        alt="<?php echo htmlspecialchars($company['company_name']); ?>"
                        style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                <?php else: ?>
                    <?php echo strtoupper(substr($company['company_name'], 0, 2)); ?>
                <?php endif; ?>
            </div>
            <h3><?php echo htmlspecialchars($company['company_name'] ?? 'HR Manager'); ?></h3>
            <span class="role-badge hr">HR Manager</span>
        </div>

        <nav class="sidebar-nav">
            <a href="<?php echo BASE_URL; ?>/hr/index.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/hr/jobs.php" class="nav-item">
                <i class="fas fa-briefcase"></i>
                <span>My Jobs</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/hr/post-job.php" class="nav-item">
                <i class="fas fa-plus-circle"></i>
                <span>Post New Job</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/hr/applications.php" class="nav-item">
                <i class="fas fa-file-alt"></i>
                <span>Applications</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/hr/calendar.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Calendar</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/hr/company.php" class="nav-item">
                <i class="fas fa-building"></i>
                <span>Company Profile</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/hr/settings.php" class="nav-item active">
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
                                <input type="email" class="form-control"
                                    value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>New Email</label>
                                <input type="email" name="email" class="form-control" required
                                    placeholder="Enter new email">
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
                                    <strong>Warning:</strong> This action is irreversible. All your data including:
                                    <ul style="margin: 0.5rem 0 0 1rem;">
                                        <li>Your company profile</li>
                                        <li>All job postings</li>
                                        <li>All applications received</li>
                                        <li>All scheduled interviews</li>
                                    </ul>
                                    will be permanently deleted.
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

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        color: rgba(255, 255, 255, 0.7);
    }

    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 0.5rem;
        color: var(--text-primary);
        font-size: 0.9rem;
    }

    .form-control:focus {
        border-color: var(--primary-color);
        outline: none;
    }

    .form-actions {
        margin-top: 1.5rem;
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