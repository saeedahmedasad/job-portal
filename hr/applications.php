<?php
/**
 * JobNexus - HR Applications Management (ATS)
 * Application Tracking System for recruiters
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Job.php';
require_once '../classes/Application.php';
require_once '../classes/Company.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_HR) {
  header('Location: ' . BASE_URL . '/auth/login.php?redirect=hr/applications');
  exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$applicationModel = new Application();
$companyModel = new Company();

$hr = $userModel->findById($_SESSION['user_id']);
$company = $companyModel->findByHRUserId($_SESSION['user_id']);

// Check if company is verified
if (!$company || $company['verification_status'] !== 'verified') {
  header('Location: ' . BASE_URL . '/hr/index.php');
  exit;
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$jobFilter = $_GET['job'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');
$sortBy = $_GET['sort'] ?? 'newest';

// Get HR's jobs for filter dropdown
$stmt = $db->prepare("SELECT id, title FROM jobs WHERE posted_by = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$myJobs = $stmt->fetchAll();

// Build query
$sql = "
    SELECT a.*, j.title as job_title, j.location as job_location, j.job_type,
           CONCAT(sp.first_name, ' ', sp.last_name) as applicant_name, u.email as applicant_email,
           sp.headline, sp.phone, sp.resume_file_path as resume_path
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    JOIN users u ON a.seeker_id = u.id
    LEFT JOIN seeker_profiles sp ON u.id = sp.user_id
    WHERE j.posted_by = ?
";

$params = [$_SESSION['user_id']];

if ($statusFilter) {
  $sql .= " AND a.status = ?";
  $params[] = $statusFilter;
}

if ($jobFilter) {
  $sql .= " AND a.job_id = ?";
  $params[] = $jobFilter;
}

if ($searchQuery) {
  $sql .= " AND (CONCAT(sp.first_name, ' ', sp.last_name) LIKE ? OR u.email LIKE ? OR j.title LIKE ?)";
  $searchParam = '%' . $searchQuery . '%';
  $params[] = $searchParam;
  $params[] = $searchParam;
  $params[] = $searchParam;
}

// Sorting
switch ($sortBy) {
  case 'oldest':
    $sql .= " ORDER BY a.applied_at ASC";
    break;
  case 'name':
    $sql .= " ORDER BY sp.first_name ASC, sp.last_name ASC";
    break;
  case 'status':
    $sql .= " ORDER BY a.status ASC, a.applied_at DESC";
    break;
  default:
    $sql .= " ORDER BY a.applied_at DESC";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Status counts
$stmt = $db->prepare("
    SELECT a.status, COUNT(*) as count 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE j.posted_by = ?
    GROUP BY a.status
");
$stmt->execute([$_SESSION['user_id']]);
$statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalApplications = array_sum($statusCounts);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
  $appId = (int) $_POST['application_id'];
  $newStatus = $_POST['status'];

  // Verify ownership
  $stmt = $db->prepare("
        SELECT a.id FROM applications a 
        JOIN jobs j ON a.job_id = j.id 
        WHERE a.id = ? AND j.posted_by = ?
    ");
  $stmt->execute([$appId, $_SESSION['user_id']]);

  if ($stmt->fetch()) {
    $applicationModel->updateStatus($appId, $newStatus);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
  }
}

$pageTitle = 'Applications';
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
      <a href="<?php echo BASE_URL; ?>/hr/jobs.php" class="nav-item">
        <i class="fas fa-briefcase"></i>
        <span>My Jobs</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/hr/post-job.php" class="nav-item">
        <i class="fas fa-plus-circle"></i>
        <span>Post New Job</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/hr/applications.php" class="nav-item active">
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
        <h1><i class="fas fa-file-alt"></i> Application Tracking</h1>
        <p>Manage and track all incoming applications</p>
      </div>
      <div class="header-right">
        <a href="<?php echo BASE_URL; ?>/hr/post-job.php" class="btn btn-primary">
          <i class="fas fa-plus"></i> Post New Job
        </a>
      </div>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
      <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>"
        class="stat-item <?php echo !$statusFilter ? 'active' : ''; ?>">
        <span class="stat-count"><?php echo $totalApplications; ?></span>
        <span class="stat-label">All</span>
      </a>
      <?php
      $statuses = [
        'applied' => ['label' => 'New', 'color' => 'warning'],
        'viewed' => ['label' => 'Viewed', 'color' => 'info'],
        'shortlisted' => ['label' => 'Shortlisted', 'color' => 'primary'],
        'interview' => ['label' => 'Interview', 'color' => 'purple'],
        'offered' => ['label' => 'Offered', 'color' => 'success'],
        'hired' => ['label' => 'Hired', 'color' => 'success'],
        'rejected' => ['label' => 'Rejected', 'color' => 'danger']
      ];
      foreach ($statuses as $status => $info):
        ?>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => $status])); ?>"
          class="stat-item <?php echo $statusFilter === $status ? 'active' : ''; ?> <?php echo $info['color']; ?>">
          <span class="stat-count"><?php echo $statusCounts[$status] ?? 0; ?></span>
          <span class="stat-label"><?php echo $info['label']; ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <form method="GET" class="filters-form">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">

        <div class="filter-group search-group">
          <i class="fas fa-search"></i>
          <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($searchQuery); ?>"
            placeholder="Search by name, email, or job...">
        </div>

        <div class="filter-group">
          <select name="job" class="form-control">
            <option value="">All Jobs</option>
            <?php foreach ($myJobs as $job): ?>
              <option value="<?php echo $job['id']; ?>" <?php echo $jobFilter == $job['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($job['title']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <select name="sort" class="form-control">
            <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
            <option value="oldest" <?php echo $sortBy === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
            <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>By Name</option>
            <option value="status" <?php echo $sortBy === 'status' ? 'selected' : ''; ?>>By Status</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="fas fa-filter"></i> Filter
        </button>

        <?php if ($searchQuery || $jobFilter || $statusFilter): ?>
          <a href="<?php echo BASE_URL; ?>/hr/applications.php" class="btn btn-outline-secondary">
            <i class="fas fa-times"></i> Clear
          </a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Applications List -->
    <?php if (empty($applications)): ?>
      <div class="empty-state">
        <div class="empty-icon">
          <i class="fas fa-inbox"></i>
        </div>
        <h2>No Applications Found</h2>
        <?php if ($searchQuery || $jobFilter || $statusFilter): ?>
          <p>Try adjusting your filters or search criteria</p>
          <a href="<?php echo BASE_URL; ?>/hr/applications.php" class="btn btn-primary">
            Clear Filters
          </a>
        <?php else: ?>
          <p>Applications will appear here when candidates apply to your jobs</p>
          <a href="<?php echo BASE_URL; ?>/hr/post-job.php" class="btn btn-primary">
            Post a Job
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="applications-grid">
        <?php foreach ($applications as $app): ?>
          <div class="application-card" data-status="<?php echo $app['status']; ?>">
            <div class="card-header">
              <div class="applicant-avatar">
                <?php echo strtoupper(substr($app['applicant_name'], 0, 1)); ?>
              </div>
              <div class="applicant-info">
                <h3><?php echo htmlspecialchars($app['applicant_name']); ?></h3>
                <p class="headline"><?php echo htmlspecialchars($app['headline'] ?? $app['applicant_email']); ?></p>
              </div>
              <div class="status-dropdown">
                <form method="POST" class="status-form">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                  <select name="status" class="status-select <?php echo $app['status']; ?>" onchange="this.form.submit()">
                    <option value="applied" <?php echo $app['status'] === 'applied' ? 'selected' : ''; ?>>New</option>
                    <option value="viewed" <?php echo $app['status'] === 'viewed' ? 'selected' : ''; ?>>Viewed</option>
                    <option value="shortlisted" <?php echo $app['status'] === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted
                    </option>
                    <option value="interview" <?php echo $app['status'] === 'interview' ? 'selected' : ''; ?>>Interview
                    </option>
                    <option value="offered" <?php echo $app['status'] === 'offered' ? 'selected' : ''; ?>>Offered</option>
                    <option value="hired" <?php echo $app['status'] === 'hired' ? 'selected' : ''; ?>>Hired</option>
                    <option value="rejected" <?php echo $app['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                  </select>
                </form>
              </div>
            </div>

            <div class="card-body">
              <div class="job-applied">
                <i class="fas fa-briefcase"></i>
                <span><?php echo htmlspecialchars($app['job_title']); ?></span>
              </div>

              <div class="meta-row">
                <span class="meta-item">
                  <i class="fas fa-calendar"></i>
                  Applied <?php echo date('M j, Y', strtotime($app['applied_at'])); ?>
                </span>
                <span class="meta-item job-type <?php echo $app['job_type']; ?>">
                  <?php echo ucfirst(str_replace('-', ' ', $app['job_type'])); ?>
                </span>
              </div>

              <?php if ($app['cover_letter']): ?>
                <div class="cover-letter-preview">
                  <p><?php echo htmlspecialchars(substr($app['cover_letter'], 0, 150)); ?>...</p>
                </div>
              <?php endif; ?>
            </div>

            <div class="card-footer">
              <div class="contact-actions">
                <a href="mailto:<?php echo $app['applicant_email']; ?>" class="btn-icon" title="Email">
                  <i class="fas fa-envelope"></i>
                </a>
                <?php if ($app['phone']): ?>
                  <a href="tel:<?php echo $app['phone']; ?>" class="btn-icon" title="Call">
                    <i class="fas fa-phone"></i>
                  </a>
                <?php endif; ?>
                <?php if ($app['resume_path']): ?>
                  <a href="<?php echo BASE_URL; ?>/uploads/resumes/<?php echo $app['resume_path']; ?>" class="btn-icon"
                    title="View Resume" target="_blank">
                    <i class="fas fa-file-pdf"></i>
                  </a>
                <?php endif; ?>
              </div>
              <div class="main-actions">
                <a href="<?php echo BASE_URL; ?>/hr/application.php?id=<?php echo $app['id']; ?>"
                  class="btn btn-primary btn-sm">
                  View Details
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<style>
  /* Applications Page */
  .applications-page {
    min-height: calc(100vh - 70px);
    padding: 2rem;
    margin-top: 70px;
    background: var(--bg-dark);
  }

  .page-container {
    max-width: 1400px;
    margin: 0 auto;
  }

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
  }

  .page-header h1 {
    font-family: var(--font-heading);
    font-size: 2rem;
    margin-bottom: 0.25rem;
  }

  .page-header p {
    color: var(--text-muted);
  }

  /* Stats Bar */
  .stats-bar {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
  }

  .stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem 1.5rem;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    text-decoration: none;
    color: var(--text-light);
    min-width: 100px;
    transition: all 0.3s ease;
  }

  .stat-item:hover {
    border-color: var(--primary-color);
    transform: translateY(-2px);
  }

  .stat-item.active {
    background: rgba(0, 230, 118, 0.1);
    border-color: var(--primary-color);
  }

  .stat-item.warning.active {
    background: rgba(255, 193, 7, 0.1);
    border-color: #FFC107;
  }

  .stat-item.info.active {
    background: rgba(33, 150, 243, 0.1);
    border-color: #2196F3;
  }

  .stat-item.primary.active {
    background: rgba(0, 230, 118, 0.1);
    border-color: var(--primary-color);
  }

  .stat-item.purple.active {
    background: rgba(156, 39, 176, 0.1);
    border-color: #9C27B0;
  }

  .stat-item.success.active {
    background: rgba(76, 175, 80, 0.1);
    border-color: #4CAF50;
  }

  .stat-item.danger.active {
    background: rgba(244, 67, 54, 0.1);
    border-color: #F44336;
  }

  .stat-count {
    font-size: 1.5rem;
    font-weight: 700;
  }

  .stat-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  /* Filters Bar */
  .filters-bar {
    background: var(--card-bg);
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    border: 1px solid var(--border-color);
  }

  .filters-form {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
  }

  .filter-group {
    position: relative;
  }

  .filter-group.search-group {
    flex: 1;
    min-width: 250px;
  }

  .filter-group.search-group i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
  }

  .filter-group.search-group input {
    padding-left: 2.5rem;
  }

  .filter-group .form-control {
    min-width: 180px;
  }

  /* Applications Grid */
  .applications-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.5rem;
  }

  /* Application Card */
  .application-card {
    background: var(--card-bg);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s ease;
  }

  .application-card:hover {
    border-color: var(--primary-color);
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  }

  .application-card .card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
  }

  .applicant-avatar {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #9C27B0, var(--primary-color));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 600;
    color: white;
    flex-shrink: 0;
  }

  .applicant-info {
    flex: 1;
    min-width: 0;
  }

  .applicant-info h3 {
    font-size: 1rem;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .applicant-info .headline {
    color: var(--text-muted);
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* Status Dropdown */
  .status-dropdown {
    flex-shrink: 0;
  }

  .status-select {
    padding: 0.5rem 2rem 0.5rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.5rem center;
    background-size: 16px;
  }

  .status-select.pending {
    background-color: rgba(255, 193, 7, 0.15);
    color: #FFC107;
  }

  .status-select.reviewed {
    background-color: rgba(33, 150, 243, 0.15);
    color: #2196F3;
  }

  .status-select.shortlisted {
    background-color: rgba(0, 230, 118, 0.15);
    color: var(--primary-color);
  }

  .status-select.interview {
    background-color: rgba(156, 39, 176, 0.15);
    color: #9C27B0;
  }

  .status-select.offered,
  .status-select.hired {
    background-color: rgba(76, 175, 80, 0.15);
    color: #4CAF50;
  }

  .status-select.rejected {
    background-color: rgba(244, 67, 54, 0.15);
    color: #F44336;
  }

  /* Card Body */
  .application-card .card-body {
    padding: 1.5rem;
  }

  .job-applied {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
  }

  .job-applied i {
    color: var(--primary-color);
  }

  .job-applied span {
    font-weight: 500;
  }

  .meta-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
  }

  .meta-item {
    font-size: 0.8rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .meta-item.job-type {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-weight: 500;
  }

  .meta-item.job-type.full-time {
    background: rgba(0, 230, 118, 0.1);
    color: var(--primary-color);
  }

  .meta-item.job-type.part-time {
    background: rgba(33, 150, 243, 0.1);
    color: #2196F3;
  }

  .meta-item.job-type.contract {
    background: rgba(255, 193, 7, 0.1);
    color: #FFC107;
  }

  .meta-item.job-type.internship {
    background: rgba(156, 39, 176, 0.1);
    color: #9C27B0;
  }

  .cover-letter-preview {
    padding: 1rem;
    background: var(--bg-dark);
    border-radius: 8px;
  }

  .cover-letter-preview p {
    font-size: 0.85rem;
    color: var(--text-muted);
    line-height: 1.6;
    margin: 0;
  }

  /* Card Footer */
  .application-card .card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    background: var(--bg-dark);
    border-top: 1px solid var(--border-color);
  }

  .contact-actions {
    display: flex;
    gap: 0.5rem;
  }

  .contact-actions .btn-icon {
    width: 36px;
    height: 36px;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    text-decoration: none;
    transition: all 0.3s ease;
  }

  .contact-actions .btn-icon:hover {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--bg-dark);
  }

  /* Empty State */
  .empty-state {
    text-align: center;
    padding: 5rem 2rem;
    background: var(--card-bg);
    border-radius: 20px;
    border: 1px solid var(--border-color);
  }

  .empty-icon {
    width: 100px;
    height: 100px;
    background: rgba(0, 230, 118, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
  }

  .empty-icon i {
    font-size: 3rem;
    color: var(--primary-color);
  }

  .empty-state h2 {
    font-family: var(--font-heading);
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
  }

  .empty-state p {
    color: var(--text-muted);
    margin-bottom: 1.5rem;
  }

  /* Responsive */
  @media (max-width: 768px) {
    .applications-page {
      padding: 1rem;
    }

    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }

    .stats-bar {
      gap: 0.25rem;
    }

    .stat-item {
      padding: 0.75rem 1rem;
      min-width: 70px;
    }

    .stat-count {
      font-size: 1.25rem;
    }

    .filters-form {
      flex-direction: column;
      align-items: stretch;
    }

    .filter-group {
      width: 100%;
    }

    .filter-group .form-control {
      width: 100%;
    }

    .applications-grid {
      grid-template-columns: 1fr;
    }

    .application-card .card-header {
      flex-wrap: wrap;
    }

    .status-dropdown {
      width: 100%;
      margin-top: 0.5rem;
    }

    .status-select {
      width: 100%;
    }
  }
</style>

<?php require_once '../includes/footer.php'; ?>