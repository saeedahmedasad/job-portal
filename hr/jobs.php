<?php
/**
 * JobNexus - HR Jobs Management
 * View and manage job postings
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Job.php';
require_once '../classes/Company.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_HR) {
  header('Location: ' . BASE_URL . '/auth/login.php?redirect=hr/jobs');
  exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$jobModel = new Job();
$companyModel = new Company();

$hr = $userModel->findById($_SESSION['user_id']);

// Get HR's company
$stmt = $db->prepare("SELECT * FROM companies WHERE hr_user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
  header('Location: ' . BASE_URL . '/hr/create-company.php');
  exit;
}

// Handle job actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $jobId = (int) ($_POST['job_id'] ?? 0);

  if ($action === 'delete' && $jobId) {
    // Verify job belongs to this company
    $stmt = $db->prepare("DELETE FROM jobs WHERE id = ? AND company_id = ?");
    if ($stmt->execute([$jobId, $company['id']])) {
      $message = 'Job deleted successfully.';
      $messageType = 'success';
    } else {
      $message = 'Failed to delete job.';
      $messageType = 'error';
    }
  } elseif ($action === 'toggle_status' && $jobId) {
    $newStatus = $_POST['new_status'] ?? 'active';
    $stmt = $db->prepare("UPDATE jobs SET status = ? WHERE id = ? AND company_id = ?");
    if ($stmt->execute([$newStatus, $jobId, $company['id']])) {
      $message = 'Job status updated.';
      $messageType = 'success';
    }
  } elseif ($action === 'toggle_featured' && $jobId) {
    $stmt = $db->prepare("UPDATE jobs SET is_featured = NOT is_featured WHERE id = ? AND company_id = ?");
    if ($stmt->execute([$jobId, $company['id']])) {
      $message = 'Featured status updated.';
      $messageType = 'success';
    }
  }
}

// Check for query params
if (isset($_GET['created'])) {
  $message = 'Job posted successfully!';
  $messageType = 'success';
}
if (isset($_GET['updated'])) {
  $message = 'Job updated successfully!';
  $messageType = 'success';
}

// Get filters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');
$sortBy = $_GET['sort'] ?? 'newest';

// Build query
$sql = "SELECT j.*, 
        (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
        FROM jobs j 
        WHERE j.company_id = ?";
$params = [$company['id']];

if ($statusFilter) {
  $sql .= " AND j.status = ?";
  $params[] = $statusFilter;
}

if ($searchQuery) {
  $sql .= " AND (j.title LIKE ? OR j.description LIKE ? OR j.location LIKE ?)";
  $searchLike = "%$searchQuery%";
  $params[] = $searchLike;
  $params[] = $searchLike;
  $params[] = $searchLike;
}

// Sorting
switch ($sortBy) {
  case 'oldest':
    $sql .= " ORDER BY j.created_at ASC";
    break;
  case 'title':
    $sql .= " ORDER BY j.title ASC";
    break;
  case 'applications':
    $sql .= " ORDER BY application_count DESC";
    break;
  case 'deadline':
    $sql .= " ORDER BY j.application_deadline ASC";
    break;
  default:
    $sql .= " ORDER BY j.created_at DESC";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$stmt = $db->prepare("SELECT 
  COUNT(*) as total,
  SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
  SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
  SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
  SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
  SUM(CASE WHEN application_deadline < CURDATE() AND status = 'active' THEN 1 ELSE 0 END) as expired
  FROM jobs WHERE company_id = ?");
$stmt->execute([$company['id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total applications
$stmt = $db->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id = j.id WHERE j.company_id = ?");
$stmt->execute([$company['id']]);
$totalApplications = $stmt->fetchColumn();

$pageTitle = 'Manage Jobs';
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
          <?php echo strtoupper(substr($company['company_name'] ?? 'HR', 0, 2)); ?>
        <?php endif; ?>
      </div>
      <h3><?php echo htmlspecialchars($company['company_name'] ?? $hr['email']); ?></h3>
      <span class="role-badge hr">HR Manager</span>
    </div>

    <nav class="sidebar-nav">
      <a href="<?php echo BASE_URL; ?>/hr/index.php" class="nav-item">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/hr/jobs.php" class="nav-item active">
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
        <h1><i class="fas fa-briefcase"></i> My Job Postings</h1>
        <p>Manage your job listings and track applications</p>
      </div>
      <div class="header-right">
        <a href="<?php echo BASE_URL; ?>/hr/post-job.php" class="btn btn-primary">
          <i class="fas fa-plus"></i> Post New Job
        </a>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?php echo $messageType; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card primary">
        <div class="stat-icon">
          <i class="fas fa-briefcase"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($stats['total'] ?? 0); ?></h3>
          <p>Total Jobs</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">All time</span>
        </div>
      </div>

      <div class="stat-card success">
        <div class="stat-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($stats['active'] ?? 0); ?></h3>
          <p>Active Jobs</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">Receiving applications</span>
        </div>
      </div>

      <div class="stat-card info">
        <div class="stat-icon">
          <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($totalApplications); ?></h3>
          <p>Applications</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">Total received</span>
        </div>
      </div>

      <div class="stat-card warning">
        <div class="stat-icon">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($stats['expired'] ?? 0); ?></h3>
          <p>Expired</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">Past deadline</span>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-card glass-card">
      <form method="GET" class="filters-form">
        <div class="filter-group search-group">
          <i class="fas fa-search"></i>
          <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($searchQuery); ?>"
            placeholder="Search jobs...">
        </div>

        <div class="filter-group">
          <select name="status" class="form-control">
            <option value="">All Status</option>
            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="paused" <?php echo $statusFilter === 'paused' ? 'selected' : ''; ?>>Paused</option>
            <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
            <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Draft</option>
          </select>
        </div>

        <div class="filter-group">
          <select name="sort" class="form-control">
            <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
            <option value="oldest" <?php echo $sortBy === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
            <option value="title" <?php echo $sortBy === 'title' ? 'selected' : ''; ?>>By Title</option>
            <option value="applications" <?php echo $sortBy === 'applications' ? 'selected' : ''; ?>>Most Applications
            </option>
            <option value="deadline" <?php echo $sortBy === 'deadline' ? 'selected' : ''; ?>>By Deadline</option>
          </select>
        </div>

        <div class="filter-actions">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i> Filter
          </button>
          <?php if ($searchQuery || $statusFilter): ?>
            <a href="<?php echo BASE_URL; ?>/hr/jobs.php" class="btn btn-outline">
              <i class="fas fa-times"></i> Clear
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Jobs List -->
    <?php if (empty($jobs)): ?>
      <div class="empty-state glass-card">
        <div class="empty-icon">
          <i class="fas fa-briefcase"></i>
        </div>
        <h2>No Jobs Found</h2>
        <?php if ($searchQuery || $statusFilter): ?>
          <p>Try adjusting your filters or search criteria</p>
          <a href="<?php echo BASE_URL; ?>/hr/jobs.php" class="btn btn-primary">
            Clear Filters
          </a>
        <?php else: ?>
          <p>Start by posting your first job</p>
          <a href="<?php echo BASE_URL; ?>/hr/post-job.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Post a Job
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="data-table-card glass-card">
        <div class="table-header">
          <h3><i class="fas fa-briefcase"></i> Job Listings</h3>
          <span class="results-count"><?php echo count($jobs); ?> job(s)</span>
        </div>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Job Title</th>
                <th>Type</th>
                <th>Location</th>
                <th>Applications</th>
                <th>Status</th>
                <th>Deadline</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($jobs as $job):
                $isExpired = strtotime($job['application_deadline']) < strtotime('today');
                ?>
                <tr>
                  <td>
                    <div class="job-cell">
                      <div class="job-title">
                        <?php echo htmlspecialchars($job['title']); ?>
                        <?php if ($job['is_featured']): ?>
                          <span class="badge badge-featured">Featured</span>
                        <?php endif; ?>
                      </div>
                      <div class="job-meta">
                        Posted <?php echo date('M j, Y', strtotime($job['created_at'])); ?>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="job-type-badge <?php echo $job['job_type']; ?>">
                      <?php echo ucfirst(str_replace('-', ' ', $job['job_type'])); ?>
                    </span>
                  </td>
                  <td>
                    <span class="location-cell">
                      <i class="fas fa-map-marker-alt"></i>
                      <?php echo htmlspecialchars($job['location']); ?>
                    </span>
                  </td>
                  <td>
                    <a href="<?php echo BASE_URL; ?>/hr/applications.php?job=<?php echo $job['id']; ?>"
                      class="applications-link">
                      <strong><?php echo $job['application_count']; ?></strong> applicant(s)
                    </a>
                  </td>
                  <td>
                    <?php if ($isExpired && $job['status'] === 'active'): ?>
                      <span class="status-badge status-expired">Expired</span>
                    <?php else: ?>
                      <span class="status-badge status-<?php echo $job['status']; ?>">
                        <?php echo ucfirst($job['status']); ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="deadline-cell <?php echo $isExpired ? 'expired' : ''; ?>">
                      <?php echo date('M j, Y', strtotime($job['application_deadline'])); ?>
                    </span>
                  </td>
                  <td>
                    <div class="action-buttons">
                      <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $job['id']; ?>" class="btn-icon"
                        title="View">
                        <i class="fas fa-eye"></i>
                      </a>
                      <a href="<?php echo BASE_URL; ?>/hr/edit-job.php?id=<?php echo $job['id']; ?>" class="btn-icon"
                        title="Edit">
                        <i class="fas fa-edit"></i>
                      </a>
                      <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?');">
                        <input type="hidden" name="action" value="toggle_featured">
                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                        <button type="submit" class="btn-icon <?php echo $job['is_featured'] ? 'active' : ''; ?>"
                          title="<?php echo $job['is_featured'] ? 'Unfeature' : 'Feature'; ?>">
                          <i class="fas fa-star"></i>
                        </button>
                      </form>
                      <?php if ($job['status'] === 'active'): ?>
                        <form method="POST" style="display: inline;">
                          <input type="hidden" name="action" value="toggle_status">
                          <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                          <input type="hidden" name="new_status" value="paused">
                          <button type="submit" class="btn-icon" title="Pause">
                            <i class="fas fa-pause"></i>
                          </button>
                        </form>
                      <?php elseif ($job['status'] === 'paused'): ?>
                        <form method="POST" style="display: inline;">
                          <input type="hidden" name="action" value="toggle_status">
                          <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                          <input type="hidden" name="new_status" value="active">
                          <button type="submit" class="btn-icon" title="Activate">
                            <i class="fas fa-play"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                      <form method="POST" style="display: inline;"
                        onsubmit="return confirm('Delete this job? This cannot be undone.');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                        <button type="submit" class="btn-icon delete" title="Delete">
                          <i class="fas fa-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

  </main>
</div>

<style>
  .job-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }

  .job-cell .job-title {
    font-weight: 500;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .job-cell .job-meta {
    font-size: 0.75rem;
    color: var(--text-muted);
  }

  .location-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
  }

  .location-cell i {
    color: var(--text-muted);
  }

  .applications-link {
    color: var(--accent-primary);
    text-decoration: none;
  }

  .applications-link:hover {
    text-decoration: underline;
  }

  .deadline-cell.expired {
    color: var(--error);
  }

  .btn-icon.active {
    color: #ffc107;
  }

  .empty-state {
    text-align: center;
    padding: 4rem 2rem;
  }

  .empty-state .empty-icon {
    width: 80px;
    height: 80px;
    background: var(--bg-tertiary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    color: var(--text-muted);
  }

  .empty-state h2 {
    margin-bottom: 0.5rem;
  }

  .empty-state p {
    color: var(--text-muted);
    margin-bottom: 1.5rem;
  }
</style>

<?php require_once '../includes/footer.php'; ?>