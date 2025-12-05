<?php
/**
 * JobNexus - Seeker Profile Page
 * Resume-style professional profile display and edit
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/SeekerProfile.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_SEEKER) {
  header('Location: ' . BASE_URL . '/auth/login.php?redirect=seeker/profile');
  exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$profileModel = new SeekerProfile();

$user = $userModel->findById($_SESSION['user_id']);
$profile = $profileModel->findByUserId($_SESSION['user_id']);

// Create profile if doesn't exist
if (!$profile) {
  $profileModel->create([
    'user_id' => $_SESSION['user_id'],
    'first_name' => '',
    'last_name' => '',
    'headline' => null,
    'phone' => null,
    'location' => null
  ]);
  $profile = $profileModel->findByUserId($_SESSION['user_id']);
}

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? 'update';

  if ($action === 'update_basic') {
    // Update basic info
    $updateData = [
      'first_name' => trim($_POST['first_name'] ?? ''),
      'last_name' => trim($_POST['last_name'] ?? ''),
      'headline' => trim($_POST['headline'] ?? ''),
      'bio' => trim($_POST['bio'] ?? ''),
      'phone' => trim($_POST['phone'] ?? ''),
      'location' => trim($_POST['location'] ?? ''),
      'portfolio_url' => trim($_POST['portfolio_url'] ?? ''),
      'linkedin_url' => trim($_POST['linkedin_url'] ?? ''),
      'github_url' => trim($_POST['github_url'] ?? '')
    ];

    if ($profileModel->update($profile['id'], $updateData)) {
      $message = 'Profile updated successfully!';
      $messageType = 'success';
      $profile = $profileModel->findByUserId($_SESSION['user_id']);
    } else {
      $message = 'Failed to update profile.';
      $messageType = 'error';
    }
  } elseif ($action === 'update_skills') {
    $skills = array_filter(array_map('trim', explode(',', $_POST['skills'] ?? '')));
    $profileModel->update($profile['id'], ['skills' => json_encode($skills)]);
    $message = 'Skills updated successfully!';
    $messageType = 'success';
    $profile = $profileModel->findByUserId($_SESSION['user_id']);
  } elseif ($action === 'add_experience') {
    $expData = [
      'job_title' => trim($_POST['exp_title'] ?? ''),
      'company_name' => trim($_POST['exp_company'] ?? ''),
      'location' => trim($_POST['exp_location'] ?? ''),
      'start_date' => $_POST['exp_start_date'] ?? null,
      'end_date' => isset($_POST['exp_current']) ? null : ($_POST['exp_end_date'] ?? null),
      'is_current' => isset($_POST['exp_current']) ? 1 : 0,
      'description' => trim($_POST['exp_description'] ?? '')
    ];
    $profileModel->addExperience($profile['id'], $expData);
    $message = 'Experience added successfully!';
    $messageType = 'success';
    $profile = $profileModel->findByUserId($_SESSION['user_id']);
  } elseif ($action === 'delete_experience') {
    $expId = (int) ($_POST['exp_id'] ?? 0);
    if ($expId) {
      $profileModel->deleteExperience($expId);
    }
    $message = 'Experience removed.';
    $messageType = 'success';
    $profile = $profileModel->findByUserId($_SESSION['user_id']);
  } elseif ($action === 'add_education') {
    $eduData = [
      'degree' => trim($_POST['edu_degree'] ?? ''),
      'field_of_study' => trim($_POST['edu_field'] ?? ''),
      'institution' => trim($_POST['edu_institution'] ?? ''),
      'location' => trim($_POST['edu_location'] ?? ''),
      'start_date' => $_POST['edu_start_year'] ? $_POST['edu_start_year'] . '-01-01' : null,
      'end_date' => isset($_POST['edu_current']) ? null : ($_POST['edu_end_year'] ? $_POST['edu_end_year'] . '-01-01' : null),
      'is_current' => isset($_POST['edu_current']) ? 1 : 0,
      'grade' => trim($_POST['edu_gpa'] ?? '')
    ];
    $profileModel->addEducation($profile['id'], $eduData);
    $message = 'Education added successfully!';
    $messageType = 'success';
    $profile = $profileModel->findByUserId($_SESSION['user_id']);
  } elseif ($action === 'delete_education') {
    $eduId = (int) ($_POST['edu_id'] ?? 0);
    if ($eduId) {
      $profileModel->deleteEducation($eduId);
    }
    $message = 'Education removed.';
    $messageType = 'success';
    $profile = $profileModel->findByUserId($_SESSION['user_id']);
  } elseif ($action === 'upload_resume') {
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
        $uploadPath = '../uploads/resumes/' . $filename;

        // Delete old resume if exists
        if ($profile['resume_file_path'] && file_exists('../uploads/resumes/' . $profile['resume_file_path'])) {
          unlink('../uploads/resumes/' . $profile['resume_file_path']);
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
  } elseif ($action === 'upload_photo') {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
      $allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
      $maxImageSize = 2 * 1024 * 1024; // 2MB

      if (!in_array($_FILES['profile_photo']['type'], $allowedImageTypes)) {
        $message = 'Invalid image type. Please upload JPG, PNG or WEBP.';
        $messageType = 'error';
      } elseif ($_FILES['profile_photo']['size'] > $maxImageSize) {
        $message = 'Image too large. Maximum size is 2MB.';
        $messageType = 'error';
      } else {
        $extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
        $uploadPath = '../uploads/avatars/' . $filename;

        // Delete old photo if exists
        if (!empty($profile['profile_photo']) && file_exists('../uploads/avatars/' . $profile['profile_photo'])) {
          @unlink('../uploads/avatars/' . $profile['profile_photo']);
        }

        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadPath)) {
          $profileModel->update($profile['id'], ['profile_photo' => $filename]);
          $message = 'Profile photo updated successfully!';
          $messageType = 'success';
          $profile = $profileModel->findByUserId($_SESSION['user_id']);
        } else {
          $message = 'Failed to upload profile photo.';
          $messageType = 'error';
        }
      }
    }
  } elseif ($action === 'remove_photo') {
    if (!empty($profile['profile_photo']) && file_exists('../uploads/avatars/' . $profile['profile_photo'])) {
      @unlink('../uploads/avatars/' . $profile['profile_photo']);
    }
    $profileModel->update($profile['id'], ['profile_photo' => null]);
    $message = 'Profile photo removed.';
    $messageType = 'success';
    $profile = $profileModel->findByUserId($_SESSION['user_id']);
  } elseif ($action === 'upload_photo') {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
      $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      $maxSize = 2 * 1024 * 1024; // 2MB

      if (!in_array($_FILES['profile_photo']['type'], $allowedTypes)) {
        $message = 'Invalid file type. Please upload JPG, PNG, GIF or WebP.';
        $messageType = 'error';
      } elseif ($_FILES['profile_photo']['size'] > $maxSize) {
        $message = 'File too large. Maximum size is 2MB.';
        $messageType = 'error';
      } else {
        $extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
        $uploadPath = '../uploads/profiles/' . $filename;

        // Create directory if not exists
        if (!file_exists('../uploads/profiles/')) {
          mkdir('../uploads/profiles/', 0777, true);
        }

        // Delete old photo if exists
        if ($profile['profile_photo'] && file_exists('../uploads/profiles/' . $profile['profile_photo'])) {
          unlink('../uploads/profiles/' . $profile['profile_photo']);
        }

        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadPath)) {
          $profileModel->update($profile['id'], ['profile_photo' => $filename]);
          $message = 'Profile photo updated successfully!';
          $messageType = 'success';
          $profile = $profileModel->findByUserId($_SESSION['user_id']);
        } else {
          $message = 'Failed to upload profile photo.';
          $messageType = 'error';
        }
      }
    }
  }
}

// Parse profile data
$skills = is_array($profile['skills']) ? $profile['skills'] : (json_decode($profile['skills'] ?? '[]', true) ?: []);
$experience = $profile['experience'] ?? [];
$education = $profile['education'] ?? [];

$pageTitle = 'My Profile';
require_once '../includes/header.php';
?>

<div class="profile-page">
  <div class="profile-container">
    <?php if ($message): ?>
      <div class="alert alert-<?php echo $messageType; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <!-- Resume Style Profile -->
    <div class="resume-wrapper">
      <!-- Resume Header -->
      <header class="resume-header">
        <div class="resume-avatar" onclick="document.getElementById('photoInput').click();"
          style="cursor: pointer; position: relative; overflow: hidden;">
          <?php
          $profilePhotoPath = __DIR__ . '/../uploads/avatars/' . ($profile['profile_photo'] ?? '');
          if (!empty($profile['profile_photo']) && file_exists($profilePhotoPath)):
            ?>
            <img src="<?php echo BASE_URL; ?>/uploads/avatars/<?php echo $profile['profile_photo']; ?>"
              alt="Profile Photo">
          <?php else: ?>
            <span><?php echo strtoupper(substr($profile['first_name'] ?? 'U', 0, 1)); ?></span>
          <?php endif; ?>

          <div class="avatar-overlay"
            style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.5); color: white; padding: 5px; text-align: center; opacity: 0; transition: opacity 0.3s; width: 100%;">
            <i class="fas fa-camera"></i>
          </div>
        </div>
        <style>
          .resume-avatar:hover .avatar-overlay {
            opacity: 1;
          }
        </style>
        <div class="resume-identity">
          <h1><?php echo htmlspecialchars(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')); ?></h1>
          <h2 class="headline"><?php echo htmlspecialchars($profile['headline'] ?? 'Add your professional headline'); ?>
          </h2>
          <div class="contact-info">
            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
            <?php if ($profile['phone']): ?>
              <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($profile['phone']); ?></span>
            <?php endif; ?>
            <?php if ($profile['location']): ?>
              <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($profile['location']); ?></span>
            <?php endif; ?>
          </div>
          <div class="social-links">
            <?php if ($profile['linkedin_url']): ?>
              <a href="<?php echo htmlspecialchars($profile['linkedin_url']); ?>" target="_blank" class="social-link">
                <i class="fab fa-linkedin"></i>
              </a>
            <?php endif; ?>
            <?php if ($profile['github_url']): ?>
              <a href="<?php echo htmlspecialchars($profile['github_url']); ?>" target="_blank" class="social-link">
                <i class="fab fa-github"></i>
              </a>
            <?php endif; ?>
            <?php if ($profile['portfolio_url']): ?>
              <a href="<?php echo htmlspecialchars($profile['portfolio_url']); ?>" target="_blank" class="social-link">
                <i class="fas fa-globe"></i>
              </a>
            <?php endif; ?>
          </div>
        </div>
        <div class="resume-actions">
          <button class="btn btn-outline-primary btn-sm" onclick="openModal('basicInfoModal')">
            <i class="fas fa-edit"></i> Edit
          </button>
          <?php if ($profile['resume_file_path']): ?>
            <a href="<?php echo BASE_URL; ?>/uploads/resumes/<?php echo $profile['resume_file_path']; ?>"
              class="btn btn-primary btn-sm" target="_blank">
              <i class="fas fa-download"></i> Download Resume
            </a>
          <?php endif; ?>
        </div>
      </header>

      <!-- Resume Body -->
      <div class="resume-body">
        <!-- Summary Section -->
        <section class="resume-section">
          <div class="section-header">
            <h3><i class="fas fa-user"></i> Professional Summary</h3>
            <button class="btn-icon" onclick="openModal('basicInfoModal')">
              <i class="fas fa-edit"></i>
            </button>
          </div>
          <div class="section-content">
            <?php if ($profile['bio']): ?>
              <p class="summary-text"><?php echo nl2br(htmlspecialchars($profile['bio'])); ?></p>
            <?php else: ?>
              <p class="empty-placeholder">
                Add a professional summary to introduce yourself to potential employers.
                <button class="btn btn-sm btn-outline-primary" onclick="openModal('basicInfoModal')">Add Summary</button>
              </p>
            <?php endif; ?>
          </div>
        </section>

        <!-- Skills Section -->
        <section class="resume-section">
          <div class="section-header">
            <h3><i class="fas fa-cogs"></i> Skills</h3>
            <button class="btn-icon" onclick="openModal('skillsModal')">
              <i class="fas fa-edit"></i>
            </button>
          </div>
          <div class="section-content">
            <?php if (!empty($skills)): ?>
              <div class="skills-grid">
                <?php foreach ($skills as $skill): ?>
                  <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="empty-placeholder">
                Add your skills to help employers find you.
                <button class="btn btn-sm btn-outline-primary" onclick="openModal('skillsModal')">Add Skills</button>
              </p>
            <?php endif; ?>
          </div>
        </section>

        <!-- Experience Section -->
        <section class="resume-section">
          <div class="section-header">
            <h3><i class="fas fa-briefcase"></i> Work Experience</h3>
            <button class="btn btn-sm btn-primary" onclick="openModal('experienceModal')">
              <i class="fas fa-plus"></i> Add
            </button>
          </div>
          <div class="section-content">
            <?php if (!empty($experience)): ?>
              <div class="timeline">
                <?php foreach ($experience as $exp): ?>
                  <div class="timeline-item">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                      <div class="timeline-header">
                        <div class="timeline-title">
                          <h4><?php echo htmlspecialchars($exp['job_title']); ?></h4>
                          <p class="company"><?php echo htmlspecialchars($exp['company_name']); ?></p>
                        </div>
                        <div class="timeline-meta">
                          <span class="date">
                            <?php echo date('M Y', strtotime($exp['start_date'])); ?> -
                            <?php echo $exp['is_current'] ? 'Present' : date('M Y', strtotime($exp['end_date'])); ?>
                          </span>
                          <?php if ($exp['location']): ?>
                            <span class="location">
                              <i class="fas fa-map-marker-alt"></i>
                              <?php echo htmlspecialchars($exp['location']); ?>
                            </span>
                          <?php endif; ?>
                        </div>
                        <form method="POST" class="delete-form" onsubmit="return confirm('Remove this experience?');">
                          <input type="hidden" name="action" value="delete_experience">
                          <input type="hidden" name="exp_id" value="<?php echo $exp['id']; ?>">
                          <button type="submit" class="btn-icon danger">
                            <i class="fas fa-trash"></i>
                          </button>
                        </form>
                      </div>
                      <?php if ($exp['description']): ?>
                        <p class="description"><?php echo nl2br(htmlspecialchars($exp['description'])); ?></p>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="empty-placeholder">
                Add your work experience to showcase your career journey.
                <button class="btn btn-sm btn-outline-primary" onclick="openModal('experienceModal')">Add
                  Experience</button>
              </p>
            <?php endif; ?>
          </div>
        </section>

        <!-- Education Section -->
        <section class="resume-section">
          <div class="section-header">
            <h3><i class="fas fa-graduation-cap"></i> Education</h3>
            <button class="btn btn-sm btn-primary" onclick="openModal('educationModal')">
              <i class="fas fa-plus"></i> Add
            </button>
          </div>
          <div class="section-content">
            <?php if (!empty($education)): ?>
              <div class="education-list">
                <?php foreach ($education as $edu): ?>
                  <div class="education-item">
                    <div class="edu-icon">
                      <i class="fas fa-university"></i>
                    </div>
                    <div class="edu-content">
                      <div class="edu-header">
                        <div class="edu-title">
                          <h4><?php echo htmlspecialchars($edu['degree']); ?><?php if ($edu['field_of_study']): ?> in
                              <?php echo htmlspecialchars($edu['field_of_study']); ?>     <?php endif; ?>
                          </h4>
                          <p class="institution"><?php echo htmlspecialchars($edu['institution']); ?></p>
                        </div>
                        <div class="edu-meta">
                          <span class="date">
                            <?php echo $edu['start_date'] ? date('Y', strtotime($edu['start_date'])) : ''; ?> -
                            <?php echo $edu['is_current'] ? 'Present' : ($edu['end_date'] ? date('Y', strtotime($edu['end_date'])) : ''); ?>
                          </span>
                          <?php if ($edu['grade']): ?>
                            <span class="gpa">GPA: <?php echo htmlspecialchars($edu['grade']); ?></span>
                          <?php endif; ?>
                        </div>
                        <form method="POST" class="delete-form" onsubmit="return confirm('Remove this education?');">
                          <input type="hidden" name="action" value="delete_education">
                          <input type="hidden" name="edu_id" value="<?php echo $edu['id']; ?>">
                          <button type="submit" class="btn-icon danger">
                            <i class="fas fa-trash"></i>
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="empty-placeholder">
                Add your educational background.
                <button class="btn btn-sm btn-outline-primary" onclick="openModal('educationModal')">Add
                  Education</button>
              </p>
            <?php endif; ?>
          </div>
        </section>

        <!-- Resume Upload Section -->
        <section class="resume-section">
          <div class="section-header">
            <h3><i class="fas fa-file-pdf"></i> Resume Document</h3>
          </div>
          <div class="section-content">
            <div class="resume-upload-area">
              <?php if ($profile['resume_file_path']): ?>
                <div class="current-resume">
                  <div class="file-icon">
                    <i class="fas fa-file-pdf"></i>
                  </div>
                  <div class="file-info">
                    <h4>Current Resume</h4>
                    <p><?php echo $profile['resume_file_path']; ?></p>
                  </div>
                  <div class="file-actions">
                    <a href="<?php echo BASE_URL; ?>/uploads/resumes/<?php echo $profile['resume_file_path']; ?>"
                      class="btn btn-outline-primary btn-sm" target="_blank">
                      <i class="fas fa-eye"></i> View
                    </a>
                    <button class="btn btn-outline-primary btn-sm"
                      onclick="document.getElementById('resumeInput').click()">
                      <i class="fas fa-upload"></i> Replace
                    </button>
                  </div>
                </div>
              <?php else: ?>
                <div class="upload-prompt" onclick="document.getElementById('resumeInput').click()">
                  <i class="fas fa-cloud-upload-alt"></i>
                  <h4>Upload Your Resume</h4>
                  <p>Drag & drop or click to upload (PDF, DOC, DOCX - Max 5MB)</p>
                </div>
              <?php endif; ?>

              <form method="POST" enctype="multipart/form-data" id="resumeForm" style="display: none;">
                <input type="hidden" name="action" value="upload_resume">
                <input type="file" name="resume" id="resumeInput" accept=".pdf,.doc,.docx"
                  onchange="document.getElementById('resumeForm').submit();">
              </form>
              <!-- Hidden photo upload form -->
              <form method="POST" enctype="multipart/form-data" id="photoForm" style="display: none;">
                <input type="hidden" name="action" value="upload_photo">
                <input type="file" name="profile_photo" id="photoInput" accept="image/png,image/jpeg,image/webp"
                  onchange="document.getElementById('photoForm').submit();">
              </form>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</div>

<!-- Basic Info Modal -->
<div class="modal" id="basicInfoModal">
  <div class="modal-backdrop" onclick="closeModal('basicInfoModal')"></div>
  <div class="modal-content">
    <div class="modal-header">
      <h3>Edit Basic Information</h3>
      <button class="btn-icon" onclick="closeModal('basicInfoModal')">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update_basic">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" class="form-control"
              value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" placeholder="Your first name"
              required>
          </div>
          <div class="form-group">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" class="form-control"
              value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" placeholder="Your last name"
              required>
          </div>
        </div>

        <div class="form-group">
          <label for="headline">Professional Headline</label>
          <input type="text" id="headline" name="headline" class="form-control"
            value="<?php echo htmlspecialchars($profile['headline'] ?? ''); ?>"
            placeholder="e.g., Senior Software Engineer | Full Stack Developer">
        </div>

        <div class="form-group">
          <label for="bio">Professional Summary</label>
          <textarea id="bio" name="bio" class="form-control" rows="5"
            placeholder="Write a brief professional summary..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="phone">Phone</label>
            <input type="tel" id="phone" name="phone" class="form-control"
              value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" placeholder="+1 (123) 456-7890">
          </div>
          <div class="form-group">
            <label for="location">Location</label>
            <input type="text" id="location" name="location" class="form-control"
              value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>" placeholder="City, State/Country">
          </div>
        </div>

        <div class="form-group">
          <label for="portfolio_url">Website</label>
          <input type="url" id="portfolio_url" name="portfolio_url" class="form-control"
            value="<?php echo htmlspecialchars($profile['portfolio_url'] ?? ''); ?>"
            placeholder="https://yourwebsite.com">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="linkedin_url">LinkedIn</label>
            <input type="url" id="linkedin_url" name="linkedin_url" class="form-control"
              value="<?php echo htmlspecialchars($profile['linkedin_url'] ?? ''); ?>"
              placeholder="https://linkedin.com/in/yourprofile">
          </div>
          <div class="form-group">
            <label for="github_url">GitHub</label>
            <input type="url" id="github_url" name="github_url" class="form-control"
              value="<?php echo htmlspecialchars($profile['github_url'] ?? ''); ?>"
              placeholder="https://github.com/yourusername">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" onclick="closeModal('basicInfoModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Skills Modal -->
<div class="modal" id="skillsModal">
  <div class="modal-backdrop" onclick="closeModal('skillsModal')"></div>
  <div class="modal-content">
    <div class="modal-header">
      <h3>Edit Skills</h3>
      <button class="btn-icon" onclick="closeModal('skillsModal')">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update_skills">
      <div class="modal-body">
        <div class="form-group">
          <label for="skills">Skills (comma-separated)</label>
          <textarea id="skills" name="skills" class="form-control" rows="4"
            placeholder="JavaScript, Python, React, Node.js, SQL, AWS..."><?php echo htmlspecialchars(implode(', ', $skills)); ?></textarea>
          <small class="form-hint">Enter your skills separated by commas</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" onclick="closeModal('skillsModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Skills</button>
      </div>
    </form>
  </div>
</div>

<!-- Experience Modal -->
<div class="modal" id="experienceModal">
  <div class="modal-backdrop" onclick="closeModal('experienceModal')"></div>
  <div class="modal-content modal-lg">
    <div class="modal-header">
      <h3>Add Work Experience</h3>
      <button class="btn-icon" onclick="closeModal('experienceModal')">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_experience">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label for="exp_title">Job Title *</label>
            <input type="text" id="exp_title" name="exp_title" class="form-control" required
              placeholder="e.g., Software Engineer">
          </div>
          <div class="form-group">
            <label for="exp_company">Company *</label>
            <input type="text" id="exp_company" name="exp_company" class="form-control" required
              placeholder="e.g., Google">
          </div>
        </div>

        <div class="form-group">
          <label for="exp_location">Location</label>
          <input type="text" id="exp_location" name="exp_location" class="form-control"
            placeholder="e.g., San Francisco, CA">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="exp_start_date">Start Date *</label>
            <input type="month" id="exp_start_date" name="exp_start_date" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="exp_end_date">End Date</label>
            <input type="month" id="exp_end_date" name="exp_end_date" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="exp_current" id="exp_current"
              onchange="document.getElementById('exp_end_date').disabled = this.checked;">
            I currently work here
          </label>
        </div>

        <div class="form-group">
          <label for="exp_description">Description</label>
          <textarea id="exp_description" name="exp_description" class="form-control" rows="4"
            placeholder="Describe your responsibilities and achievements..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" onclick="closeModal('experienceModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Experience</button>
      </div>
    </form>
  </div>
</div>

<!-- Education Modal -->
<div class="modal" id="educationModal">
  <div class="modal-backdrop" onclick="closeModal('educationModal')"></div>
  <div class="modal-content modal-lg">
    <div class="modal-header">
      <h3>Add Education</h3>
      <button class="btn-icon" onclick="closeModal('educationModal')">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_education">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label for="edu_degree">Degree *</label>
            <select id="edu_degree" name="edu_degree" class="form-control" required>
              <option value="">Select Degree</option>
              <option value="High School Diploma">High School Diploma</option>
              <option value="Associate's">Associate's Degree</option>
              <option value="Bachelor's">Bachelor's Degree</option>
              <option value="Master's">Master's Degree</option>
              <option value="MBA">MBA</option>
              <option value="Ph.D.">Ph.D.</option>
              <option value="Certificate">Certificate</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label for="edu_field">Field of Study *</label>
            <input type="text" id="edu_field" name="edu_field" class="form-control" required
              placeholder="e.g., Computer Science">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="edu_institution">Institution *</label>
            <input type="text" id="edu_institution" name="edu_institution" class="form-control" required
              placeholder="e.g., MIT">
          </div>
          <div class="form-group">
            <label for="edu_location">Location</label>
            <input type="text" id="edu_location" name="edu_location" class="form-control"
              placeholder="e.g., Cambridge, MA">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="edu_start_year">Start Year *</label>
            <input type="number" id="edu_start_year" name="edu_start_year" class="form-control" required min="1950"
              max="2030" placeholder="2018">
          </div>
          <div class="form-group">
            <label for="edu_end_year">End Year</label>
            <input type="number" id="edu_end_year" name="edu_end_year" class="form-control" min="1950" max="2030"
              placeholder="2022">
          </div>
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="edu_current" id="edu_current"
              onchange="document.getElementById('edu_end_year').disabled = this.checked;">
            I'm currently studying here
          </label>
        </div>

        <div class="form-group">
          <label for="edu_gpa">GPA (optional)</label>
          <input type="text" id="edu_gpa" name="edu_gpa" class="form-control" placeholder="e.g., 3.8/4.0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" onclick="closeModal('educationModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Education</button>
      </div>
    </form>
  </div>
</div>

<style>
  /* Profile Page Styles */
  .profile-page {
    min-height: calc(100vh - 70px);
    padding: 2rem;
    margin-top: 70px;
    background: var(--bg-dark);
  }

  .profile-container {
    max-width: 900px;
    margin: 0 auto;
  }

  /* Resume Wrapper */
  .resume-wrapper {
    background: var(--card-bg);
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid var(--border-color);
  }

  /* Resume Header */
  .resume-header {
    background: linear-gradient(135deg, rgba(156, 39, 176, 0.1), rgba(0, 230, 118, 0.05));
    padding: 3rem;
    display: flex;
    align-items: flex-start;
    gap: 2rem;
    border-bottom: 1px solid var(--border-color);
  }

  .resume-avatar {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, #9C27B0, var(--primary-color));
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .resume-avatar span {
    font-size: 3rem;
    font-weight: 700;
    color: white;
  }

  .resume-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 20px;
    display: block;
  }

  .resume-identity {
    flex: 1;
  }

  .resume-identity h1 {
    font-family: var(--font-heading);
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
  }

  .resume-identity .headline {
    font-size: 1.25rem;
    color: var(--primary-color);
    font-weight: 400;
    margin-bottom: 1rem;
  }

  .contact-info {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-bottom: 1rem;
  }

  .contact-info span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.9rem;
  }

  .contact-info i {
    color: var(--primary-color);
  }

  .social-links {
    display: flex;
    gap: 0.75rem;
  }

  .social-link {
    width: 36px;
    height: 36px;
    background: var(--bg-dark);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    text-decoration: none;
    transition: all 0.3s ease;
  }

  .social-link:hover {
    background: var(--primary-color);
    color: var(--bg-dark);
  }

  .resume-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  /* Resume Body */
  .resume-body {
    padding: 2rem 3rem;
  }

  /* Resume Section */
  .resume-section {
    margin-bottom: 2.5rem;
  }

  .resume-section:last-child {
    margin-bottom: 0;
  }

  .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--primary-color);
  }

  .section-header h3 {
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .section-header h3 i {
    color: var(--primary-color);
  }

  .btn-icon {
    width: 36px;
    height: 36px;
    background: transparent;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
  }

  .btn-icon:hover {
    background: var(--primary-color);
    color: var(--bg-dark);
    border-color: var(--primary-color);
  }

  .btn-icon.danger:hover {
    background: var(--danger);
    border-color: var(--danger);
    color: white;
  }

  /* Section Content */
  .section-content {
    padding: 0;
  }

  .summary-text {
    color: var(--text-muted);
    line-height: 1.8;
  }

  .empty-placeholder {
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
    background: var(--bg-dark);
    border-radius: 12px;
    border: 2px dashed var(--border-color);
  }

  .empty-placeholder .btn {
    margin-top: 1rem;
  }

  /* Skills Grid */
  .skills-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
  }

  .skill-tag {
    background: rgba(0, 230, 118, 0.1);
    color: var(--primary-color);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    border: 1px solid rgba(0, 230, 118, 0.3);
  }

  /* Timeline */
  .timeline {
    position: relative;
    padding-left: 30px;
  }

  .timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 8px;
    bottom: 8px;
    width: 2px;
    background: var(--border-color);
  }

  .timeline-item {
    position: relative;
    margin-bottom: 2rem;
  }

  .timeline-item:last-child {
    margin-bottom: 0;
  }

  .timeline-marker {
    position: absolute;
    left: -26px;
    top: 8px;
    width: 14px;
    height: 14px;
    background: var(--primary-color);
    border-radius: 50%;
    border: 3px solid var(--card-bg);
  }

  .timeline-content {
    background: var(--bg-dark);
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid var(--border-color);
  }

  .timeline-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
  }

  .timeline-title {
    flex: 1;
  }

  .timeline-title h4 {
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
  }

  .timeline-title .company {
    color: var(--primary-color);
    font-weight: 500;
  }

  .timeline-meta {
    text-align: right;
    font-size: 0.85rem;
    color: var(--text-muted);
  }

  .timeline-meta .date {
    display: block;
    margin-bottom: 0.25rem;
  }

  .timeline-meta .location i {
    margin-right: 0.25rem;
  }

  .timeline-content .description {
    color: var(--text-muted);
    font-size: 0.9rem;
    line-height: 1.7;
  }

  .delete-form {
    margin-left: auto;
  }

  /* Education List */
  .education-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .education-item {
    display: flex;
    gap: 1.5rem;
    background: var(--bg-dark);
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid var(--border-color);
  }

  .edu-icon {
    width: 50px;
    height: 50px;
    background: rgba(0, 230, 118, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.25rem;
    flex-shrink: 0;
  }

  .edu-content {
    flex: 1;
  }

  .edu-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
  }

  .edu-title {
    flex: 1;
  }

  .edu-title h4 {
    font-size: 1rem;
    margin-bottom: 0.25rem;
  }

  .edu-title .institution {
    color: var(--primary-color);
    font-weight: 500;
  }

  .edu-meta {
    text-align: right;
    font-size: 0.85rem;
    color: var(--text-muted);
  }

  .edu-meta .date {
    display: block;
  }

  .edu-meta .gpa {
    display: block;
    color: var(--primary-color);
  }

  /* Resume Upload Area */
  .resume-upload-area {
    padding: 0;
  }

  .current-resume {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    background: var(--bg-dark);
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid var(--border-color);
  }

  .current-resume .file-icon {
    width: 60px;
    height: 60px;
    background: rgba(244, 67, 54, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #F44336;
    font-size: 1.5rem;
  }

  .current-resume .file-info {
    flex: 1;
  }

  .current-resume .file-info h4 {
    margin-bottom: 0.25rem;
  }

  .current-resume .file-info p {
    color: var(--text-muted);
    font-size: 0.85rem;
  }

  .current-resume .file-actions {
    display: flex;
    gap: 0.5rem;
  }

  .upload-prompt {
    text-align: center;
    padding: 3rem;
    background: var(--bg-dark);
    border: 2px dashed var(--border-color);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .upload-prompt:hover {
    border-color: var(--primary-color);
    background: rgba(0, 230, 118, 0.02);
  }

  .upload-prompt i {
    font-size: 3rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
  }

  .upload-prompt h4 {
    margin-bottom: 0.5rem;
  }

  .upload-prompt p {
    color: var(--text-muted);
    font-size: 0.9rem;
  }

  /* Modal Styles */
  .modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1000;
    align-items: center;
    justify-content: center;
  }

  .modal.active {
    display: flex;
  }

  .modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
  }

  .modal-content {
    position: relative;
    background: var(--card-bg);
    border-radius: 16px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    border: 1px solid var(--border-color);
  }

  .modal-content.modal-lg {
    max-width: 700px;
  }

  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
  }

  .modal-header h3 {
    font-size: 1.25rem;
  }

  .modal-body {
    padding: 1.5rem;
  }

  .modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
  }

  /* Form Styles */
  .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
  }

  .form-group {
    margin-bottom: 1.25rem;
  }

  .form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-light);
  }

  .form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    background: var(--bg-dark);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-light);
    font-size: 1rem;
    transition: all 0.3s ease;
  }

  .form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(0, 230, 118, 0.1);
  }

  .form-hint {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-muted);
  }

  .checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
  }

  .checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--primary-color);
  }

  /* Alert Styles */
  .alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  .alert i {
    font-size: 1.25rem;
  }

  .alert-success {
    background: rgba(76, 175, 80, 0.1);
    border: 1px solid rgba(76, 175, 80, 0.3);
    color: #4CAF50;
  }

  .alert-error {
    background: rgba(244, 67, 54, 0.1);
    border: 1px solid rgba(244, 67, 54, 0.3);
    color: #F44336;
  }

  /* Responsive */
  @media (max-width: 768px) {
    .profile-page {
      padding: 1rem;
    }

    .resume-header {
      flex-direction: column;
      text-align: center;
      padding: 2rem;
    }

    .resume-identity h1 {
      font-size: 2rem;
    }

    .contact-info {
      justify-content: center;
    }

    .social-links {
      justify-content: center;
    }

    .resume-actions {
      flex-direction: row;
      justify-content: center;
    }

    .resume-body {
      padding: 1.5rem;
    }

    .form-row {
      grid-template-columns: 1fr;
    }

    .timeline-header {
      flex-direction: column;
    }

    .timeline-meta {
      text-align: left;
    }

    .edu-header {
      flex-direction: column;
    }

    .edu-meta {
      text-align: left;
    }

    .current-resume {
      flex-direction: column;
      text-align: center;
    }

    .current-resume .file-actions {
      width: 100%;
      justify-content: center;
    }
  }
</style>

<script>
  function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
    document.body.style.overflow = '';
  }

  // Close modal on escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal.active').forEach(modal => {
        modal.classList.remove('active');
      });
      document.body.style.overflow = '';
    }
  });
</script>

<?php require_once '../includes/footer.php'; ?>