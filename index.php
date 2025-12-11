<?php

/**
 * JobNexus - Home Page
 * Premium Job Portal Landing Page
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Job.php';
require_once __DIR__ . '/classes/Company.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Application.php';

$jobModel = new Job();
$companyModel = new Company();
$userModel = new User();
$appModel = new Application();

// Get featured jobs
$featuredJobs = $jobModel->getFeatured(6);

// Get all categories
$categories = $jobModel->getCategories();

// Get job stats
$stats = $jobModel->getStats();
$companyStats = $companyModel->getStats();
$userStats = $userModel->getStats();
$appStats = $appModel->getStats();

// Get recent jobs
$recentJobs = $jobModel->getActive([], 1, 9);


$pageTitle = SITE_TAGLINE;
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section with Search -->
<section class="hero-search">
  <div class="container">
    <div class="hero-content">
      <h1 class="hero-title">
        Find Your <span class="heading-gradient">Dream Career</span>
      </h1>
      <p class="hero-subtitle">
        Discover thousands of job opportunities with the world's leading companies.
        Your next career move starts here.
      </p>
    </div>

    <div class="search-container">
      <form action="<?php echo BASE_URL; ?>/jobs/" method="GET" class="search-box">
        <div class="search-input-group">
          <i class="fas fa-search"></i>
          <input type="text" name="search" class="search-input" placeholder="Job title, keywords, or company...">
        </div>

        <div class="search-divider"></div>

        <select name="category" class="search-select">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['name']); ?></option>
          <?php endforeach; ?>
        </select>

        <div class="search-divider"></div>

        <select name="location_type" class="search-select">
          <option value="">Location Type</option>
          <option value="remote">Remote</option>
          <option value="onsite">On-site</option>
          <option value="hybrid">Hybrid</option>
        </select>

        <button type="submit" class="btn btn-primary search-btn">
          <i class="fas fa-search"></i>
          Search Jobs
        </button>
      </form>

      <!-- <div class="quick-filters">
        <button class="quick-filter" data-filter="remote">🏠 Remote</button>
        <button class="quick-filter" data-filter="technology">💻 Technology</button>
        <button class="quick-filter" data-filter="design">🎨 Design</button>
        <button class="quick-filter" data-filter="marketing">📢 Marketing</button>
        <button class="quick-filter" data-filter="finance">💰 Finance</button>
      </div> -->
    </div>
  </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
  <div class="container">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon green">
          <i class="fas fa-briefcase"></i>
        </div>
        <div class="stat-value"><?php echo number_format($stats['active_jobs'] ?? 0); ?>+</div>
        <div class="stat-label">Active Jobs</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon blue">
          <i class="fas fa-building"></i>
        </div>
        <div class="stat-value"><?php echo number_format($companyStats['total'] ?? 0); ?>+</div>
        <div class="stat-label">Companies Hiring</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon orange">
          <i class="fas fa-users"></i>
        </div>
        <div class="stat-value"><?php echo number_format($userStats['seeker_count'] ?? 0); ?>+</div>
        <div class="stat-label">Job Seekers</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon purple">
          <i class="fas fa-handshake"></i>
        </div>
        <div class="stat-value"><?php echo number_format($appStats['hired'] ?? 0); ?>+</div>
        <div class="stat-label">Successful Hires</div>
      </div>
    </div>
  </div>
</section>

<!-- Featured Jobs Section -->
<?php if (!empty($featuredJobs)): ?>
  <section class="jobs-section">
    <div class="container">
      <div class="section-header">
        <div>
          <h2 class="section-title">Featured Opportunities</h2>
          <p class="section-subtitle">Handpicked jobs from top companies</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/jobs/" class="btn btn-secondary">
          View All Jobs <i class="fas fa-arrow-right"></i>
        </a>
      </div>

      <div class="jobs-grid">
        <?php foreach ($featuredJobs as $job): ?>
          <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $job['id']; ?>"
            class="job-card <?php echo $job['is_featured'] ? 'featured' : ''; ?>">
            <div class="job-card-header">
              <div class="job-card-logo">
                <?php if ($job['logo']): ?>
                  <img src="<?php echo LOGO_URL . sanitize($job['logo']); ?>"
                    alt="<?php echo sanitize($job['company_name']); ?>">
                <?php else: ?>
                  <span class="initials"><?php echo getInitials($job['company_name']); ?></span>
                <?php endif; ?>
              </div>
              <div class="job-card-info">
                <h3 class="job-card-title"><?php echo sanitize($job['title']); ?></h3>
                <p class="job-card-company"><?php echo sanitize($job['company_name']); ?></p>
              </div>
            </div>

            <div class="job-card-meta">
              <span class="tag tag-<?php echo $job['location_type']; ?>">
                <i
                  class="fas fa-<?php echo $job['location_type'] === 'remote' ? 'home' : ($job['location_type'] === 'hybrid' ? 'building' : 'map-marker-alt'); ?>"></i>
                <?php echo ucfirst($job['location_type']); ?>
              </span>
              <span class="tag">
                <i class="fas fa-clock"></i>
                <?php echo JOB_TYPES[$job['job_type']] ?? $job['job_type']; ?>
              </span>
              <?php if ($job['location']): ?>
                <span class="tag">
                  <i class="fas fa-map-marker-alt"></i>
                  <?php echo sanitize($job['location']); ?>
                </span>
              <?php endif; ?>
            </div>

            <?php if ($job['skills_required']): ?>
              <div class="job-card-tags">
                <?php
                $skills = is_array($job['skills_required']) ? $job['skills_required'] : json_decode($job['skills_required'], true);
                if ($skills):
                  $displaySkills = array_slice($skills, 0, 3);
                  foreach ($displaySkills as $skill):
                ?>
                    <span class="tag tag-primary"><?php echo sanitize($skill); ?></span>
                  <?php
                  endforeach;
                  if (count($skills) > 3):
                  ?>
                    <span class="tag">+<?php echo count($skills) - 3; ?> more</span>
                <?php
                  endif;
                endif;
                ?>
              </div>
            <?php endif; ?>

            <div class="job-card-footer">
              <?php if ($job['show_salary'] && $job['salary_min']): ?>
                <span class="job-card-salary">
                  <?php echo formatCurrency($job['salary_min']); ?>
                  <?php if ($job['salary_max']): ?>
                    - <?php echo formatCurrency($job['salary_max']); ?>
                  <?php endif; ?>
                  <small>/yr</small>
                </span>
              <?php else: ?>
                <span class="job-card-salary text-muted">Salary not disclosed</span>
              <?php endif; ?>
              <span class="job-card-time"><?php echo timeAgo($job['published_at'] ?? $job['created_at']); ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<!-- Categories Section -->
<section class="categories-section">
  <div class="container">
    <div class="section-header">
      <div>
        <h2 class="section-title">Browse by Category</h2>
        <p class="section-subtitle">Explore jobs across different industries</p>
      </div>
    </div>

    <div class="categories-grid">
      <?php foreach (array_slice($categories, 0, 8) as $category): ?>
        <a href="<?php echo BASE_URL; ?>/jobs/?category=<?php echo $category['id']; ?>" class="category-card">
          <div class="category-icon">
            <i class="fas <?php echo $category['icon'] ?? 'fa-folder'; ?>"></i>
          </div>
          <h3 class="category-name"><?php echo sanitize($category['name']); ?></h3>
          <span class="category-count"><?php echo number_format($category['job_count'] ?? 0); ?> jobs</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Recent Jobs Section -->
<?php if (!empty($recentJobs['jobs'])): ?>
  <section class="jobs-section bg-secondary">
    <div class="container">
      <div class="section-header">
        <div>
          <h2 class="section-title">Latest Job Postings</h2>
          <p class="section-subtitle">Fresh opportunities added every day</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/jobs/" class="btn btn-secondary">
          Browse All <i class="fas fa-arrow-right"></i>
        </a>
      </div>

      <div class="jobs-grid">
        <?php foreach ($recentJobs['jobs'] as $job): ?>
          <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $job['id']; ?>" class="job-card">
            <div class="job-card-header">
              <div class="job-card-logo">
                <?php if ($job['logo']): ?>
                  <img src="<?php echo LOGO_URL . sanitize($job['logo']); ?>"
                    alt="<?php echo sanitize($job['company_name']); ?>">
                <?php else: ?>
                  <span class="initials"><?php echo getInitials($job['company_name']); ?></span>
                <?php endif; ?>
              </div>
              <div class="job-card-info">
                <h3 class="job-card-title"><?php echo sanitize($job['title']); ?></h3>
                <p class="job-card-company"><?php echo sanitize($job['company_name']); ?></p>
              </div>
            </div>

            <div class="job-card-meta">
              <span class="tag tag-<?php echo $job['location_type']; ?>">
                <?php echo ucfirst($job['location_type']); ?>
              </span>
              <span class="tag">
                <?php echo JOB_TYPES[$job['job_type']] ?? $job['job_type']; ?>
              </span>
            </div>

            <div class="job-card-footer">
              <?php if ($job['show_salary'] && $job['salary_min']): ?>
                <span class="job-card-salary">
                  <?php echo formatCurrency($job['salary_min']); ?>+
                </span>
              <?php else: ?>
                <span class="job-card-salary text-muted">-</span>
              <?php endif; ?>
              <span class="job-card-time"><?php echo timeAgo($job['published_at'] ?? $job['created_at']); ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<!-- CTA Section -->
<section class="cta-section">
  <div class="container">
    <div class="cta-content">
      <div class="cta-text">
        <h2>Ready to Take the Next Step?</h2>
        <p>Join millions of professionals who have found their dream careers through JobNexus.</p>
      </div>
      <div class="cta-actions">
        <a href="<?php echo BASE_URL; ?>/auth/register.php" class="btn btn-primary btn-lg">
          <i class="fas fa-rocket"></i> Get Started Free
        </a>
        <a href="<?php echo BASE_URL; ?>/auth/register.php?role=hr" class="btn btn-secondary btn-lg">
          <i class="fas fa-building"></i> For Employers
        </a>
      </div>
    </div>
  </div>
</section>

<style>
  /* Stats Section */
  .stats-section {
    padding: var(--spacing-3xl) 0;
    margin-top: -60px;
    position: relative;
    z-index: 10;
  }

  .stats-section .stats-grid {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-xl);
    padding: var(--spacing-xl);
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--spacing-xl);
    box-shadow: var(--shadow-lg);
  }

  .stats-section .stat-card {
    background: transparent;
    border: none;
    text-align: center;
    padding: var(--spacing-md);
  }

  .stats-section .stat-card:hover {
    transform: none;
  }

  .stats-section .stat-icon {
    margin: 0 auto var(--spacing-md);
  }

  /* Categories Section */
  .categories-section {
    padding: var(--spacing-3xl) 0;
    background: var(--bg-secondary);
  }

  .categories-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--spacing-lg);
  }

  .category-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: var(--spacing-xl);
    text-align: center;
    transition: all var(--transition-normal);
    text-decoration: none;
  }

  .category-card:hover {
    border-color: var(--accent-primary);
    transform: translateY(-4px);
    box-shadow: var(--shadow-glow);
  }

  .category-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--radius-lg);
    background: rgba(0, 230, 118, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto var(--spacing-md);
    font-size: 1.5rem;
    color: var(--accent-primary);
  }

  .category-name {
    font-family: var(--font-body);
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: var(--spacing-xs);
  }

  .category-count {
    font-size: 0.9rem;
    color: var(--text-muted);
  }

  /* CTA Section */
  .cta-section {
    padding: var(--spacing-3xl) 0;
    background: linear-gradient(135deg, rgba(0, 230, 118, 0.1) 0%, var(--bg-primary) 100%);
  }

  .cta-content {
    text-align: center;
    max-width: 700px;
    margin: 0 auto;
  }

  .cta-text h2 {
    font-size: 2.5rem;
    margin-bottom: var(--spacing-md);
  }

  .cta-text p {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin-bottom: var(--spacing-xl);
  }

  .cta-actions {
    display: flex;
    justify-content: center;
    gap: var(--spacing-md);
    flex-wrap: wrap;
  }

  .bg-secondary {
    background: var(--bg-secondary);
  }

  @media (max-width: 1024px) {

    .stats-section .stats-grid,
    .categories-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media (max-width: 640px) {
    .stats-section .stats-grid {
      grid-template-columns: repeat(2, 1fr);
      gap: var(--spacing-md);
    }

    .categories-grid {
      grid-template-columns: 1fr;
    }

    .cta-actions {
      flex-direction: column;
    }

    .cta-actions .btn {
      width: 100%;
    }
  }

  .search-select option {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
  }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>