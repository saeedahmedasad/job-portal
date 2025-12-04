<?php
/**
 * JobNexus - Admin User Management
 * Manage all users in the system
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_ADMIN) {
  header('Location: ../auth/login.php');
  exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $targetUserId = intval($_POST['user_id'] ?? 0);

  if ($action === 'update_status' && $targetUserId) {
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['active', 'inactive'])) {
      $isActive = ($status === 'active') ? 1 : 0;
      $stmt = $db->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
      if ($stmt->execute([$isActive, $targetUserId])) {
        $message = 'User status updated successfully.';
        $messageType = 'success';
      } else {
        $message = 'Error updating user status.';
        $messageType = 'error';
      }
    }
  }

  if ($action === 'delete' && $targetUserId) {
    // Don't allow deleting yourself
    if ($targetUserId === $_SESSION['user_id']) {
      $message = 'You cannot delete your own account.';
      $messageType = 'error';
    } else {
      // Soft delete - set is_active to 0
      $stmt = $db->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?");
      if ($stmt->execute([$targetUserId])) {
        $message = 'User deleted successfully.';
        $messageType = 'success';
      } else {
        $message = 'Error deleting user.';
        $messageType = 'error';
      }
    }
  }
}

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["1=1"];
$params = [];

if ($search) {
  $where[] = "(u.email LIKE ?)";
  $searchTerm = "%$search%";
  $params[] = $searchTerm;
}

if ($roleFilter) {
  $where[] = "u.role = ?";
  $params[] = $roleFilter;
}

if ($statusFilter) {
  if ($statusFilter === 'active') {
    $where[] = "u.is_active = 1";
  } elseif ($statusFilter === 'inactive') {
    $where[] = "u.is_active = 0";
  }
}

$whereClause = implode(' AND ', $where);

// Get total count
$countStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE $whereClause");
$countStmt->execute($params);
$totalUsers = $countStmt->fetchColumn();
$totalPages = ceil($totalUsers / $perPage);

// Get users
$sql = "
    SELECT u.*, c.company_name
    FROM users u
    LEFT JOIN companies c ON c.hr_user_id = u.id
    WHERE $whereClause
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$statsStmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN role = 'seeker' THEN 1 ELSE 0 END) as seekers,
        SUM(CASE WHEN role = 'hr' THEN 1 ELSE 0 END) as hr,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
    FROM users
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = "User Management - JobNexus Admin";
include '../includes/header.php';
?>

<div class="dashboard-container">
  <!-- Sidebar -->
  <aside class="dashboard-sidebar">
    <div class="sidebar-header">
      <div class="admin-avatar">
        <?php echo strtoupper(substr($_SESSION['user_email'] ?? 'A', 0, 1)); ?>
      </div>
      <h3><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Admin'); ?></h3>
      <span class="role-badge admin">Administrator</span>
    </div>

    <nav class="sidebar-nav">
      <a href="<?php echo BASE_URL; ?>/admin/index.php" class="nav-item">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/admin/users.php" class="nav-item active">
        <i class="fas fa-users"></i>
        <span>Manage Users</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/admin/companies.php" class="nav-item">
        <i class="fas fa-building"></i>
        <span>Companies</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/admin/jobs.php" class="nav-item">
        <i class="fas fa-briefcase"></i>
        <span>All Jobs</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/admin/reports.php" class="nav-item">
        <i class="fas fa-chart-bar"></i>
        <span>Reports</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/admin/settings.php" class="nav-item">
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
        <h1><i class="fas fa-users"></i> User Management</h1>
        <p>Manage all users and their access levels</p>
      </div>
      <div class="header-right">
        <!-- <button class="btn btn-primary" onclick="openModal('addUserModal')">
          <i class="fas fa-user-plus"></i> Add User
        </button> -->
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?php echo $messageType; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card primary">
        <div class="stat-icon">
          <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($stats['total']); ?></h3>
          <p>Total Users</p>
        </div>
      </div>
      <div class="stat-card info">
        <div class="stat-icon">
          <i class="fas fa-user-tie"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($stats['seekers']); ?></h3>
          <p>Job Seekers</p>
        </div>
      </div>
      <div class="stat-card purple">
        <div class="stat-icon">
          <i class="fas fa-user-cog"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($stats['hr']); ?></h3>
          <p>HR Managers</p>
        </div>
      </div>
      <div class="stat-card success">
        <div class="stat-icon">
          <i class="fas fa-user-plus"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($stats['today']); ?></h3>
          <p>New Today</p>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-card glass-card">
      <form method="GET" class="filters-form">
        <div class="filter-group">
          <label for="search">Search</label>
          <input type="text" id="search" name="search" placeholder="Search users..."
            value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-group">
          <label for="role">Role</label>
          <select id="role" name="role">
            <option value="">All Roles</option>
            <option value="<?php echo ROLE_SEEKER; ?>" <?php echo $roleFilter === ROLE_SEEKER ? 'selected' : ''; ?>>Job
              Seekers</option>
            <option value="<?php echo ROLE_HR; ?>" <?php echo $roleFilter === ROLE_HR ? 'selected' : ''; ?>>HR Managers
            </option>
            <option value="<?php echo ROLE_ADMIN; ?>" <?php echo $roleFilter === ROLE_ADMIN ? 'selected' : ''; ?>>
              Administrators</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="">All Status</option>
            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
          </select>
        </div>
        <div class="filter-actions">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-search"></i> Filter
          </button>
          <?php if ($search || $roleFilter || $statusFilter): ?>
            <a href="users.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Users Table -->
    <div class="data-table-card glass-card">
      <div class="table-header">
        <h3><i class="fas fa-list"></i> User Accounts (<?php echo number_format($totalUsers); ?>)</h3>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Email</th>
              <th>Role</th>
              <th>Company</th>
              <th>Status</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr>
                <td colspan="7" class="empty-table">
                  <i class="fas fa-users"></i>
                  <p>No users found</p>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td>
                    <div class="user-cell">
                      <div class="user-avatar-small">
                        <?php echo strtoupper(substr($u['email'], 0, 1)); ?>
                      </div>
                      <div class="user-details">
                        <strong><?php echo htmlspecialchars($u['email']); ?></strong>
                        <span class="user-id">ID: <?php echo $u['id']; ?></span>
                      </div>
                    </div>
                  </td>
                  <td><?php echo htmlspecialchars($u['email']); ?></td>
                  <td>
                    <?php
                    $roleLabels = [
                      ROLE_ADMIN => ['Admin', 'admin'],
                      ROLE_HR => ['HR Manager', 'hr'],
                      ROLE_SEEKER => ['Seeker', 'seeker']
                    ];
                    $roleInfo = $roleLabels[$u['role']] ?? ['Unknown', 'default'];
                    ?>
                    <span class="role-badge <?php echo $roleInfo[1]; ?>"><?php echo $roleInfo[0]; ?></span>
                  </td>
                  <td>
                    <?php echo $u['company_name'] ? htmlspecialchars($u['company_name']) : '-'; ?>
                  </td>
                  <td>
                    <span class="status-badge <?php echo $u['is_active'] ? 'active' : 'inactive'; ?>">
                      <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                  </td>
                  <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                  <td>
                    <div class="action-buttons">
                      <button class="btn btn-icon" onclick="viewUser(<?php echo $u['id']; ?>)" title="View">
                        <i class="fas fa-eye"></i>
                      </button>
                      <button class="btn btn-icon" onclick="editUser(<?php echo htmlspecialchars(json_encode($u)); ?>)"
                        title="Edit">
                        <i class="fas fa-edit"></i>
                      </button>
                      <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                        <button class="btn btn-icon danger"
                          onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['email']); ?>')"
                          title="Delete">
                          <i class="fas fa-trash"></i>
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $roleFilter; ?>&status=<?php echo urlencode($statusFilter); ?>"
              class="btn btn-icon">
              <i class="fas fa-chevron-left"></i>
            </a>
          <?php endif; ?>

          <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

          <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $roleFilter; ?>&status=<?php echo urlencode($statusFilter); ?>"
              class="btn btn-icon">
              <i class="fas fa-chevron-right"></i>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Edit User</h3>
      <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
    </div>
    <div class="modal-body">
      <form id="editUserForm" method="POST">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="user_id" id="editUserId">

        <div class="form-group">
          <label>User</label>
          <input type="text" id="editUserName" disabled>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select name="status" id="editUserStatus">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn-ghost" onclick="closeModal('editUserModal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
  <div class="modal-content modal-sm">
    <div class="modal-header">
      <h3>Confirm Delete</h3>
      <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
    </div>
    <div class="modal-body">
      <p>Are you sure you want to delete <strong id="deleteUserName"></strong>?</p>
      <p class="text-muted">This action cannot be undone.</p>

      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" id="deleteUserId">

        <div class="form-actions">
          <button type="button" class="btn btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  /* Page-specific overrides only - most styles come from dashboard.css */
  .stat-card.info .stat-icon {
    background: rgba(100, 181, 246, 0.15);
    color: #64b5f6;
  }

  .stat-card.purple .stat-icon {
    background: rgba(186, 104, 200, 0.15);
    color: #ba68c8;
  }
</style>

<script>
  function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
  }

  function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
  }

  function viewUser(userId) {
    window.location.href = 'user-detail.php?id=' + userId;
  }

  function editUser(userData) {
    const user = typeof userData === 'string' ? JSON.parse(userData) : userData;

    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUserName').value = user.email;
    document.getElementById('editUserStatus').value = user.is_active ? 'active' : 'inactive';

    openModal('editUserModal');
  }

  function confirmDelete(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    openModal('deleteModal');
  }

  // Close modal on outside click
  document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function (e) {
      if (e.target === this) {
        this.classList.remove('active');
      }
    });
  });
</script>

<?php include '../includes/footer.php'; ?>