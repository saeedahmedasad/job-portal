<?php
/**
 * JobNexus - HR/Recruiter Dashboard
 * Job management, applicant tracking, and recruitment tools
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Job.php';
require_once '../classes/Company.php';
require_once '../classes/Application.php';
require_once '../classes/Event.php';

// Check authentication and role
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_HR) {
  header('Location: ' . BASE_URL . '/auth/login.php?redirect=hr');
  exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$jobModel = new Job();
$companyModel = new Company();
$applicationModel = new Application();
$eventModel = new Event();

// Get current HR info and company
$hr = $userModel->findById($_SESSION['user_id']);
$company = $companyModel->findByHRUserId($_SESSION['user_id']);

// Check if company is verified
if (!$company || $company['verification_status'] !== 'verified') {
  $pageTitle = 'Account Pending Verification';
  require_once '../includes/header.php';
  ?>
  <div class="verification-pending-container">
    <div class="pending-card">
      <div class="pending-icon">
        <i class="fas fa-clock"></i>
      </div>
      <h1>Account Pending Verification</h1>
      <p>Your HR account is currently under review by our administrators. You will receive access to the dashboard once
        your account has been verified.</p>
      <div class="pending-info">
        <div class="info-item">
          <i class="fas fa-user"></i>
          <span><?php echo htmlspecialchars($hr['email']); ?></span>
        </div>
        <div class="info-item">
          <i class="fas fa-envelope"></i>
          <span><?php echo htmlspecialchars($hr['email']); ?></span>
        </div>
        <div class="info-item">
          <i class="fas fa-calendar"></i>
          <span>Registered: <?php echo date('F j, Y', strtotime($hr['created_at'])); ?></span>
        </div>
      </div>
      <p class="pending-note">This process typically takes 1-2 business days. If you have any questions, please contact
        our support team.</p>
      <a href="<?php echo BASE_URL; ?>" class="btn btn-primary">
        <i class="fas fa-home"></i> Return to Home
      </a>
    </div>
  </div>

  <style>
    .verification-pending-container {
      min-height: calc(100vh - 70px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      margin-top: 70px;
    }

    .pending-card {
      background: var(--card-bg);
      border-radius: 20px;
      padding: 3rem;
      text-align: center;
      max-width: 500px;
      border: 1px solid var(--border-color);
    }

    .pending-icon {
      width: 100px;
      height: 100px;
      background: rgba(255, 193, 7, 0.1);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 2rem;
      font-size: 3rem;
      color: var(--warning);
    }

    .pending-card h1 {
      font-family: var(--font-heading);
      font-size: 1.75rem;
      margin-bottom: 1rem;
    }

    .pending-card>p {
      color: var(--text-muted);
      margin-bottom: 2rem;
    }

    .pending-info {
      background: var(--bg-dark);
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .pending-info .info-item {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 0.75rem 0;
      border-bottom: 1px solid var(--border-color);
    }

    .pending-info .info-item:last-child {
      border-bottom: none;
    }

    .pending-info .info-item i {
      color: var(--primary-color);
      width: 20px;
    }

    .pending-note {
      font-size: 0.9rem;
      color: var(--text-muted);
      margin-bottom: 2rem;
    }
  </style>

  <?php
  require_once '../includes/footer.php';
  exit;
}

// Company was already fetched above

// Dashboard Statistics
// My Jobs
$stmt = $db->prepare("SELECT COUNT(*) as total FROM jobs WHERE posted_by = ?");
$stmt->execute([$_SESSION['user_id']]);
$myJobs = $stmt->fetch()['total'];

// Active Jobs
$stmt = $db->prepare("SELECT COUNT(*) as total FROM jobs WHERE posted_by = ? AND status = 'active' AND (application_deadline IS NULL OR application_deadline >= CURDATE())");
$stmt->execute([$_SESSION['user_id']]);
$activeJobs = $stmt->fetch()['total'];

// Total Applications Received
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE j.posted_by = ?
");
$stmt->execute([$_SESSION['user_id']]);
$totalApplications = $stmt->fetch()['total'];

// Pending Applications
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE j.posted_by = ? AND a.status = 'pending'
");
$stmt->execute([$_SESSION['user_id']]);
$pendingApplications = $stmt->fetch()['total'];

// Shortlisted Candidates
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE j.posted_by = ? AND a.status = 'shortlisted'
");
$stmt->execute([$_SESSION['user_id']]);
$shortlistedCandidates = $stmt->fetch()['total'];

// Scheduled Interviews
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM events 
    WHERE hr_user_id = ? AND event_date >= CURDATE() AND status = 'scheduled'
");
$stmt->execute([$_SESSION['user_id']]);
$scheduledInterviews = $stmt->fetch()['total'];

// Hired This Month
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE j.posted_by = ? AND a.status = 'hired' 
    AND MONTH(a.updated_at) = MONTH(CURDATE()) 
    AND YEAR(a.updated_at) = YEAR(CURDATE())
");
$stmt->execute([$_SESSION['user_id']]);
$hiredThisMonth = $stmt->fetch()['total'];

// New Applications Today
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE j.posted_by = ? AND DATE(a.applied_at) = CURDATE()
");
$stmt->execute([$_SESSION['user_id']]);
$newAppsToday = $stmt->fetch()['total'];

// Recent Applications
$stmt = $db->prepare("
    SELECT a.*, j.title as job_title, CONCAT(sp.first_name, ' ', sp.last_name) as applicant_name, u.email as applicant_email,
           sp.headline as applicant_headline
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    JOIN users u ON a.seeker_id = u.id
    LEFT JOIN seeker_profiles sp ON u.id = sp.user_id
    WHERE j.posted_by = ?
    ORDER BY a.applied_at DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$recentApplications = $stmt->fetchAll();

// My Recent Jobs
$stmt = $db->prepare("
    SELECT j.*, 
           (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
    FROM jobs j 
    WHERE j.posted_by = ?
    ORDER BY j.created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$myRecentJobs = $stmt->fetchAll();

// Upcoming Interviews
$stmt = $db->prepare("
    SELECT e.*, CONCAT(sp.first_name, ' ', sp.last_name) as seeker_name, j.title as job_title
    FROM events e
    LEFT JOIN seeker_profiles sp ON e.seeker_user_id = sp.user_id
    LEFT JOIN applications a ON e.application_id = a.id
    LEFT JOIN jobs j ON a.job_id = j.id
    WHERE e.hr_user_id = ? AND e.event_date >= CURDATE() AND e.status = 'scheduled'
    ORDER BY e.event_date ASC, e.event_time ASC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$upcomingInterviews = $stmt->fetchAll();

// Application Stats by Status
$stmt = $db->prepare("
    SELECT a.status, COUNT(*) as count 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE j.posted_by = ?
    GROUP BY a.status
");
$stmt->execute([$_SESSION['user_id']]);
$appStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Handle Actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'update_status') {
    $applicationId = (int) $_POST['application_id'];
    $newStatus = $_POST['status'];

    // Verify this application belongs to HR's job
    $stmt = $db->prepare("
            SELECT a.* FROM applications a 
            JOIN jobs j ON a.job_id = j.id 
            WHERE a.id = ? AND j.posted_by = ?
        ");
    $stmt->execute([$applicationId, $_SESSION['user_id']]);

    if ($stmt->fetch()) {
      $applicationModel->updateStatus($applicationId, $newStatus);
      $message = 'Application status updated successfully!';
      $messageType = 'success';
    }
  }
}

$pageTitle = 'HR Dashboard';
require_once '../includes/header.php';
?>

<div class="dashboard-container">
  <!-- Sidebar -->
  <aside class="dashboard-sidebar">
    <div class="sidebar-header">
      <div class="hr-avatar">
        <i class="fas fa-user-tie"></i>
      </div>
      <h3><?php echo htmlspecialchars($hr['email']); ?></h3>
      <span class="role-badge hr">HR / Recruiter</span>
      <?php if ($company): ?>
        <p class="company-name">
          <i class="fas fa-building"></i>
          <?php echo htmlspecialchars($company['company_name']); ?>
        </p>
      <?php endif; ?>
    </div>

    <nav class="sidebar-nav">
      <a href="<?php echo BASE_URL; ?>/hr/index.php" class="nav-item active">
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
        <h1>HR Dashboard</h1>
        <p>Manage your recruitment pipeline</p>
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
          <h3><?php echo number_format($myJobs); ?></h3>
          <p>Total Jobs</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label"><?php echo $activeJobs; ?> active</span>
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
          <?php if ($newAppsToday > 0): ?>
            <span class="stat-change positive">
              <i class="fas fa-arrow-up"></i> <?php echo $newAppsToday; ?> today
            </span>
          <?php else: ?>
            <span class="stat-label">No new today</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="stat-card warning">
        <div class="stat-icon">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($pendingApplications); ?></h3>
          <p>Pending Review</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">Awaiting action</span>
        </div>
      </div>

      <div class="stat-card purple">
        <div class="stat-icon">
          <i class="fas fa-star"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($shortlistedCandidates); ?></h3>
          <p>Shortlisted</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">Top candidates</span>
        </div>
      </div>

      <div class="stat-card secondary">
        <div class="stat-icon">
          <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($scheduledInterviews); ?></h3>
          <p>Interviews</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">Upcoming</span>
        </div>
      </div>

      <div class="stat-card success">
        <div class="stat-icon">
          <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($hiredThisMonth); ?></h3>
          <p>Hired</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">This month</span>
        </div>
      </div>
    </div>

    <!-- Recruitment Pipeline -->
    <div class="dashboard-section pipeline-section">
      <div class="section-header">
        <h2><i class="fas fa-filter"></i> Recruitment Pipeline</h2>
        <a href="<?php echo BASE_URL; ?>/hr/applications.php" class="btn btn-outline-primary btn-sm">
          View All Applications
        </a>
      </div>

      <div class="pipeline-stages">
        <?php
        $stages = [
          'applied' => ['label' => 'New', 'icon' => 'inbox', 'color' => 'warning'],
          'viewed' => ['label' => 'Viewed', 'icon' => 'eye', 'color' => 'info'],
          'shortlisted' => ['label' => 'Shortlisted', 'icon' => 'star', 'color' => 'primary'],
          'interview' => ['label' => 'Interview', 'icon' => 'calendar', 'color' => 'purple'],
          'offered' => ['label' => 'Offered', 'icon' => 'gift', 'color' => 'success'],
          'hired' => ['label' => 'Hired', 'icon' => 'check-circle', 'color' => 'success'],
          'rejected' => ['label' => 'Rejected', 'icon' => 'times-circle', 'color' => 'danger']
        ];
        ?>

        <?php foreach ($stages as $status => $info): ?>
          <?php $count = $appStats[$status] ?? 0; ?>
          <div class="pipeline-stage <?php echo $info['color']; ?>">
            <div class="stage-icon">
              <i class="fas fa-<?php echo $info['icon']; ?>"></i>
            </div>
            <div class="stage-count"><?php echo $count; ?></div>
            <div class="stage-label"><?php echo $info['label']; ?></div>
          </div>
          <?php if ($status !== 'rejected'): ?>
            <div class="pipeline-arrow">
              <i class="fas fa-chevron-right"></i>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Main Panels -->
    <div class="dashboard-panels">
      <!-- Recent Applications -->
      <div class="dashboard-panel applications-panel">
        <div class="panel-header">
          <h2><i class="fas fa-file-alt"></i> Recent Applications</h2>
          <a href="<?php echo BASE_URL; ?>/hr/applications.php" class="btn btn-text">
            View All <i class="fas fa-arrow-right"></i>
          </a>
        </div>
        <div class="panel-content">
          <?php if (empty($recentApplications)): ?>
            <div class="empty-state">
              <i class="fas fa-inbox"></i>
              <p>No applications yet</p>
              <a href="<?php echo BASE_URL; ?>/hr/post-job.php" class="btn btn-primary btn-sm">Post a Job</a>
            </div>
          <?php else: ?>
            <div class="applications-list">
              <?php foreach ($recentApplications as $app): ?>
                <div class="application-item">
                  <div class="applicant-info">
                    <div class="applicant-avatar">
                      <?php echo strtoupper(substr($app['applicant_name'], 0, 1)); ?>
                    </div>
                    <div class="applicant-details">
                      <h4><?php echo htmlspecialchars($app['applicant_name']); ?></h4>
                      <p class="applicant-headline">
                        <?php echo htmlspecialchars($app['applicant_headline'] ?? $app['applicant_email']); ?></p>
                      <p class="job-applied">Applied for:
                        <strong><?php echo htmlspecialchars($app['job_title']); ?></strong></p>
                    </div>
                  </div>
                  <div class="application-meta">
                    <span class="status-badge <?php echo $app['status']; ?>">
                      <?php echo ucfirst($app['status']); ?>
                    </span>
                    <span class="applied-date">
                      <?php echo date('M j', strtotime($app['applied_at'])); ?>
                    </span>
                  </div>
                  <div class="application-actions">
                    <a href="<?php echo BASE_URL; ?>/hr/application.php?id=<?php echo $app['id']; ?>"
                      class="btn btn-outline-primary btn-sm">
                      View
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- My Jobs + Upcoming Interviews -->
      <div class="side-panels">
        <!-- My Jobs -->
        <div class="dashboard-panel">
          <div class="panel-header">
            <h2><i class="fas fa-briefcase"></i> My Jobs</h2>
            <a href="<?php echo BASE_URL; ?>/hr/jobs.php" class="btn btn-text">
              All Jobs <i class="fas fa-arrow-right"></i>
            </a>
          </div>
          <div class="panel-content">
            <?php if (empty($myRecentJobs)): ?>
              <div class="empty-state">
                <i class="fas fa-briefcase"></i>
                <p>No jobs posted yet</p>
                <a href="<?php echo BASE_URL; ?>/hr/post-job.php" class="btn btn-primary btn-sm">Post Your First Job</a>
              </div>
            <?php else: ?>
              <div class="jobs-mini-list">
                <?php foreach ($myRecentJobs as $job): ?>
                  <div class="job-mini-item">
                    <div class="job-mini-info">
                      <h4><?php echo htmlspecialchars($job['title']); ?></h4>
                      <div class="job-mini-meta">
                        <span class="job-type-badge <?php echo $job['job_type']; ?>">
                          <?php echo ucfirst(str_replace('-', ' ', $job['job_type'])); ?>
                        </span>
                        <span class="status-badge <?php echo $job['status']; ?>">
                          <?php echo ucfirst($job['status']); ?>
                        </span>
                      </div>
                    </div>
                    <div class="job-mini-stats">
                      <span class="applicant-count">
                        <i class="fas fa-users"></i> <?php echo $job['application_count']; ?>
                      </span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Upcoming Interviews -->
        <div class="dashboard-panel">
          <div class="panel-header">
            <h2><i class="fas fa-calendar-alt"></i> Upcoming Interviews</h2>
            <a href="<?php echo BASE_URL; ?>/hr/calendar.php" class="btn btn-text">
              Calendar <i class="fas fa-arrow-right"></i>
            </a>
          </div>
          <div class="panel-content">
            <?php if (empty($upcomingInterviews)): ?>
              <div class="empty-state">
                <i class="fas fa-calendar-check"></i>
                <p>No upcoming interviews</p>
              </div>
            <?php else: ?>
              <div class="interviews-list">
                <?php foreach ($upcomingInterviews as $interview): ?>
                  <div class="interview-item">
                    <div class="interview-date">
                      <span class="day"><?php echo date('d', strtotime($interview['event_date'])); ?></span>
                      <span class="month"><?php echo date('M', strtotime($interview['event_date'])); ?></span>
                    </div>
                    <div class="interview-info">
                      <h4><?php echo htmlspecialchars($interview['seeker_name']); ?></h4>
                      <p><?php echo htmlspecialchars($interview['job_title'] ?? 'Interview'); ?></p>
                      <span class="interview-time">
                        <i class="fas fa-clock"></i>
                        <?php echo date('g:i A', strtotime($interview['event_time'])); ?>
                      </span>
                    </div>
                    <div class="interview-type <?php echo $interview['event_type']; ?>">
                      <?php if ($interview['event_type'] === 'video'): ?>
                        <i class="fas fa-video"></i>
                      <?php elseif ($interview['event_type'] === 'phone'): ?>
                        <i class="fas fa-phone"></i>
                      <?php else: ?>
                        <i class="fas fa-building"></i>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="dashboard-section">
      <div class="section-header">
        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
      </div>

      <div class="quick-actions-grid">
        <a href="<?php echo BASE_URL; ?>/hr/post-job.php" class="quick-action-card">
          <div class="action-icon primary">
            <i class="fas fa-plus-circle"></i>
          </div>
          <span>Post New Job</span>
        </a>

        <a href="<?php echo BASE_URL; ?>/hr/applications.php?status=pending" class="quick-action-card">
          <div class="action-icon warning">
            <i class="fas fa-inbox"></i>
          </div>
          <span>Review Applications</span>
        </a>

        <a href="<?php echo BASE_URL; ?>/hr/candidates.php" class="quick-action-card">
          <div class="action-icon info">
            <i class="fas fa-search"></i>
          </div>
          <span>Search Candidates</span>
        </a>

        <a href="<?php echo BASE_URL; ?>/hr/calendar.php?action=schedule" class="quick-action-card">
          <div class="action-icon purple">
            <i class="fas fa-calendar-plus"></i>
          </div>
          <span>Schedule Interview</span>
        </a>

        <a href="<?php echo BASE_URL; ?>/hr/company.php" class="quick-action-card">
          <div class="action-icon secondary">
            <i class="fas fa-building"></i>
          </div>
          <span>Edit Company</span>
        </a>

        <a href="<?php echo BASE_URL; ?>/hr/reports.php" class="quick-action-card">
          <div class="action-icon success">
            <i class="fas fa-chart-line"></i>
          </div>
          <span>View Reports</span>
        </a>
      </div>
    </div>
  </main>
</div>

<style>
  /* HR Dashboard - Page Specific Overrides Only */
  /* Most styles are now in /assets/css/dashboard.css */
  
  /* Responsive overrides for this page */
  @media (max-width: 1200px) {
    .dashboard-panels {
      grid-template-columns: 1fr;
    }

    .side-panels {
      flex-direction: row;
    }

    .side-panels .dashboard-panel {
      flex: 1;
    }
  }

  @media (max-width: 768px) {
    .side-panels {
      flex-direction: column;
    }
  }
</style>

<?php require_once '../includes/footer.php'; ?>
