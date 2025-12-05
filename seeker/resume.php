<?php
/**
 * JobNexus - Resume Builder
 * Create and manage resume
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/SeekerProfile.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_SEEKER) {
  header('Location: ' . BASE_URL . '/auth/login.php?redirect=seeker/resume');
  exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$profileModel = new SeekerProfile();

$user = $userModel->findById($_SESSION['user_id']);
$profile = $profileModel->findByUserId($_SESSION['user_id']);

if (!$profile) {
  header('Location: ' . BASE_URL . '/seeker/profile.php?setup=1');
  exit;
}

// Parse profile data
$skills = is_array($profile['skills']) ? $profile['skills'] : (json_decode($profile['skills'] ?? '[]', true) ?: []);
$experience = $profile['experience'] ?? [];
$education = $profile['education'] ?? [];

$message = '';
$messageType = '';

// Handle resume upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'upload_resume') {
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
      $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
      $maxSize = 5 * 1024 * 1024; // 5MB

      if (!in_array($_FILES['resume']['type'], $allowedTypes)) {
        $message = 'Invalid file type. Please upload PDF or Word document.';
        $messageType = 'error';
      } elseif ($_FILES['resume']['size'] > $maxSize) {
        $message = 'File too large. Maximum size is 5MB.';
        $messageType = 'error';
      } else {
        $extension = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
        $filename = 'resume_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
        $uploadDir = '../uploads/resumes/';

        if (!is_dir($uploadDir)) {
          mkdir($uploadDir, 0755, true);
        }

        $uploadPath = $uploadDir . $filename;

        // Delete old resume if exists
        if ($profile['resume_file_path'] && file_exists($uploadDir . $profile['resume_file_path'])) {
          unlink($uploadDir . $profile['resume_file_path']);
        }

        if (move_uploaded_file($_FILES['resume']['tmp_name'], $uploadPath)) {
          $profileModel->update($profile['id'], ['resume_file_path' => $filename]);
          $message = 'Resume uploaded successfully!';
          $messageType = 'success';
          $profile = $profileModel->findByUserId($_SESSION['user_id']);
        } else {
          $message = 'Failed to upload resume.';
          $messageType = 'error';
        }
      }
    }
  } elseif ($action === 'delete_resume') {
    if ($profile['resume_file_path']) {
      $filePath = '../uploads/resumes/' . $profile['resume_file_path'];
      if (file_exists($filePath)) {
        unlink($filePath);
      }
      $profileModel->update($profile['id'], ['resume_file_path' => null]);
      $message = 'Resume deleted.';
      $messageType = 'success';
      $profile = $profileModel->findByUserId($_SESSION['user_id']);
    }
  } elseif ($action === 'use_generated_resume') {
    // Save the generated HTML resume as PDF using provided base64 data
    if (isset($_POST['pdf_data']) && !empty($_POST['pdf_data'])) {
      $pdfData = $_POST['pdf_data'];

      // Remove the data URL prefix
      $pdfData = str_replace('data:application/pdf;base64,', '', $pdfData);
      $pdfData = str_replace(' ', '+', $pdfData);
      $pdfContent = base64_decode($pdfData);

      if ($pdfContent) {
        $filename = 'resume_' . $_SESSION['user_id'] . '_' . time() . '.pdf';
        $uploadDir = '../uploads/resumes/';

        if (!is_dir($uploadDir)) {
          mkdir($uploadDir, 0755, true);
        }

        // Delete old resume if exists
        if ($profile['resume_file_path'] && file_exists($uploadDir . $profile['resume_file_path'])) {
          unlink($uploadDir . $profile['resume_file_path']);
        }

        $uploadPath = $uploadDir . $filename;

        if (file_put_contents($uploadPath, $pdfContent)) {
          $profileModel->update($profile['id'], ['resume_file_path' => $filename]);
          $message = 'Resume generated and saved successfully!';
          $messageType = 'success';
          $profile = $profileModel->findByUserId($_SESSION['user_id']);
        } else {
          $message = 'Failed to save generated resume.';
          $messageType = 'error';
        }
      } else {
        $message = 'Invalid PDF data received.';
        $messageType = 'error';
      }
    } else {
      $message = 'No PDF data received.';
      $messageType = 'error';
    }
  }
}

$pageTitle = 'Resume Builder';
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
      <a href="<?php echo BASE_URL; ?>/seeker/saved-jobs.php" class="nav-item">
        <i class="fas fa-bookmark"></i>
        <span>Saved Jobs</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/seeker/calendar.php" class="nav-item">
        <i class="fas fa-calendar-alt"></i>
        <span>Calendar</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/seeker/resume.php" class="nav-item active">
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
        <h1><i class="fas fa-file-pdf"></i> Resume Builder</h1>
        <p>Upload your resume or generate one from your profile</p>
      </div>
      <div class="header-right">
        <button onclick="window.print()" class="btn btn-outline">
          <i class="fas fa-print"></i> Print
        </button>
        <button onclick="generatePDF()" class="btn btn-primary">
          <i class="fas fa-download"></i> Download PDF
        </button>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?php echo $messageType; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card primary">
        <div class="stat-icon">
          <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $profile['resume_file_path'] ? '1' : '0'; ?></h3>
          <p>Uploaded Resume</p>
        </div>
        <div class="stat-footer">
          <span
            class="stat-label"><?php echo $profile['resume_file_path'] ? 'Ready to share' : 'Not uploaded'; ?></span>
        </div>
      </div>

      <div class="stat-card success">
        <div class="stat-icon">
          <i class="fas fa-briefcase"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo count($experience); ?></h3>
          <p>Experience</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">Work history entries</span>
        </div>
      </div>

      <div class="stat-card info">
        <div class="stat-icon">
          <i class="fas fa-graduation-cap"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo count($education); ?></h3>
          <p>Education</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">Degrees & certifications</span>
        </div>
      </div>

      <div class="stat-card purple">
        <div class="stat-icon">
          <i class="fas fa-code"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo count($skills); ?></h3>
          <p>Skills</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">Listed skills</span>
        </div>
      </div>
    </div>

    <div class="resume-layout">
      <!-- Upload Section -->
      <div class="upload-section glass-card">
        <h3><i class="fas fa-upload"></i> Upload Resume</h3>
        <p>Upload your existing resume (PDF or Word)</p>

        <?php if ($profile['resume_file_path']): ?>
          <div class="current-resume">
            <div class="resume-file">
              <i class="fas fa-file-pdf"></i>
              <div class="file-info">
                <span class="file-name"><?php echo htmlspecialchars($profile['resume_file_path']); ?></span>
                <span class="file-meta">Uploaded resume</span>
              </div>
            </div>
            <div class="resume-actions">
              <a href="<?php echo BASE_URL; ?>/uploads/resumes/<?php echo $profile['resume_file_path']; ?>"
                class="btn btn-outline" target="_blank">
                <i class="fas fa-eye"></i> View
              </a>
              <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="delete_resume">
                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Delete this resume?');">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </form>
            </div>
          </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="upload-form">
          <input type="hidden" name="action" value="upload_resume">
          <div class="upload-area" id="uploadArea">
            <input type="file" name="resume" id="resumeFile" accept=".pdf,.doc,.docx" required>
            <div class="upload-placeholder">
              <i class="fas fa-cloud-upload-alt"></i>
              <span>Drag & drop or click to upload</span>
              <small>PDF, DOC, DOCX (max 5MB)</small>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-full">
            <i class="fas fa-upload"></i> Upload Resume
          </button>
        </form>
      </div>

      <!-- Resume Preview -->
      <div class="resume-preview glass-card" id="resumePreview">
        <div class="preview-header">
          <div class="preview-header-top">
            <div>
              <h3><i class="fas fa-eye"></i> Resume Preview</h3>
              <p>Based on your profile information</p>
            </div>
            <div class="preview-actions">
              <button onclick="downloadResumePDF()" class="btn btn-outline btn-sm">
                <i class="fas fa-download"></i> Save as PDF
              </button>
              <button onclick="useAsResume()" class="btn btn-primary btn-sm">
                <i class="fas fa-check"></i> Use as Resume
              </button>
            </div>
          </div>
        </div>

        <div class="resume-document">
          <!-- Header -->
          <header class="resume-doc-header">
            <h1><?php echo htmlspecialchars(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')); ?>
            </h1>
            <p class="headline"><?php echo htmlspecialchars($profile['headline'] ?? ''); ?></p>
            <div class="contact-row">
              <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
              <?php if ($profile['phone']): ?>
                <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($profile['phone']); ?></span>
              <?php endif; ?>
              <?php if ($profile['location']): ?>
                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($profile['location']); ?></span>
              <?php endif; ?>
            </div>
          </header>

          <?php if ($profile['bio']): ?>
            <section class="resume-section">
              <h2>Professional Summary</h2>
              <p><?php echo nl2br(htmlspecialchars($profile['bio'])); ?></p>
            </section>
          <?php endif; ?>

          <?php if (!empty($experience)): ?>
            <section class="resume-section">
              <h2>Work Experience</h2>
              <?php foreach ($experience as $exp): ?>
                <div class="resume-item">
                  <div class="item-header">
                    <h3><?php echo htmlspecialchars($exp['job_title'] ?? ''); ?></h3>
                    <span class="dates">
                      <?php echo date('M Y', strtotime($exp['start_date'] ?? 'now')); ?> -
                      <?php echo $exp['is_current'] ? 'Present' : date('M Y', strtotime($exp['end_date'] ?? 'now')); ?>
                    </span>
                  </div>
                  <p class="company"><?php echo htmlspecialchars($exp['company_name'] ?? ''); ?></p>
                  <?php if (!empty($exp['description'])): ?>
                    <p class="description"><?php echo nl2br(htmlspecialchars($exp['description'])); ?></p>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </section>
          <?php endif; ?>

          <?php if (!empty($education)): ?>
            <section class="resume-section">
              <h2>Education</h2>
              <?php foreach ($education as $edu): ?>
                <div class="resume-item">
                  <div class="item-header">
                    <h3><?php echo htmlspecialchars($edu['degree'] ?? ''); ?> in
                      <?php echo htmlspecialchars($edu['field_of_study'] ?? ''); ?></h3>
                    <span class="dates">
                      <?php echo date('Y', strtotime($edu['start_date'] ?? 'now')); ?> -
                      <?php echo $edu['is_current'] ? 'Present' : date('Y', strtotime($edu['end_date'] ?? 'now')); ?>
                    </span>
                  </div>
                  <p class="institution"><?php echo htmlspecialchars($edu['institution'] ?? ''); ?></p>
                  <?php if (!empty($edu['grade'])): ?>
                    <p class="grade">GPA: <?php echo htmlspecialchars($edu['grade']); ?></p>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </section>
          <?php endif; ?>

          <?php if (!empty($skills)): ?>
            <section class="resume-section">
              <h2>Skills</h2>
              <div class="skills-list">
                <?php foreach ($skills as $skill): ?>
                  <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                <?php endforeach; ?>
              </div>
            </section>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <!-- Hidden form for submitting generated resume -->
    <form id="useResumeForm" method="POST" style="display: none;">
      <input type="hidden" name="action" value="use_generated_resume">
      <input type="hidden" name="pdf_data" id="pdfDataInput">
    </form>
  </main>
</div>

<style>
  .resume-layout {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 2rem;
  }

  .upload-section {
    padding: 1.5rem;
    height: fit-content;
  }

  .upload-section h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
  }

  .upload-section>p {
    color: var(--text-muted);
    font-size: 0.875rem;
    margin-bottom: 1.5rem;
  }

  .current-resume {
    background: var(--bg-tertiary);
    border-radius: var(--radius-md);
    padding: 1rem;
    margin-bottom: 1.5rem;
  }

  .resume-file {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
  }

  .resume-file i {
    font-size: 2rem;
    color: #f44336;
  }

  .file-info {
    display: flex;
    flex-direction: column;
  }

  .file-name {
    font-weight: 500;
    word-break: break-all;
  }

  .file-meta {
    font-size: 0.75rem;
    color: var(--text-muted);
  }

  .resume-actions {
    display: flex;
    gap: 0.5rem;
  }

  .upload-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .upload-area {
    position: relative;
    border: 2px dashed var(--border-color);
    border-radius: var(--radius-md);
    padding: 2rem;
    text-align: center;
    transition: var(--transition-fast);
    cursor: pointer;
  }

  .upload-area:hover {
    border-color: var(--accent-primary);
    background: rgba(0, 230, 118, 0.05);
  }

  .upload-area input {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
  }

  .upload-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
  }

  .upload-placeholder i {
    font-size: 2.5rem;
    color: var(--text-muted);
  }

  .upload-placeholder small {
    font-size: 0.75rem;
  }

  .btn-full {
    width: 100%;
  }

  .btn-outline-danger {
    border-color: var(--error);
    color: var(--error);
  }

  .btn-outline-danger:hover {
    background: var(--error);
    color: white;
  }

  /* Resume Preview */
  .resume-preview {
    padding: 1.5rem;
  }

  .preview-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
  }

  .preview-header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
  }

  .preview-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
  }

  .preview-header p {
    color: var(--text-muted);
    font-size: 0.875rem;
  }

  .preview-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
  }

  .btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
  }

  .resume-document {
    background: white;
    color: #1a1a2e;
    padding: 2rem;
    border-radius: var(--radius-md);
    max-width: 800px;
  }

  .resume-doc-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--accent-primary);
  }

  .resume-doc-header h1 {
    font-size: 2rem;
    margin-bottom: 0.25rem;
    color: #1a1a2e;
  }

  .resume-doc-header .headline {
    color: #666;
    font-size: 1.125rem;
    margin-bottom: 1rem;
  }

  .contact-row {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 1.5rem;
    font-size: 0.875rem;
    color: #555;
  }

  .contact-row span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .contact-row i {
    color: var(--accent-primary);
  }

  .resume-section {
    margin-bottom: 1.5rem;
  }

  .resume-section h2 {
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--accent-primary);
    border-bottom: 1px solid #ddd;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
  }

  .resume-item {
    margin-bottom: 1rem;
  }

  .resume-item .item-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
  }

  .resume-item h3 {
    font-size: 1rem;
    margin-bottom: 0.125rem;
    color: #1a1a2e;
  }

  .resume-item .dates {
    font-size: 0.875rem;
    color: #666;
  }

  .resume-item .company,
  .resume-item .institution {
    color: #555;
    font-size: 0.9375rem;
    margin-bottom: 0.25rem;
  }

  .resume-item .description {
    font-size: 0.875rem;
    color: #555;
    line-height: 1.6;
  }

  .resume-item .grade {
    font-size: 0.875rem;
    color: #666;
  }

  .skills-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .skill-tag {
    background: #f0f0f0;
    color: #333;
    padding: 0.375rem 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
  }

  @media (max-width: 1024px) {
    .resume-layout {
      grid-template-columns: 1fr;
    }
  }

  @media print {

    .dashboard-sidebar,
    .dashboard-header,
    .upload-section,
    .preview-header,
    .stats-grid {
      display: none !important;
    }

    .dashboard-main {
      margin-left: 0 !important;
      padding: 0 !important;
    }

    .resume-preview {
      background: none !important;
      padding: 0 !important;
    }

    .resume-document {
      box-shadow: none !important;
    }
  }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
  // Drag and drop handling
  const uploadArea = document.getElementById('uploadArea');
  const fileInput = document.getElementById('resumeFile');

  uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.style.borderColor = 'var(--accent-primary)';
    uploadArea.style.background = 'rgba(0, 230, 118, 0.1)';
  });

  uploadArea.addEventListener('dragleave', () => {
    uploadArea.style.borderColor = '';
    uploadArea.style.background = '';
  });

  uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.style.borderColor = '';
    uploadArea.style.background = '';

    if (e.dataTransfer.files.length) {
      fileInput.files = e.dataTransfer.files;
      updateFileName(e.dataTransfer.files[0].name);
    }
  });

  fileInput.addEventListener('change', () => {
    if (fileInput.files.length) {
      updateFileName(fileInput.files[0].name);
    }
  });

  function updateFileName(name) {
    const placeholder = uploadArea.querySelector('.upload-placeholder span');
    placeholder.textContent = name;
  }

  function generatePDF() {
    window.print();
  }

  // Download resume as PDF
  function downloadResumePDF() {
    const element = document.querySelector('.resume-document');
    const name = '<?php echo htmlspecialchars(($profile['first_name'] ?? '') . '_' . ($profile['last_name'] ?? '')); ?>'.replace(/\s+/g, '_');

    const opt = {
      margin: 0.5,
      filename: name + '_Resume.pdf',
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2, useCORS: true },
      jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    };

    // Show loading state
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;

    html2pdf().set(opt).from(element).save().then(() => {
      btn.innerHTML = originalText;
      btn.disabled = false;
    }).catch((err) => {
      console.error('PDF generation failed:', err);
      btn.innerHTML = originalText;
      btn.disabled = false;
      alert('Failed to generate PDF. Please try again.');
    });
  }

  // Use generated resume as profile resume
  function useAsResume() {
    const element = document.querySelector('.resume-document');
    const name = '<?php echo htmlspecialchars(($profile['first_name'] ?? '') . '_' . ($profile['last_name'] ?? '')); ?>'.replace(/\s+/g, '_');

    const opt = {
      margin: 0.5,
      filename: name + '_Resume.pdf',
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2, useCORS: true },
      jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    };

    // Show loading state
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;

    // Generate PDF and get base64 data
    html2pdf().set(opt).from(element).outputPdf('datauristring').then((pdfDataUri) => {
      // Submit the form with PDF data
      document.getElementById('pdfDataInput').value = pdfDataUri;
      document.getElementById('useResumeForm').submit();
    }).catch((err) => {
      console.error('PDF generation failed:', err);
      btn.innerHTML = originalText;
      btn.disabled = false;
      alert('Failed to generate resume. Please try again.');
    });
  }
</script>

<?php require_once '../includes/footer.php'; ?>