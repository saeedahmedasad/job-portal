<?php
/**
 * JobNexus - HR Reports & Analytics
 * View recruitment performance metrics and analytics
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Company.php';

// Check authentication and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_HR) {
  header('Location: ' . BASE_URL . '/auth/login.php?redirect=hr/reports');
  exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();

// Get HR's company
$stmt = $db->prepare("SELECT * FROM companies WHERE hr_user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company || $company['verification_status'] !== 'verified') {
  header('Location: ' . BASE_URL . '/hr/index.php');
  exit;
}

$hr = $userModel->findById($_SESSION['user_id']);

// Get date range filter
$dateRange = $_GET['range'] ?? '30days';
$customStart = $_GET['start'] ?? '';
$customEnd = $_GET['end'] ?? '';

// Calculate date range
switch ($dateRange) {
  case '7days':
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = date('Y-m-d');
    $rangeLabel = 'Last 7 Days';
    break;
  case '30days':
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');
    $rangeLabel = 'Last 30 Days';
    break;
  case '90days':
    $startDate = date('Y-m-d', strtotime('-90 days'));
    $endDate = date('Y-m-d');
    $rangeLabel = 'Last 90 Days';
    break;
  case 'year':
    $startDate = date('Y-m-d', strtotime('-1 year'));
    $endDate = date('Y-m-d');
    $rangeLabel = 'Last Year';
    break;
  case 'custom':
    $startDate = $customStart ?: date('Y-m-d', strtotime('-30 days'));
    $endDate = $customEnd ?: date('Y-m-d');
    $rangeLabel = 'Custom Range';
    break;
  default:
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');
    $rangeLabel = 'Last 30 Days';
}

// Job Statistics for this company
$stmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE company_id = ? AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)");
$stmt->execute([$company['id'], $startDate, $endDate]);
$newJobs = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE company_id = ? AND status = 'active'");
$stmt->execute([$company['id']]);
$activeJobs = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM jobs WHERE company_id = ? GROUP BY status");
$stmt->execute([$company['id']]);
$jobsByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Application Statistics
$stmt = $db->prepare("
  SELECT COUNT(*) FROM applications a
  JOIN jobs j ON a.job_id = j.id
  WHERE j.company_id = ? AND a.applied_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
");
$stmt->execute([$company['id'], $startDate, $endDate]);
$newApplications = $stmt->fetchColumn();

$stmt = $db->prepare("
  SELECT COUNT(*) FROM applications a
  JOIN jobs j ON a.job_id = j.id
  WHERE j.company_id = ?
");
$stmt->execute([$company['id']]);
$totalApplications = $stmt->fetchColumn();

$stmt = $db->prepare("
  SELECT a.status, COUNT(*) as count FROM applications a
  JOIN jobs j ON a.job_id = j.id
  WHERE j.company_id = ?
  GROUP BY a.status
");
$stmt->execute([$company['id']]);
$appsByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Interview Statistics
$stmt = $db->prepare("
  SELECT COUNT(*) FROM events 
  WHERE hr_user_id = ? AND event_date BETWEEN ? AND ?
");
$stmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
$scheduledInterviews = $stmt->fetchColumn();

$stmt = $db->prepare("
  SELECT COUNT(*) FROM events 
  WHERE hr_user_id = ? AND status = 'completed'
");
$stmt->execute([$_SESSION['user_id']]);
$completedInterviews = $stmt->fetchColumn();

// Hiring metrics
$stmt = $db->prepare("
  SELECT COUNT(*) FROM applications a
  JOIN jobs j ON a.job_id = j.id
  WHERE j.company_id = ? AND a.status = 'hired'
");
$stmt->execute([$company['id']]);
$totalHires = $stmt->fetchColumn();

$stmt = $db->prepare("
  SELECT COUNT(*) FROM applications a
  JOIN jobs j ON a.job_id = j.id
  WHERE j.company_id = ? AND a.status = 'hired' AND a.applied_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
");
$stmt->execute([$company['id'], $startDate, $endDate]);
$recentHires = $stmt->fetchColumn();

// Top performing jobs
$stmt = $db->prepare("
  SELECT j.id, j.title, j.job_type, j.status, COUNT(a.id) as applications_count,
         SUM(CASE WHEN a.status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
         SUM(CASE WHEN a.status = 'interview' THEN 1 ELSE 0 END) as interviews,
         SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) as hired
  FROM jobs j
  LEFT JOIN applications a ON j.id = a.job_id
  WHERE j.company_id = ?
  GROUP BY j.id
  ORDER BY applications_count DESC
  LIMIT 10
");
$stmt->execute([$company['id']]);
$topJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Application trend data
$stmt = $db->prepare("
  SELECT DATE(a.applied_at) as date, COUNT(*) as count 
  FROM applications a
  JOIN jobs j ON a.job_id = j.id
  WHERE j.company_id = ? AND a.applied_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
  GROUP BY DATE(a.applied_at) 
  ORDER BY date
");
$stmt->execute([$company['id'], $startDate, $endDate]);
$applicationTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate conversion rate
$conversionRate = $totalApplications > 0 ? round(($totalHires / $totalApplications) * 100, 1) : 0;

$pageTitle = 'Reports & Analytics';
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
      <a href="<?php echo BASE_URL; ?>/hr/applications.php" class="nav-item">
        <i class="fas fa-file-alt"></i>
        <span>Applications</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/hr/calendar.php" class="nav-item">
        <i class="fas fa-calendar-alt"></i>
        <span>Calendar</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/hr/reports.php" class="nav-item active">
        <i class="fas fa-chart-line"></i>
        <span>Reports</span>
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
        <h1><i class="fas fa-chart-line"></i> Reports & Analytics</h1>
        <p>Track your recruitment performance</p>
      </div>
      <div class="header-right">
        <form method="GET" class="date-filter-form">
          <select name="range" onchange="this.form.submit()" class="form-control">
            <option value="7days" <?php echo $dateRange === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
            <option value="30days" <?php echo $dateRange === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
            <option value="90days" <?php echo $dateRange === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
            <option value="year" <?php echo $dateRange === 'year' ? 'selected' : ''; ?>>Last Year</option>
          </select>
        </form>
      </div>
    </div>

    <!-- Key Metrics -->
    <div class="stats-grid">
      <div class="stat-card primary">
        <div class="stat-icon">
          <i class="fas fa-briefcase"></i>
        </div>
        <div class="stat-info">
          <span class="stat-value"><?php echo $activeJobs; ?></span>
          <span class="stat-label">Active Jobs</span>
        </div>
        <div class="stat-footer">
          <span class="stat-change positive">
            <i class="fas fa-plus"></i> <?php echo $newJobs; ?> new
          </span>
          <span class="stat-period"><?php echo $rangeLabel; ?></span>
        </div>
      </div>

      <div class="stat-card info">
        <div class="stat-icon">
          <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
          <span class="stat-value"><?php echo $totalApplications; ?></span>
          <span class="stat-label">Total Applications</span>
        </div>
        <div class="stat-footer">
          <span class="stat-change positive">
            <i class="fas fa-plus"></i> <?php echo $newApplications; ?> new
          </span>
          <span class="stat-period"><?php echo $rangeLabel; ?></span>
        </div>
      </div>

      <div class="stat-card purple">
        <div class="stat-icon">
          <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-info">
          <span class="stat-value"><?php echo $scheduledInterviews; ?></span>
          <span class="stat-label">Interviews Scheduled</span>
        </div>
        <div class="stat-footer">
          <span class="stat-label"><?php echo $completedInterviews; ?> completed</span>
        </div>
      </div>

      <div class="stat-card success">
        <div class="stat-icon">
          <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-info">
          <span class="stat-value"><?php echo $totalHires; ?></span>
          <span class="stat-label">Total Hires</span>
        </div>
        <div class="stat-footer">
          <span class="stat-change positive">
            <i class="fas fa-plus"></i> <?php echo $recentHires; ?> new
          </span>
          <span class="stat-period"><?php echo $rangeLabel; ?></span>
        </div>
      </div>
    </div>

    <!-- Performance Overview -->
    <div class="reports-grid">
      <!-- Pipeline Overview -->
      <div class="glass-card report-card">
        <h3><i class="fas fa-filter"></i> Application Pipeline</h3>
        <div class="pipeline-stats">
          <?php
          $pipelineStages = [
            'applied' => ['label' => 'New', 'color' => '#FFC107'],
            'viewed' => ['label' => 'Viewed', 'color' => '#2196F3'],
            'shortlisted' => ['label' => 'Shortlisted', 'color' => '#00E676'],
            'interview' => ['label' => 'Interview', 'color' => '#9C27B0'],
            'offered' => ['label' => 'Offered', 'color' => '#4CAF50'],
            'hired' => ['label' => 'Hired', 'color' => '#00C853'],
            'rejected' => ['label' => 'Rejected', 'color' => '#F44336']
          ];
          foreach ($pipelineStages as $status => $info):
            $count = $appsByStatus[$status] ?? 0;
            $percentage = $totalApplications > 0 ? round(($count / $totalApplications) * 100) : 0;
            ?>
            <div class="pipeline-stat-item">
              <div class="pipeline-stat-header">
                <span class="pipeline-label"><?php echo $info['label']; ?></span>
                <span class="pipeline-count"><?php echo $count; ?></span>
              </div>
              <div class="pipeline-bar">
                <div class="pipeline-fill"
                  style="width: <?php echo $percentage; ?>%; background: <?php echo $info['color']; ?>"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Conversion Rate -->
      <div class="glass-card report-card">
        <h3><i class="fas fa-percentage"></i> Conversion Metrics</h3>
        <div class="conversion-metrics">
          <div class="conversion-circle">
            <svg viewBox="0 0 36 36">
              <path class="circle-bg"
                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
              <path class="circle-fill" stroke-dasharray="<?php echo $conversionRate; ?>, 100"
                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
            </svg>
            <div class="conversion-value"><?php echo $conversionRate; ?>%</div>
          </div>
          <div class="conversion-label">Hire Rate</div>
          <p class="conversion-desc">
            <?php echo $totalHires; ?> hires from <?php echo $totalApplications; ?> applications
          </p>
        </div>
      </div>

      <!-- Job Status Breakdown -->
      <div class="glass-card report-card">
        <h3><i class="fas fa-briefcase"></i> Job Status</h3>
        <div class="status-breakdown">
          <?php
          $jobStatuses = [
            'active' => ['label' => 'Active', 'color' => '#00E676', 'icon' => 'check-circle'],
            'paused' => ['label' => 'Paused', 'color' => '#FFC107', 'icon' => 'pause-circle'],
            'closed' => ['label' => 'Closed', 'color' => '#9E9E9E', 'icon' => 'times-circle'],
            'draft' => ['label' => 'Draft', 'color' => '#2196F3', 'icon' => 'edit']
          ];
          foreach ($jobStatuses as $status => $info):
            $count = $jobsByStatus[$status] ?? 0;
            ?>
            <div class="status-item">
              <i class="fas fa-<?php echo $info['icon']; ?>" style="color: <?php echo $info['color']; ?>"></i>
              <span class="status-label"><?php echo $info['label']; ?></span>
              <span class="status-count"><?php echo $count; ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Top Jobs Table -->
    <div class="glass-card">
      <div class="card-header">
        <h3><i class="fas fa-star"></i> Top Performing Jobs</h3>
        <a href="<?php echo BASE_URL; ?>/hr/jobs.php" class="btn btn-outline btn-sm">View All Jobs</a>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Job Title</th>
              <th>Type</th>
              <th>Status</th>
              <th>Applications</th>
              <th>Shortlisted</th>
              <th>Interviews</th>
              <th>Hired</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($topJobs)): ?>
              <tr>
                <td colspan="7" class="text-center text-muted">No jobs posted yet</td>
              </tr>
            <?php else: ?>
              <?php foreach ($topJobs as $job): ?>
                <tr>
                  <td>
                    <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $job['id']; ?>" class="job-link">
                      <?php echo htmlspecialchars($job['title']); ?>
                    </a>
                  </td>
                  <td><span class="badge badge-outline"><?php echo ucfirst($job['job_type']); ?></span></td>
                  <td>
                    <span class="status-badge <?php echo $job['status']; ?>">
                      <?php echo ucfirst($job['status']); ?>
                    </span>
                  </td>
                  <td class="text-center"><?php echo $job['applications_count']; ?></td>
                  <td class="text-center"><?php echo $job['shortlisted']; ?></td>
                  <td class="text-center"><?php echo $job['interviews']; ?></td>
                  <td class="text-center"><?php echo $job['hired']; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<style>
  .date-filter-form {
    display: flex;
    gap: 0.5rem;
  }

  .date-filter-form .form-control {
    min-width: 150px;
  }

  .reports-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
  }

  .report-card {
    padding: 1.5rem;
  }

  .report-card h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }

  .report-card h3 i {
    color: var(--primary-color);
  }

  /* Pipeline Stats */
  .pipeline-stats {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .pipeline-stat-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .pipeline-stat-header {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
  }

  .pipeline-label {
    color: var(--text-muted);
  }

  .pipeline-count {
    font-weight: 600;
    color: var(--text-primary);
  }

  .pipeline-bar {
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
  }

  .pipeline-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s ease;
  }

  /* Conversion Metrics */
  .conversion-metrics {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
  }

  .conversion-circle {
    width: 150px;
    height: 150px;
    position: relative;
  }

  .conversion-circle svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
  }

  .circle-bg {
    fill: none;
    stroke: rgba(255, 255, 255, 0.1);
    stroke-width: 3.8;
  }

  .circle-fill {
    fill: none;
    stroke: var(--primary-color);
    stroke-width: 3.8;
    stroke-linecap: round;
    transition: stroke-dasharray 0.5s ease;
  }

  .conversion-value {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-color);
  }

  .conversion-label {
    font-size: 1rem;
    font-weight: 600;
    margin-top: 1rem;
    color: var(--text-primary);
  }

  .conversion-desc {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin-top: 0.5rem;
  }

  /* Status Breakdown */
  .status-breakdown {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .status-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 0.5rem;
  }

  .status-item i {
    font-size: 1.25rem;
  }

  .status-label {
    flex: 1;
    color: var(--text-secondary);
  }

  .status-count {
    font-weight: 600;
    font-size: 1.25rem;
    color: var(--text-primary);
  }

  /* Card Header */
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }

  .card-header h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1rem;
    margin: 0;
  }

  .card-header h3 i {
    color: var(--primary-color);
  }

  .job-link {
    color: var(--text-primary);
    text-decoration: none;
  }

  .job-link:hover {
    color: var(--primary-color);
  }

  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
  }

  .status-badge.active {
    background: rgba(0, 230, 118, 0.15);
    color: #00E676;
  }

  .status-badge.paused {
    background: rgba(255, 193, 7, 0.15);
    color: #FFC107;
  }

  .status-badge.closed {
    background: rgba(158, 158, 158, 0.15);
    color: #9E9E9E;
  }

  .status-badge.draft {
    background: rgba(33, 150, 243, 0.15);
    color: #2196F3;
  }

  .badge-outline {
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: transparent;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    color: var(--text-muted);
  }

  .text-center {
    text-align: center;
  }

  .text-muted {
    color: var(--text-muted);
  }

  /* Responsive */
  @media (max-width: 1200px) {
    .reports-grid {
      grid-template-columns: 1fr 1fr;
    }
  }

  @media (max-width: 768px) {
    .reports-grid {
      grid-template-columns: 1fr;
    }

    .conversion-circle {
      width: 120px;
      height: 120px;
    }

    .conversion-value {
      font-size: 1.5rem;
    }
  }
</style>

<?php require_once '../includes/footer.php'; ?>