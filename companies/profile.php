<?php
/**
 * JobNexus - Company Profile Page
 * Public view of company details and job listings
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Company.php';
require_once '../classes/Job.php';

$db = Database::getInstance()->getConnection();
$companyModel = new Company();
$jobModel = new Job();

// Get company ID from URL
$companyId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$companyId) {
  header('Location: ' . BASE_URL . '/companies/');
  exit;
}

// Get company details
$company = $companyModel->findById($companyId);

// Check if company exists
if (!$company) {
  header('Location: ' . BASE_URL . '/companies/');
  exit;
}

// Allow admins to view unverified companies, others can only view verified
$isAdmin = isLoggedIn() && hasRole(ROLE_ADMIN);
if ($company['verification_status'] !== 'verified' && !$isAdmin) {
  // Show 404 or redirect for non-admins
  header('Location: ' . BASE_URL . '/companies/');
  exit;
}

// Get active jobs for this company
$stmt = $db->prepare("
    SELECT j.*, jc.name as category_name,
           (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
    FROM jobs j 
    LEFT JOIN job_categories jc ON j.category_id = jc.id
    WHERE j.company_id = ? AND j.status = 'active'
    ORDER BY j.created_at DESC
");
$stmt->execute([$companyId]);
$activeJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get company statistics
$stmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE company_id = ? AND status = 'active'");
$stmt->execute([$companyId]);
$totalActiveJobs = $stmt->fetchColumn();

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT a.seeker_id) 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE j.company_id = ?
");
$stmt->execute([$companyId]);
$totalApplicants = $stmt->fetchColumn();

// Check if user has saved any jobs from this company
$savedJobs = [];
if (isset($_SESSION['user_id']) && $_SESSION['role'] === ROLE_SEEKER) {
  $stmt = $db->prepare("
        SELECT job_id FROM saved_jobs 
        WHERE user_id = ? AND job_id IN (SELECT id FROM jobs WHERE company_id = ?)
    ");
  $stmt->execute([$_SESSION['user_id'], $companyId]);
  $savedJobs = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$pageTitle = htmlspecialchars($company['company_name']) . " - JobNexus";
include '../includes/header.php';
?>

<div class="company-profile-page">
  <!-- Company Header -->
  <div class="company-hero">
    <div class="container">
      <div class="company-hero-content">
        <div class="company-logo-large">
          <?php if ($company['logo']): ?>
            <img src="<?php echo BASE_URL; ?>/uploads/logos/<?php echo htmlspecialchars($company['logo']); ?>"
              alt="<?php echo htmlspecialchars($company['company_name']); ?>">
          <?php else: ?>
            <span><?php echo strtoupper(substr($company['company_name'], 0, 2)); ?></span>
          <?php endif; ?>
        </div>
        <div class="company-hero-info">
          <div class="company-badges">
            <?php if ($company['verification_status'] === 'verified'): ?>
              <span class="verified-badge">
                <i class="fas fa-check-circle"></i> Verified Company
              </span>
            <?php elseif ($isAdmin): ?>
              <span class="badge badge-warning"
                style="background: #ff9800; color: #fff; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem;">
                <i class="fas fa-clock"></i> Pending Verification
              </span>
            <?php endif; ?>
            <?php if ($company['industry']): ?>
              <span class="industry-badge"><?php echo htmlspecialchars($company['industry']); ?></span>
            <?php endif; ?>
          </div>
          <h1><?php echo htmlspecialchars($company['company_name']); ?></h1>
          <div class="company-meta">
            <?php if ($company['headquarters']): ?>
              <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($company['headquarters']); ?></span>
            <?php endif; ?>
            <?php if ($company['website']): ?>
              <a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank">
                <i class="fas fa-globe"></i> Website
              </a>
            <?php endif; ?>
            <?php if ($company['company_size']): ?>
              <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($company['company_size']); ?>
                employees</span>
            <?php endif; ?>
          </div>
          <div class="company-stats-mini">
            <div class="stat-item">
              <span class="stat-value"><?php echo $totalActiveJobs; ?></span>
              <span class="stat-label">Open Positions</span>
            </div>
            <div class="stat-item">
              <span class="stat-value"><?php echo $totalApplicants; ?>+</span>
              <span class="stat-label">Applicants</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="container">
    <div class="company-content-grid">
      <!-- Main Content -->
      <div class="company-main">
        <!-- About Section -->
        <section class="glass-card company-section">
          <h2><i class="fas fa-building"></i> About <?php echo htmlspecialchars($company['company_name']); ?></h2>
          <div class="about-content">
            <?php if ($company['description']): ?>
              <?php echo nl2br(htmlspecialchars($company['description'])); ?>
            <?php else: ?>
              <p class="placeholder-text">This company hasn't added a description yet.</p>
            <?php endif; ?>
          </div>
        </section>

        <!-- Open Positions -->
        <section class="glass-card company-section">
          <div class="section-header">
            <h2><i class="fas fa-briefcase"></i> Open Positions</h2>
            <span class="job-count"><?php echo count($activeJobs); ?> jobs</span>
          </div>

          <?php if (empty($activeJobs)): ?>
            <div class="empty-state">
              <i class="fas fa-briefcase"></i>
              <p>No open positions at the moment</p>
              <p class="subtext">Check back later for new opportunities</p>
            </div>
          <?php else: ?>
            <div class="jobs-list">
              <?php foreach ($activeJobs as $job): ?>
                <?php
                $isSaved = in_array($job['id'], $savedJobs);
                $deadline = $job['application_deadline'] ? new DateTime($job['application_deadline']) : null;
                $daysLeft = $deadline ? (new DateTime())->diff($deadline)->days : null;
                ?>
                <div class="job-list-item">
                  <div class="job-main-info">
                    <h3>
                      <a href="../jobs/view.php?id=<?php echo $job['id']; ?>">
                        <?php echo htmlspecialchars($job['title']); ?>
                      </a>
                    </h3>
                    <div class="job-details">
                      <span class="job-type <?php echo $job['job_type']; ?>">
                        <i class="fas fa-briefcase"></i>
                        <?php echo ucfirst(str_replace('-', ' ', $job['job_type'])); ?>
                      </span>
                      <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                      <?php if ($job['salary_min'] || $job['salary_max']): ?>
                        <span class="salary">
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
                        </span>
                      <?php endif; ?>
                    </div>
                    <div class="job-tags">
                      <span class="category-tag"><?php echo htmlspecialchars($job['category_name'] ?? 'General'); ?></span>
                      <?php if ($job['experience_level']): ?>
                        <span class="experience-tag"><?php echo ucfirst($job['experience_level']); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="job-actions">
                    <?php if ($deadline): ?>
                      <span class="deadline <?php echo $daysLeft <= 3 ? 'urgent' : ''; ?>">
                        <i class="fas fa-clock"></i>
                        <?php echo $daysLeft; ?> days left
                      </span>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === ROLE_SEEKER): ?>
                      <button class="save-btn <?php echo $isSaved ? 'active' : ''; ?>"
                        onclick="toggleSave(<?php echo $job['id']; ?>, this)">
                        <i class="fas fa-heart"></i>
                      </button>
                    <?php endif; ?>
                    <a href="../jobs/view.php?id=<?php echo $job['id']; ?>" class="btn btn-primary btn-sm">
                      View & Apply
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </div>

      <!-- Sidebar -->
      <aside class="company-sidebar">
        <!-- Company Info Card -->
        <div class="glass-card sidebar-card">
          <h3>Company Info</h3>
          <ul class="info-list">
            <?php if ($company['industry']): ?>
              <li>
                <i class="fas fa-industry"></i>
                <div>
                  <strong>Industry</strong>
                  <span><?php echo htmlspecialchars($company['industry']); ?></span>
                </div>
              </li>
            <?php endif; ?>
            <?php if ($company['company_size']): ?>
              <li>
                <i class="fas fa-users"></i>
                <div>
                  <strong>Company Size</strong>
                  <span><?php echo htmlspecialchars($company['company_size']); ?> employees</span>
                </div>
              </li>
            <?php endif; ?>
            <?php if ($company['founded_year']): ?>
              <li>
                <i class="fas fa-calendar-alt"></i>
                <div>
                  <strong>Founded</strong>
                  <span><?php echo htmlspecialchars($company['founded_year']); ?></span>
                </div>
              </li>
            <?php endif; ?>
            <?php if ($company['headquarters']): ?>
              <li>
                <i class="fas fa-map-marker-alt"></i>
                <div>
                  <strong>Headquarters</strong>
                  <span><?php echo htmlspecialchars($company['headquarters']); ?></span>
                </div>
              </li>
            <?php endif; ?>
          </ul>
        </div>

        <!-- Contact Card -->
        <div class="glass-card sidebar-card">
          <h3>Get in Touch</h3>
          <div class="contact-links">
            <?php if ($company['website']): ?>
              <a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank" class="contact-link">
                <i class="fas fa-globe"></i>
                <span>Visit Website</span>
              </a>
            <?php endif; ?>
            <?php if ($company['email']): ?>
              <a href="mailto:<?php echo htmlspecialchars($company['email']); ?>" class="contact-link">
                <i class="fas fa-envelope"></i>
                <span><?php echo htmlspecialchars($company['email']); ?></span>
              </a>
            <?php endif; ?>
            <?php if ($company['phone']): ?>
              <a href="tel:<?php echo htmlspecialchars($company['phone']); ?>" class="contact-link">
                <i class="fas fa-phone"></i>
                <span><?php echo htmlspecialchars($company['phone']); ?></span>
              </a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Social Links -->
        <?php if (($company['social_linkedin'] ?? null) || ($company['social_twitter'] ?? null)): ?>
          <div class="glass-card sidebar-card">
            <h3>Follow Us</h3>
            <div class="social-links">
              <?php if ($company['social_linkedin'] ?? null): ?>
                <a href="<?php echo htmlspecialchars($company['social_linkedin']); ?>" target="_blank"
                  class="social-btn linkedin">
                  <i class="fab fa-linkedin"></i>
                </a>
              <?php endif; ?>
              <?php if ($company['social_twitter'] ?? null): ?>
                <a href="<?php echo htmlspecialchars($company['social_twitter']); ?>" target="_blank"
                  class="social-btn twitter">
                  <i class="fab fa-twitter"></i>
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Similar Companies -->
        <?php
        $stmt = $db->prepare("
                    SELECT id, company_name, logo, headquarters 
                    FROM companies 
                    WHERE verification_status = 'verified' AND id != ? AND industry = ?
                    LIMIT 3
                ");
        $stmt->execute([$companyId, $company['industry']]);
        $similarCompanies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <?php if (!empty($similarCompanies)): ?>
          <div class="glass-card sidebar-card">
            <h3>Similar Companies</h3>
            <div class="similar-companies-list">
              <?php foreach ($similarCompanies as $similar): ?>
                <a href="profile.php?id=<?php echo $similar['id']; ?>" class="similar-company-item">
                  <div class="company-logo-small">
                    <?php if ($similar['logo']): ?>
                      <img src="<?php echo BASE_URL; ?>/uploads/logos/<?php echo htmlspecialchars($similar['logo']); ?>"
                        alt="">
                    <?php else: ?>
                      <?php echo strtoupper(substr($similar['company_name'], 0, 2)); ?>
                    <?php endif; ?>
                  </div>
                  <div class="company-mini-info">
                    <strong><?php echo htmlspecialchars($similar['company_name']); ?></strong>
                    <span><?php echo htmlspecialchars($similar['headquarters'] ?? 'Location not specified'); ?></span>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </aside>
    </div>
  </div>
</div>

<style>
  /* =====================================================
     Company Profile - Using Global Theme Variables
     ===================================================== */
  .company-profile-page {
    min-height: 100vh;
    padding-top: 70px;
    background: var(--bg-primary);
  }

  /* Hero Section */
  .company-hero {
    background: linear-gradient(180deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
    padding: 4rem 0;
    margin-bottom: 3rem;
    border-bottom: 1px solid var(--border-color);
    position: relative;
    overflow: hidden;
  }

  .company-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(0, 230, 118, 0.06) 0%, transparent 70%);
    pointer-events: none;
  }

  .company-hero::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(64, 196, 255, 0.04) 0%, transparent 70%);
    pointer-events: none;
  }

  .company-hero-content {
    display: flex;
    align-items: flex-start;
    gap: 2.5rem;
    position: relative;
    z-index: 1;
  }

  .company-logo-large {
    width: 130px;
    height: 130px;
    border-radius: var(--radius-lg);
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow: var(--shadow-lg);
    transition: all var(--transition-normal);
  }

  .company-logo-large:hover {
    transform: scale(1.02);
    box-shadow: var(--shadow-glow);
    border-color: var(--accent-primary);
  }

  .company-logo-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .company-logo-large span {
    font-family: var(--font-heading);
    font-size: 2.5rem;
    background: var(--accent-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .company-badges {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
  }

  .verified-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(0, 230, 118, 0.15);
    color: var(--accent-primary);
    border-radius: var(--radius-full);
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid rgba(0, 230, 118, 0.25);
  }

  .industry-badge {
    padding: 0.5rem 1rem;
    background: rgba(64, 196, 255, 0.15);
    color: var(--info);
    border-radius: var(--radius-full);
    font-size: 0.8rem;
    font-weight: 500;
    border: 1px solid rgba(64, 196, 255, 0.2);
  }

  .company-hero-info h1 {
    font-size: 2.5rem;
    margin: 0 0 1.25rem;
    color: var(--text-primary);
    font-family: var(--font-heading);
    letter-spacing: 0.02em;
    line-height: 1.2;
  }

  .company-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-bottom: 2rem;
  }

  .company-meta span,
  .company-meta a {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    color: var(--text-secondary);
    text-decoration: none;
    transition: color var(--transition-fast);
  }

  .company-meta a:hover {
    color: var(--accent-primary);
  }

  .company-meta i {
    color: var(--accent-primary);
    font-size: 0.9rem;
  }

  .company-stats-mini {
    display: flex;
    gap: 3rem;
  }

  .stat-item {
    text-align: left;
  }

  .stat-value {
    display: block;
    font-size: 2.25rem;
    font-weight: 700;
    font-family: var(--font-heading);
    background: var(--accent-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.2;
  }

  .stat-label {
    font-size: 0.8rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  /* Content Grid */
  .company-content-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 2.5rem;
    padding-bottom: 4rem;
  }

  /* Glass Card */
  .glass-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 1.75rem;
    border: 1px solid var(--border-color);
    backdrop-filter: var(--glass-blur);
    -webkit-backdrop-filter: var(--glass-blur);
    transition: all var(--transition-normal);
  }

  .glass-card:hover {
    border-color: var(--border-light);
    box-shadow: var(--shadow-md);
  }

  .company-section {
    margin-bottom: 2rem;
  }

  .company-section h2 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.35rem;
    font-family: var(--font-heading);
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    letter-spacing: 0.02em;
  }

  .company-section h2 i {
    color: var(--accent-primary);
    font-size: 1.1rem;
  }

  .about-content {
    color: var(--text-secondary);
    line-height: 1.8;
    font-size: 0.95rem;
  }

  .placeholder-text {
    color: var(--text-muted);
    font-style: italic;
  }

  .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
  }

  .section-header h2 {
    margin: 0;
    padding: 0;
    border: none;
  }

  .job-count {
    padding: 0.4rem 1rem;
    background: rgba(0, 230, 118, 0.15);
    color: var(--accent-primary);
    border-radius: var(--radius-full);
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid rgba(0, 230, 118, 0.25);
  }

  /* Jobs List */
  .jobs-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .job-list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: var(--bg-tertiary);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    transition: all var(--transition-normal);
    position: relative;
    overflow: hidden;
  }

  .job-list-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: var(--accent-gradient);
    opacity: 0;
    transition: opacity var(--transition-normal);
  }

  .job-list-item:hover {
    border-color: var(--accent-primary);
    transform: translateY(-4px);
    box-shadow: var(--shadow-glow);
  }

  .job-list-item:hover::before {
    opacity: 1;
  }

  .job-main-info h3 {
    margin: 0 0 0.75rem;
    font-size: 1.1rem;
    font-family: var(--font-body);
    font-weight: 600;
  }

  .job-main-info h3 a {
    color: var(--text-primary);
    text-decoration: none;
    transition: color var(--transition-fast);
  }

  .job-main-info h3 a:hover {
    color: var(--accent-primary);
  }

  .job-details {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    margin-bottom: 0.75rem;
  }

  .job-details span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
  }

  .job-details span i {
    font-size: 0.8rem;
    color: var(--text-muted);
  }

  .job-type {
    font-weight: 500;
  }

  .job-type.full-time {
    color: var(--accent-primary) !important;
  }

  .job-type.part-time {
    color: var(--info) !important;
  }

  .job-type.contract {
    color: var(--warning) !important;
  }

  .job-type.remote {
    color: #ce93d8 !important;
  }

  .job-type.internship {
    color: #4DD0E1 !important;
  }

  .salary {
    color: var(--accent-primary) !important;
    font-weight: 600;
  }

  .job-tags {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
  }

  .job-tags span {
    padding: 0.35rem 0.75rem;
    background: var(--bg-tertiary);
    border-radius: var(--radius-sm);
    font-size: 0.75rem;
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
  }

  .job-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-shrink: 0;
  }

  .deadline {
    font-size: 0.8rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.4rem;
  }

  .deadline.urgent {
    color: var(--warning);
  }

  .save-btn {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    color: var(--text-muted);
    cursor: pointer;
    transition: all var(--transition-normal);
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .save-btn:hover {
    border-color: var(--error);
    color: var(--error);
    transform: scale(1.1);
  }

  .save-btn.active {
    color: var(--error);
    background: rgba(255, 82, 82, 0.15);
    border-color: rgba(255, 82, 82, 0.3);
  }

  .btn {
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius-md);
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
    transition: all var(--transition-normal);
    text-decoration: none;
    font-size: 0.9rem;
  }

  .btn-primary {
    background: var(--accent-gradient);
    color: var(--text-inverse);
    box-shadow: var(--accent-glow);
  }

  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 30px rgba(0, 230, 118, 0.4);
    color: var(--text-inverse);
  }

  .btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
  }

  /* Sidebar */
  .sidebar-card {
    margin-bottom: 1.5rem;
  }

  .sidebar-card h3 {
    font-size: 1rem;
    font-family: var(--font-heading);
    margin-bottom: 1.25rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    letter-spacing: 0.02em;
  }

  .info-list {
    list-style: none;
    padding: 0;
    margin: 0;
  }

  .info-list li {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border-color);
  }

  .info-list li:last-child {
    border-bottom: none;
    padding-bottom: 0;
  }

  .info-list li:first-child {
    padding-top: 0;
  }

  .info-list li i {
    color: var(--accent-primary);
    width: 20px;
    margin-top: 0.15rem;
    font-size: 0.95rem;
  }

  .info-list li div {
    flex: 1;
  }

  .info-list li strong {
    display: block;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-bottom: 0.35rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
  }

  .info-list li span {
    color: var(--text-primary);
    font-size: 0.95rem;
  }

  .contact-links {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .contact-link {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-tertiary);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    text-decoration: none;
    transition: all var(--transition-normal);
    border: 1px solid var(--border-color);
  }

  .contact-link:hover {
    background: var(--bg-hover);
    border-color: var(--accent-primary);
    transform: translateX(5px);
    color: var(--accent-primary);
  }

  .contact-link i {
    color: var(--accent-primary);
    width: 20px;
    text-align: center;
  }

  .contact-link span {
    font-size: 0.9rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .social-links {
    display: flex;
    gap: 0.75rem;
  }

  .social-btn {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    transition: all var(--transition-normal);
    font-size: 1.1rem;
  }

  .social-btn:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
    color: white;
  }

  .social-btn.linkedin {
    background: linear-gradient(135deg, #0077b5, #005885);
  }

  .social-btn.twitter {
    background: linear-gradient(135deg, #1da1f2, #0d8ddb);
  }

  .social-btn.facebook {
    background: linear-gradient(135deg, #1877f2, #0d65d9);
  }

  .similar-companies-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .similar-company-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-tertiary);
    border-radius: var(--radius-md);
    text-decoration: none;
    transition: all var(--transition-normal);
    border: 1px solid var(--border-color);
  }

  .similar-company-item:hover {
    background: var(--bg-hover);
    border-color: var(--accent-primary);
    transform: translateX(5px);
  }

  .company-logo-small {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    font-family: var(--font-heading);
    font-size: 0.85rem;
    color: var(--accent-primary);
    flex-shrink: 0;
  }

  .company-logo-small img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .company-mini-info strong {
    display: block;
    color: var(--text-primary);
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
  }

  .company-mini-info span {
    font-size: 0.8rem;
    color: var(--text-muted);
  }

  /* Empty State */
  .empty-state {
    text-align: center;
    padding: var(--spacing-3xl) var(--spacing-xl);
  }

  .empty-state i {
    font-size: 4rem;
    color: var(--accent-primary);
    opacity: 0.25;
    margin-bottom: 1.5rem;
    display: block;
  }

  .empty-state p {
    color: var(--text-secondary);
    margin: 0;
    font-size: 1.1rem;
  }

  .empty-state .subtext {
    color: var(--text-muted);
    font-size: 0.9rem;
    margin-top: 0.5rem;
  }

  /* Responsive */
  @media (max-width: 1024px) {
    .company-content-grid {
      grid-template-columns: 1fr;
    }

    .company-sidebar {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1.5rem;
    }

    .company-sidebar .sidebar-card {
      margin-bottom: 0;
    }
  }

  @media (max-width: 768px) {
    .company-hero {
      padding: 3rem 0;
    }

    .company-hero-content {
      flex-direction: column;
      align-items: center;
      text-align: center;
    }

    .company-logo-large {
      width: 120px;
      height: 120px;
    }

    .company-hero-info h1 {
      font-size: 2rem;
    }

    .company-badges {
      justify-content: center;
    }

    .company-meta {
      justify-content: center;
    }

    .company-stats-mini {
      justify-content: center;
    }

    .company-sidebar {
      grid-template-columns: 1fr;
    }

    .job-list-item {
      flex-direction: column;
      align-items: flex-start;
      gap: 1.25rem;
    }

    .job-actions {
      width: 100%;
      justify-content: space-between;
    }
  }

  @media (max-width: 480px) {
    .company-hero-info h1 {
      font-size: 1.75rem;
    }

    .stat-value {
      font-size: 1.75rem;
    }

    .company-stats-mini {
      gap: 2rem;
    }
  }
</style>

<script>
  function toggleSave(jobId, btn) {
    <?php if (!isset($_SESSION['user_id'])): ?>
      window.location.href = '../auth/login.php';
      return;
    <?php endif; ?>

    const formData = new FormData();
    formData.append('ajax_action', 'toggle');
    formData.append('job_id', jobId);

    fetch('../seeker/saved-jobs.php', {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          btn.classList.toggle('active', data.saved);
          showNotification(data.message, 'success');
        }
      })
      .catch(error => {
        showNotification('Error updating saved job', 'error');
      });
  }
</script>

<?php include '../includes/footer.php'; ?>