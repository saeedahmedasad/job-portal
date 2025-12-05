<?php
/**
 * JobNexus - Admin User Detail View
 * View detailed information about a specific user
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/SeekerProfile.php';
require_once '../classes/Company.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_ADMIN) {
  header('Location: ../auth/login.php');
  exit;
}

$db = Database::getInstance()->getConnection();

// Get user ID from URL
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$userId) {
  header('Location: users.php');
  exit;
}

// Get user details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  header('Location: users.php');
  exit;
}

// Get role-specific details
$profile = null;
$company = null;
$applications = [];
$jobs = [];

if ($user['role'] === ROLE_SEEKER) {
  // Get seeker profile
  $stmt = $db->prepare("SELECT * FROM seeker_profiles WHERE user_id = ?");
  $stmt->execute([$userId]);
  $profile = $stmt->fetch(PDO::FETCH_ASSOC);

  // Get education
  $stmt = $db->prepare("SELECT * FROM education WHERE seeker_id = ? ORDER BY end_date DESC");
  $stmt->execute([$userId]);
  $education = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Get experience
  $stmt = $db->prepare("SELECT * FROM experience WHERE seeker_id = ? ORDER BY end_date DESC");
  $stmt->execute([$userId]);
  $experience = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Get applications
  $stmt = $db->prepare("
    SELECT a.*, j.title as job_title, c.company_name 
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN companies c ON j.company_id = c.id
    WHERE a.seeker_id = ?
    ORDER BY a.applied_at DESC
    LIMIT 10
  ");
  $stmt->execute([$userId]);
  $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($user['role'] === ROLE_HR) {
  // Get company
  $stmt = $db->prepare("SELECT * FROM companies WHERE hr_user_id = ?");
  $stmt->execute([$userId]);
  $company = $stmt->fetch(PDO::FETCH_ASSOC);

  // Get posted jobs
  if ($company) {
    $stmt = $db->prepare("
      SELECT j.*, 
        (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
      FROM jobs j
      WHERE j.company_id = ?
      ORDER BY j.created_at DESC
      LIMIT 10
    ");
    $stmt->execute([$company['id']]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}

// Get activity stats
$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$stmt->execute([$userId]);
$notificationCount = $stmt->fetchColumn();

$pageTitle = "User Details - Admin";
include '../includes/header.php';
?>

<div class="admin-container">
  <div class="admin-header">
    <div class="header-left">
      <a href="users.php" class="btn btn-ghost">
        <i class="fas fa-arrow-left"></i> Back to Users
      </a>
      <h1>User Details</h1>
    </div>
    <div class="header-actions">
      <?php if ($user['role'] === ROLE_SEEKER && $profile): ?>
        <a href="<?php echo BASE_URL; ?>/hr/seeker-profile.php?id=<?php echo $userId; ?>" class="btn btn-outline-primary"
          target="_blank">
          <i class="fas fa-external-link-alt"></i> View Public Profile
        </a>
      <?php elseif ($user['role'] === ROLE_HR && $company): ?>
        <a href="<?php echo BASE_URL; ?>/companies/profile.php?id=<?php echo $company['id']; ?>"
          class="btn btn-outline-primary" target="_blank">
          <i class="fas fa-external-link-alt"></i> View Company Profile
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="user-detail-grid">
    <!-- Main User Info Card -->
    <div class="glass-card user-main-card">
      <div class="user-header">
        <div class="user-avatar large">
          <?php if ($user['role'] === ROLE_SEEKER && $profile): ?>
            <?php echo strtoupper(substr($profile['first_name'] ?? 'U', 0, 1)); ?>
          <?php elseif ($user['role'] === ROLE_HR && $company && $company['logo']): ?>
            <img src="<?php echo BASE_URL; ?>/uploads/logos/<?php echo $company['logo']; ?>" alt="Company Logo">
          <?php else: ?>
            <?php echo strtoupper(substr($user['email'], 0, 1)); ?>
          <?php endif; ?>
        </div>
        <div class="user-info">
          <h2>
            <?php
            if ($user['role'] === ROLE_SEEKER && $profile) {
              echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']);
            } elseif ($user['role'] === ROLE_HR && $company) {
              echo htmlspecialchars($company['company_name']);
            } else {
              echo htmlspecialchars($user['email']);
            }
            ?>
          </h2>
          <p class="user-email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
          <div class="user-badges">
            <span class="badge badge-<?php echo $user['role']; ?>">
              <?php echo ucfirst($user['role']); ?>
            </span>
            <span class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
              <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
            </span>
            <?php if ($user['role'] === ROLE_HR && $company): ?>
              <span class="badge badge-<?php echo $company['verification_status']; ?>">
                <?php echo ucfirst($company['verification_status']); ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="user-meta">
        <div class="meta-item">
          <i class="fas fa-calendar-plus"></i>
          <div>
            <span class="label">Joined</span>
            <span class="value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
          </div>
        </div>
        <div class="meta-item">
          <i class="fas fa-clock"></i>
          <div>
            <span class="label">Last Updated</span>
            <span class="value"><?php echo date('F j, Y', strtotime($user['updated_at'])); ?></span>
          </div>
        </div>
        <div class="meta-item">
          <i class="fas fa-bell"></i>
          <div>
            <span class="label">Notifications</span>
            <span class="value"><?php echo $notificationCount; ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Role-Specific Details -->
    <?php if ($user['role'] === ROLE_SEEKER && $profile): ?>
      <!-- Seeker Profile Details -->
      <div class="glass-card">
        <h3><i class="fas fa-user"></i> Profile Information</h3>
        <div class="detail-grid">
          <div class="detail-item">
            <span class="label">Phone</span>
            <span class="value"><?php echo htmlspecialchars($profile['phone'] ?? 'Not provided'); ?></span>
          </div>
          <div class="detail-item">
            <span class="label">Location</span>
            <span class="value"><?php echo htmlspecialchars($profile['location'] ?? 'Not provided'); ?></span>
          </div>
          <div class="detail-item">
            <span class="label">Job Title</span>
            <span class="value"><?php echo htmlspecialchars($profile['headline'] ?? 'Not provided'); ?></span>
          </div>
          <div class="detail-item">
            <span class="label">Experience</span>
            <span class="value"><?php echo htmlspecialchars($profile['experience_years'] ?? '0'); ?> years</span>
          </div>
          <div class="detail-item">
            <span class="label">Profile Views</span>
            <span class="value"><?php echo $profile['profile_views'] ?? 0; ?></span>
          </div>
          <?php if (!empty($profile['resume_file_path'])): ?>
            <div class="detail-item">
              <span class="label">Resume</span>
              <span class="value">
                <a href="<?php echo BASE_URL; ?>/uploads/resumes/<?php echo $profile['resume_file_path']; ?>"
                  target="_blank" class="btn btn-sm btn-outline-primary">
                  <i class="fas fa-file-pdf"></i> View Resume
                </a>
              </span>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($profile['bio']): ?>
          <div class="detail-section">
            <h4>Bio</h4>
            <p><?php echo nl2br(htmlspecialchars($profile['bio'])); ?></p>
          </div>
        <?php endif; ?>

        <?php if ($profile['skills']): ?>
          <div class="detail-section">
            <h4>Skills</h4>
            <div class="skills-list">
              <?php foreach (explode(',', $profile['skills']) as $skill): ?>
                <span class="skill-tag"><?php echo htmlspecialchars(trim($skill)); ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Education -->
      <?php if (!empty($education)): ?>
        <div class="glass-card">
          <h3><i class="fas fa-graduation-cap"></i> Education</h3>
          <div class="timeline">
            <?php foreach ($education as $edu): ?>
              <div class="timeline-item">
                <div class="timeline-content">
                  <h4><?php echo htmlspecialchars($edu['degree']); ?></h4>
                  <p class="institution"><?php echo htmlspecialchars($edu['institution']); ?></p>
                  <p class="date">
                    <?php echo date('M Y', strtotime($edu['start_date'])); ?> -
                    <?php echo $edu['end_date'] ? date('M Y', strtotime($edu['end_date'])) : 'Present'; ?>
                  </p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Experience -->
      <?php if (!empty($experience)): ?>
        <div class="glass-card">
          <h3><i class="fas fa-briefcase"></i> Experience</h3>
          <div class="timeline">
            <?php foreach ($experience as $exp): ?>
              <div class="timeline-item">
                <div class="timeline-content">
                  <h4><?php echo htmlspecialchars($exp['job_title']); ?></h4>
                  <p class="company"><?php echo htmlspecialchars($exp['company_name']); ?></p>
                  <p class="date">
                    <?php echo date('M Y', strtotime($exp['start_date'])); ?> -
                    <?php echo $exp['end_date'] ? date('M Y', strtotime($exp['end_date'])) : 'Present'; ?>
                  </p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Applications -->
      <?php if (!empty($applications)): ?>
        <div class="glass-card">
          <h3><i class="fas fa-file-alt"></i> Recent Applications</h3>
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Job</th>
                  <th>Company</th>
                  <th>Status</th>
                  <th>Applied</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($applications as $app): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                    <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                    <td>
                      <span class="badge badge-<?php echo $app['status']; ?>">
                        <?php echo ucfirst($app['status']); ?>
                      </span>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($app['applied_at'])); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    <?php elseif ($user['role'] === ROLE_HR && $company): ?>
      <!-- Company Details -->
      <div class="glass-card">
        <h3><i class="fas fa-building"></i> Company Information</h3>
        <div class="detail-grid">
          <div class="detail-item">
            <span class="label">Company Name</span>
            <span class="value"><?php echo htmlspecialchars($company['company_name']); ?></span>
          </div>
          <div class="detail-item">
            <span class="label">Industry</span>
            <span class="value"><?php echo htmlspecialchars($company['industry'] ?? 'Not specified'); ?></span>
          </div>
          <div class="detail-item">
            <span class="label">Company Size</span>
            <span class="value"><?php echo htmlspecialchars($company['company_size'] ?? 'Not specified'); ?></span>
          </div>
          <div class="detail-item">
            <span class="label">Location</span>
            <span class="value"><?php echo htmlspecialchars($company['location'] ?? 'Not specified'); ?></span>
          </div>
          <div class="detail-item">
            <span class="label">Website</span>
            <span class="value">
              <?php if ($company['website']): ?>
                <a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank">
                  <?php echo htmlspecialchars($company['website']); ?>
                </a>
              <?php else: ?>
                Not provided
              <?php endif; ?>
            </span>
          </div>
          <div class="detail-item">
            <span class="label">Verification Status</span>
            <span class="value">
              <span class="badge badge-<?php echo $company['verification_status']; ?>">
                <?php echo ucfirst($company['verification_status']); ?>
              </span>
            </span>
          </div>
        </div>

        <?php if ($company['description']): ?>
          <div class="detail-section">
            <h4>Company Description</h4>
            <p><?php echo nl2br(htmlspecialchars($company['description'])); ?></p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Posted Jobs -->
      <?php if (!empty($jobs)): ?>
        <div class="glass-card">
          <h3><i class="fas fa-briefcase"></i> Posted Jobs</h3>
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Job Title</th>
                  <th>Type</th>
                  <th>Applications</th>
                  <th>Status</th>
                  <th>Posted</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($jobs as $job): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($job['title']); ?></td>
                    <td><?php echo ucfirst($job['job_type']); ?></td>
                    <td><?php echo $job['application_count']; ?></td>
                    <td>
                      <span class="badge badge-<?php echo $job['status']; ?>">
                        <?php echo ucfirst($job['status']); ?>
                      </span>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    <?php elseif ($user['role'] === ROLE_ADMIN): ?>
      <!-- Admin Info -->
      <div class="glass-card">
        <h3><i class="fas fa-shield-alt"></i> Administrator Account</h3>
        <p>This is an administrator account with full system access.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<style>
  .admin-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
  }

  .admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
  }

  .header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  .header-left h1 {
    margin: 0;
    font-size: 1.75rem;
  }

  .user-detail-grid {
    display: grid;
    gap: 1.5rem;
  }

  .user-main-card {
    padding: 2rem;
  }

  .user-header {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }

  .user-avatar.large {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), #00ff88);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 700;
    color: #000;
    flex-shrink: 0;
    overflow: hidden;
  }

  .user-avatar.large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .user-info h2 {
    margin: 0 0 0.5rem;
    font-size: 1.75rem;
  }

  .user-email {
    color: var(--text-muted);
    margin-bottom: 1rem;
  }

  .user-email i {
    margin-right: 0.5rem;
  }

  .user-badges {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
  }

  .badge {
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
  }

  .badge-seeker {
    background: rgba(100, 181, 246, 0.2);
    color: #64b5f6;
  }

  .badge-hr {
    background: rgba(186, 104, 200, 0.2);
    color: #ba68c8;
  }

  .badge-admin {
    background: rgba(255, 152, 0, 0.2);
    color: #ff9800;
  }

  .badge-success,
  .badge-active {
    background: rgba(0, 230, 118, 0.2);
    color: #00E676;
  }

  .badge-danger,
  .badge-inactive {
    background: rgba(255, 82, 82, 0.2);
    color: #ff5252;
  }

  .badge-verified {
    background: rgba(0, 230, 118, 0.2);
    color: #00E676;
  }

  .badge-pending {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
  }

  .badge-rejected {
    background: rgba(255, 82, 82, 0.2);
    color: #ff5252;
  }

  .badge-interview {
    background: rgba(156, 39, 176, 0.2);
    color: #9c27b0;
  }

  .badge-hired {
    background: rgba(0, 230, 118, 0.2);
    color: #00E676;
  }

  .badge-shortlisted {
    background: rgba(33, 150, 243, 0.2);
    color: #2196f3;
  }

  .user-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
  }

  .meta-item {
    display: flex;
    gap: 1rem;
    align-items: center;
  }

  .meta-item i {
    font-size: 1.5rem;
    color: var(--primary-color);
    width: 40px;
    text-align: center;
  }

  .meta-item .label {
    display: block;
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
  }

  .meta-item .value {
    font-weight: 600;
  }

  .glass-card h3 {
    margin: 0 0 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .glass-card h3 i {
    color: var(--primary-color);
  }

  .detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.25rem;
  }

  .detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }

  .detail-item .label {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
  }

  .detail-item .value {
    font-weight: 500;
  }

  .detail-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
  }

  .detail-section h4 {
    margin: 0 0 0.75rem;
    font-size: 0.875rem;
    color: var(--text-muted);
    text-transform: uppercase;
  }

  .skills-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .skill-tag {
    background: rgba(0, 230, 118, 0.15);
    color: var(--primary-color);
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
  }

  .timeline {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .timeline-item {
    padding-left: 1.5rem;
    border-left: 2px solid var(--primary-color);
  }

  .timeline-content h4 {
    margin: 0 0 0.25rem;
  }

  .timeline-content p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.875rem;
  }

  .timeline-content .date {
    font-size: 0.8rem;
  }

  .table-responsive {
    overflow-x: auto;
  }

  .data-table {
    width: 100%;
    border-collapse: collapse;
  }

  .data-table th,
  .data-table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }

  .data-table th {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: var(--text-muted);
    font-weight: 600;
  }

  @media (max-width: 768px) {
    .admin-container {
      padding: 1rem;
    }

    .user-header {
      flex-direction: column;
      text-align: center;
    }

    .user-badges {
      justify-content: center;
    }

    .admin-header {
      flex-direction: column;
      align-items: flex-start;
    }
  }
</style>

<?php include '../includes/footer.php'; ?>