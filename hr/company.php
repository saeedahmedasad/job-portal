<?php
/**
 * JobNexus - HR Company Management
 * Manage company profile and settings
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Company.php';
require_once '../classes/User.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_HR) {
  header('Location: ../auth/login.php');
  exit;
}

$db = Database::getInstance()->getConnection();
$companyModel = new Company();

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Get user's company (companies.hr_user_id references users.id)
$stmt = $db->prepare("SELECT c.* FROM companies c WHERE c.hr_user_id = ?");
$stmt->execute([$userId]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
  // User doesn't have a company yet, redirect to create one
  header('Location: create-company.php');
  exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'update_profile') {
    // Update basic info
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $companySize = trim($_POST['company_size'] ?? '');
    $foundedYear = trim($_POST['founded_year'] ?? '');
    $headquarters = trim($_POST['headquarters'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($name)) {
      $message = 'Company name is required.';
      $messageType = 'error';
    } else {
      $stmt = $db->prepare("
                UPDATE companies SET 
                    company_name = ?, description = ?, industry = ?, company_size = ?, 
                    founded_year = ?, headquarters = ?, website = ?, email = ?, phone = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

      if (
        $stmt->execute([
          $name,
          $description,
          $industry,
          $companySize,
          $foundedYear ?: null,
          $headquarters,
          $website,
          $email,
          $phone,
          $company['id']
        ])
      ) {
        $message = 'Company profile updated successfully!';
        $messageType = 'success';

        // Refresh company data
        $stmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
        $stmt->execute([$company['id']]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
      } else {
        $message = 'Error updating company profile.';
        $messageType = 'error';
      }
    }
  }

  if ($action === 'update_social') {
    $linkedin = trim($_POST['linkedin'] ?? '');
    $twitter = trim($_POST['twitter'] ?? '');

    $stmt = $db->prepare("
            UPDATE companies SET 
                social_linkedin = ?, social_twitter = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

    if ($stmt->execute([$linkedin, $twitter, $company['id']])) {
      $message = 'Social links updated successfully!';
      $messageType = 'success';

      // Refresh company data
      $stmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
      $stmt->execute([$company['id']]);
      $company = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
      $message = 'Error updating social links.';
      $messageType = 'error';
    }
  }

  if ($action === 'upload_logo') {
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
      $file = $_FILES['logo'];
      $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      $maxSize = 5 * 1024 * 1024; // 5MB

      if (!in_array($file['type'], $allowedTypes)) {
        $message = 'Invalid file type. Please upload a JPG, PNG, GIF, or WebP image.';
        $messageType = 'error';
      } elseif ($file['size'] > $maxSize) {
        $message = 'File is too large. Maximum size is 5MB.';
        $messageType = 'error';
      } else {
        $uploadDir = '../uploads/logos/';
        if (!is_dir($uploadDir)) {
          mkdir($uploadDir, 0755, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'company_' . $company['id'] . '_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
          // Delete old logo if exists
          if ($company['logo'] && file_exists($uploadDir . $company['logo'])) {
            unlink($uploadDir . $company['logo']);
          }

          // Update database
          $stmt = $db->prepare("UPDATE companies SET logo = ? WHERE id = ?");
          $stmt->execute([$filename, $company['id']]);

          $company['logo'] = $filename;
          $message = 'Logo uploaded successfully!';
          $messageType = 'success';
        } else {
          $message = 'Error uploading file.';
          $messageType = 'error';
        }
      }
    } else {
      $message = 'Please select a file to upload.';
      $messageType = 'error';
    }
  }
}

// Industry options
$industries = [
  'Technology',
  'Healthcare',
  'Finance',
  'Education',
  'Manufacturing',
  'Retail',
  'Real Estate',
  'Transportation',
  'Entertainment',
  'Hospitality',
  'Energy',
  'Agriculture',
  'Construction',
  'Legal',
  'Marketing',
  'Consulting',
  'Non-profit',
  'Government',
  'Telecommunications',
  'Other'
];

// Company size options
$companySizes = [
  '1-10',
  '11-50',
  '51-200',
  '201-500',
  '501-1000',
  '1001-5000',
  '5000+'
];

$pageTitle = "Company Settings - JobNexus";
include '../includes/header.php';
?>

<div class="dashboard-container">
  <!-- Sidebar -->
  <aside class="dashboard-sidebar">
    <div class="sidebar-header">
      <div class="hr-avatar">
        <?php echo strtoupper(substr($company['company_name'], 0, 2)); ?>
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
      <a href="<?php echo BASE_URL; ?>/hr/company.php" class="nav-item active">
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
        <h1><i class="fas fa-building"></i> Company Settings</h1>
        <p>Manage your company profile and branding</p>
      </div>
      <div class="header-right">
        <a href="../companies/profile.php?id=<?php echo $company['id']; ?>" target="_blank" class="btn btn-outline">
          <i class="fas fa-external-link-alt"></i> View Public Profile
        </a>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?php echo $messageType; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <!-- Verification Status -->
    <div class="verification-status <?php echo ($company['verification_status'] ?? 'pending') === 'verified' ? 'verified' : 'pending'; ?>">
      <div class="status-icon">
        <i class="fas fa-<?php echo ($company['verification_status'] ?? 'pending') === 'verified' ? 'check-circle' : 'clock'; ?>"></i>
      </div>
      <div class="status-info">
        <h3>
          <?php echo ($company['verification_status'] ?? 'pending') === 'verified' ? 'Verified Company' : 'Verification Pending'; ?>
        </h3>
        <p>
          <?php echo ($company['verification_status'] ?? 'pending') === 'verified'
            ? 'Your company profile is verified and visible to job seekers.'
            : 'Your company profile is under review. You can still post jobs while waiting.'; ?>
        </p>
      </div>
    </div>

    <div class="settings-grid">
      <!-- Logo Section -->
      <div class="glass-card settings-section">
        <h2><i class="fas fa-image"></i> Company Logo</h2>

        <div class="logo-upload-section">
          <div class="current-logo">
            <?php if ($company['logo']): ?>
              <img src="../uploads/logos/<?php echo htmlspecialchars($company['logo']); ?>"
                alt="<?php echo htmlspecialchars($company['company_name']); ?>">
            <?php else: ?>
              <div class="placeholder-logo">
                <?php echo strtoupper(substr($company['company_name'], 0, 2)); ?>
              </div>
            <?php endif; ?>
          </div>

          <form method="POST" enctype="multipart/form-data" class="logo-form">
            <input type="hidden" name="action" value="upload_logo">
            <div class="upload-area" id="logoDropArea">
              <i class="fas fa-cloud-upload-alt"></i>
              <p>Drag & drop or click to upload</p>
              <span>JPG, PNG, GIF or WebP (max 5MB)</span>
              <input type="file" name="logo" id="logoInput" accept="image/*">
            </div>
            <button type="submit" class="btn btn-primary btn-block">
              <i class="fas fa-upload"></i> Upload Logo
            </button>
          </form>
        </div>
      </div>

      <!-- Basic Info Section -->
      <div class="glass-card settings-section">
        <h2><i class="fas fa-building"></i> Company Information</h2>

        <form method="POST" class="settings-form">
          <input type="hidden" name="action" value="update_profile">

          <div class="form-row">
            <div class="form-group">
              <label for="name">Company Name *</label>
              <input type="text" id="name" name="name" required
                value="<?php echo htmlspecialchars($company['company_name']); ?>">
            </div>
            <div class="form-group">
              <label for="industry">Industry</label>
              <select id="industry" name="industry">
                <option value="">Select Industry</option>
                <?php foreach ($industries as $ind): ?>
                  <option value="<?php echo $ind; ?>" <?php echo $company['industry'] === $ind ? 'selected' : ''; ?>>
                    <?php echo $ind; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="description">About Company</label>
            <textarea id="description" name="description" rows="5"
              placeholder="Tell job seekers about your company, culture, and what makes you unique..."><?php echo htmlspecialchars($company['description'] ?? ''); ?></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="company_size">Company Size</label>
              <select id="company_size" name="company_size">
                <option value="">Select Size</option>
                <?php foreach ($companySizes as $size): ?>
                  <option value="<?php echo $size; ?>" <?php echo $company['company_size'] === $size ? 'selected' : ''; ?>>
                    <?php echo $size; ?> employees
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="founded_year">Founded Year</label>
              <input type="number" id="founded_year" name="founded_year" min="1800" max="<?php echo date('Y'); ?>"
                value="<?php echo htmlspecialchars($company['founded_year'] ?? ''); ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="headquarters">Headquarters Location</label>
            <input type="text" id="headquarters" name="headquarters" placeholder="City, Country"
              value="<?php echo htmlspecialchars($company['headquarters'] ?? ''); ?>">
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="website">Website</label>
              <input type="url" id="website" name="website" placeholder="https://www.example.com"
                value="<?php echo htmlspecialchars($company['website'] ?? ''); ?>">
            </div>
            <div class="form-group">
              <label for="email">Contact Email</label>
              <input type="email" id="email" name="email" placeholder="contact@example.com"
                value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" placeholder="+1 (555) 000-0000"
              value="<?php echo htmlspecialchars($company['phone'] ?? ''); ?>">
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Changes
          </button>
        </form>
      </div>

      <!-- Social Links Section -->
      <div class="glass-card settings-section">
        <h2><i class="fas fa-share-alt"></i> Social Media</h2>

        <form method="POST" class="settings-form">
          <input type="hidden" name="action" value="update_social">

          <div class="form-group social-input">
            <label for="linkedin">
              <i class="fab fa-linkedin"></i> LinkedIn
            </label>
            <input type="url" id="linkedin" name="linkedin" placeholder="https://www.linkedin.com/company/..."
              value="<?php echo htmlspecialchars($company['social_linkedin'] ?? ''); ?>">
          </div>

          <div class="form-group social-input">
            <label for="twitter">
              <i class="fab fa-twitter"></i> Twitter
            </label>
            <input type="url" id="twitter" name="twitter" placeholder="https://twitter.com/..."
              value="<?php echo htmlspecialchars($company['social_twitter'] ?? ''); ?>">
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Social Links
          </button>
        </form>
      </div>

      <!-- Stats Section -->
      <div class="glass-card settings-section">
        <h2><i class="fas fa-chart-bar"></i> Company Statistics</h2>

        <?php
        // Get stats
        $stmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE company_id = ? AND status = 'active'");
        $stmt->execute([$company['id']]);
        $activeJobs = $stmt->fetchColumn();

        $stmt = $db->prepare("
                    SELECT COUNT(*) FROM applications a
                    JOIN jobs j ON a.job_id = j.id
                    WHERE j.company_id = ?
                ");
        $stmt->execute([$company['id']]);
        $totalApplications = $stmt->fetchColumn();

        $stmt = $db->prepare("
                    SELECT COUNT(*) FROM applications a
                    JOIN jobs j ON a.job_id = j.id
                    WHERE j.company_id = ? AND a.status = 'hired'
                ");
        $stmt->execute([$company['id']]);
        $hires = $stmt->fetchColumn();
        ?>

        <div class="stats-grid-mini">
          <div class="stat-item">
            <span class="stat-value"><?php echo $activeJobs; ?></span>
            <span class="stat-label">Active Jobs</span>
          </div>
          <div class="stat-item">
            <span class="stat-value"><?php echo $totalApplications; ?></span>
            <span class="stat-label">Applications</span>
          </div>
          <div class="stat-item">
            <span class="stat-value"><?php echo $hires; ?></span>
            <span class="stat-label">Hires</span>
          </div>
        </div>

        <div class="member-since">
          <i class="fas fa-calendar-alt"></i>
          Member since <?php echo date('F Y', strtotime($company['created_at'])); ?>
        </div>
      </div>
    </div>
  </main>
</div>

<style>
  .verification-status {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.5rem;
    border-radius: 0.75rem;
    margin-bottom: 2rem;
  }

  .verification-status.verified {
    background: rgba(0, 230, 118, 0.1);
    border: 1px solid rgba(0, 230, 118, 0.3);
  }

  .verification-status.pending {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid rgba(255, 193, 7, 0.3);
  }

  .status-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
  }

  .verified .status-icon {
    background: rgba(0, 230, 118, 0.2);
    color: var(--primary-color);
  }

  .pending .status-icon {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
  }

  .status-info h3 {
    margin: 0 0 0.25rem;
    font-size: 1.1rem;
    color: var(--text-primary);
  }

  .status-info p {
    margin: 0;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
  }

  .settings-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 2rem;
  }

  .settings-section h2 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }

  .settings-section h2 i {
    color: var(--primary-color);
  }

  /* Logo Upload */
  .logo-upload-section {
    display: flex;
    gap: 2rem;
    align-items: flex-start;
  }

  .current-logo {
    width: 120px;
    height: 120px;
    border-radius: 1rem;
    overflow: hidden;
    flex-shrink: 0;
  }

  .current-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .placeholder-logo {
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-color);
  }

  .logo-form {
    flex: 1;
  }

  .upload-area {
    border: 2px dashed rgba(255, 255, 255, 0.2);
    border-radius: 0.75rem;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 1rem;
    position: relative;
  }

  .upload-area:hover {
    border-color: var(--primary-color);
    background: rgba(0, 230, 118, 0.05);
  }

  .upload-area i {
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.3);
    margin-bottom: 0.75rem;
    display: block;
  }

  .upload-area p {
    color: rgba(255, 255, 255, 0.7);
    margin: 0 0 0.25rem;
  }

  .upload-area span {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
  }

  .upload-area input {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
  }

  /* Form Styles */
  .settings-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
  }

  .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
  }

  .form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
  }

  .form-group input,
  .form-group select,
  .form-group textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    color: var(--text-primary);
    font-size: 0.9rem;
    transition: border-color 0.3s ease;
  }

  .form-group input:focus,
  .form-group select:focus,
  .form-group textarea:focus {
    border-color: var(--primary-color);
    outline: none;
  }

  .form-group textarea {
    resize: vertical;
    min-height: 100px;
  }

  .social-input label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .social-input label i {
    font-size: 1.25rem;
  }

  .social-input label .fa-linkedin {
    color: #0077b5;
  }

  .social-input label .fa-twitter {
    color: #1da1f2;
  }

  .social-input label .fa-facebook {
    color: #1877f2;
  }

  .social-input label .fa-instagram {
    color: #e4405f;
  }

  /* Stats */
  .stats-grid-mini {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
  }

  .stat-item {
    text-align: center;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 0.5rem;
  }

  .stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color);
  }

  .stat-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
  }

  .member-since {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.6);
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
  }

  .member-since i {
    color: var(--primary-color);
  }

  /* Alert */
  .alert {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-radius: 0.5rem;
    margin-bottom: 2rem;
  }

  .alert-success {
    background: rgba(0, 230, 118, 0.1);
    border: 1px solid rgba(0, 230, 118, 0.3);
    color: var(--primary-color);
  }

  .alert-error {
    background: rgba(244, 67, 54, 0.1);
    border: 1px solid rgba(244, 67, 54, 0.3);
    color: #f44336;
  }

  /* Responsive */
  @media (max-width: 1024px) {
    .settings-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 768px) {
    .logo-upload-section {
      flex-direction: column;
      align-items: center;
    }

    .form-row {
      grid-template-columns: 1fr;
    }

    .stats-grid-mini {
      grid-template-columns: 1fr;
    }
  }
</style>

<script>
  // Drag and drop for logo
  const dropArea = document.getElementById('logoDropArea');
  const fileInput = document.getElementById('logoInput');

  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropArea.addEventListener(eventName, preventDefaults, false);
  });

  function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  }

  ['dragenter', 'dragover'].forEach(eventName => {
    dropArea.addEventListener(eventName, highlight, false);
  });

  ['dragleave', 'drop'].forEach(eventName => {
    dropArea.addEventListener(eventName, unhighlight, false);
  });

  function highlight() {
    dropArea.classList.add('highlight');
  }

  function unhighlight() {
    dropArea.classList.remove('highlight');
  }

  dropArea.addEventListener('drop', handleDrop, false);

  function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    fileInput.files = files;
  }

  // Preview image before upload
  fileInput.addEventListener('change', function () {
    if (this.files && this.files[0]) {
      const reader = new FileReader();
      reader.onload = function (e) {
        dropArea.querySelector('i').style.display = 'none';
        dropArea.querySelector('p').innerHTML = '<img src="' + e.target.result + '" style="max-width: 100%; max-height: 100px; border-radius: 0.5rem;">';
        dropArea.querySelector('span').textContent = 'Ready to upload';
      };
      reader.readAsDataURL(this.files[0]);
    }
  });
</script>

<?php include '../includes/footer.php'; ?>