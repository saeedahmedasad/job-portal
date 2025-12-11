<?php

/**
 * JobNexus - Jobs Listing Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Job.php';

$jobModel = new Job();

// Get categories for filter
$categories = $jobModel->getCategories();

// Build filters from query params
$filters = [
    'search' => sanitize($_GET['search'] ?? ''),
    'category' => intval($_GET['category'] ?? 0),
    'location_type' => sanitize($_GET['location_type'] ?? ''),
    'job_type' => sanitize($_GET['job_type'] ?? ''),
    'experience_level' => sanitize($_GET['experience_level'] ?? ''),
    'salary_min' => intval($_GET['salary_min'] ?? 0),
    'sort' => sanitize($_GET['sort'] ?? 'newest')
];

// Get current page
$page = max(1, intval($_GET['page'] ?? 1));

// Get jobs
$result = $jobModel->getActive($filters, $page, JOBS_PER_PAGE);
$jobs = $result['jobs'];
$totalPages = $result['pages'];
$totalJobs = $result['total'];

$pageTitle = 'Find Jobs';
include __DIR__ . '/../includes/header.php';
?>

<div class="jobs-page">
    <div class="container">
        <!-- Search Header -->
        <div class="jobs-header">
            <div class="jobs-header-content">
                <h1>Find Your Perfect Job</h1>
                <?php if (!empty($filters['search'])): ?>
                    <p class="search-results-info">
                        <span class="results-count"><?php echo number_format($totalJobs); ?></span>
                        <?php echo $totalJobs === 1 ? 'job' : 'jobs'; ?> found for
                        "<span class="search-term"><?php echo sanitize($filters['search']); ?></span>"
                    </p>
                <?php else: ?>
                    <p><?php echo number_format($totalJobs); ?> jobs available</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Search Form -->
        <form class="search-form" method="GET" action="">
            <div class="search-box">
                <div class="search-input-group">
                    <i class="fas fa-search"></i>
                    <input
                        type="text"
                        name="search"
                        class="search-input"
                        placeholder="Job title, keywords, or company..."
                        value="<?php echo sanitize($filters['search']); ?>">
                </div>
                <button type="submit" class="btn btn-primary search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        </form>

        <div class="jobs-layout">
            <!-- Filters Sidebar -->
            <aside class="filters-sidebar">
                <form id="filtersForm" method="GET" action="">
                    <input type="hidden" name="search" value="<?php echo sanitize($filters['search']); ?>">

                    <!-- Category Filter -->
                    <div class="filter-section">
                        <h3 class="filter-title">Category</h3>
                        <div class="filter-options">
                            <label class="filter-option">
                                <input type="radio" name="category" value="" <?php echo !$filters['category'] ? 'checked' : ''; ?>>
                                <span>All Categories</span>
                            </label>
                            <?php foreach ($categories as $cat): ?>
                                <label class="filter-option">
                                    <input type="radio" name="category" value="<?php echo $cat['id']; ?>" <?php echo $filters['category'] == $cat['id'] ? 'checked' : ''; ?>>
                                    <span><?php echo sanitize($cat['name']); ?></span>
                                    <span class="filter-count"><?php echo $cat['job_count']; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Location Type Filter -->
                    <div class="filter-section">
                        <h3 class="filter-title">Location Type</h3>
                        <div class="filter-options">
                            <label class="filter-option">
                                <input type="radio" name="location_type" value="" <?php echo !$filters['location_type'] ? 'checked' : ''; ?>>
                                <span>All Types</span>
                            </label>
                            <?php foreach (LOCATION_TYPES as $key => $label): ?>
                                <label class="filter-option">
                                    <input type="radio" name="location_type" value="<?php echo $key; ?>" <?php echo $filters['location_type'] === $key ? 'checked' : ''; ?>>
                                    <span><?php echo $label; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Job Type Filter -->
                    <div class="filter-section">
                        <h3 class="filter-title">Job Type</h3>
                        <div class="filter-options">
                            <label class="filter-option">
                                <input type="radio" name="job_type" value="" <?php echo !$filters['job_type'] ? 'checked' : ''; ?>>
                                <span>All Types</span>
                            </label>
                            <?php foreach (JOB_TYPES as $key => $label): ?>
                                <label class="filter-option">
                                    <input type="radio" name="job_type" value="<?php echo $key; ?>" <?php echo $filters['job_type'] === $key ? 'checked' : ''; ?>>
                                    <span><?php echo $label; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Experience Level Filter -->
                    <div class="filter-section">
                        <h3 class="filter-title">Experience Level</h3>
                        <div class="filter-options">
                            <label class="filter-option">
                                <input type="radio" name="experience_level" value="" <?php echo !$filters['experience_level'] ? 'checked' : ''; ?>>
                                <span>All Levels</span>
                            </label>
                            <?php foreach (EXPERIENCE_LEVELS as $key => $label): ?>
                                <label class="filter-option">
                                    <input type="radio" name="experience_level" value="<?php echo $key; ?>" <?php echo $filters['experience_level'] === $key ? 'checked' : ''; ?>>
                                    <span><?php echo $label; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                    <a href="<?php echo BASE_URL; ?>/jobs/" class="btn btn-ghost btn-block mt-2">Clear All</a>
                </form>
            </aside>

            <!-- Jobs List -->
            <div class="jobs-content">
                <!-- Sort Bar -->
                <div class="jobs-toolbar">
                    <p class="jobs-count">
                        Showing <?php echo count($jobs); ?> of <?php echo number_format($totalJobs); ?> jobs
                    </p>
                    <div class="jobs-sort">
                        <label>Sort by:</label>
                        <select name="sort" onchange="updateSort(this.value)">
                            <option value="newest" <?php echo $filters['sort'] === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $filters['sort'] === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="salary_high" <?php echo $filters['sort'] === 'salary_high' ? 'selected' : ''; ?>>Highest Salary</option>
                            <option value="salary_low" <?php echo $filters['sort'] === 'salary_low' ? 'selected' : ''; ?>>Lowest Salary</option>
                        </select>
                    </div>
                </div>

                <!-- Jobs Grid -->
                <?php if (!empty($jobs)): ?>
                    <div class="jobs-grid">
                        <?php foreach ($jobs as $job): ?>
                            <a href="<?php echo BASE_URL; ?>/jobs/view.php?id=<?php echo $job['id']; ?>" class="job-card <?php echo $job['is_featured'] ? 'featured' : ''; ?>">
                                <?php if ($job['is_featured']): ?>
                                    <span class="job-badge featured">Featured</span>
                                <?php endif; ?>
                                <?php if ($job['is_urgent']): ?>
                                    <span class="job-badge urgent">Urgent</span>
                                <?php endif; ?>

                                <div class="job-card-header">
                                    <div class="job-card-logo">
                                        <?php if ($job['logo']): ?>
                                            <img src="<?php echo LOGO_URL . sanitize($job['logo']); ?>" alt="<?php echo sanitize($job['company_name']); ?>">
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
                                        <i class="fas fa-<?php echo $job['location_type'] === 'remote' ? 'home' : ($job['location_type'] === 'hybrid' ? 'building' : 'map-marker-alt'); ?>"></i>
                                        <?php echo ucfirst($job['location_type']); ?>
                                    </span>
                                    <span class="tag">
                                        <i class="fas fa-clock"></i>
                                        <?php echo JOB_TYPES[$job['job_type']] ?? $job['job_type']; ?>
                                    </span>
                                    <?php if ($job['location']): ?>
                                        <span class="tag">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo sanitize(truncate($job['location'], 20)); ?>
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
                                        </span>
                                    <?php else: ?>
                                        <span class="job-card-salary text-muted">Salary not disclosed</span>
                                    <?php endif; ?>
                                    <span class="job-card-time"><?php echo timeAgo($job['published_at'] ?? $job['created_at']); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-item">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            if ($startPage > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-item">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="pagination-item disabled">...</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="pagination-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="pagination-item disabled">...</span>
                                <?php endif; ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" class="pagination-item"><?php echo $totalPages; ?></a>
                            <?php endif; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-item">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-results">
                        <div class="no-results-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <?php if (!empty($filters['search'])): ?>
                            <h3>No jobs found for "<?php echo sanitize($filters['search']); ?>"</h3>
                            <p>We couldn't find any jobs matching your search. Try these suggestions:</p>
                            <ul class="search-suggestions">
                                <li><i class="fas fa-check text-accent"></i> Check for spelling errors</li>
                                <li><i class="fas fa-check text-accent"></i> Try more general keywords</li>
                                <li><i class="fas fa-check text-accent"></i> Remove some filters</li>
                                <li><i class="fas fa-check text-accent"></i> Browse all available jobs</li>
                            </ul>
                        <?php else: ?>
                            <h3>No jobs found</h3>
                            <p>Try adjusting your filter criteria to find more opportunities.</p>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>/jobs/" class="btn btn-primary" style="margin-top: var(--spacing-lg);">
                            <i class="fas fa-refresh"></i> Clear All Filters
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .jobs-page {
        padding: 100px 0 var(--spacing-3xl);
        min-height: 100vh;
    }

    .jobs-header {
        text-align: center;
        margin-bottom: var(--spacing-xl);
    }

    .jobs-header h1 {
        font-size: 2.5rem;
        margin-bottom: var(--spacing-sm);
    }

    .jobs-header p {
        color: var(--text-secondary);
        font-size: 1.1rem;
    }

    .search-form {
        max-width: 800px;
        margin: 0 auto var(--spacing-xl);
    }

    .search-form .search-box {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-xl);
        padding: 0.5rem;
        display: flex;
        gap: var(--spacing-sm);
    }

    .jobs-layout {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: var(--spacing-xl);
    }

    /* Filters Sidebar */
    .filters-sidebar {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: var(--spacing-lg);
        height: fit-content;
        position: sticky;
        top: 90px;
    }

    .filter-section {
        margin-bottom: var(--spacing-lg);
        padding-bottom: var(--spacing-lg);
        border-bottom: 1px solid var(--border-color);
    }

    .filter-section:last-of-type {
        border-bottom: none;
    }

    .filter-title {
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: var(--spacing-md);
        color: var(--text-primary);
    }

    .filter-options {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
        max-height: 200px;
        overflow-y: auto;
    }

    .filter-option {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        cursor: pointer;
        padding: var(--spacing-xs) 0;
        font-size: 0.9rem;
        color: var(--text-secondary);
        transition: color var(--transition-fast);
    }

    .filter-option:hover {
        color: var(--text-primary);
    }

    .filter-option input {
        accent-color: var(--accent-primary);
    }

    .filter-option span:first-of-type {
        flex: 1;
    }

    .filter-count {
        font-size: 0.8rem;
        color: var(--text-muted);
        background: var(--bg-tertiary);
        padding: 0.125rem 0.5rem;
        border-radius: var(--radius-full);
    }

    /* Jobs Content */
    .jobs-content {
        min-height: 500px;
    }

    .jobs-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--spacing-lg);
    }

    .jobs-count {
        color: var(--text-secondary);
        font-size: 0.95rem;
    }

    .jobs-sort {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
    }

    .jobs-sort label {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }

    .jobs-sort select {
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 0.5rem 1rem;
        color: var(--text-primary);
        font-size: 0.9rem;
        cursor: pointer;
    }

    .job-badge {
        position: absolute;
        top: var(--spacing-md);
        right: var(--spacing-md);
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: var(--radius-full);
        text-transform: uppercase;
    }

    .job-badge.featured {
        background: rgba(0, 230, 118, 0.15);
        color: var(--accent-primary);
    }

    .job-badge.urgent {
        background: rgba(255, 82, 82, 0.15);
        color: var(--error);
    }

    @media (max-width: 1024px) {
        .jobs-layout {
            grid-template-columns: 1fr;
        }

        .filters-sidebar {
            position: static;
        }
    }

    @media (max-width: 640px) {
        .jobs-header h1 {
            font-size: 1.75rem;
        }

        .jobs-toolbar {
            flex-direction: column;
            gap: var(--spacing-md);
            align-items: flex-start;
        }
    }
</style>

<script>
    function updateSort(value) {
        const url = new URL(window.location);
        url.searchParams.set('sort', value);
        window.location = url;
    }

    // Auto-submit filters on change
    document.querySelectorAll('.filter-option input').forEach(input => {
        input.addEventListener('change', () => {
            document.getElementById('filtersForm').submit();
        });
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>