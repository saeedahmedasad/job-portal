<?php
/**
 * JobNexus - Saved Jobs
 * View and manage saved/bookmarked jobs
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Job.php';
require_once '../classes/SeekerProfile.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_SEEKER) {
  header('Location: ../auth/login.php');
  exit;
}

$db = Database::getInstance()->getConnection();
$job = new Job();
$profileModel = new SeekerProfile();

$userId = $_SESSION['user_id'];
$profile = $profileModel->findByUserId($userId);
$message = '';
$messageType = '';

// Handle unsave action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if ($_POST['action'] === 'unsave' && isset($_POST['job_id'])) {
    $jobId = intval($_POST['job_id']);

    $stmt = $db->prepare("DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?");
    if ($stmt->execute([$userId, $jobId])) {
      $message = 'Job removed from saved list.';
      $messageType = 'info';
    } else {
      $message = 'Error removing job.';
      $messageType = 'error';
    }
  }
}

// Handle AJAX save/unsave
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
  header('Content-Type: application/json');

  $jobId = intval($_POST['job_id']);
  $action = $_POST['ajax_action'];

  if ($action === 'toggle') {
    // Check if already saved
    $stmt = $db->prepare("SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ?");
    $stmt->execute([$userId, $jobId]);

    if ($stmt->fetch()) {
      // Unsave
      $stmt = $db->prepare("DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?");
      $stmt->execute([$userId, $jobId]);
      echo json_encode(['success' => true, 'saved' => false, 'message' => 'Job removed from saved list']);
    } else {
      // Save
      $stmt = $db->prepare("INSERT INTO saved_jobs (user_id, job_id, saved_at) VALUES (?, ?, NOW())");
      $stmt->execute([$userId, $jobId]);
      echo json_encode(['success' => true, 'saved' => true, 'message' => 'Job saved successfully']);
    }
  }
  exit;
}

// Get saved jobs with details
$stmt = $db->prepare("
    SELECT j.*, c.company_name, c.logo as company_logo, c.headquarters as company_location,
           sj.saved_at, cat.name as category_name,
           (SELECT COUNT(*) FROM applications WHERE job_id = j.id AND seeker_id = ?) as applied
    FROM saved_jobs sj
    JOIN jobs j ON sj.job_id = j.id
    JOIN companies c ON j.company_id = c.id
    LEFT JOIN job_categories cat ON j.category_id = cat.id
    WHERE sj.user_id = ?
    ORDER BY sj.saved_at DESC
");
$stmt->execute([$userId, $userId]);
$savedJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get job categories for filtering
$categories = [];
foreach ($savedJobs as $sj) {
  if (!empty($sj['category_name']) && !in_array($sj['category_name'], $categories)) {
    $categories[] = $sj['category_name'];
  }
}

// Get stats
$totalSaved = count($savedJobs);
$activeJobs = count(array_filter($savedJobs, fn($j) => $j['status'] === 'active'));
$appliedJobs = count(array_filter($savedJobs, fn($j) => $j['applied'] > 0));
$expiredJobs = count(array_filter($savedJobs, fn($j) => $j['status'] !== 'active'));

$pageTitle = "Saved Jobs - JobNexus";
include '../includes/header.php';
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
      <a href="<?php echo BASE_URL; ?>/seeker/saved-jobs.php" class="nav-item active">
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
        <h1><i class="fas fa-bookmark"></i> Saved Jobs</h1>
        <p>Jobs you've bookmarked for later review</p>
      </div>
      <div class="header-right">
        <a href="<?php echo BASE_URL; ?>/jobs" class="btn btn-primary">
          <i class="fas fa-search"></i> Find More Jobs
        </a>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?php echo $messageType; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card primary">
        <div class="stat-icon">
          <i class="fas fa-bookmark"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $totalSaved; ?></h3>
          <p>Total Saved</p>
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
          <h3><?php echo $activeJobs; ?></h3>
          <p>Active Jobs</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">Still accepting</span>
        </div>
      </div>
      <div class="stat-card info">
        <div class="stat-icon">
          <i class="fas fa-paper-plane"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $appliedJobs; ?></h3>
          <p>Applied</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">From saved</span>
        </div>
      </div>
      <div class="stat-card warning">
        <div class="stat-icon">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $expiredJobs; ?></h3>
          <p>Expired</p>
          </div>
          <div class="stat-footer">
            <span class="stat-label">No longer open</span>
        </div>
      </div>
    </div>

    <?php if (empty($savedJobs)): ?>
      <!-- Empty State -->
      <div class="dashboard-panel">
        <div class="empty-state">
          <div class="empty-icon-wrapper">
            <i class="fas fa-bookmark"></i>
          </div>
          <h3>No Saved Jobs Yet</h3>
          <p>Start exploring and save jobs that interest you. They'll appear here for easy access.</p>
          <a href="<?php echo BASE_URL; ?>/jobs" class="btn btn-primary">
            <i class="fas fa-search"></i> Browse Jobs
          </a>
          </div>
      </div>
    <?php else: ?>
      <!-- Filter Section -->
      <div class="dashboard-section">
        <div class="glass-card filter-card">
          <div class="filter-row">
            <div class="filter-group">
              <label><i class="fas fa-folder"></i> Category</label>
              <select id="filterCategory" onchange="filterJobs()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
              </select>
              </div>
              <div class="filter-group">
              <label><i class="fas fa-toggle-on"></i> Status</label>
              <select id="filterStatus" onchange="filterJobs()">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="applied">Applied</option>
                <option value="expired">Expired</option>
              </select>
            </div>
            <div class="filter-group">
              <label><i class="fas fa-sort"></i> Sort By</label>
              <select id="sortBy" onchange="filterJobs()">
                <option value="saved_desc">Recently Saved</option>
                <option value="saved_asc">Oldest Saved</option>
                <option value="deadline">Deadline Soon</option>
                <option value="salary">Highest Salary</option>
              </select>
            </div>
            <button class="btn btn-ghost btn-sm" onclick="clearFilters()">
              <i class="fas fa-times"></i> Clear
            </button>
          </div>
        </div>
      </div>

      <!-- Saved Jobs Grid -->
      <div class="saved-jobs-grid" id="savedJobsGrid">
        <?php foreach ($savedJobs as $sj): ?>
          <?php
          $isActive = $sj['status'] === 'active';
          $isApplied = $sj['applied'] > 0;
          $deadline = $sj['application_deadline'] ? new DateTime($sj['application_deadline']) : null;
          $daysLeft = $deadline ? (new DateTime())->diff($deadline)->days : null;
          $isExpiringSoon = $deadline && $daysLeft <= 3 && $isActive;
          ?>
                              <div class="glass-card saved-job-card <?php echo !$isActive ? 'expired' : ''; ?>"
            data-category="<?php echo htmlspecialchars($sj['category_name'] ?? ''); ?>"
            data-status="<?php echo $isActive ? ($isApplied ? 'applied' : 'active') : 'expired'; ?>"
            data-saved="<?php echo strtotime($sj['saved_at']); ?>"
            data-deadline="<?php echo $sj['application_deadline'] ? strtotime($sj['application_deadline']) : ''; ?>"
            data-salary="<?php echo $sj['salary_max'] ?? 0; ?>">

            <!-- Card Header -->
            <div class="card-header-actions">
              <div class="job-badges">
                <?php if (!$isActive): ?>
                                                <span class="status-badge expired"><i class="fas fa-ban"></i> Closed</span>
                                          <?php elseif ($isExpiringSoon): ?>
                                          <span class="status-badge urgent"><i class="fas fa-fire"></i> Expiring Soon</span>
                                          <?php elseif ($isApplied): ?>
                                          <span class="status-badge applied"><i class="fas fa-check"></i> Applied</span>
                                          <?php else: ?>
                                          <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                          <?php endif; ?>
                                  </div>
                                  <button class="save-btn saved" onclick="toggleSave(<?php echo $sj['id']; ?>, this)" title="Remove from saved">
                                <i class="fas fa-heart"></i>
                              </button>
            </div>

            <!-- Company Info -->
            <div class="job-company-row">
              <div class="company-logo-wrapper">
                <?php if ($sj['company_logo']): ?>
                                                <img src="<?php echo BASE_URL; ?>/uploads/logos/<?php echo htmlspecialchars($sj['company_logo']); ?>"
                    alt="<?php echo htmlspecialchars($sj['company_name']); ?>">
                <?php else: ?>
                                                <span class="logo-placeholder"><?php echo strtoupper(substr($sj['company_name'], 0, 2)); ?></span>
                <?php endif; ?>
              </div>
              <div class="company-details">
                <h4 class="company-name"><?php echo htmlspecialchars($sj['company_name']); ?></h4>
                <span class="company-location">
                  <i class="fas fa-map-marker-alt"></i>
                  <?php echo htmlspecialchars($sj['location']); ?>
                </span>
              </div>
            </div>

            <!-- Job Title -->
            <h3 class="job-title">
              <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $sj['id']; ?>">
                <?php echo htmlspecialchars($sj['title']); ?>
              </a>
            </h3>

            <!-- Job Meta Tags -->
            <div class="job-tags">
              <span class="tag type-<?php echo $sj['job_type']; ?>">
                <i class="fas fa-briefcase"></i>
                <?php echo ucfirst(str_replace('-', ' ', $sj['job_type'])); ?>
              </span>
              <span class="tag">
                <i class="fas fa-tag"></i>
                <?php echo htmlspecialchars($sj['category_name'] ?? 'General'); ?>
              </span>
              <?php if ($sj['experience_level']): ?>
                <span class="tag">
                  <i class="fas fa-layer-group"></i>
                  <?php echo ucfirst($sj['experience_level']); ?>
                </span>
              <?php endif; ?>
            </div>

            <!-- Salary -->
            <?php if ($sj['salary_min'] || $sj['salary_max']): ?>
                                            <div class="job-salary">
                                              <i class="fas fa-money-bill-wave"></i>
                                              <span class="salary-amount">
                                            <?php
                                            if ($sj['salary_min'] && $sj['salary_max']) {
                                        echo '$' . number_format($sj['salary_min']) . ' - $' . number_format($sj['salary_max']);
                                      } elseif ($sj['salary_min']) {
                                        echo 'From $' . number_format($sj['salary_min']);
                                      } else {
                                        echo 'Up to $' . number_format($sj['salary_max']);
                                      }
                                      ?>
                </span>
                <span class="salary-period">/year</span>
              </div>
            <?php endif; ?>

            <!-- Deadline Progress -->
            <?php if ($deadline && $isActive): ?>
              <div class="deadline-indicator <?php echo $isExpiringSoon ? 'urgent' : ''; ?>">
                <div class="deadline-info">
                  <i class="fas fa-hourglass-half"></i>
                  <span><?php echo $daysLeft; ?> days left to apply</span>
                </div>
                <div class="deadline-progress">
                  <div class="progress-fill" style="width: <?php echo max(0, min(100, 100 - ($daysLeft / 30 * 100))); ?>%"></div>
                </div>
              </div>
            <?php endif; ?>
            
            <!-- Card Footer -->
            <div class="card-footer">
              <div class="saved-date">
                <i class="fas fa-bookmark"></i>
                Saved <?php echo date('M d, Y', strtotime($sj['saved_at'])); ?>
              </div>
              <div class="card-actions">
                <?php if ($isActive && !$isApplied): ?>
                                                <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $sj['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-paper-plane"></i> Apply Now
                                          </a>
                                          <?php elseif ($isApplied): ?>
                                                <a href="<?php echo BASE_URL; ?>/seeker/applications.php" class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i> View Status
                                          </a>
                                          <?php else: ?>
                                                <span class="text-muted"><i class="fas fa-ban"></i> No longer accepting</span>
                                          <?php endif; ?>
                                          </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- No Results -->
      <div class="dashboard-panel no-results" id="noResults" style="display: none;">
        <div class="empty-state">
          <div class="empty-icon-wrapper">
            <i class="fas fa-filter"></i>
          </div>
          <h3>No Jobs Match Your Filters</h3>
          <p>Try adjusting your filters to see more results</p>
          <button class="btn btn-primary" onclick="clearFilters()">
            <i class="fas fa-times"></i> Clear Filters
          </button>
        </div>
      </div>
    <?php endif; ?>
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
    padding: 2rem 1.5rem;
    overflow-y: auto;
    z-index: 100;
    backdrop-filter: blur(20px);
  }

  .sidebar-header {
    text-align: center;
    padding-bottom: 1.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    background: transparent;
  }

  .seeker-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), #00b386);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.5rem;
    font-weight: 700;
    color: #000;
    box-shadow: 0 8px 25px rgba(0, 230, 118, 0.3);
  }

  .sidebar-header h3 {
    margin: 0 0 0.5rem;
    font-size: 1.1rem;
    color: var(--text-primary);
  }

  .role-badge.seeker {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: rgba(0, 230, 118, 0.15);
    color: var(--primary-color);
    border-radius: 1rem;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .user-headline {
    margin-top: 0.75rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    line-height: 1.4;
  }

  .sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }

  .sidebar-nav .nav-item {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem 1rem;
    color: rgba(255, 255, 255, 0.6);
    text-decoration: none;
    border-radius: 0.75rem;
    transition: all 0.3s ease;
    font-size: 0.9rem;
  }

  .sidebar-nav .nav-item:hover {
    color: var(--text-primary);
    background: rgba(255, 255, 255, 0.05);
  }

  .sidebar-nav .nav-item.active {
    color: var(--primary-color);
    background: rgba(0, 230, 118, 0.1);
    font-weight: 500;
  }

  .sidebar-nav .nav-item i {
    width: 20px;
    text-align: center;
    font-size: 1rem;
  }

  .sidebar-nav .nav-item .badge {
    margin-left: auto;
    padding: 0.2rem 0.5rem;
    background: rgba(0, 230, 118, 0.2);
    color: var(--primary-color);
    border-radius: 0.5rem;
    font-size: 0.7rem;
    font-weight: 600;
  }

  .sidebar-nav .nav-item .badge.info {
    background: rgba(100, 181, 246, 0.2);
    color: #64b5f6;
  }

  .sidebar-footer {
    margin-top: auto;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
  }

  .logout-btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    color: rgba(255, 255, 255, 0.5);
    text-decoration: none;
    border-radius: 0.75rem;
    transition: all 0.3s ease;
    font-size: 0.9rem;
  }

  .logout-btn:hover {
    color: #f44336;
    background: rgba(244, 67, 54, 0.1);
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
    display: flex;
    align-items: center;
    gap: 0.75rem;
    letter-spacing: 1px;
  }

  .header-left h1 i {
    color: var(--primary-color);
    font-size: 1.75rem;
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
  }

  .stat-card:hover {
    transform: translateY(-4px);
    border-color: rgba(0, 230, 118, 0.3);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
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

  .stat-card.success .stat-icon {
    background: rgba(76, 175, 80, 0.15);
    color: #4caf50;
  }

  .stat-card.info .stat-icon {
    background: rgba(100, 181, 246, 0.15);
    color: #64b5f6;
  }

  .stat-card.warning .stat-icon {
    background: rgba(255, 193, 7, 0.15);
    color: #ffc107;
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

  /* Glass Card */
  .glass-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 1rem;
    backdrop-filter: blur(10px);
  }

  /* Dashboard Panel */
  .dashboard-panel {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 1rem;
    padding: 2rem;
  }

  /* Dashboard Section */
  .dashboard-section {
    margin-bottom: 1.5rem;
  }

  /* Filter Card */
  .filter-card {
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
  }

  .filter-row {
    display: flex;
    align-items: flex-end;
    gap: 1.5rem;
    flex-wrap: wrap;
  }

  .filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex: 1;
    min-width: 180px;
  }

  .filter-group label {
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .filter-group label i {
    color: var(--primary-color);
  }

  .filter-group select {
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    color: var(--text-primary);
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .filter-group select:hover {
    border-color: rgba(255, 255, 255, 0.2);
  }

  .filter-group select:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 230, 118, 0.1);
  }

  /* Saved Jobs Grid */
  .saved-jobs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.5rem;
  }

  /* Job Card Styles */
  .saved-job-card {
    position: relative;
    padding: 1.5rem;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .saved-job-card:hover {
    transform: translateY(-4px);
    border-color: rgba(0, 230, 118, 0.3);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
  }

  .saved-job-card.expired {
    opacity: 0.7;
  }

  .saved-job-card.expired:hover {
    opacity: 0.85;
  }

  /* Card Header */
  .card-header-actions {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
  }

  .job-badges {
    display: flex;
    gap: 0.5rem;
  }

  .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .status-badge.active {
    background: rgba(0, 230, 118, 0.15);
    color: var(--primary-color);
  }

  .status-badge.applied {
    background: rgba(100, 181, 246, 0.15);
    color: #64b5f6;
  }

  .status-badge.expired {
    background: rgba(244, 67, 54, 0.15);
    color: #f44336;
  }

  .status-badge.urgent {
    background: rgba(255, 152, 0, 0.15);
    color: #ff9800;
    animation: pulse 2s infinite;
  }

  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
  }

  /* Save Button */
  .save-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
  }

  .save-btn.saved {
    background: rgba(255, 82, 82, 0.15);
    color: #ff5252;
  }

  .save-btn:hover {
    transform: scale(1.1);
    background: rgba(255, 82, 82, 0.25);
  }

  /* Company Row */
  .job-company-row {
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  .company-logo-wrapper {
    width: 52px;
    height: 52px;
    border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.1);
  }

  .company-logo-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .logo-placeholder {
    font-size: 1rem;
    font-weight: 700;
    color: var(--primary-color);
  }

  .company-details {
    flex: 1;
  }

  .company-name {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
  }

  .company-location {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    display: flex;
    align-items: center;
    gap: 0.375rem;
  }

  .company-location i {
    font-size: 0.7rem;
    color: var(--primary-color);
  }

  /* Job Title */
  .job-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    line-height: 1.3;
  }

  .job-title a {
    color: var(--text-primary);
    text-decoration: none;
    transition: color 0.3s ease;
  }

  .job-title a:hover {
    color: var(--primary-color);
  }

  /* Job Tags */
  .job-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .tag {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 2rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.08);
  }

  .tag i {
    font-size: 0.65rem;
    opacity: 0.7;
  }

  .tag.type-full-time { color: var(--primary-color); border-color: rgba(0, 230, 118, 0.2); }
  .tag.type-part-time { color: #64b5f6; border-color: rgba(100, 181, 246, 0.2); }
  .tag.type-contract { color: #ffc107; border-color: rgba(255, 193, 7, 0.2); }
  .tag.type-remote { color: #ba68c8; border-color: rgba(186, 104, 200, 0.2); }
  .tag.type-internship { color: #4dd0e1; border-color: rgba(77, 208, 225, 0.2); }

  /* Salary */
  .job-salary {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: rgba(0, 230, 118, 0.08);
    border-radius: 0.5rem;
    border: 1px solid rgba(0, 230, 118, 0.15);
  }

  .job-salary i {
    color: var(--primary-color);
    font-size: 0.9rem;
  }

  .salary-amount {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--primary-color);
  }

  .salary-period {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
  }

  /* Deadline Indicator */
  .deadline-indicator {
    padding: 0.75rem;
    background: rgba(255, 193, 7, 0.08);
    border-radius: 0.5rem;
    border: 1px solid rgba(255, 193, 7, 0.15);
  }

  .deadline-indicator.urgent {
    background: rgba(255, 152, 0, 0.1);
    border-color: rgba(255, 152, 0, 0.2);
  }

  .deadline-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: #ffc107;
    margin-bottom: 0.5rem;
  }

  .deadline-indicator.urgent .deadline-info {
    color: #ff9800;
  }

  .deadline-progress {
    height: 4px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
    overflow: hidden;
  }

  .progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #ffc107, #ff9800);
    border-radius: 2px;
    transition: width 0.3s ease;
  }

  .deadline-indicator.urgent .progress-fill {
    background: linear-gradient(90deg, #ff9800, #f44336);
  }

  /* Card Footer */
  .card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    margin-top: auto;
  }

  .saved-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
  }

  .saved-date i {
    color: var(--primary-color);
  }

  .card-actions {
    display: flex;
    gap: 0.5rem;
  }

  .text-muted {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
    font-style: italic;
  }

  /* Empty State */
  .empty-icon-wrapper {
    width: 100px;
    height: 100px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, rgba(0, 230, 118, 0.1), rgba(0, 230, 118, 0.05));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(0, 230, 118, 0.2);
  }

  .empty-icon-wrapper i {
    font-size: 2.5rem;
    color: var(--primary-color);
  }

  .empty-state h3 {
    margin: 0 0 0.5rem;
    font-size: 1.25rem;
    color: var(--text-primary);
  }

  .empty-state p {
    color: rgba(255, 255, 255, 0.6);
    margin-bottom: 1.5rem;
    max-width: 300px;
    margin-left: auto;
    margin-right: auto;
  }

  /* Responsive */
  @media (max-width: 1200px) {
    .stats-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media (max-width: 1024px) {
    .dashboard-sidebar {
      width: 240px;
    }

    .dashboard-main {
      margin-left: 240px;
    }

    .saved-jobs-grid {
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
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
      gap: 1rem;
    }

    .stat-card {
      padding: 1rem;
    }

    .stat-card .stat-content h3 {
      font-size: 1.5rem;
    }

    .filter-row {
      flex-direction: column;
      gap: 1rem;
    }

    .filter-group {
      width: 100%;
    }

    .saved-jobs-grid {
      grid-template-columns: 1fr;
    }

    .card-footer {
      flex-direction: column;
      gap: 1rem;
      align-items: stretch;
      text-align: center;
    }

    .card-actions {
      justify-content: center;
    }
  }

  @media (max-width: 480px) {
    .stats-grid {
      grid-template-columns: 1fr;
    }

    .header-left h1 {
      font-size: 1.5rem;
    }

    .header-left h1 i {
      font-size: 1.25rem;
    }
  }
</style>

<script>
  function toggleSave(jobId, btn) {
    const formData = new FormData();
    formData.append('ajax_action', 'toggle');
    formData.append('job_id', jobId);

    // Add loading state
    btn.classList.add('loading');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('<?php echo BASE_URL; ?>/seeker/saved-jobs.php', {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          if (!data.saved) {
            // Remove the card with animation
            const card = btn.closest('.saved-job-card');
            card.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.8) translateY(-20px)';
            setTimeout(() => {
              card.remove();
              updateStats();
              checkEmpty();
            }, 400);
          }
          showToast(data.message, 'success');
        }
      })
      .catch(error => {
        btn.classList.remove('loading');
        btn.innerHTML = '<i class="fas fa-heart"></i>';
        showToast('Error updating saved job', 'error');
      });
  }

  function filterJobs() {
    const category = document.getElementById('filterCategory').value;
    const status = document.getElementById('filterStatus').value;
    const sortBy = document.getElementById('sortBy').value;

    const grid = document.getElementById('savedJobsGrid');
    const cards = Array.from(document.querySelectorAll('.saved-job-card'));
    let visibleCount = 0;

    // Sort cards
    cards.sort((a, b) => {
      switch (sortBy) {
        case 'saved_desc':
          return parseInt(b.dataset.saved) - parseInt(a.dataset.saved);
        case 'saved_asc':
          return parseInt(a.dataset.saved) - parseInt(b.dataset.saved);
        case 'deadline':
          const deadlineA = parseInt(a.dataset.deadline) || Infinity;
          const deadlineB = parseInt(b.dataset.deadline) || Infinity;
          return deadlineA - deadlineB;
        case 'salary':
          return parseInt(b.dataset.salary) - parseInt(a.dataset.salary);
        default:
          return 0;
      }
    });

    // Re-append sorted cards with animation
    cards.forEach((card, index) => {
      grid.appendChild(card);
      const matchCategory = !category || card.dataset.category === category;
      const matchStatus = !status || card.dataset.status === status;

      if (matchCategory && matchStatus) {
        card.style.display = '';
        card.style.animationDelay = `${index * 0.05}s`;
        visibleCount++;
      } else {
        card.style.display = 'none';
      }
    });

    // Show/hide no results
    document.getElementById('noResults').style.display = visibleCount === 0 ? 'block' : 'none';
  }

  function clearFilters() {
    document.getElementById('filterCategory').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('sortBy').value = 'saved_desc';
    filterJobs();
  }

  function updateStats() {
    // Update the stats by counting remaining cards
    const allCards = document.querySelectorAll('.saved-job-card');
    const activeCards = document.querySelectorAll('.saved-job-card:not(.expired)');
    const appliedCards = document.querySelectorAll('.saved-job-card[data-status="applied"]');
    const expiredCards = document.querySelectorAll('.saved-job-card.expired');

    // Update stat numbers if needed (optional - would need stat element IDs)
  }

  function checkEmpty() {
    const cards = document.querySelectorAll('.saved-job-card');
    if (cards.length === 0) {
      setTimeout(() => location.reload(), 500);
    }
  }

  function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type} show`;
    toast.innerHTML = `
      <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
      <span>${message}</span>
    `;

    // Add to container
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    container.appendChild(toast);

    // Remove after delay
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  // Add smooth entrance animation on load
  document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.saved-job-card');
    cards.forEach((card, index) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(20px)';
      setTimeout(() => {
        card.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      }, index * 50);
    });
  });
</script>

<?php include '../includes/footer.php'; ?>