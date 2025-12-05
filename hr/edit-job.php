<?php
/**
 * JobNexus - Edit Job
 * HR/Recruiter job editing form
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Job.php';
require_once '../classes/Company.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_HR) {
  header('Location: ' . BASE_URL . '/auth/login.php?redirect=hr/jobs');
  exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$jobModel = new Job();
$companyModel = new Company();

$hr = $userModel->findById($_SESSION['user_id']);

// Get HR's company
$stmt = $db->prepare("SELECT * FROM companies WHERE hr_user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
  header('Location: ' . BASE_URL . '/hr/create-company.php');
  exit;
}

// Get job ID from URL
$jobId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$jobId) {
  header('Location: ' . BASE_URL . '/hr/jobs.php');
  exit;
}

// Get job details - verify it belongs to this company
$stmt = $db->prepare("SELECT * FROM jobs WHERE id = ? AND company_id = ?");
$stmt->execute([$jobId, $company['id']]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
  header('Location: ' . BASE_URL . '/hr/jobs.php?error=notfound');
  exit;
}

$message = '';
$messageType = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate inputs
  $title = trim($_POST['title'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $requirements = trim($_POST['requirements'] ?? '');
  $responsibilities = trim($_POST['responsibilities'] ?? '');
  $benefits = trim($_POST['benefits'] ?? '');
  $location = trim($_POST['location'] ?? '');
  $jobType = $_POST['job_type'] ?? '';
  $locationType = $_POST['location_type'] ?? 'onsite';
  $experienceLevel = $_POST['experience_level'] ?? '';
  $salaryMin = !empty($_POST['salary_min']) ? (float) $_POST['salary_min'] : null;
  $salaryMax = !empty($_POST['salary_max']) ? (float) $_POST['salary_max'] : null;
  $skills = trim($_POST['skills'] ?? '');
  $deadline = $_POST['deadline'] ?? '';
  $vacancies = (int) ($_POST['vacancies'] ?? 1);
  $status = $_POST['status'] ?? 'active';

  // Validation
  if (empty($title)) {
    $errors[] = 'Job title is required.';
  }
  if (empty($description)) {
    $errors[] = 'Job description is required.';
  }
  if (empty($location)) {
    $errors[] = 'Location is required.';
  }
  if (empty($jobType)) {
    $errors[] = 'Job type is required.';
  }
  if (!empty($deadline) && strtotime($deadline) < strtotime('today')) {
    $errors[] = 'Deadline must be a future date.';
  }
  if ($salaryMin && $salaryMax && $salaryMin > $salaryMax) {
    $errors[] = 'Minimum salary cannot be greater than maximum salary.';
  }

  if (empty($errors)) {
    // Prepare skills as JSON
    $skillsArray = [];
    if ($skills) {
      $skillsArray = array_map('trim', explode(',', $skills));
    }

    // Update job
    $sql = "UPDATE jobs SET 
              title = ?,
              description = ?,
              requirements = ?,
              responsibilities = ?,
              benefits = ?,
              location = ?,
              job_type = ?,
              location_type = ?,
              experience_level = ?,
              salary_min = ?,
              salary_max = ?,
              skills_required = ?,
              application_deadline = ?,
              positions_available = ?,
              status = ?,
              updated_at = NOW()
            WHERE id = ? AND company_id = ?";

    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
      $title,
      $description,
      $requirements,
      $responsibilities,
      $benefits,
      $location,
      $jobType,
      $locationType,
      $experienceLevel ?: null,
      $salaryMin,
      $salaryMax,
      json_encode($skillsArray),
      $deadline ?: null,
      $vacancies,
      $status,
      $jobId,
      $company['id']
    ]);

    if ($result) {
      header('Location: ' . BASE_URL . '/hr/jobs.php?updated=1');
      exit;
    } else {
      $message = 'Failed to update job. Please try again.';
      $messageType = 'error';
    }
  } else {
    $message = implode('<br>', $errors);
    $messageType = 'error';
  }
}

// Prepare form values (from POST or existing job)
$formData = [
  'title' => $_POST['title'] ?? $job['title'] ?? '',
  'description' => $_POST['description'] ?? $job['description'] ?? '',
  'requirements' => $_POST['requirements'] ?? $job['requirements'] ?? '',
  'responsibilities' => $_POST['responsibilities'] ?? $job['responsibilities'] ?? '',
  'benefits' => $_POST['benefits'] ?? $job['benefits'] ?? '',
  'location' => $_POST['location'] ?? $job['location'] ?? '',
  'job_type' => $_POST['job_type'] ?? $job['job_type'] ?? '',
  'location_type' => $_POST['location_type'] ?? $job['location_type'] ?? 'onsite',
  'experience_level' => $_POST['experience_level'] ?? $job['experience_level'] ?? '',
  'salary_min' => $_POST['salary_min'] ?? $job['salary_min'] ?? '',
  'salary_max' => $_POST['salary_max'] ?? $job['salary_max'] ?? '',
  'skills' => $_POST['skills'] ?? (is_array(json_decode($job['skills_required'] ?? '', true)) ? implode(', ', json_decode($job['skills_required'], true)) : ''),
  'deadline' => $_POST['deadline'] ?? (($job['application_deadline'] ?? null) ? date('Y-m-d', strtotime($job['application_deadline'])) : ''),
  'vacancies' => $_POST['vacancies'] ?? $job['positions_available'] ?? 1,
  'status' => $_POST['status'] ?? $job['status'] ?? 'draft'
];

$pageTitle = 'Edit Job - ' . $job['title'];
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
      <a href="<?php echo BASE_URL; ?>/hr/jobs.php" class="nav-item active">
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
        <a href="<?php echo BASE_URL; ?>/hr/jobs.php" class="back-link"
          style="color: var(--text-secondary); margin-bottom: 0.5rem; display: inline-block;">
          <i class="fas fa-arrow-left"></i> Back to Jobs
        </a>
        <h1><i class="fas fa-edit"></i> Edit Job</h1>
        <p>Update your job listing details</p>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?php echo $messageType; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="job-form">
      <div class="form-layout">
        <!-- Main Form -->
        <div class="form-main">
          <!-- Basic Info Section -->
          <div class="form-section">
            <div class="section-title">
              <i class="fas fa-info-circle"></i>
              <h2>Basic Information</h2>
            </div>

            <div class="form-group">
              <label for="title">Job Title <span class="required">*</span></label>
              <input type="text" id="title" name="title" class="form-control" required
                value="<?php echo htmlspecialchars($formData['title']); ?>"
                placeholder="e.g., Senior Software Engineer">
              <small class="form-hint">Be specific - "Senior React Developer" is better than "Developer"</small>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="job_type">Job Type <span class="required">*</span></label>
                <select id="job_type" name="job_type" class="form-control" required>
                  <option value="">Select Type</option>
                  <option value="full-time" <?php echo $formData['job_type'] === 'full-time' ? 'selected' : ''; ?>>Full
                    Time</option>
                  <option value="part-time" <?php echo $formData['job_type'] === 'part-time' ? 'selected' : ''; ?>>Part
                    Time</option>
                  <option value="contract" <?php echo $formData['job_type'] === 'contract' ? 'selected' : ''; ?>>Contract
                  </option>
                  <option value="internship" <?php echo $formData['job_type'] === 'internship' ? 'selected' : ''; ?>>
                    Internship</option>
                  <option value="freelance" <?php echo $formData['job_type'] === 'freelance' ? 'selected' : ''; ?>>
                    Freelance</option>
                </select>
              </div>
              <div class="form-group">
                <label for="experience_level">Experience Level</label>
                <select id="experience_level" name="experience_level" class="form-control">
                  <option value="">Select Level</option>
                  <option value="entry" <?php echo $formData['experience_level'] === 'entry' ? 'selected' : ''; ?>>Entry
                    Level</option>
                  <option value="mid" <?php echo $formData['experience_level'] === 'mid' ? 'selected' : ''; ?>>Mid Level
                  </option>
                  <option value="senior" <?php echo $formData['experience_level'] === 'senior' ? 'selected' : ''; ?>>
                    Senior Level</option>
                  <option value="lead" <?php echo $formData['experience_level'] === 'lead' ? 'selected' : ''; ?>>Lead /
                    Manager</option>
                  <option value="executive" <?php echo $formData['experience_level'] === 'executive' ? 'selected' : ''; ?>>Executive</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="location">Location <span class="required">*</span></label>
                <input type="text" id="location" name="location" class="form-control" required
                  value="<?php echo htmlspecialchars($formData['location']); ?>" placeholder="e.g., San Francisco, CA">
              </div>
              <div class="form-group">
                <label for="location_type">Location Type</label>
                <select id="location_type" name="location_type" class="form-control">
                  <option value="onsite" <?php echo $formData['location_type'] === 'onsite' ? 'selected' : ''; ?>>On-site
                  </option>
                  <option value="remote" <?php echo $formData['location_type'] === 'remote' ? 'selected' : ''; ?>>Remote
                  </option>
                  <option value="hybrid" <?php echo $formData['location_type'] === 'hybrid' ? 'selected' : ''; ?>>Hybrid
                  </option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label for="vacancies">Number of Vacancies</label>
              <input type="number" id="vacancies" name="vacancies" class="form-control"
                value="<?php echo htmlspecialchars($formData['vacancies']); ?>" min="1" max="100">
            </div>
          </div>

          <!-- Description Section -->
          <div class="form-section">
            <div class="section-title">
              <i class="fas fa-align-left"></i>
              <h2>Job Details</h2>
            </div>

            <div class="form-group">
              <label for="description">Job Description <span class="required">*</span></label>
              <textarea id="description" name="description" class="form-control" rows="8" required
                placeholder="Describe the role, responsibilities, and what a typical day looks like..."><?php echo htmlspecialchars($formData['description']); ?></textarea>
              <small class="form-hint">Include key responsibilities, team structure, and impact of the role</small>
            </div>

            <!-- <div class="form-group">
              <label for="responsibilities">Key Responsibilities</label>
              <textarea id="responsibilities" name="responsibilities" class="form-control" rows="6"
                placeholder="List the main responsibilities and duties..."><?php echo htmlspecialchars($formData['responsibilities']); ?></textarea>
            </div> -->

            <div class="form-group">
              <label for="requirements">Requirements</label>
              <textarea id="requirements" name="requirements" class="form-control" rows="6"
                placeholder="List the qualifications, skills, and experience required..."><?php echo htmlspecialchars($formData['requirements']); ?></textarea>
              <small class="form-hint">Be clear about must-haves vs nice-to-haves</small>
            </div>

            <div class="form-group">
              <label for="benefits">Benefits & Perks</label>
              <textarea id="benefits" name="benefits" class="form-control" rows="4"
                placeholder="Health insurance, 401k, remote work options, learning budget..."><?php echo htmlspecialchars($formData['benefits']); ?></textarea>
            </div>

            <div class="form-group">
              <label for="skills">Required Skills</label>
              <input type="text" id="skills" name="skills" class="form-control"
                value="<?php echo htmlspecialchars($formData['skills']); ?>"
                placeholder="JavaScript, React, Node.js, Python (comma-separated)">
              <small class="form-hint">Skills help candidates find your job</small>
            </div>
          </div>

          <!-- Salary Section -->
          <div class="form-section">
            <div class="section-title">
              <i class="fas fa-dollar-sign"></i>
              <h2>Compensation</h2>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="salary_min">Minimum Salary ($/year)</label>
                <input type="number" id="salary_min" name="salary_min" class="form-control"
                  value="<?php echo htmlspecialchars($formData['salary_min']); ?>" placeholder="50000" min="0">
              </div>
              <div class="form-group">
                <label for="salary_max">Maximum Salary ($/year)</label>
                <input type="number" id="salary_max" name="salary_max" class="form-control"
                  value="<?php echo htmlspecialchars($formData['salary_max']); ?>" placeholder="80000" min="0">
              </div>
            </div>
            <small class="form-hint">Salary information increases application rates by up to 30%</small>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="form-sidebar">
          <!-- Company Card -->
          <?php if ($company): ?>
            <div class="sidebar-card company-card">
              <h3>Posting As</h3>
              <div class="company-info">
                <div class="company-logo">
                  <?php if ($company['logo']): ?>
                    <img src="<?php echo BASE_URL; ?>/uploads/logos/<?php echo $company['logo']; ?>" alt="">
                  <?php else: ?>
                    <i class="fas fa-building"></i>
                  <?php endif; ?>
                </div>
                <div class="company-details">
                  <h4><?php echo htmlspecialchars($company['company_name']); ?></h4>
                  <?php if ($company['verification_status'] === 'verified'): ?>
                    <span class="verified-badge">
                      <i class="fas fa-check-circle"></i> Verified
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <!-- Job Stats Card -->
          <div class="sidebar-card">
            <h3>Job Statistics</h3>
            <div class="job-stats">
              <div class="stat-item">
                <span class="stat-label">Views</span>
                <span class="stat-value"><?php echo number_format($job['views_count']); ?></span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Applications</span>
                <span class="stat-value"><?php echo number_format($job['applications_count']); ?></span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Posted</span>
                <span class="stat-value"><?php echo date('M d, Y', strtotime($job['created_at'])); ?></span>
              </div>
            </div>
          </div>

          <!-- Settings Card -->
          <div class="sidebar-card">
            <h3>Job Settings</h3>

            <div class="form-group">
              <label for="deadline">Application Deadline</label>
              <input type="date" id="deadline" name="deadline" class="form-control"
                value="<?php echo htmlspecialchars($formData['deadline']); ?>">
            </div>

            <div class="form-group">
              <label for="status">Status</label>
              <select id="status" name="status" class="form-control">
                <option value="active" <?php echo $formData['status'] === 'active' ? 'selected' : ''; ?>>Active (visible
                  to all)</option>
                <option value="draft" <?php echo $formData['status'] === 'draft' ? 'selected' : ''; ?>>Draft (save for
                  later)</option>
                <option value="paused" <?php echo $formData['status'] === 'paused' ? 'selected' : ''; ?>>Paused
                  (temporarily hidden)</option>
                <option value="closed" <?php echo $formData['status'] === 'closed' ? 'selected' : ''; ?>>Closed (no longer
                  accepting)</option>
              </select>
            </div>
          </div>

          <!-- Actions Card -->
          <div class="sidebar-card actions-card">
            <button type="submit" class="btn btn-primary btn-block btn-lg">
              <i class="fas fa-save"></i> Save Changes
            </button>
            <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $job['id']; ?>"
              class="btn btn-outline-secondary btn-block" target="_blank">
              <i class="fas fa-eye"></i> View Job Listing
            </a>
            <a href="<?php echo BASE_URL; ?>/hr/jobs.php" class="btn btn-text btn-block">
              Cancel
            </a>
          </div>
        </div>
      </div>
    </form>
  </main>
</div>

<style>
  /* Edit Job Page Styles - inherits from post-job.php */
  .back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    text-decoration: none;
    margin-bottom: 1rem;
    transition: color 0.3s ease;
  }

  .back-link:hover {
    color: var(--primary-color);
  }

  /* Form Layout */
  .form-layout {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 2rem;
    align-items: start;
  }

  .form-main {
    display: flex;
    flex-direction: column;
    gap: 2rem;
  }

  /* Form Section */
  .form-section {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 2rem;
    border: 1px solid var(--border-color);
  }

  .section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
  }

  .section-title i {
    color: var(--primary-color);
    font-size: 1.25rem;
  }

  .section-title h2 {
    font-size: 1.25rem;
  }

  /* Sidebar */
  .form-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    position: sticky;
    top: 90px;
  }

  .sidebar-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--border-color);
  }

  .sidebar-card h3 {
    font-size: 1rem;
    margin-bottom: 1rem;
    color: var(--text-light);
  }

  /* Company Card */
  .company-card .company-info {
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  .company-card .company-logo {
    width: 50px;
    height: 50px;
    background: var(--bg-dark);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }

  .company-card .company-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }

  .company-card .company-logo i {
    font-size: 1.25rem;
    color: var(--text-muted);
  }

  .company-card .company-details h4 {
    font-size: 1rem;
    margin-bottom: 0.25rem;
  }

  .verified-badge {
    font-size: 0.75rem;
    color: var(--primary-color);
  }

  /* Job Stats */
  .job-stats {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .stat-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border-color);
  }

  .stat-item:last-child {
    border-bottom: none;
  }

  .stat-label {
    color: var(--text-muted);
    font-size: 0.9rem;
  }

  .stat-value {
    font-weight: 600;
    color: var(--text-light);
  }

  /* Actions Card */
  .actions-card {
    background: linear-gradient(135deg, rgba(0, 230, 118, 0.05), rgba(156, 39, 176, 0.05));
    border-color: rgba(0, 230, 118, 0.2);
  }

  .btn-block {
    width: 100%;
    margin-bottom: 0.75rem;
  }

  .btn-block:last-child {
    margin-bottom: 0;
  }

  .btn-lg {
    padding: 1rem 1.5rem;
    font-size: 1rem;
  }

  .btn-outline-secondary {
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-light);
    padding: 0.75rem 1rem;
    border-radius: 8px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
  }

  .btn-outline-secondary:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
  }

  .btn-text {
    background: transparent;
    border: none;
    color: var(--text-muted);
    padding: 0.75rem 1rem;
    text-decoration: none;
    display: block;
    text-align: center;
    transition: color 0.3s ease;
  }

  .btn-text:hover {
    color: var(--text-light);
  }

  /* Form Elements */
  .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
  }

  .form-group {
    margin-bottom: 1.25rem;
  }

  .form-group:last-child {
    margin-bottom: 0;
  }

  .form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-light);
  }

  .required {
    color: var(--danger);
  }

  .form-control {
    width: 100%;
    padding: 0.875rem 1rem;
    background: var(--bg-dark);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    color: var(--text-light);
    font-size: 1rem;
    transition: all 0.3s ease;
  }

  .form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(0, 230, 118, 0.1);
  }

  .form-control::placeholder {
    color: var(--text-muted);
  }

  textarea.form-control {
    resize: vertical;
    min-height: 100px;
  }

  .form-hint {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-muted);
  }

  /* Alert */
  .alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
  }

  .alert i {
    font-size: 1.25rem;
    margin-top: 0.1rem;
  }

  .alert-error {
    background: rgba(244, 67, 54, 0.1);
    border: 1px solid rgba(244, 67, 54, 0.3);
    color: #F44336;
  }

  .alert-success {
    background: rgba(76, 175, 80, 0.1);
    border: 1px solid rgba(76, 175, 80, 0.3);
    color: #4CAF50;
  }

  /* Responsive */
  @media (max-width: 992px) {
    .form-layout {
      grid-template-columns: 1fr;
    }

    .form-sidebar {
      position: static;
      order: -1;
    }
  }

  @media (max-width: 768px) {
    .form-row {
      grid-template-columns: 1fr;
    }

    .form-section {
      padding: 1.5rem;
    }
  }
</style>

<?php require_once '../includes/footer.php'; ?>