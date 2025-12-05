<?php
/**
 * JobNexus - Seeker Applications Page
 * Track application status and history
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';
require_once '../classes/SeekerProfile.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_SEEKER) {
  header('Location: ' . BASE_URL . '/auth/login.php?redirect=seeker/applications');
  exit;
}

$db = Database::getInstance()->getConnection();
$applicationModel = new Application();
$profileModel = new SeekerProfile();
$profile = $profileModel->findByUserId($_SESSION['user_id']);

// Filters
$statusFilter = $_GET['status'] ?? '';

// Get all applications
$sql = "
    SELECT a.*, j.title as job_title, j.location, j.job_type, j.salary_min, j.salary_max,
           c.company_name, c.logo
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    LEFT JOIN companies c ON j.company_id = c.id
    WHERE a.seeker_id = ?
";

$params = [$_SESSION['user_id']];

if ($statusFilter) {
  $sql .= " AND a.status = ?";
  $params[] = $statusFilter;
}

$sql .= " ORDER BY a.applied_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Status counts
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM applications 
    WHERE seeker_id = ?
    GROUP BY status
");
$stmt->execute([$_SESSION['user_id']]);
$statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalApplications = array_sum($statusCounts);

$pageTitle = 'My Applications';
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
            alt="<?php echo htmlspecialchars($profile['first_name']); ?>"
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
      <a href="<?php echo BASE_URL; ?>/seeker/applications.php" class="nav-item active">
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
      <a href="<?php echo BASE_URL; ?>/seeker/settings.php" class="nav-item">
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
        <h1><i class="fas fa-file-alt"></i> My Applications</h1>
        <p>Track your job application status</p>
      </div>
      <div class="header-right">
        <a href="<?php echo BASE_URL; ?>/jobs" class="btn btn-primary">
          <i class="fas fa-search"></i> Find More Jobs
        </a>
      </div>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
      <a href="<?php echo BASE_URL; ?>/seeker/applications.php"
        class="stat-item <?php echo !$statusFilter ? 'active' : ''; ?>">
        <span class="stat-count"><?php echo $totalApplications; ?></span>
        <span class="stat-label">All</span>
      </a>
      <?php
      $statuses = [
        'applied' => ['label' => 'Applied', 'icon' => 'paper-plane', 'color' => 'warning'],
        'viewed' => ['label' => 'Viewed', 'icon' => 'eye', 'color' => 'info'],
        'shortlisted' => ['label' => 'Shortlisted', 'icon' => 'star', 'color' => 'primary'],
        'interview' => ['label' => 'Interview', 'icon' => 'calendar', 'color' => 'purple'],
        'offered' => ['label' => 'Offered', 'icon' => 'gift', 'color' => 'success'],
        'hired' => ['label' => 'Hired', 'icon' => 'trophy', 'color' => 'success'],
        'rejected' => ['label' => 'Rejected', 'icon' => 'times', 'color' => 'danger']
      ];
      foreach ($statuses as $status => $info):
        ?>
        <a href="?status=<?php echo $status; ?>"
          class="stat-item <?php echo $statusFilter === $status ? 'active' : ''; ?> <?php echo $info['color']; ?>">
          <span class="stat-icon"><i class="fas fa-<?php echo $info['icon']; ?>"></i></span>
          <span class="stat-count"><?php echo $statusCounts[$status] ?? 0; ?></span>
          <span class="stat-label"><?php echo $info['label']; ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Applications List -->
    <?php if (empty($applications)): ?>
      <div class="empty-state">
        <div class="empty-icon">
          <i class="fas fa-paper-plane"></i>
        </div>
        <h2>No Applications Yet</h2>
        <p>Start your job search journey and apply to positions that match your skills</p>
        <a href="<?php echo BASE_URL; ?>/jobs" class="btn btn-primary btn-lg">
          <i class="fas fa-search"></i> Browse Jobs
        </a>
      </div>
    <?php else: ?>
      <div class="applications-list">
        <?php foreach ($applications as $app): ?>
          <div class="application-card">
            <div class="card-left">
              <div class="company-logo">
                <?php if ($app['logo']): ?>
                  <img src="<?php echo BASE_URL; ?>/uploads/logos/<?php echo $app['logo']; ?>" alt="">
                <?php else: ?>
                  <i class="fas fa-building"></i>
                <?php endif; ?>
              </div>
            </div>

            <div class="card-main">
              <div class="job-info">
                <h3>
                  <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $app['job_id']; ?>">
                    <?php echo htmlspecialchars($app['job_title']); ?>
                  </a>
                </h3>
                <p class="company"><?php echo htmlspecialchars($app['company_name']); ?></p>
              </div>

              <div class="job-meta">
                <span class="meta-item">
                  <i class="fas fa-map-marker-alt"></i>
                  <?php echo htmlspecialchars($app['location']); ?>
                </span>
                <span class="meta-item job-type <?php echo $app['job_type']; ?>">
                  <?php echo ucfirst(str_replace('-', ' ', $app['job_type'])); ?>
                </span>
                <?php if ($app['salary_min'] || $app['salary_max']): ?>
                  <span class="meta-item salary">
                    <i class="fas fa-dollar-sign"></i>
                    <?php
                    if ($app['salary_min'] && $app['salary_max']) {
                      echo number_format($app['salary_min']) . ' - ' . number_format($app['salary_max']);
                    } elseif ($app['salary_min']) {
                      echo 'From ' . number_format($app['salary_min']);
                    } else {
                      echo 'Up to ' . number_format($app['salary_max']);
                    }
                    ?>
                  </span>
                <?php endif; ?>
              </div>

              <div class="application-date">
                <i class="fas fa-clock"></i>
                Applied on <?php echo date('F j, Y', strtotime($app['applied_at'])); ?>
                <span class="time-ago">
                  (<?php
                  $diff = time() - strtotime($app['applied_at']);
                  if ($diff < 86400) {
                    echo 'today';
                  } elseif ($diff < 172800) {
                    echo 'yesterday';
                  } elseif ($diff < 604800) {
                    echo floor($diff / 86400) . ' days ago';
                  } elseif ($diff < 2592000) {
                    echo floor($diff / 604800) . ' weeks ago';
                  } else {
                    echo floor($diff / 2592000) . ' months ago';
                  }
                  ?>)
                </span>
              </div>
            </div>

            <div class="card-right">
              <?php
              $statusInfo = $statuses[$app['status']] ?? ['label' => ucfirst($app['status']), 'icon' => 'circle'];
              ?>
              <div class="status-badge <?php echo $app['status']; ?>">
                <i class="fas fa-<?php echo $statusInfo['icon']; ?>"></i>
                <?php echo $statusInfo['label']; ?>
              </div>

              <?php if ($app['status'] === 'interview'): ?>
                <a href="<?php echo BASE_URL; ?>/seeker/calendar.php" class="btn btn-outline-primary btn-sm">
                  <i class="fas fa-calendar"></i> View Schedule
                </a>
              <?php endif; ?>

              <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $app['job_id']; ?>" class="btn btn-text">
                View Job <i class="fas fa-arrow-right"></i>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<style>
  /* Seeker Applications Page */
  .applications-page.seeker {
    min-height: calc(100vh - 70px);
    padding: 2rem;
    margin-top: 70px;
    background: var(--bg-dark);
  }

  .page-container {
    max-width: 1100px;
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
    gap: 0.75rem;
    margin-bottom: 2rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
  }

  .stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem 1.25rem;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    text-decoration: none;
    color: var(--text-light);
    min-width: 90px;
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

  .stat-icon {
    margin-bottom: 0.25rem;
    font-size: 1rem;
  }

  .stat-item.warning .stat-icon {
    color: #FFC107;
  }

  .stat-item.info .stat-icon {
    color: #2196F3;
  }

  .stat-item.primary .stat-icon {
    color: var(--primary-color);
  }

  .stat-item.purple .stat-icon {
    color: #9C27B0;
  }

  .stat-item.success .stat-icon {
    color: #4CAF50;
  }

  .stat-item.danger .stat-icon {
    color: #F44336;
  }

  .stat-count {
    font-size: 1.25rem;
    font-weight: 700;
  }

  .stat-label {
    font-size: 0.7rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  /* Applications List */
  .applications-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .application-card {
    display: flex;
    gap: 1.5rem;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s ease;
  }

  .application-card:hover {
    border-color: var(--primary-color);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
  }

  .card-left {
    flex-shrink: 0;
  }

  .company-logo {
    width: 70px;
    height: 70px;
    background: var(--bg-dark);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }

  .company-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }

  .company-logo i {
    font-size: 1.5rem;
    color: var(--text-muted);
  }

  .card-main {
    flex: 1;
    min-width: 0;
  }

  .job-info h3 {
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
  }

  .job-info h3 a {
    color: var(--text-light);
    text-decoration: none;
  }

  .job-info h3 a:hover {
    color: var(--primary-color);
  }

  .job-info .company {
    color: var(--primary-color);
    font-weight: 500;
    margin-bottom: 0.75rem;
  }

  .job-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 0.75rem;
  }

  .meta-item {
    font-size: 0.85rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .meta-item i {
    color: var(--primary-color);
  }

  .meta-item.job-type {
    padding: 0.25rem 0.75rem;
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

  .meta-item.salary {
    color: var(--primary-color);
    font-weight: 500;
  }

  .application-date {
    font-size: 0.8rem;
    color: var(--text-muted);
  }

  .application-date i {
    margin-right: 0.5rem;
  }

  .time-ago {
    opacity: 0.7;
  }

  .card-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.75rem;
    flex-shrink: 0;
  }

  /* Status Badge */
  .status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .status-badge.applied {
    background: rgba(255, 193, 7, 0.15);
    color: #FFC107;
  }

  .status-badge.viewed {
    background: rgba(33, 150, 243, 0.15);
    color: #2196F3;
  }

  .status-badge.shortlisted {
    background: rgba(0, 230, 118, 0.15);
    color: var(--primary-color);
  }

  .status-badge.interview {
    background: rgba(156, 39, 176, 0.15);
    color: #9C27B0;
  }

  .status-badge.offered,
  .status-badge.hired {
    background: rgba(76, 175, 80, 0.15);
    color: #4CAF50;
  }

  .status-badge.rejected,
  .status-badge.withdrawn {
    background: rgba(244, 67, 54, 0.15);
    color: #F44336;
  }

  .btn-text {
    color: var(--text-muted);
    background: none;
    border: none;
    font-size: 0.85rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    transition: color 0.3s ease;
  }

  .btn-text:hover {
    color: var(--primary-color);
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
    .applications-page.seeker {
      padding: 1rem;
    }

    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }

    .stats-bar {
      gap: 0.5rem;
    }

    .stat-item {
      padding: 0.75rem;
      min-width: 70px;
    }

    .application-card {
      flex-direction: column;
    }

    .card-left {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .company-logo {
      width: 50px;
      height: 50px;
    }

    .card-right {
      flex-direction: row;
      flex-wrap: wrap;
      justify-content: flex-start;
      align-items: center;
    }
  }
</style>

<?php require_once '../includes/footer.php'; ?>