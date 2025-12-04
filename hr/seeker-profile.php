<?php
/**
 * JobNexus - Public Seeker Profile View
 * Allows HR/Recruiters to view job seeker profiles
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/SeekerProfile.php';

// Check if HR is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_HR) {
  header('Location: ' . BASE_URL . '/auth/login.php');
  exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$profileModel = new SeekerProfile();

// Get seeker ID from URL
$seekerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$seekerId) {
  header('Location: ' . BASE_URL . '/hr/candidates.php');
  exit;
}

// Get seeker profile
$stmt = $db->prepare("
  SELECT sp.*, u.email, u.created_at as joined_at
  FROM seeker_profiles sp
  JOIN users u ON sp.user_id = u.id
  WHERE sp.user_id = ? AND u.role = 'seeker' AND u.is_active = 1
");
$stmt->execute([$seekerId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
  header('Location: ' . BASE_URL . '/hr/candidates.php');
  exit;
}

// Increment profile views count
$stmt = $db->prepare("UPDATE seeker_profiles SET profile_views = profile_views + 1 WHERE user_id = ?");
$stmt->execute([$seekerId]);

// Get HR's company
$stmt = $db->prepare("SELECT * FROM companies WHERE hr_user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Get seeker's experience (using seeker_profiles.id as seeker_id)
$stmt = $db->prepare("SELECT * FROM experience WHERE seeker_id = ? ORDER BY is_current DESC, end_date DESC, start_date DESC");
$stmt->execute([$profile['id']]);
$experiences = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get seeker's education (using seeker_profiles.id as seeker_id)
$stmt = $db->prepare("SELECT * FROM education WHERE seeker_id = ? ORDER BY is_current DESC, end_date DESC, start_date DESC");
$stmt->execute([$profile['id']]);
$education = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if this seeker has applied to any of our jobs
$stmt = $db->prepare("
  SELECT a.*, j.title as job_title
  FROM applications a
  JOIN jobs j ON a.job_id = j.id
  WHERE a.seeker_id = ? AND j.company_id = ?
  ORDER BY a.applied_at DESC
");
$stmt->execute([$seekerId, $company['id'] ?? 0]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = $profile['first_name'] . ' ' . $profile['last_name'] . ' - Profile';
require_once '../includes/header.php';
?>

<div class="dashboard-container">
  <!-- Sidebar -->
  <aside class="dashboard-sidebar">
    <div class="sidebar-header">
      <div class="hr-avatar">
        <?php echo strtoupper(substr($company['company_name'] ?? 'HR', 0, 2)); ?>
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
      <a href="<?php echo BASE_URL; ?>/hr/applications.php" class="nav-item">
        <i class="fas fa-file-alt"></i>
        <span>Applications</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/hr/candidates.php" class="nav-item active">
        <i class="fas fa-users"></i>
        <span>Candidates</span>
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
        <a href="<?php echo BASE_URL; ?>/hr/candidates.php" class="back-link">
          <i class="fas fa-arrow-left"></i> Back to Candidates
        </a>
        <h1>Candidate Profile</h1>
      </div>
      <div class="header-right">
        <?php if ($profile['resume_file_path']): ?>
          <a href="<?php echo BASE_URL; ?>/uploads/resumes/<?php echo htmlspecialchars($profile['resume_file_path']); ?>"
            class="btn btn-primary" target="_blank">
            <i class="fas fa-download"></i> Download Resume
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="profile-layout">
      <!-- Profile Header -->
      <div class="profile-header glass-card">
        <div class="profile-avatar">
          <?php if ($profile['profile_photo']): ?>
            <img src="<?php echo BASE_URL; ?>/uploads/profiles/<?php echo htmlspecialchars($profile['profile_photo']); ?>"
              alt="<?php echo htmlspecialchars($profile['first_name']); ?>">
          <?php else: ?>
            <?php echo strtoupper(substr($profile['first_name'], 0, 1) . substr($profile['last_name'], 0, 1)); ?>
          <?php endif; ?>
        </div>
        <div class="profile-info">
          <h1><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></h1>
          <?php if ($profile['headline']): ?>
            <p class="profile-headline"><?php echo htmlspecialchars($profile['headline']); ?></p>
          <?php endif; ?>
          <div class="profile-meta">
            <?php if ($profile['location']): ?>
              <span class="meta-item">
                <i class="fas fa-map-marker-alt"></i>
                <?php echo htmlspecialchars($profile['location']); ?>
              </span>
            <?php endif; ?>
            <span class="meta-item">
              <i class="fas fa-envelope"></i>
              <?php echo htmlspecialchars($profile['email']); ?>
            </span>
            <?php if ($profile['phone']): ?>
              <span class="meta-item">
                <i class="fas fa-phone"></i>
                <?php echo htmlspecialchars($profile['phone']); ?>
              </span>
            <?php endif; ?>
          </div>
          <div class="profile-links">
            <?php if ($profile['linkedin_url']): ?>
              <a href="<?php echo htmlspecialchars($profile['linkedin_url']); ?>" target="_blank"
                class="social-link linkedin">
                <i class="fab fa-linkedin"></i>
              </a>
            <?php endif; ?>
            <?php if ($profile['github_url']): ?>
              <a href="<?php echo htmlspecialchars($profile['github_url']); ?>" target="_blank"
                class="social-link github">
                <i class="fab fa-github"></i>
              </a>
            <?php endif; ?>
            <?php if ($profile['portfolio_url']): ?>
              <a href="<?php echo htmlspecialchars($profile['portfolio_url']); ?>" target="_blank"
                class="social-link portfolio">
                <i class="fas fa-globe"></i>
              </a>
            <?php endif; ?>
          </div>
        </div>
        <div class="profile-joined">
          <i class="fas fa-calendar"></i>
          Joined <?php echo date('F Y', strtotime($profile['joined_at'])); ?>
        </div>
      </div>

      <div class="profile-grid">
        <!-- Left Column -->
        <div class="profile-column">
          <!-- About -->
          <?php if ($profile['bio'] || $profile['summary']): ?>
            <div class="profile-section glass-card">
              <h2><i class="fas fa-user"></i> About</h2>
              <p><?php echo nl2br(htmlspecialchars($profile['bio'] ?? $profile['summary'] ?? '')); ?></p>
            </div>
          <?php endif; ?>

          <!-- Experience -->
          <?php if (!empty($experiences)): ?>
            <div class="profile-section glass-card">
              <h2><i class="fas fa-briefcase"></i> Experience</h2>
              <div class="experience-list">
                <?php foreach ($experiences as $exp): ?>
                  <div class="experience-item">
                    <div class="exp-header">
                      <h3><?php echo htmlspecialchars($exp['job_title']); ?></h3>
                      <span class="exp-company"><?php echo htmlspecialchars($exp['company_name']); ?></span>
                    </div>
                    <div class="exp-meta">
                      <span class="exp-dates">
                        <i class="fas fa-calendar"></i>
                        <?php echo date('M Y', strtotime($exp['start_date'])); ?> -
                        <?php echo $exp['is_current'] ? 'Present' : date('M Y', strtotime($exp['end_date'])); ?>
                      </span>
                      <?php if ($exp['location']): ?>
                        <span class="exp-location">
                          <i class="fas fa-map-marker-alt"></i>
                          <?php echo htmlspecialchars($exp['location']); ?>
                        </span>
                      <?php endif; ?>
                    </div>
                    <?php if ($exp['description']): ?>
                      <p class="exp-description"><?php echo nl2br(htmlspecialchars($exp['description'])); ?></p>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Education -->
          <?php if (!empty($education)): ?>
            <div class="profile-section glass-card">
              <h2><i class="fas fa-graduation-cap"></i> Education</h2>
              <div class="education-list">
                <?php foreach ($education as $edu): ?>
                  <div class="education-item">
                    <h3>
                      <?php echo htmlspecialchars($edu['degree']); ?>
                      <?php echo $edu['field_of_study'] ? ' in ' . htmlspecialchars($edu['field_of_study']) : ''; ?>
                    </h3>
                    <span class="edu-school"><?php echo htmlspecialchars($edu['institution']); ?></span>
                    <span class="edu-dates">
                      <?php echo $edu['start_date'] ? date('Y', strtotime($edu['start_date'])) : ''; ?> -
                      <?php echo $edu['is_current'] ? 'Present' : ($edu['end_date'] ? date('Y', strtotime($edu['end_date'])) : ''); ?>
                    </span>
                    <?php if ($edu['grade']): ?>
                      <span class="edu-grade">Grade: <?php echo htmlspecialchars($edu['grade']); ?></span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- Right Column -->
        <div class="profile-column sidebar">
          <!-- Skills -->
          <?php if ($profile['skills']): ?>
            <div class="profile-section glass-card">
              <h2><i class="fas fa-code"></i> Skills</h2>
              <div class="skills-list">
                <?php
                $skillsArr = json_decode($profile['skills'], true);
                if (is_array($skillsArr)):
                  foreach ($skillsArr as $skill):
                    ?>
                    <span class="skill-badge"><?php echo htmlspecialchars($skill); ?></span>
                    <?php
                  endforeach;
                endif;
                ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Applications to your company -->
          <?php if (!empty($applications)): ?>
            <div class="profile-section glass-card">
              <h2><i class="fas fa-file-alt"></i> Applications</h2>
              <p class="section-note">Applications to your company</p>
              <div class="applications-list">
                <?php foreach ($applications as $app): ?>
                  <a href="<?php echo BASE_URL; ?>/hr/application.php?id=<?php echo $app['id']; ?>"
                    class="application-link">
                    <span class="app-job"><?php echo htmlspecialchars($app['job_title']); ?></span>
                    <span class="app-status status-<?php echo $app['status']; ?>">
                      <?php echo ucfirst($app['status']); ?>
                    </span>
                    <span class="app-date"><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Quick Info -->
          <div class="profile-section glass-card">
            <h2><i class="fas fa-info-circle"></i> Quick Info</h2>
            <ul class="info-list">
              <li>
                <span class="info-label">Email</span>
                <span class="info-value"><?php echo htmlspecialchars($profile['email']); ?></span>
              </li>
              <?php if ($profile['phone']): ?>
                <li>
                  <span class="info-label">Phone</span>
                  <span class="info-value"><?php echo htmlspecialchars($profile['phone']); ?></span>
                </li>
              <?php endif; ?>
              <?php if ($profile['location']): ?>
                <li>
                  <span class="info-label">Location</span>
                  <span class="info-value"><?php echo htmlspecialchars($profile['location']); ?></span>
                </li>
              <?php endif; ?>
              <li>
                <span class="info-label">Member Since</span>
                <span class="info-value"><?php echo date('F Y', strtotime($profile['joined_at'])); ?></span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<style>
  .back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
    transition: color 0.3s ease;
  }

  .back-link:hover {
    color: var(--primary-color);
  }

  .profile-layout {
    display: flex;
    flex-direction: column;
    gap: 2rem;
  }

  /* Profile Header */
  .profile-header {
    display: flex;
    gap: 2rem;
    padding: 2rem;
    align-items: flex-start;
  }

  .profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: rgba(0, 230, 118, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 2.5rem;
    color: var(--primary-color);
    flex-shrink: 0;
    overflow: hidden;
  }

  .profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .profile-info {
    flex: 1;
  }

  .profile-info h1 {
    font-size: 1.75rem;
    margin: 0 0 0.5rem;
  }

  .profile-headline {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0 0 1rem;
  }

  .profile-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-bottom: 1rem;
  }

  .meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--text-muted);
  }

  .meta-item i {
    color: var(--primary-color);
  }

  .profile-links {
    display: flex;
    gap: 0.75rem;
  }

  .social-link {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-secondary);
    transition: all 0.3s ease;
    font-size: 1.1rem;
  }

  .social-link:hover {
    transform: translateY(-2px);
  }

  .social-link.linkedin:hover {
    background: #0077b5;
    color: white;
  }

  .social-link.github:hover {
    background: #333;
    color: white;
  }

  .social-link.portfolio:hover {
    background: var(--primary-color);
    color: #000;
  }

  .profile-joined {
    font-size: 0.875rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
  }

  .profile-joined i {
    color: var(--primary-color);
  }

  /* Profile Grid */
  .profile-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
  }

  .profile-section {
    padding: 1.5rem;
    margin-bottom: 0;
  }

  .profile-section+.profile-section {
    margin-top: 1.5rem;
  }

  .profile-section h2 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.1rem;
    margin: 0 0 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }

  .profile-section h2 i {
    color: var(--primary-color);
  }

  .profile-section p {
    color: var(--text-secondary);
    line-height: 1.7;
    margin: 0;
  }

  /* Experience */
  .experience-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }

  .experience-item {
    padding-left: 1rem;
    border-left: 2px solid var(--primary-color);
  }

  .exp-header h3 {
    font-size: 1rem;
    margin: 0 0 0.25rem;
  }

  .exp-company {
    font-size: 0.9rem;
    color: var(--primary-color);
    font-weight: 500;
  }

  .exp-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin: 0.5rem 0;
    font-size: 0.8rem;
    color: var(--text-muted);
  }

  .exp-meta span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
  }

  .exp-description {
    margin-top: 0.75rem;
    font-size: 0.9rem;
  }

  /* Education */
  .education-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }

  .education-item {
    padding-left: 1rem;
    border-left: 2px solid #2196F3;
  }

  .education-item h3 {
    font-size: 1rem;
    margin: 0 0 0.25rem;
  }

  .edu-school {
    display: block;
    font-size: 0.9rem;
    color: #2196F3;
    font-weight: 500;
  }

  .edu-dates,
  .edu-grade {
    display: block;
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
  }

  /* Skills */
  .skills-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .skill-badge {
    padding: 0.35rem 0.75rem;
    background: rgba(0, 230, 118, 0.1);
    border: 1px solid rgba(0, 230, 118, 0.3);
    border-radius: 20px;
    font-size: 0.8rem;
    color: var(--primary-color);
  }

  /* Applications */
  .section-note {
    font-size: 0.8rem !important;
    color: var(--text-muted) !important;
    margin-bottom: 1rem !important;
  }

  .applications-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .application-link {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 0.5rem;
    text-decoration: none;
    transition: all 0.3s ease;
  }

  .application-link:hover {
    background: rgba(255, 255, 255, 0.06);
  }

  .app-job {
    font-weight: 500;
    color: var(--text-primary);
  }

  .app-status {
    font-size: 0.75rem;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    width: fit-content;
  }

  .app-status.status-applied {
    background: rgba(255, 193, 7, 0.15);
    color: #FFC107;
  }

  .app-status.status-viewed {
    background: rgba(33, 150, 243, 0.15);
    color: #2196F3;
  }

  .app-status.status-shortlisted {
    background: rgba(0, 230, 118, 0.15);
    color: #00E676;
  }

  .app-status.status-interview {
    background: rgba(156, 39, 176, 0.15);
    color: #9C27B0;
  }

  .app-status.status-offered {
    background: rgba(76, 175, 80, 0.15);
    color: #4CAF50;
  }

  .app-status.status-hired {
    background: rgba(0, 200, 83, 0.15);
    color: #00C853;
  }

  .app-status.status-rejected {
    background: rgba(244, 67, 54, 0.15);
    color: #F44336;
  }

  .app-date {
    font-size: 0.75rem;
    color: var(--text-muted);
  }

  /* Info List */
  .info-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .info-list li {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }

  .info-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
  }

  .info-value {
    color: var(--text-primary);
    word-break: break-all;
  }

  /* Button */
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    font-size: 0.9rem;
    text-decoration: none;
  }

  .btn-primary {
    background: linear-gradient(135deg, #00E676, #00C853);
    color: #000 !important;
    box-shadow: 0 4px 15px rgba(0, 230, 118, 0.3);
  }

  .btn-primary:hover {
    background: linear-gradient(135deg, #00ff88, #00E676);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 230, 118, 0.4);
  }

  /* Responsive */
  @media (max-width: 992px) {
    .profile-grid {
      grid-template-columns: 1fr;
    }

    .profile-header {
      flex-direction: column;
      align-items: center;
      text-align: center;
    }

    .profile-meta {
      justify-content: center;
    }

    .profile-links {
      justify-content: center;
    }

    .profile-joined {
      margin-top: 1rem;
    }
  }

  @media (max-width: 768px) {
    .profile-avatar {
      width: 100px;
      height: 100px;
      font-size: 2rem;
    }

    .profile-info h1 {
      font-size: 1.5rem;
    }
  }

  /* Ensure footer appears above sidebar */
  footer,
  .site-footer {
    position: relative;
    z-index: 1001;
  }
</style>

<?php require_once '../includes/footer.php'; ?>