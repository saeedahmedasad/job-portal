<?php
/**
 * JobNexus - Job Detail Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Job.php';
require_once __DIR__ . '/../classes/Application.php';
require_once __DIR__ . '/../classes/SeekerProfile.php';
require_once __DIR__ . '/../includes/notification-helper.php';

$jobModel = new Job();
$appModel = new Application();

// Get job ID
$jobId = intval($_GET['id'] ?? 0);

if (!$jobId) {
  redirect(BASE_URL . '/jobs/');
}

// Get job details
$job = $jobModel->findById($jobId);

if (!$job || $job['status'] !== 'active' || $job['verification_status'] !== 'verified') {
  setFlash('error', 'Job not found or no longer available');
  redirect(BASE_URL . '/jobs/');
}

// Increment view count
$jobModel->incrementViews($jobId);

// Check if user has already applied
$hasApplied = false;
$hasSaved = false;
$seekerProfile = null;
if (isLoggedIn() && hasRole(ROLE_SEEKER)) {
  $seekerModel = new SeekerProfile();
  $seekerProfile = $seekerModel->findByUserId(getCurrentUserId());
  if ($seekerProfile) {
    $hasApplied = $appModel->hasApplied($jobId, getCurrentUserId());
  }

  // Check if job is saved
  $db = Database::getInstance()->getConnection();
  $stmt = $db->prepare("SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ?");
  $stmt->execute([getCurrentUserId(), $jobId]);
  $hasSaved = $stmt->fetch() ? true : false;
}

// Handle save/unsave job
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_job'])) {
  if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect(BASE_URL . '/auth/login.php');
  }

  if (hasRole(ROLE_SEEKER)) {
    $db = Database::getInstance()->getConnection();

    if ($hasSaved) {
      // Unsave
      $stmt = $db->prepare("DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?");
      $stmt->execute([getCurrentUserId(), $jobId]);
      $hasSaved = false;
      setFlash('success', 'Job removed from saved list');
    } else {
      // Save
      $stmt = $db->prepare("INSERT INTO saved_jobs (user_id, job_id, saved_at) VALUES (?, ?, NOW())");
      $stmt->execute([getCurrentUserId(), $jobId]);
      $hasSaved = true;
      setFlash('success', 'Job saved successfully!');
    }
    redirect($_SERVER['REQUEST_URI']);
  }
}

// Handle application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
  if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect(BASE_URL . '/auth/login.php');
  }

  if (!hasRole(ROLE_SEEKER)) {
    setFlash('error', 'Only job seekers can apply for jobs');
    redirect($_SERVER['REQUEST_URI']);
  }

  if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request');
    redirect($_SERVER['REQUEST_URI']);
  }

  if ($hasApplied) {
    setFlash('error', 'You have already applied to this job');
    redirect($_SERVER['REQUEST_URI']);
  }

  $coverLetter = sanitize($_POST['cover_letter'] ?? '');

  // Handle resume upload
  $resumeFile = null;
  if (!empty($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
    $errors = validateFileUpload($_FILES['resume'], ALLOWED_RESUME_TYPES);
    if (empty($errors)) {
      $resumeFile = uploadFile($_FILES['resume'], RESUME_PATH, 'resume_');
    }
  }

  $applicationId = $appModel->create([
    'job_id' => $jobId,
    'seeker_id' => getCurrentUserId(),
    'cover_letter' => $coverLetter,
    'resume_file' => $resumeFile
  ]);

  if ($applicationId) {
    // Get seeker name for notification
    $profileModel = new SeekerProfile();
    $seekerProfile = $profileModel->findByUserId(getCurrentUserId());
    $seekerName = $seekerProfile ? $seekerProfile['first_name'] . ' ' . $seekerProfile['last_name'] : 'A candidate';

    // Notify HR about new application
    notifyNewApplication(
      $job['posted_by'],
      $seekerName,
      $job['title'],
      $applicationId
    );

    setFlash('success', 'Application submitted successfully!');
    redirect(BASE_URL . '/seeker/applications.php');
  } else {
    setFlash('error', 'Failed to submit application. Please try again.');
    redirect($_SERVER['REQUEST_URI']);
  }
}

// Get similar jobs
$similarJobs = $jobModel->getActive(['category' => $job['category_id']], 1, 3)['jobs'];
$similarJobs = array_filter($similarJobs, fn($j) => $j['id'] !== $jobId);

$pageTitle = $job['title'] . ' at ' . $job['company_name'];
include __DIR__ . '/../includes/header.php';
?>

<div class="job-detail-page">
  <div class="container">
    <div class="job-detail-layout">
      <!-- Main Content -->
      <div class="job-detail-main">
        <!-- Job Header -->
        <div class="job-detail-header">
          <div class="job-detail-company">
            <div class="company-logo">
              <?php if ($job['logo']): ?>
                <img src="<?php echo LOGO_URL . sanitize($job['logo']); ?>"
                  alt="<?php echo sanitize($job['company_name']); ?>">
              <?php else: ?>
                <span class="initials"><?php echo getInitials($job['company_name']); ?></span>
              <?php endif; ?>
            </div>
            <div class="company-info">
              <h1 class="job-title"><?php echo sanitize($job['title']); ?></h1>
              <div class="company-meta">
                <a href="<?php echo BASE_URL; ?>/companies/profile.php?id=<?php echo $job['company_id']; ?>"
                  class="company-name">
                  <?php echo sanitize($job['company_name']); ?>
                </a>
                <?php if ($job['industry']): ?>
                  <span class="meta-divider">•</span>
                  <span class="company-industry"><?php echo sanitize($job['industry']); ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="job-detail-tags">
            <span class="tag tag-<?php echo $job['location_type']; ?>">
              <i
                class="fas fa-<?php echo $job['location_type'] === 'remote' ? 'home' : ($job['location_type'] === 'hybrid' ? 'building' : 'map-marker-alt'); ?>"></i>
              <?php echo ucfirst($job['location_type']); ?>
            </span>
            <span class="tag">
              <i class="fas fa-clock"></i>
              <?php echo JOB_TYPES[$job['job_type']] ?? $job['job_type']; ?>
            </span>
            <span class="tag">
              <i class="fas fa-layer-group"></i>
              <?php echo EXPERIENCE_LEVELS[$job['experience_level']] ?? $job['experience_level']; ?>
            </span>
            <?php if ($job['location']): ?>
              <span class="tag">
                <i class="fas fa-map-marker-alt"></i>
                <?php echo sanitize($job['location']); ?>
              </span>
            <?php endif; ?>
          </div>

          <div class="job-meta-row">
            <div class="job-meta-item">
              <i class="fas fa-eye"></i>
              <span><?php echo number_format($job['views_count']); ?> views</span>
            </div>
            <div class="job-meta-item">
              <i class="fas fa-users"></i>
              <span><?php echo number_format($job['applications_count']); ?> applicants</span>
            </div>
            <div class="job-meta-item">
              <i class="fas fa-calendar"></i>
              <span>Posted <?php echo timeAgo($job['published_at'] ?? $job['created_at']); ?></span>
            </div>
            <?php if ($job['application_deadline']): ?>
              <div class="job-meta-item">
                <i class="fas fa-hourglass-half"></i>
                <span>Deadline: <?php echo formatDate($job['application_deadline']); ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Job Description -->
        <div class="job-section">
          <h2 class="section-title"><i class="fas fa-align-left"></i> Job Description</h2>
          <div class="job-content">
            <?php echo nl2br(sanitize($job['description'])); ?>
          </div>
        </div>

        <!-- Requirements -->
        <?php if ($job['requirements']): ?>
          <div class="job-section">
            <h2 class="section-title"><i class="fas fa-list-ul"></i> Requirements</h2>
            <div class="job-content">
              <?php echo nl2br(sanitize($job['requirements'])); ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Responsibilities -->
        <?php if ($job['responsibilities']): ?>
          <div class="job-section">
            <h2 class="section-title"><i class="fas fa-tasks"></i> Responsibilities</h2>
            <div class="job-content">
              <?php echo nl2br(sanitize($job['responsibilities'])); ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Benefits -->
        <?php if ($job['benefits']): ?>
          <div class="job-section">
            <h2 class="section-title"><i class="fas fa-gift"></i> Benefits</h2>
            <div class="job-content">
              <?php echo nl2br(sanitize($job['benefits'])); ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Skills -->
        <?php if ($job['skills_required']): ?>
          <div class="job-section">
            <h2 class="section-title"><i class="fas fa-code"></i> Required Skills</h2>
            <div class="skills-container">
              <?php
              $skills = is_array($job['skills_required']) ? $job['skills_required'] : json_decode($job['skills_required'], true);
              if ($skills):
                foreach ($skills as $skill):
                  ?>
                  <span class="tag tag-primary"><?php echo sanitize($skill); ?></span>
                  <?php
                endforeach;
              endif;
              ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Sidebar -->
      <aside class="job-detail-sidebar">
        <!-- Apply Card -->
        <div class="apply-card">
          <?php if ($job['show_salary'] && $job['salary_min']): ?>
            <div class="salary-display">
              <span class="salary-label">Salary</span>
              <span class="salary-value">
                <?php echo formatCurrency($job['salary_min']); ?>
                <?php if ($job['salary_max']): ?>
                  - <?php echo formatCurrency($job['salary_max']); ?>
                <?php endif; ?>
              </span>
              <span class="salary-period">/year</span>
            </div>
          <?php endif; ?>

          <?php if ($hasApplied): ?>
            <div class="applied-status">
              <i class="fas fa-check-circle"></i>
              <span>You have applied</span>
            </div>
            <a href="<?php echo BASE_URL; ?>/seeker/applications.php" class="btn btn-secondary btn-block">
              View My Applications
            </a>
          <?php elseif (isLoggedIn() && hasRole(ROLE_HR)): ?>
            <div class="alert alert-info">
              <i class="fas fa-info-circle"></i>
              <span>Employers cannot apply to jobs</span>
            </div>
          <?php else: ?>
            <button class="btn btn-primary btn-block btn-lg" data-modal-open="applyModal">
              <i class="fas fa-paper-plane"></i> Apply Now
            </button>
          <?php endif; ?>

          <?php if (isLoggedIn() && hasRole(ROLE_SEEKER)): ?>
            <form method="POST" style="margin: 0;">
              <input type="hidden" name="save_job" value="1">
              <button type="submit" class="btn <?php echo $hasSaved ? 'btn-primary' : 'btn-secondary'; ?> btn-block">
                <i class="<?php echo $hasSaved ? 'fas' : 'far'; ?> fa-bookmark"></i>
                <?php echo $hasSaved ? 'Saved' : 'Save Job'; ?>
              </button>
            </form>
          <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/auth/login.php" class="btn btn-secondary btn-block">
              <i class="far fa-bookmark"></i> Save Job
            </a>
          <?php endif; ?>

          <button class="btn btn-ghost btn-block">
            <i class="fas fa-share-alt"></i> Share
          </button>
        </div>

        <!-- Company Card -->
        <div class="company-card">
          <h3>About the Company</h3>
          <div class="company-card-header">
            <div class="company-logo-sm">
              <?php if ($job['logo']): ?>
                <img src="<?php echo LOGO_URL . sanitize($job['logo']); ?>"
                  alt="<?php echo sanitize($job['company_name']); ?>">
              <?php else: ?>
                <span class="initials"><?php echo getInitials($job['company_name']); ?></span>
              <?php endif; ?>
            </div>
            <div>
              <h4><?php echo sanitize($job['company_name']); ?></h4>
              <?php if ($job['industry']): ?>
                <span class="text-muted"><?php echo sanitize($job['industry']); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($job['website']): ?>
            <a href="<?php echo sanitize($job['website']); ?>" target="_blank" class="btn btn-outline btn-block btn-sm">
              <i class="fas fa-external-link-alt"></i> Visit Website
            </a>
          <?php endif; ?>
          <a href="<?php echo BASE_URL; ?>/companies/profile.php?id=<?php echo $job['company_id']; ?>"
            class="btn btn-ghost btn-block btn-sm">
            View All Jobs
          </a>
        </div>
      </aside>
    </div>

    <!-- Similar Jobs -->
    <?php if (!empty($similarJobs)): ?>
      <div class="similar-jobs-section">
        <h2 class="section-title">Similar Jobs</h2>
        <div class="jobs-grid">
          <?php foreach (array_slice($similarJobs, 0, 3) as $similarJob): ?>
            <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $similarJob['id']; ?>" class="job-card">
              <div class="job-card-header">
                <div class="job-card-logo">
                  <?php if ($similarJob['logo']): ?>
                    <img src="<?php echo LOGO_URL . sanitize($similarJob['logo']); ?>" alt="">
                  <?php else: ?>
                    <span class="initials"><?php echo getInitials($similarJob['company_name']); ?></span>
                  <?php endif; ?>
                </div>
                <div class="job-card-info">
                  <h3 class="job-card-title"><?php echo sanitize($similarJob['title']); ?></h3>
                  <p class="job-card-company"><?php echo sanitize($similarJob['company_name']); ?></p>
                </div>
              </div>
              <div class="job-card-meta">
                <span class="tag tag-<?php echo $similarJob['location_type']; ?>">
                  <?php echo ucfirst($similarJob['location_type']); ?>
                </span>
              </div>
              <div class="job-card-footer">
                <?php if ($similarJob['show_salary'] && $similarJob['salary_min']): ?>
                  <span class="job-card-salary"><?php echo formatCurrency($similarJob['salary_min']); ?>+</span>
                <?php else: ?>
                  <span class="job-card-salary text-muted">-</span>
                <?php endif; ?>
                <span
                  class="job-card-time"><?php echo timeAgo($similarJob['published_at'] ?? $similarJob['created_at']); ?></span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Apply Modal -->
<div class="modal-overlay" id="applyModal">
  <div class="modal-content modal-lg">
    <div class="modal-header">
      <h3 class="modal-title">Apply for <?php echo sanitize($job['title']); ?></h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
      <input type="hidden" name="apply" value="1">

      <div class="modal-body">
        <?php if (!isLoggedIn()): ?>
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
              <strong>Login Required</strong>
              <p>Please <a href="<?php echo BASE_URL; ?>/auth/login.php">sign in</a> or <a
                  href="<?php echo BASE_URL; ?>/auth/register.php">create an account</a> to apply for this job.</p>
            </div>
          </div>
        <?php else: ?>
          <div class="form-group">
            <label class="form-label">Cover Letter (Optional)</label>
            <textarea name="cover_letter" class="form-control" rows="6"
              placeholder="Tell us why you're a great fit for this role..."></textarea>
            <div class="form-hint">A good cover letter can significantly increase your chances</div>
          </div>

          <div class="form-group">
            <label class="form-label">Resume</label>
            <?php if ($seekerProfile && $seekerProfile['resume_file_path']): ?>
              <div class="current-resume">
                <i class="fas fa-file-pdf"></i>
                <span>Using your profile resume</span>
                <a href="<?php echo RESUME_URL . $seekerProfile['resume_file_path']; ?>" target="_blank"
                  class="btn btn-sm btn-ghost">View</a>
              </div>
              <p class="form-hint">Or upload a different resume:</p>
            <?php endif; ?>
            <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx">
            <div class="form-hint">PDF, DOC, DOCX up to 5MB</div>
          </div>
        <?php endif; ?>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <?php if (isLoggedIn()): ?>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> Submit Application
          </button>
        <?php else: ?>
          <a href="<?php echo BASE_URL; ?>/auth/login.php" class="btn btn-primary">Sign In to Apply</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<style>
  .job-detail-page {
    padding: 100px 0 var(--spacing-3xl);
    min-height: 100vh;
  }

  .job-detail-layout {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: var(--spacing-xl);
  }

  /* Job Header */
  .job-detail-header {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-xl);
    padding: var(--spacing-xl);
    margin-bottom: var(--spacing-xl);
  }

  .job-detail-company {
    display: flex;
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
  }

  .company-logo {
    width: 80px;
    height: 80px;
    border-radius: var(--radius-lg);
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
  }

  .company-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .company-logo .initials {
    font-family: var(--font-heading);
    font-size: 2rem;
    color: var(--accent-primary);
  }

  .job-title {
    font-size: 1.75rem;
    margin-bottom: var(--spacing-sm);
  }

  .company-meta {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    color: var(--text-secondary);
  }

  .company-name {
    color: var(--accent-primary);
    font-weight: 500;
  }

  .meta-divider {
    color: var(--text-muted);
  }

  .job-detail-tags {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-sm);
    margin-bottom: var(--spacing-lg);
  }

  .job-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-lg);
    padding-top: var(--spacing-lg);
    border-top: 1px solid var(--border-color);
  }

  .job-meta-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    color: var(--text-secondary);
    font-size: 0.9rem;
  }

  .job-meta-item i {
    color: var(--accent-primary);
  }

  /* Job Sections */
  .job-section {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: var(--spacing-xl);
    margin-bottom: var(--spacing-lg);
  }

  .job-section .section-title {
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--border-color);
  }

  .job-section .section-title i {
    color: var(--accent-primary);
  }

  .job-content {
    color: var(--text-secondary);
    line-height: 1.8;
  }

  /* Sidebar */
  .job-detail-sidebar {
    position: sticky;
    top: 90px;
    height: fit-content;
  }

  .apply-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
  }

  .salary-display {
    text-align: center;
    padding-bottom: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
    border-bottom: 1px solid var(--border-color);
  }

  .salary-label {
    display: block;
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: var(--spacing-xs);
  }

  .salary-value {
    font-family: var(--font-heading);
    font-size: 2rem;
    color: var(--accent-primary);
  }

  .salary-period {
    font-size: 0.9rem;
    color: var(--text-secondary);
  }

  .applied-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-md);
    background: rgba(0, 230, 118, 0.1);
    border-radius: var(--radius-md);
    color: var(--accent-primary);
    font-weight: 500;
    margin-bottom: var(--spacing-md);
  }

  .company-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
  }

  .company-card h3 {
    font-size: 1rem;
    margin-bottom: var(--spacing-lg);
    color: var(--text-secondary);
  }

  .company-card-header {
    display: flex;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
  }

  .company-logo-sm {
    width: 50px;
    height: 50px;
    border-radius: var(--radius-md);
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }

  .company-logo-sm img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .company-logo-sm .initials {
    font-size: 1rem;
    color: var(--accent-primary);
  }

  .company-card h4 {
    font-size: 1rem;
    margin-bottom: 0.25rem;
  }

  /* Similar Jobs */
  .similar-jobs-section {
    margin-top: var(--spacing-3xl);
    padding-top: var(--spacing-3xl);
    border-top: 1px solid var(--border-color);
  }

  .similar-jobs-section .section-title {
    margin-bottom: var(--spacing-xl);
  }

  /* Modal Large */
  .modal-lg {
    max-width: 600px;
  }

  .current-resume {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    background: var(--bg-tertiary);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-md);
  }

  .current-resume i {
    font-size: 1.5rem;
    color: var(--error);
  }

  .current-resume span {
    flex: 1;
  }

  @media (max-width: 1024px) {
    .job-detail-layout {
      grid-template-columns: 1fr;
    }

    .job-detail-sidebar {
      position: static;
    }
  }

  @media (max-width: 640px) {
    .job-detail-company {
      flex-direction: column;
    }

    .job-title {
      font-size: 1.5rem;
    }
  }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>