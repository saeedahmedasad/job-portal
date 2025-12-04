<?php
/**
 * JobNexus - Notifications Page
 * View all user notifications
 */

require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';

if (!isLoggedIn()) {
  header('Location: ' . BASE_URL . '/auth/login.php');
  exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$user = $userModel->findById($_SESSION['user_id']);

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
  }

  if ($_POST['action'] === 'mark_all_read') {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    setFlash('success', 'All notifications marked as read');
    header('Location: ' . BASE_URL . '/notifications.php');
    exit;
  }

  if ($_POST['action'] === 'delete_all') {
    $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    setFlash('success', 'All notifications deleted');
    header('Location: ' . BASE_URL . '/notifications.php');
    exit;
  }

  if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
    $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([(int) $_POST['id'], $_SESSION['user_id']]);
    setFlash('success', 'Notification deleted');
    header('Location: ' . BASE_URL . '/notifications.php');
    exit;
  }
}

// Mark single notification as read via GET
if (isset($_GET['read'])) {
  $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
  $stmt->execute([(int) $_GET['read'], $_SESSION['user_id']]);

  // Get notification link to redirect
  $stmt = $db->prepare("SELECT link FROM notifications WHERE id = ?");
  $stmt->execute([(int) $_GET['read']]);
  $notification = $stmt->fetch();

  if ($notification && $notification['link']) {
    header('Location: ' . BASE_URL . $notification['link']);
    exit;
  }
}

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filter
$filter = $_GET['filter'] ?? 'all';
$whereClause = "WHERE user_id = ?";
$params = [$_SESSION['user_id']];

if ($filter === 'unread') {
  $whereClause .= " AND is_read = 0";
}

// Get total count
$stmt = $db->prepare("SELECT COUNT(*) FROM notifications $whereClause");
$stmt->execute($params);
$totalCount = $stmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// Get unread count
$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unreadCount = $stmt->fetchColumn();

// Get notifications
$stmt = $db->prepare("
  SELECT * FROM notifications 
  $whereClause 
  ORDER BY created_at DESC 
  LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Notifications';
include 'includes/header.php';
?>

<style>
  .notifications-page {
    padding: var(--spacing-2xl) 0;
    min-height: 70vh;
  }

  .notifications-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--spacing-xl);
    flex-wrap: wrap;
    gap: var(--spacing-md);
  }

  .notifications-title {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
  }

  .notifications-title h1 {
    font-size: 1.75rem;
    margin: 0;
  }

  .unread-badge {
    background: var(--error);
    color: white;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.25rem 0.75rem;
    border-radius: var(--radius-full);
  }

  .notifications-actions {
    display: flex;
    gap: var(--spacing-sm);
  }

  .filter-tabs {
    display: flex;
    gap: var(--spacing-xs);
    margin-bottom: var(--spacing-lg);
    background: var(--bg-tertiary);
    padding: 0.25rem;
    border-radius: var(--radius-md);
    width: fit-content;
  }

  .filter-tab {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
    background: transparent;
    border: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all var(--transition-fast);
    text-decoration: none;
  }

  .filter-tab:hover {
    color: var(--text-primary);
  }

  .filter-tab.active {
    background: var(--bg-card);
    color: var(--accent-primary);
    font-weight: 500;
  }

  .notifications-list {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    overflow: hidden;
  }

  .notification-item-full {
    display: flex;
    gap: var(--spacing-lg);
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    transition: background var(--transition-fast);
    position: relative;
  }

  .notification-item-full:last-child {
    border-bottom: none;
  }

  .notification-item-full:hover {
    background: var(--bg-hover);
  }

  .notification-item-full.unread {
    background: rgba(0, 230, 118, 0.03);
  }

  .notification-item-full.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--accent-primary);
  }

  .notification-icon-lg {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.25rem;
  }

  .notification-icon-lg.type-application {
    background: rgba(0, 230, 118, 0.15);
    color: var(--accent-primary);
  }

  .notification-icon-lg.type-interview {
    background: rgba(64, 196, 255, 0.15);
    color: var(--info);
  }

  .notification-icon-lg.type-message {
    background: rgba(255, 171, 64, 0.15);
    color: var(--warning);
  }

  .notification-icon-lg.type-job {
    background: rgba(156, 39, 176, 0.15);
    color: #9c27b0;
  }

  .notification-icon-lg.type-system {
    background: rgba(158, 158, 158, 0.15);
    color: var(--text-secondary);
  }

  .notification-body {
    flex: 1;
    min-width: 0;
  }

  .notification-title-full {
    font-size: 1rem;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.375rem;
  }

  .notification-message-full {
    font-size: 0.9rem;
    color: var(--text-secondary);
    line-height: 1.5;
    margin-bottom: 0.5rem;
  }

  .notification-meta {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    font-size: 0.85rem;
    color: var(--text-muted);
  }

  .notification-link {
    color: var(--accent-primary);
    text-decoration: none;
    font-weight: 500;
  }

  .notification-link:hover {
    text-decoration: underline;
  }

  .notification-actions-full {
    display: flex;
    gap: var(--spacing-sm);
    align-items: flex-start;
  }

  .notification-actions-full .btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
    opacity: 0.5;
  }

  .notification-item-full:hover .notification-actions-full .btn-icon {
    opacity: 1;
  }

  .empty-notifications {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
  }

  .empty-notifications i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.3;
  }

  .empty-notifications h3 {
    font-size: 1.25rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
  }

  .pagination {
    display: flex;
    justify-content: center;
    gap: var(--spacing-xs);
    margin-top: var(--spacing-xl);
  }

  .pagination a,
  .pagination span {
    padding: 0.5rem 1rem;
    border-radius: var(--radius-sm);
    font-size: 0.9rem;
    text-decoration: none;
    transition: all var(--transition-fast);
  }

  .pagination a {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
  }

  .pagination a:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
    border-color: var(--accent-primary);
  }

  .pagination span.current {
    background: var(--accent-primary);
    color: var(--text-inverse);
    border: 1px solid var(--accent-primary);
  }

  @media (max-width: 768px) {
    .notifications-header {
      flex-direction: column;
      align-items: flex-start;
    }

    .notification-item-full {
      flex-direction: column;
      gap: var(--spacing-md);
    }

    .notification-actions-full {
      align-self: flex-end;
    }
  }
</style>

<div class="notifications-page">
  <div class="container">
    <div class="notifications-header">
      <div class="notifications-title">
        <h1><i class="fas fa-bell"></i> Notifications</h1>
        <?php if ($unreadCount > 0): ?>
          <span class="unread-badge"><?php echo $unreadCount; ?> unread</span>
        <?php endif; ?>
      </div>

      <div class="notifications-actions">
        <?php if ($unreadCount > 0): ?>
          <form method="POST" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-outline btn-sm">
              <i class="fas fa-check-double"></i> Mark All Read
            </button>
          </form>
        <?php endif; ?>

        <?php if ($totalCount > 0): ?>
          <form method="POST" style="display: inline;" onsubmit="return confirm('Delete all notifications?')">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete_all">
            <button type="submit" class="btn btn-ghost btn-sm text-error">
              <i class="fas fa-trash"></i> Delete All
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="filter-tabs">
      <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
        All (<?php echo $totalCount; ?>)
      </a>
      <a href="?filter=unread" class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
        Unread (<?php echo $unreadCount; ?>)
      </a>
    </div>

    <?php if (empty($notifications)): ?>
      <div class="notifications-list">
        <div class="empty-notifications">
          <i class="far fa-bell-slash"></i>
          <h3>No notifications</h3>
          <p>
            <?php echo $filter === 'unread' ? 'You have no unread notifications.' : 'You don\'t have any notifications yet.'; ?>
          </p>
        </div>
      </div>
    <?php else: ?>
      <div class="notifications-list">
        <?php foreach ($notifications as $n): ?>
          <div class="notification-item-full <?php echo $n['is_read'] ? '' : 'unread'; ?>">
            <div class="notification-icon-lg type-<?php echo sanitize($n['type']); ?>">
              <i class="<?php
              echo match ($n['type']) {
                'application' => 'fas fa-file-alt',
                'interview' => 'fas fa-calendar-check',
                'message' => 'fas fa-envelope',
                'job' => 'fas fa-briefcase',
                default => 'fas fa-info-circle'
              };
              ?>"></i>
            </div>

            <div class="notification-body">
              <div class="notification-title-full"><?php echo sanitize($n['title']); ?></div>
              <div class="notification-message-full"><?php echo sanitize($n['message']); ?></div>
              <div class="notification-meta">
                <span><i class="far fa-clock"></i> <?php echo timeAgo($n['created_at']); ?></span>
                <?php if ($n['link']): ?>
                  <a href="<?php echo BASE_URL; ?>/notifications.php?read=<?php echo $n['id']; ?>" class="notification-link">
                    View Details <i class="fas fa-arrow-right"></i>
                  </a>
                <?php endif; ?>
              </div>
            </div>

            <div class="notification-actions-full">
              <?php if (!$n['is_read']): ?>
                <a href="<?php echo BASE_URL; ?>/notifications.php?read=<?php echo $n['id']; ?>"
                  class="btn btn-icon btn-ghost" title="Mark as read">
                  <i class="fas fa-check"></i>
                </a>
              <?php endif; ?>
              <form method="POST" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                <button type="submit" class="btn btn-icon btn-ghost text-error" title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>">
              <i class="fas fa-chevron-left"></i> Prev
            </a>
          <?php endif; ?>

          <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <?php if ($i === $page): ?>
              <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
              <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
          <?php endfor; ?>

          <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>">
              Next <i class="fas fa-chevron-right"></i>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>