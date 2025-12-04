<?php
/**
 * JobNexus - HR Application Detail View
 * View individual application details
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_HR) {
  header('Location: ' . BASE_URL . '/auth/login.php');
  exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$applicationModel = new Application();

$hr = $userModel->findById($_SESSION['user_id']);

$appId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$appId) {
  header('Location: ' . BASE_URL . '/hr/applications.php');
  exit;
}

// Get application with ownership check
$stmt = $db->prepare("
    SELECT a.*, 
           j.title as job_title, j.slug as job_slug, j.location as job_location, 
           j.job_type, j.salary_min, j.salary_max, j.salary_period,
           CONCAT(sp.first_name, ' ', sp.last_name) as applicant_name,
           sp.first_name, sp.last_name, sp.headline, sp.phone, sp.location as applicant_location,
           sp.profile_photo, sp.resume_file_path, sp.bio, sp.linkedin_url, sp.github_url, sp.portfolio_url,
           sp.skills,
           u.email as applicant_email
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    JOIN users u ON a.seeker_id = u.id
    LEFT JOIN seeker_profiles sp ON u.id = sp.user_id
    WHERE a.id = ? AND j.posted_by = ?
");
$stmt->execute([$appId, $_SESSION['user_id']]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
  header('Location: ' . BASE_URL . '/hr/applications.php');
  exit;
}

// Handle status update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'update_status') {
    $newStatus = $_POST['status'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    $validStatuses = ['applied', 'viewed', 'shortlisted', 'interview', 'offered', 'rejected', 'hired', 'withdrawn'];
    if (in_array($newStatus, $validStatuses)) {
      if ($applicationModel->updateStatus($appId, $newStatus, $notes)) {
        $message = 'Application status updated successfully!';
        $messageType = 'success';
        $application['status'] = $newStatus;
        $application['status_notes'] = $notes;
      } else {
        $message = 'Error updating status.';
        $messageType = 'error';
      }
    }
  }

  if ($action === 'update_notes') {
    $notes = trim($_POST['hr_notes'] ?? '');
    if ($applicationModel->updateNotes($appId, $notes)) {
      $message = 'Notes saved successfully!';
      $messageType = 'success';
      $application['hr_notes'] = $notes;
    } else {
      $message = 'Error saving notes.';
      $messageType = 'error';
    }
  }

  if ($action === 'update_rating') {
    $rating = (int) ($_POST['rating'] ?? 0);
    if ($rating >= 1 && $rating <= 5) {
      $stmt = $db->prepare("UPDATE applications SET rating = ? WHERE id = ?");
      if ($stmt->execute([$rating, $appId])) {
        $message = 'Rating updated!';
        $messageType = 'success';
        $application['rating'] = $rating;
      }
    }
  }
}

// Get company info for sidebar
$stmt = $db->prepare("SELECT * FROM companies WHERE hr_user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'Application - ' . $application['applicant_name'];
require_once '../includes/header.php';

// Parse skills if JSON
$skills = [];
if (!empty($application['skills'])) {
  if (is_string($application['skills'])) {
    $skills = json_decode($application['skills'], true) ?? [];
  } elseif (is_array($application['skills'])) {
    $skills = $application['skills'];
  }
}
?>

<div class="dashboard-container">
  <!-- Sidebar -->
  <aside class="dashboard-sidebar">
    <div class="sidebar-header">
      <div class="hr-avatar">
        <?php echo strtoupper(substr($company['company_name'] ?? 'HR', 0, 2)); ?>
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
        <a href="<?php echo BASE_URL; ?>/hr/applications.php" class="back-link">
          <i class="fas fa-arrow-left"></i> Back to Applications
        </a>
        <h1><?php echo htmlspecialchars($application['applicant_name']); ?></h1>
        <p>Applied for <?php echo htmlspecialchars($application['job_title']); ?></p>
      </div>
      <div class="header-right">
        <span class="status-badge status-<?php echo $application['status']; ?>">
          <?php echo ucfirst($application['status']); ?>
        </span>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?php echo $messageType; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <div class="application-detail-grid">
      <!-- Applicant Profile -->
      <div class="glass-card applicant-profile">
        <div class="profile-header">
          <div class="profile-avatar">
            <?php if ($application['profile_photo']): ?>
              <img
                src="<?php echo BASE_URL; ?>/uploads/photos/<?php echo htmlspecialchars($application['profile_photo']); ?>"
                alt="<?php echo htmlspecialchars($application['applicant_name']); ?>">
            <?php else: ?>
              <?php echo strtoupper(substr($application['first_name'] ?? 'U', 0, 1)); ?>
            <?php endif; ?>
          </div>
          <div class="profile-info">
            <h2><?php echo htmlspecialchars($application['applicant_name']); ?></h2>
            <p class="headline"><?php echo htmlspecialchars($application['headline'] ?? ''); ?></p>
            <?php if ($application['applicant_location']): ?>
              <p class="location"><i class="fas fa-map-marker-alt"></i>
                <?php echo htmlspecialchars($application['applicant_location']); ?></p>
            <?php endif; ?>
          </div>
        </div>

        <div class="profile-actions">
          <a href="mailto:<?php echo $application['applicant_email']; ?>" class="btn btn-primary">
            <i class="fas fa-envelope"></i> Email
          </a>
          <?php if ($application['phone']): ?>
            <a href="tel:<?php echo $application['phone']; ?>" class="btn btn-outline">
              <i class="fas fa-phone"></i> Call
            </a>
          <?php endif; ?>
          <?php if ($application['resume_file_path']): ?>
            <a href="<?php echo BASE_URL; ?>/uploads/resumes/<?php echo $application['resume_file_path']; ?>"
              class="btn btn-outline" target="_blank">
              <i class="fas fa-file-pdf"></i> Resume
            </a>
          <?php endif; ?>
        </div>

        <div class="profile-details">
          <?php if (!empty($application['headline'])): ?>
            <div class="detail-item">
              <i class="fas fa-briefcase"></i>
              <span><?php echo htmlspecialchars($application['headline']); ?></span>
            </div>
          <?php endif; ?>
          <?php if (!empty($application['applicant_location'])): ?>
            <div class="detail-item">
              <i class="fas fa-map-marker-alt"></i>
              <span><?php echo htmlspecialchars($application['applicant_location']); ?></span>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($skills)): ?>
          <div class="skills-section">
            <h4>Skills</h4>
            <div class="skills-list">
              <?php foreach ($skills as $skill): ?>
                <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($application['bio']): ?>
          <div class="bio-section">
            <h4>About</h4>
            <p><?php echo nl2br(htmlspecialchars($application['bio'])); ?></p>
          </div>
        <?php endif; ?>

        <div class="social-links">
          <?php if ($application['linkedin_url']): ?>
            <a href="<?php echo $application['linkedin_url']; ?>" target="_blank" class="social-link linkedin">
              <i class="fab fa-linkedin"></i>
            </a>
          <?php endif; ?>
          <?php if ($application['github_url']): ?>
            <a href="<?php echo $application['github_url']; ?>" target="_blank" class="social-link github">
              <i class="fab fa-github"></i>
            </a>
          <?php endif; ?>
          <?php if ($application['portfolio_url']): ?>
            <a href="<?php echo $application['portfolio_url']; ?>" target="_blank" class="social-link portfolio">
              <i class="fas fa-globe"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Application Details -->
      <div class="application-details">
        <!-- Status Update -->
        <div class="glass-card">
          <h3><i class="fas fa-tasks"></i> Update Status</h3>
          <form method="POST" class="status-update-form">
            <input type="hidden" name="action" value="update_status">
            <div class="form-row">
              <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                  <option value="applied" <?php echo $application['status'] === 'applied' ? 'selected' : ''; ?>>New /
                    Applied</option>
                  <option value="viewed" <?php echo $application['status'] === 'viewed' ? 'selected' : ''; ?>>Viewed
                  </option>
                  <option value="shortlisted" <?php echo $application['status'] === 'shortlisted' ? 'selected' : ''; ?>>
                    Shortlisted</option>
                  <option value="interview" <?php echo $application['status'] === 'interview' ? 'selected' : ''; ?>>
                    Interview</option>
                  <option value="offered" <?php echo $application['status'] === 'offered' ? 'selected' : ''; ?>>Offered
                  </option>
                  <option value="hired" <?php echo $application['status'] === 'hired' ? 'selected' : ''; ?>>Hired</option>
                  <option value="rejected" <?php echo $application['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected
                  </option>
                </select>
              </div>
              <button type="submit" class="btn btn-primary">Update</button>
            </div>
            <div class="form-group">
              <label>Status Notes (optional)</label>
              <textarea name="notes" class="form-control" rows="2"
                placeholder="Add notes about this status change..."><?php echo htmlspecialchars($application['status_notes'] ?? ''); ?></textarea>
            </div>
          </form>
        </div>

        <!-- Rating -->
        <div class="glass-card">
          <h3><i class="fas fa-star"></i> Rate Candidate</h3>
          <form method="POST" class="rating-form">
            <input type="hidden" name="action" value="update_rating">
            <div class="star-rating">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <button type="submit" name="rating" value="<?php echo $i; ?>"
                  class="star-btn <?php echo ($application['rating'] ?? 0) >= $i ? 'active' : ''; ?>">
                  <i class="fas fa-star"></i>
                </button>
              <?php endfor; ?>
            </div>
          </form>
        </div>

        <!-- Cover Letter -->
        <?php if ($application['cover_letter']): ?>
          <div class="glass-card">
            <h3><i class="fas fa-file-alt"></i> Cover Letter</h3>
            <div class="cover-letter-content">
              <?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- HR Notes -->
        <div class="glass-card">
          <h3><i class="fas fa-sticky-note"></i> Internal Notes</h3>
          <form method="POST">
            <input type="hidden" name="action" value="update_notes">
            <div class="form-group">
              <textarea name="hr_notes" class="form-control" rows="4"
                placeholder="Add private notes about this candidate..."><?php echo htmlspecialchars($application['hr_notes'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> Save Notes
            </button>
          </form>
        </div>

        <!-- Timeline -->
        <div class="glass-card">
          <h3><i class="fas fa-history"></i> Timeline</h3>
          <div class="timeline">
            <div class="timeline-item">
              <div class="timeline-icon applied">
                <i class="fas fa-paper-plane"></i>
              </div>
              <div class="timeline-content">
                <strong>Applied</strong>
                <span><?php echo date('M j, Y g:i A', strtotime($application['applied_at'])); ?></span>
              </div>
            </div>
            <?php if ($application['viewed_at']): ?>
              <div class="timeline-item">
                <div class="timeline-icon viewed">
                  <i class="fas fa-eye"></i>
                </div>
                <div class="timeline-content">
                  <strong>Viewed</strong>
                  <span><?php echo date('M j, Y g:i A', strtotime($application['viewed_at'])); ?></span>
                </div>
              </div>
            <?php endif; ?>
            <?php if ($application['shortlisted_at']): ?>
              <div class="timeline-item">
                <div class="timeline-icon shortlisted">
                  <i class="fas fa-check"></i>
                </div>
                <div class="timeline-content">
                  <strong>Shortlisted</strong>
                  <span><?php echo date('M j, Y g:i A', strtotime($application['shortlisted_at'])); ?></span>
                </div>
              </div>
            <?php endif; ?>
            <?php if ($application['interview_at']): ?>
              <div class="timeline-item">
                <div class="timeline-icon interview">
                  <i class="fas fa-calendar-check"></i>
                </div>
                <div class="timeline-content">
                  <strong>Interview Scheduled</strong>
                  <span><?php echo date('M j, Y g:i A', strtotime($application['interview_at'])); ?></span>
                </div>
              </div>
            <?php endif; ?>
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
    margin-bottom: 0.5rem;
    transition: color 0.2s;
  }

  .back-link:hover {
    color: var(--accent-primary);
  }

  .application-detail-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 1.5rem;
  }

  .applicant-profile {
    padding: 1.5rem;
    position: sticky;
    top: 100px;
    align-self: start;
  }

  .profile-header {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
  }

  .profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: var(--radius-lg);
    background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    color: white;
    overflow: hidden;
  }

  .profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .profile-info h2 {
    font-family: var(--font-heading);
    font-size: 1.25rem;
    margin-bottom: 0.25rem;
  }

  .profile-info .headline {
    color: var(--text-secondary);
    font-size: 0.9rem;
  }

  .profile-info .location {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin-top: 0.25rem;
  }

  .profile-actions {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
  }

  .profile-actions .btn {
    flex: 1;
    justify-content: center;
  }

  .profile-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
  }

  .detail-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
  }

  .detail-item i {
    color: var(--accent-primary);
    width: 20px;
  }

  .skills-section,
  .bio-section {
    margin-bottom: 1.5rem;
  }

  .skills-section h4,
  .bio-section h4 {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
  }

  .skills-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .skill-tag {
    padding: 0.35rem 0.75rem;
    background: rgba(var(--accent-primary-rgb), 0.1);
    border: 1px solid rgba(var(--accent-primary-rgb), 0.2);
    border-radius: var(--radius-full);
    font-size: 0.8rem;
    color: var(--accent-primary);
  }

  .bio-section p {
    color: var(--text-secondary);
    font-size: 0.9rem;
    line-height: 1.6;
  }

  .social-links {
    display: flex;
    gap: 0.75rem;
  }

  .social-link {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    transition: all 0.2s;
  }

  .social-link:hover {
    background: var(--accent-primary);
    color: white;
  }

  .application-details {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }

  .application-details .glass-card {
    padding: 1.5rem;
  }

  .application-details h3 {
    font-family: var(--font-heading);
    font-size: 1.1rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .application-details h3 i {
    color: var(--accent-primary);
  }

  .status-update-form .form-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
  }

  .status-update-form .form-row .form-group {
    flex: 1;
    margin-bottom: 0;
  }

  .star-rating {
    display: flex;
    gap: 0.5rem;
  }

  .star-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.2s;
    padding: 0.25rem;
  }

  .star-btn:hover,
  .star-btn.active {
    color: #ffc107;
    transform: scale(1.1);
  }

  .cover-letter-content {
    background: var(--bg-tertiary);
    padding: 1rem;
    border-radius: var(--radius-md);
    line-height: 1.7;
    color: var(--text-secondary);
  }

  .timeline {
    position: relative;
    padding-left: 30px;
  }

  .timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--bg-tertiary);
  }

  .timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
  }

  .timeline-item:last-child {
    padding-bottom: 0;
  }

  .timeline-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    position: absolute;
    left: -30px;
    background: var(--bg-secondary);
    border: 2px solid var(--accent-primary);
    color: var(--accent-primary);
  }

  .timeline-icon.applied {
    border-color: var(--warning);
    color: var(--warning);
  }

  .timeline-icon.viewed {
    border-color: var(--info);
    color: var(--info);
  }

  .timeline-icon.shortlisted {
    border-color: var(--accent-primary);
    color: var(--accent-primary);
  }

  .timeline-icon.interview {
    border-color: #9b59b6;
    color: #9b59b6;
  }

  .timeline-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }

  .timeline-content strong {
    font-size: 0.9rem;
  }

  .timeline-content span {
    font-size: 0.8rem;
    color: var(--text-muted);
  }

  @media (max-width: 1024px) {
    .application-detail-grid {
      grid-template-columns: 1fr;
    }

    .applicant-profile {
      position: static;
    }
  }
</style>

<?php require_once '../includes/footer.php'; ?>