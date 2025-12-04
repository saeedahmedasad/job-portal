<?php
/**
 * JobNexus - Job Seeker Dashboard
 * Personal job search hub, applications tracking, and profile management
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Job.php';
require_once '../classes/Application.php';
require_once '../classes/SeekerProfile.php';
require_once '../classes/Event.php';

// Check authentication and role
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_SEEKER) {
  header('Location: ' . BASE_URL . '/auth/login.php?redirect=seeker');
  exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$jobModel = new Job();
$applicationModel = new Application();
$profileModel = new SeekerProfile();
$eventModel = new Event();

// Get current user info
$user = $userModel->findById($_SESSION['user_id']);
$profile = $profileModel->findByUserId($_SESSION['user_id']);

// Recalculate and get profile completion from database
$profileCompletion = 0;
if ($profile) {
  $profileCompletion = $profileModel->calculateCompletion($profile['id']);
}

// Dashboard Statistics
// My Applications
$stmt = $db->prepare("SELECT COUNT(*) as total FROM applications WHERE seeker_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalApplications = $stmt->fetch()['total'];

// Pending Applications (newly applied)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM applications WHERE seeker_id = ? AND status = 'applied'");
$stmt->execute([$_SESSION['user_id']]);
$pendingApplications = $stmt->fetch()['total'];

// Shortlisted
$stmt = $db->prepare("SELECT COUNT(*) as total FROM applications WHERE seeker_id = ? AND status = 'shortlisted'");
$stmt->execute([$_SESSION['user_id']]);
$shortlistedCount = $stmt->fetch()['total'];

// Interview Scheduled
$stmt = $db->prepare("SELECT COUNT(*) as total FROM applications WHERE seeker_id = ? AND status = 'interview'");
$stmt->execute([$_SESSION['user_id']]);
$interviewCount = $stmt->fetch()['total'];

// Saved Jobs
$stmt = $db->prepare("SELECT COUNT(*) as total FROM saved_jobs WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$savedJobs = $stmt->fetch()['total'];

// Profile Views - get from seeker_profiles table
$profileViews = $profile['views_count'] ?? 0;

// Upcoming Interviews
$stmt = $db->prepare("
    SELECT e.*, c.company_name, j.title as job_title
    FROM events e
    LEFT JOIN applications a ON e.application_id = a.id
    LEFT JOIN jobs j ON a.job_id = j.id
    LEFT JOIN companies c ON j.company_id = c.id
    WHERE e.seeker_user_id = ? AND e.event_date >= CURDATE() AND e.status = 'scheduled'
    ORDER BY e.event_date ASC, e.event_time ASC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$upcomingInterviews = $stmt->fetchAll();

// Recent Applications
$stmt = $db->prepare("
    SELECT a.*, j.title as job_title, j.location, j.job_type, c.company_name, c.logo
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    LEFT JOIN companies c ON j.company_id = c.id
    WHERE a.seeker_id = ?
    ORDER BY a.applied_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recentApplications = $stmt->fetchAll();

// Recommended Jobs (based on profile skills if available)
$recommendedJobs = [];
if ($profile && !empty($profile['skills'])) {
  $skills = is_array($profile['skills']) ? $profile['skills'] : (json_decode($profile['skills'], true) ?: []);
  if (!empty($skills)) {
    $skillPatterns = array_map(function ($s) {
      return '%' . $s . '%'; }, array_slice($skills, 0, 5));
    $placeholders = str_repeat('j.skills_required LIKE ? OR ', count($skillPatterns) - 1) . 'j.skills_required LIKE ?';

    $sql = "SELECT j.*, c.company_name, c.logo 
                FROM jobs j 
                LEFT JOIN companies c ON j.company_id = c.id 
                WHERE j.status = 'active' AND (j.application_deadline IS NULL OR j.application_deadline >= CURDATE()) 
                AND ($placeholders)
                AND j.id NOT IN (SELECT job_id FROM applications WHERE seeker_id = ?)
                ORDER BY j.created_at DESC 
                LIMIT 6";

    $stmt = $db->prepare($sql);
    $params = array_merge($skillPatterns, [$_SESSION['user_id']]);
    $stmt->execute($params);
    $recommendedJobs = $stmt->fetchAll();
  }
}

// If no recommendations, get latest jobs
if (empty($recommendedJobs)) {
  $stmt = $db->prepare("
        SELECT j.*, c.company_name, c.logo 
        FROM jobs j 
        LEFT JOIN companies c ON j.company_id = c.id 
        WHERE j.status = 'active' AND (j.application_deadline IS NULL OR j.application_deadline >= CURDATE())
        AND j.id NOT IN (SELECT job_id FROM applications WHERE seeker_id = ?)
        ORDER BY j.created_at DESC 
        LIMIT 6
    ");
  $stmt->execute([$_SESSION['user_id']]);
  $recommendedJobs = $stmt->fetchAll();
}

// Saved Jobs List
$stmt = $db->prepare("
    SELECT j.*, c.company_name, c.logo
    FROM saved_jobs sj
    JOIN jobs j ON sj.job_id = j.id
    LEFT JOIN companies c ON j.company_id = c.id
    WHERE sj.user_id = ?
    ORDER BY sj.saved_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$savedJobsList = $stmt->fetchAll();

// Application Stats
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM applications 
    WHERE seeker_id = ?
    GROUP BY status
");
$stmt->execute([$_SESSION['user_id']]);
$appStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'My Dashboard';
require_once '../includes/header.php';
?>

<div class="dashboard-container">
  <!-- Sidebar -->
  <aside class="dashboard-sidebar">
    <div class="sidebar-header">
      <div class="seeker-avatar">
        <?php echo strtoupper(substr($profile['first_name'] ?? 'U', 0, 1)); ?>
      </div>
      <h3><?php echo htmlspecialchars(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')); ?></h3>
      <span class="role-badge seeker">Job Seeker</span>
      <?php if ($profile && $profile['headline']): ?>
        <p class="user-headline"><?php echo htmlspecialchars($profile['headline']); ?></p>
      <?php endif; ?>
    </div>

    <!-- Profile Completion -->
    <div class="profile-completion">
      <div class="completion-header">
        <span>Profile Strength</span>
        <span class="completion-percent"><?php echo $profileCompletion; ?>%</span>
      </div>
      <div class="completion-bar">
        <div class="completion-fill" style="width: <?php echo $profileCompletion; ?>%"></div>
      </div>
      <?php if ($profileCompletion < 100): ?>
        <a href="<?php echo BASE_URL; ?>/seeker/profile.php" class="completion-link">
          Complete your profile <i class="fas fa-arrow-right"></i>
        </a>
      <?php endif; ?>
    </div>

    <nav class="sidebar-nav">
      <a href="<?php echo BASE_URL; ?>/seeker/index.php" class="nav-item active">
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
        <h1>Welcome back, <?php echo htmlspecialchars($profile['first_name'] ?? 'there'); ?>!</h1>
        <p>Here's what's happening with your job search</p>
      </div>
      <div class="header-right">
        <a href="<?php echo BASE_URL; ?>/jobs" class="btn btn-primary">
          <i class="fas fa-search"></i> Find Jobs
        </a>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card primary">
        <div class="stat-icon">
          <i class="fas fa-paper-plane"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($totalApplications); ?></h3>
          <p>Applications Sent</p>
        </div>
        <div class="stat-footer">
          <a href="<?php echo BASE_URL; ?>/seeker/applications.php" class="stat-link">
            View all <i class="fas fa-arrow-right"></i>
          </a>
        </div>
      </div>

      <div class="stat-card warning">
        <div class="stat-icon">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($pendingApplications); ?></h3>
          <p>Under Review</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">Awaiting response</span>
        </div>
      </div>

      <div class="stat-card success">
        <div class="stat-icon">
          <i class="fas fa-star"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($shortlistedCount); ?></h3>
          <p>Shortlisted</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">Great progress!</span>
        </div>
      </div>

      <div class="stat-card purple">
        <div class="stat-icon">
          <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($interviewCount); ?></h3>
          <p>Interviews</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">Scheduled</span>
        </div>
      </div>

      <div class="stat-card info">
        <div class="stat-icon">
          <i class="fas fa-bookmark"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($savedJobs); ?></h3>
          <p>Saved Jobs</p>
        </div>
        <div class="stat-footer">
          <a href="<?php echo BASE_URL; ?>/seeker/saved-jobs.php" class="stat-link">
            View saved <i class="fas fa-arrow-right"></i>
          </a>
        </div>
      </div>

      <div class="stat-card secondary">
        <div class="stat-icon">
          <i class="fas fa-eye"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($profileViews); ?></h3>
          <p>Profile Views</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">This month</span>
        </div>
      </div>
    </div>

    <!-- Upcoming Interviews Alert -->
    <?php if (!empty($upcomingInterviews)): ?>
      <div class="interview-alert">
        <div class="alert-icon">
          <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="alert-content">
          <h3>Upcoming Interviews</h3>
          <p>You have <?php echo count($upcomingInterviews); ?> interview(s) scheduled</p>
        </div>
        <div class="alert-interviews">
          <?php foreach (array_slice($upcomingInterviews, 0, 2) as $interview): ?>
            <div class="mini-interview">
              <div class="interview-date-mini">
                <span class="day"><?php echo date('d', strtotime($interview['event_date'])); ?></span>
                <span class="month"><?php echo date('M', strtotime($interview['event_date'])); ?></span>
              </div>
              <div class="interview-info-mini">
                <strong><?php echo htmlspecialchars($interview['company_name']); ?></strong>
                <span><?php echo htmlspecialchars($interview['job_title'] ?? 'Interview'); ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <a href="<?php echo BASE_URL; ?>/seeker/calendar.php" class="btn btn-primary btn-sm">
          View Calendar
        </a>
      </div>
    <?php endif; ?>

    <!-- Application Status Pipeline -->
    <div class="dashboard-section">
      <div class="section-header">
        <h2><i class="fas fa-chart-line"></i> Application Journey</h2>
      </div>

      <div class="application-journey">
        <?php
        $journey = [
          'pending' => ['label' => 'Applied', 'icon' => 'paper-plane'],
          'reviewed' => ['label' => 'Reviewed', 'icon' => 'eye'],
          'shortlisted' => ['label' => 'Shortlisted', 'icon' => 'star'],
          'interview' => ['label' => 'Interview', 'icon' => 'calendar'],
          'offered' => ['label' => 'Offered', 'icon' => 'gift'],
          'hired' => ['label' => 'Hired', 'icon' => 'trophy']
        ];
        $total = array_sum($appStats) ?: 1;
        ?>

        <div class="journey-track">
          <?php foreach ($journey as $status => $info): ?>
            <?php $count = $appStats[$status] ?? 0; ?>
            <div class="journey-step <?php echo $count > 0 ? 'has-items' : ''; ?>">
              <div class="step-icon">
                <i class="fas fa-<?php echo $info['icon']; ?>"></i>
              </div>
              <div class="step-count"><?php echo $count; ?></div>
              <div class="step-label"><?php echo $info['label']; ?></div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php
        $rejectedCount = $appStats['rejected'] ?? 0;
        if ($rejectedCount > 0):
          ?>
          <div class="rejected-note">
            <i class="fas fa-info-circle"></i>
            <?php echo $rejectedCount; ?> application(s) were not successful. Don't give up!
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Main Panels Grid -->
    <div class="dashboard-panels seeker-panels">
      <!-- Recent Applications -->
      <div class="dashboard-panel">
        <div class="panel-header">
          <h2><i class="fas fa-file-alt"></i> Recent Applications</h2>
          <a href="<?php echo BASE_URL; ?>/seeker/applications.php" class="btn btn-text">
            View All <i class="fas fa-arrow-right"></i>
          </a>
        </div>
        <div class="panel-content">
          <?php if (empty($recentApplications)): ?>
            <div class="empty-state">
              <i class="fas fa-paper-plane"></i>
              <h4>No applications yet</h4>
              <p>Start your job search journey today!</p>
              <a href="<?php echo BASE_URL; ?>/jobs" class="btn btn-primary btn-sm">Browse Jobs</a>
            </div>
          <?php else: ?>
            <div class="applications-timeline">
              <?php foreach ($recentApplications as $app): ?>
                <div class="timeline-item">
                  <div class="timeline-logo">
                    <?php if ($app['logo']): ?>
                      <img src="<?php echo BASE_URL; ?>/uploads/logos/<?php echo $app['logo']; ?>" alt="">
                    <?php else: ?>
                      <i class="fas fa-building"></i>
                    <?php endif; ?>
                  </div>
                  <div class="timeline-content">
                    <h4><?php echo htmlspecialchars($app['job_title']); ?></h4>
                    <p class="company"><?php echo htmlspecialchars($app['company_name']); ?></p>
                    <div class="timeline-meta">
                      <span class="location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($app['location']); ?>
                      </span>
                      <span class="date">
                        Applied <?php echo date('M j', strtotime($app['applied_at'])); ?>
                      </span>
                    </div>
                  </div>
                  <div class="timeline-status">
                    <span class="status-badge <?php echo $app['status']; ?>">
                      <?php echo ucfirst($app['status']); ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Saved Jobs -->
      <div class="dashboard-panel">
        <div class="panel-header">
          <h2><i class="fas fa-bookmark"></i> Saved Jobs</h2>
          <a href="<?php echo BASE_URL; ?>/seeker/saved-jobs.php" class="btn btn-text">
            View All <i class="fas fa-arrow-right"></i>
          </a>
        </div>
        <div class="panel-content">
          <?php if (empty($savedJobsList)): ?>
            <div class="empty-state">
              <i class="fas fa-bookmark"></i>
              <h4>No saved jobs</h4>
              <p>Save jobs you're interested in for later</p>
              <a href="<?php echo BASE_URL; ?>/jobs" class="btn btn-primary btn-sm">Find Jobs</a>
            </div>
          <?php else: ?>
            <div class="saved-jobs-list">
              <?php foreach ($savedJobsList as $job): ?>
                <div class="saved-job-item">
                  <div class="saved-logo">
                    <?php if ($job['logo']): ?>
                      <img src="<?php echo BASE_URL; ?>/uploads/logos/<?php echo $job['logo']; ?>" alt="">
                    <?php else: ?>
                      <i class="fas fa-building"></i>
                    <?php endif; ?>
                  </div>
                  <div class="saved-info">
                    <h4>
                      <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $job['id']; ?>">
                        <?php echo htmlspecialchars($job['title']); ?>
                      </a>
                    </h4>
                    <p><?php echo htmlspecialchars($job['company_name']); ?></p>
                  </div>
                  <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $job['id']; ?>"
                    class="btn btn-outline-primary btn-sm">
                    Apply
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Recommended Jobs -->
    <div class="dashboard-section">
      <div class="section-header">
        <h2><i class="fas fa-magic"></i> Recommended for You</h2>
        <a href="<?php echo BASE_URL; ?>/jobs" class="btn btn-outline-primary btn-sm">
          Browse All Jobs
        </a>
      </div>

      <?php if (empty($recommendedJobs)): ?>
        <div class="empty-state">
          <i class="fas fa-search"></i>
          <h4>No recommendations yet</h4>
          <p>Complete your profile to get personalized job recommendations</p>
          <a href="<?php echo BASE_URL; ?>/seeker/profile.php" class="btn btn-primary">Complete Profile</a>
        </div>
      <?php else: ?>
        <div class="recommended-jobs-grid">
          <?php foreach ($recommendedJobs as $job): ?>
            <div class="job-card-mini">
              <div class="job-card-header">
                <div class="company-logo-mini">
                  <?php if ($job['logo']): ?>
                    <img src="<?php echo BASE_URL; ?>/uploads/logos/<?php echo $job['logo']; ?>" alt="">
                  <?php else: ?>
                    <i class="fas fa-building"></i>
                  <?php endif; ?>
                </div>
                <div class="job-card-title">
                  <h4>
                    <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $job['id']; ?>">
                      <?php echo htmlspecialchars($job['title']); ?>
                    </a>
                  </h4>
                  <p><?php echo htmlspecialchars($job['company_name']); ?></p>
                </div>
              </div>
              <div class="job-card-meta">
                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                <span class="job-type-badge <?php echo $job['job_type']; ?>">
                  <?php echo ucfirst(str_replace('-', ' ', $job['job_type'])); ?>
                </span>
              </div>
              <?php if ($job['salary_min'] || $job['salary_max']): ?>
                <div class="job-salary">
                  <i class="fas fa-dollar-sign"></i>
                  <?php
                  if ($job['salary_min'] && $job['salary_max']) {
                    echo number_format($job['salary_min']) . ' - ' . number_format($job['salary_max']);
                  } elseif ($job['salary_min']) {
                    echo 'From ' . number_format($job['salary_min']);
                  } else {
                    echo 'Up to ' . number_format($job['salary_max']);
                  }
                  ?>
                </div>
              <?php endif; ?>
              <div class="job-card-actions">
                <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $job['id']; ?>"
                  class="btn btn-primary btn-sm btn-block">
                  View & Apply
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="dashboard-section">
      <div class="section-header">
        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
      </div>

      <div class="quick-actions-grid">
        <a href="<?php echo BASE_URL; ?>/jobs" class="quick-action-card">
          <div class="action-icon primary">
            <i class="fas fa-search"></i>
          </div>
          <span>Search Jobs</span>
        </a>

        <a href="<?php echo BASE_URL; ?>/seeker/profile.php" class="quick-action-card">
          <div class="action-icon info">
            <i class="fas fa-user-edit"></i>
          </div>
          <span>Edit Profile</span>
        </a>

        <a href="<?php echo BASE_URL; ?>/seeker/resume.php" class="quick-action-card">
          <div class="action-icon purple">
            <i class="fas fa-file-alt"></i>
          </div>
          <span>Build Resume</span>
        </a>

        <a href="<?php echo BASE_URL; ?>/seeker/applications.php" class="quick-action-card">
          <div class="action-icon warning">
            <i class="fas fa-list-alt"></i>
          </div>
          <span>Track Applications</span>
        </a>

        <a href="<?php echo BASE_URL; ?>/seeker/calendar.php" class="quick-action-card">
          <div class="action-icon success">
            <i class="fas fa-calendar"></i>
          </div>
          <span>View Calendar</span>
        </a>

        <a href="<?php echo BASE_URL; ?>/seeker/settings.php" class="quick-action-card">
          <div class="action-icon secondary">
            <i class="fas fa-cog"></i>
          </div>
          <span>Settings</span>
        </a>
      </div>
    </div>
  </main>
</div>

<style>
  /* Dashboard Container Layout */
  .dashboard-container {
    display: flex;
    min-height: 100vh;
    padding-top: 70px;
    background: var(--bg-dark);
  }

  /* Sidebar */
  .dashboard-sidebar {
    position: fixed;
    left: 0;
    top: 70px;
    bottom: 0;
    width: 280px;
    background: linear-gradient(180deg, #121212 0%, #0a0a0a 50%, #121212 100%);
    border-right: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    z-index: 100;
    backdrop-filter: blur(20px);
  }

  .sidebar-header {
    text-align: center;
    padding: 2rem 1.5rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    background: rgba(0, 0, 0, 0.3);
  }

  /* Seeker Dashboard Specific Styles */
  .seeker-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary-color), #00b386);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 2rem;
    font-weight: 700;
    color: #000;
    box-shadow: 0 8px 30px rgba(0, 230, 118, 0.35);
  }

  .sidebar-header h3 {
    margin: 0 0 0.5rem;
    font-size: 1.1rem;
    color: var(--text-primary);
    font-weight: 600;
  }

  .role-badge.seeker {
    display: inline-block;
    padding: 0.3rem 0.85rem;
    background: rgba(0, 230, 118, 0.12);
    color: var(--primary-color);
    border-radius: 2rem;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid rgba(0, 230, 118, 0.2);
  }

  .user-headline {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
    margin-top: 0.75rem;
    text-align: center;
    line-height: 1.4;
  }

  /* Profile Completion */
  .profile-completion {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    background: rgba(0, 230, 118, 0.03);
  }

  .completion-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
  }

  .completion-percent {
    color: var(--primary-color);
    font-weight: 700;
    font-size: 0.9rem;
  }

  .completion-bar {
    height: 6px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 0.75rem;
  }

  .completion-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary-color), #00e5ff);
    border-radius: 3px;
    transition: width 0.5s ease;
    box-shadow: 0 0 10px rgba(0, 230, 118, 0.4);
  }

  .completion-link {
    font-size: 0.75rem;
    color: var(--primary-color);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
  }

  .completion-link:hover {
    color: #00e5ff;
    gap: 0.75rem;
  }

  /* Sidebar Navigation */
  .sidebar-nav {
    display: flex;
    flex-direction: column;
    padding: 1rem 1rem;
    flex: 1;
  }

  .sidebar-nav .nav-item {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.9rem 1.25rem;
    color: rgba(255, 255, 255, 0.6);
    text-decoration: none;
    border-radius: 0.75rem;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
    position: relative;
  }

  .sidebar-nav .nav-item:hover {
    color: var(--text-primary);
    background: rgba(255, 255, 255, 0.05);
    transform: translateX(4px);
  }

  .sidebar-nav .nav-item.active {
    color: var(--primary-color);
    background: rgba(0, 230, 118, 0.1);
    font-weight: 500;
  }

  .sidebar-nav .nav-item i {
    width: 22px;
    text-align: center;
    font-size: 1rem;
    transition: transform 0.3s ease;
  }

  .sidebar-nav .nav-item:hover i {
    transform: scale(1.1);
  }

  .sidebar-nav .nav-item .badge {
    margin-left: auto;
    padding: 0.2rem 0.6rem;
    background: rgba(0, 230, 118, 0.2);
    color: var(--primary-color);
    border-radius: 1rem;
    font-size: 0.7rem;
    font-weight: 600;
    min-width: 20px;
    text-align: center;
  }

  .sidebar-nav .nav-item .badge.info {
    background: rgba(100, 181, 246, 0.2);
    color: #64b5f6;
  }

  .sidebar-nav .nav-item .badge.success {
    background: rgba(76, 175, 80, 0.2);
    color: #4caf50;
  }

  .sidebar-footer {
    padding: 1rem 1.5rem 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    margin-top: auto;
  }

  .logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
    color: rgba(255, 255, 255, 0.5);
    text-decoration: none;
    border-radius: 0.75rem;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    background: rgba(244, 67, 54, 0.05);
    border: 1px solid rgba(244, 67, 54, 0.1);
  }

  .logout-btn:hover {
    color: #f44336;
    background: rgba(244, 67, 54, 0.1);
    border-color: rgba(244, 67, 54, 0.3);
  }

  /* Main Content */
  .dashboard-main {
    flex: 1;
    margin-left: 280px;
    padding: 2rem;
    min-height: calc(100vh - 70px);
  }

  /* Dashboard Header */
  .dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  }

  .header-left h1 {
    font-family: 'Staatliches', sans-serif;
    font-size: 2.25rem;
    color: var(--text-primary);
    margin: 0 0 0.5rem;
    letter-spacing: 1px;
  }

  .header-left p {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
    font-size: 0.95rem;
  }

  .header-right {
    display: flex;
    gap: 1rem;
  }

  /* Stats Grid */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
  }

  .stat-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 1rem;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 1rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
  }

  .stat-card:hover {
    transform: translateY(-4px);
    border-color: rgba(0, 230, 118, 0.3);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
  }

  .stat-card .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
  }

  .stat-card .stat-content {
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .stat-card.primary .stat-icon {
    background: rgba(0, 230, 118, 0.15);
    color: var(--primary-color);
  }

  .stat-card.warning .stat-icon {
    background: rgba(255, 193, 7, 0.15);
    color: #ffc107;
  }

  .stat-card.success .stat-icon {
    background: rgba(76, 175, 80, 0.15);
    color: #4caf50;
  }

  .stat-card.purple .stat-icon {
    background: rgba(156, 39, 176, 0.15);
    color: #9c27b0;
  }

  .stat-card.info .stat-icon {
    background: rgba(100, 181, 246, 0.15);
    color: #64b5f6;
  }

  .stat-card.secondary .stat-icon {
    background: rgba(158, 158, 158, 0.15);
    color: #9e9e9e;
  }

  .stat-card .stat-content h3 {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0 0 0.25rem;
    color: var(--text-primary);
  }

  .stat-card .stat-content p {
    color: rgba(255, 255, 255, 0.6);
    margin: 0;
    font-size: 0.85rem;
  }

  .stat-card .stat-footer {
    margin-top: 0.5rem;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    width: 100%;
  }

  .stat-card .stat-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
  }

  .stat-link {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
  }

  .stat-link:hover {
    color: #00e5ff;
    gap: 0.75rem;
  }

  /* Dashboard Sections */
  .dashboard-section {
    margin-bottom: 2rem;
  }

  .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
  }

  .section-header h2 {
    font-size: 1.25rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
  }

  .section-header h2 i {
    color: var(--primary-color);
    font-size: 1rem;
  }

  /* Dashboard Panels */
  .dashboard-panels {
    display: grid;
    gap: 1.5rem;
  }

  .dashboard-panel {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 1rem;
    overflow: hidden;
  }

  .panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    background: rgba(255, 255, 255, 0.02);
  }

  .panel-header h2 {
    font-size: 1rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
  }

  .panel-header h2 i {
    color: var(--primary-color);
    font-size: 0.9rem;
  }

  .panel-content {
    padding: 0;
  }

  /* Interview Alert */
  .interview-alert {
    background: linear-gradient(135deg, rgba(0, 230, 118, 0.08), rgba(156, 39, 176, 0.05));
    border: 1px solid rgba(0, 230, 118, 0.2);
    border-radius: 1rem;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
  }

  .interview-alert .alert-icon {
    width: 60px;
    height: 60px;
    background: rgba(0, 230, 118, 0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.5rem;
  }

  .interview-alert .alert-content {
    flex: 1;
  }

  .interview-alert .alert-content h3 {
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
    color: var(--text-primary);
  }

  .interview-alert .alert-content p {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    margin: 0;
  }

  .alert-interviews {
    display: flex;
    gap: 1rem;
  }

  .mini-interview {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    padding: 0.75rem 1rem;
    border-radius: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.05);
  }

  .interview-date-mini {
    text-align: center;
  }

  .interview-date-mini .day {
    display: block;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--primary-color);
    line-height: 1;
  }

  .interview-date-mini .month {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
  }

  .interview-info-mini strong {
    display: block;
    font-size: 0.85rem;
    color: var(--text-primary);
  }

  .interview-info-mini span {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
  }

  /* Application Journey */
  .application-journey {
    padding: 1.5rem;
  }

  .journey-track {
    display: flex;
    justify-content: space-between;
    position: relative;
    padding: 0 1rem;
  }

  .journey-track::before {
    content: '';
    position: absolute;
    top: 30px;
    left: 60px;
    right: 60px;
    height: 3px;
    background: rgba(255, 255, 255, 0.1);
    z-index: 0;
  }

  .journey-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 1;
  }

  .journey-step .step-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.03);
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.4);
    font-size: 1.25rem;
    margin-bottom: 0.75rem;
    transition: all 0.3s ease;
  }

  .journey-step.has-items .step-icon {
    background: rgba(0, 230, 118, 0.1);
    border-color: var(--primary-color);
    color: var(--primary-color);
    box-shadow: 0 0 20px rgba(0, 230, 118, 0.2);
  }

  .journey-step .step-count {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
  }

  .journey-step .step-label {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .rejected-note {
    text-align: center;
    margin-top: 1.5rem;
    padding: 0.75rem;
    background: rgba(244, 67, 54, 0.08);
    border-radius: 0.5rem;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    border: 1px solid rgba(244, 67, 54, 0.15);
  }

  .rejected-note i {
    color: #f44336;
    margin-right: 0.5rem;
  }

  /* Seeker Panels */
  .seeker-panels {
    grid-template-columns: 1fr 1fr;
  }

  /* Applications Timeline */
  .applications-timeline {
    padding: 0;
  }

  .timeline-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s ease;
  }

  .timeline-item:hover {
    background: rgba(0, 230, 118, 0.03);
  }

  .timeline-item:last-child {
    border-bottom: none;
  }

  .timeline-logo {
    width: 45px;
    height: 45px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.08);
  }

  .timeline-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }

  .timeline-logo i {
    color: rgba(255, 255, 255, 0.4);
  }

  .timeline-content {
    flex: 1;
  }

  .timeline-content h4 {
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
    color: var(--text-primary);
  }

  .timeline-content .company {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    margin-bottom: 0.25rem;
  }

  .timeline-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
  }

  .timeline-meta i {
    margin-right: 0.25rem;
    color: var(--primary-color);
  }

  .timeline-status {
    text-align: right;
  }

  /* Status Badge */
  .status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: capitalize;
  }

  .status-badge.pending {
    background: rgba(255, 193, 7, 0.15);
    color: #ffc107;
  }

  .status-badge.reviewed {
    background: rgba(100, 181, 246, 0.15);
    color: #64b5f6;
  }

  .status-badge.shortlisted {
    background: rgba(0, 230, 118, 0.15);
    color: var(--primary-color);
  }

  .status-badge.interview {
    background: rgba(156, 39, 176, 0.15);
    color: #9c27b0;
  }

  .status-badge.rejected {
    background: rgba(244, 67, 54, 0.15);
    color: #f44336;
  }

  /* Saved Jobs List */
  .saved-jobs-list {
    padding: 0;
  }

  .saved-job-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s ease;
  }

  .saved-job-item:hover {
    background: rgba(0, 230, 118, 0.03);
  }

  .saved-job-item:last-child {
    border-bottom: none;
  }

  .saved-logo {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.08);
  }

  .saved-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }

  .saved-logo i {
    color: rgba(255, 255, 255, 0.4);
  }

  .saved-info {
    flex: 1;
  }

  .saved-info h4 {
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
  }

  .saved-info h4 a {
    color: var(--text-primary);
    text-decoration: none;
    transition: color 0.3s ease;
  }

  .saved-info h4 a:hover {
    color: var(--primary-color);
  }

  .saved-info p {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
  }

  /* Recommended Jobs Grid */
  .recommended-jobs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.25rem;
    padding: 1.5rem;
  }

  .job-card-mini {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 0.75rem;
    padding: 1.25rem;
    transition: all 0.3s ease;
  }

  .job-card-mini:hover {
    border-color: rgba(0, 230, 118, 0.3);
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  }

  .job-card-header {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
  }

  .company-logo-mini {
    width: 45px;
    height: 45px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.08);
  }

  .company-logo-mini img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }

  .company-logo-mini i {
    color: rgba(255, 255, 255, 0.4);
  }

  .job-card-title h4 {
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
  }

  .job-card-title h4 a {
    color: var(--text-primary);
    text-decoration: none;
    transition: color 0.3s ease;
  }

  .job-card-title h4 a:hover {
    color: var(--primary-color);
  }

  .job-card-title p {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
  }

  .job-card-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
  }

  .job-card-meta i {
    margin-right: 0.25rem;
    color: var(--primary-color);
  }

  .job-salary {
    color: var(--primary-color);
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 1rem;
  }

  .job-salary i {
    margin-right: 0.25rem;
  }

  .job-card-actions .btn-block {
    width: 100%;
  }

  /* Quick Actions Grid */
  .quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 1rem;
  }

  .quick-action-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 0.75rem;
    padding: 1.5rem 1rem;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s ease;
  }

  .quick-action-card:hover {
    border-color: rgba(255, 255, 255, 0.1);
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
  }

  .quick-action-card .action-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.75rem;
    font-size: 1.25rem;
    transition: all 0.3s ease;
  }

  .quick-action-card span {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
    transition: color 0.3s ease;
  }

  .quick-action-card:hover span {
    color: var(--text-primary);
  }

  /* Quick Action Colors */
  .quick-action-card .action-icon.primary {
    background: rgba(0, 230, 118, 0.1);
    color: var(--primary-color);
  }

  .quick-action-card .action-icon.info {
    background: rgba(100, 181, 246, 0.1);
    color: #64b5f6;
  }

  .quick-action-card .action-icon.purple {
    background: rgba(156, 39, 176, 0.1);
    color: #9c27b0;
  }

  .quick-action-card .action-icon.warning {
    background: rgba(255, 193, 7, 0.1);
    color: #ffc107;
  }

  .quick-action-card .action-icon.success {
    background: rgba(76, 175, 80, 0.1);
    color: #4caf50;
  }

  .quick-action-card .action-icon.secondary {
    background: rgba(158, 158, 158, 0.1);
    color: #9e9e9e;
  }

  .quick-action-card:hover .action-icon.primary {
    background: var(--primary-color);
    color: #000;
    box-shadow: 0 8px 20px rgba(0, 230, 118, 0.3);
  }

  .quick-action-card:hover .action-icon.info {
    background: #64b5f6;
    color: #000;
    box-shadow: 0 8px 20px rgba(100, 181, 246, 0.3);
  }

  .quick-action-card:hover .action-icon.purple {
    background: #9c27b0;
    color: white;
    box-shadow: 0 8px 20px rgba(156, 39, 176, 0.3);
  }

  .quick-action-card:hover .action-icon.warning {
    background: #ffc107;
    color: #000;
    box-shadow: 0 8px 20px rgba(255, 193, 7, 0.3);
  }

  .quick-action-card:hover .action-icon.success {
    background: #4caf50;
    color: white;
    box-shadow: 0 8px 20px rgba(76, 175, 80, 0.3);
  }

  .quick-action-card:hover .action-icon.secondary {
    background: #9e9e9e;
    color: white;
    box-shadow: 0 8px 20px rgba(158, 158, 158, 0.3);
  }

  /* Empty State */
  .empty-state {
    padding: 3rem;
    text-align: center;
  }

  .empty-state i {
    font-size: 3rem;
    color: rgba(255, 255, 255, 0.2);
    margin-bottom: 1rem;
  }

  .empty-state h4 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
  }

  .empty-state p {
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 1rem;
  }

  /* Responsive */
  @media (max-width: 1200px) {
    .seeker-panels {
      grid-template-columns: 1fr;
    }

    .interview-alert {
      flex-wrap: wrap;
    }

    .alert-interviews {
      width: 100%;
      margin-top: 1rem;
    }

    .stats-grid {
      grid-template-columns: repeat(3, 1fr);
    }
  }

  @media (max-width: 1024px) {
    .dashboard-sidebar {
      width: 240px;
    }

    .dashboard-main {
      margin-left: 240px;
    }
  }

  @media (max-width: 768px) {
    .dashboard-sidebar {
      position: fixed;
      left: -100%;
      width: 280px;
      transition: left 0.3s ease;
    }

    .dashboard-sidebar.open {
      left: 0;
    }

    .dashboard-main {
      margin-left: 0;
      padding: 1.5rem;
    }

    .dashboard-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }

    .header-left h1 {
      font-size: 1.75rem;
    }

    .header-right {
      width: 100%;
    }

    .header-right .btn {
      width: 100%;
      justify-content: center;
    }

    .stats-grid {
      grid-template-columns: repeat(2, 1fr);
    }

    .journey-track {
      flex-wrap: wrap;
      justify-content: center;
      gap: 1.5rem;
    }

    .journey-track::before {
      display: none;
    }

    .journey-step .step-icon {
      width: 50px;
      height: 50px;
      font-size: 1rem;
    }

    .interview-alert {
      flex-direction: column;
      text-align: center;
    }

    .alert-interviews {
      flex-direction: column;
    }

    .timeline-item {
      flex-direction: column;
      align-items: flex-start;
    }

    .timeline-status {
      width: 100%;
      text-align: left;
      margin-top: 0.5rem;
    }

    .recommended-jobs-grid {
      grid-template-columns: 1fr;
    }

    .quick-actions-grid {
      grid-template-columns: repeat(3, 1fr);
    }
  }

  @media (max-width: 480px) {
    .stats-grid {
      grid-template-columns: 1fr;
    }

    .quick-actions-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }
</style>

<?php require_once '../includes/footer.php'; ?>