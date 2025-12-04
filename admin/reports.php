<?php
/**
 * JobNexus - Admin Reports & Analytics
 */

require_once '../config/config.php';
require_once '../classes/Database.php';

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_ADMIN) {
  header('Location: ' . BASE_URL . '/auth/login.php');
  exit;
}

$db = Database::getInstance()->getConnection();

// Get date range filter
$dateRange = $_GET['range'] ?? '30days';
$customStart = $_GET['start'] ?? '';
$customEnd = $_GET['end'] ?? '';

// Calculate date range
switch ($dateRange) {
  case '7days':
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = date('Y-m-d');
    break;
  case '30days':
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');
    break;
  case '90days':
    $startDate = date('Y-m-d', strtotime('-90 days'));
    $endDate = date('Y-m-d');
    break;
  case 'year':
    $startDate = date('Y-m-d', strtotime('-1 year'));
    $endDate = date('Y-m-d');
    break;
  case 'custom':
    $startDate = $customStart ?: date('Y-m-d', strtotime('-30 days'));
    $endDate = $customEnd ?: date('Y-m-d');
    break;
  default:
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');
}

// User Statistics
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)");
$stmt->execute([$startDate, $endDate]);
$newUsers = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT role, COUNT(*) as count FROM users WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) GROUP BY role");
$stmt->execute([$startDate, $endDate]);
$usersByRole = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Job Statistics
$stmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)");
$stmt->execute([$startDate, $endDate]);
$newJobs = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM jobs WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) GROUP BY status");
$stmt->execute([$startDate, $endDate]);
$jobsByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $db->prepare("SELECT job_type, COUNT(*) as count FROM jobs WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) GROUP BY job_type");
$stmt->execute([$startDate, $endDate]);
$jobsByType = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Application Statistics
$stmt = $db->prepare("SELECT COUNT(*) FROM applications WHERE applied_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)");
$stmt->execute([$startDate, $endDate]);
$newApplications = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM applications WHERE applied_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) GROUP BY status");
$stmt->execute([$startDate, $endDate]);
$appsByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Company Statistics
$stmt = $db->prepare("SELECT COUNT(*) FROM companies WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)");
$stmt->execute([$startDate, $endDate]);
$newCompanies = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT verification_status, COUNT(*) as count FROM companies WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) GROUP BY verification_status");
$stmt->execute([$startDate, $endDate]);
$companiesByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Trend Data (daily counts for charts)
$stmt = $db->prepare("
  SELECT DATE(created_at) as date, COUNT(*) as count 
  FROM users 
  WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
  GROUP BY DATE(created_at) 
  ORDER BY date
");
$stmt->execute([$startDate, $endDate]);
$userTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("
  SELECT DATE(created_at) as date, COUNT(*) as count 
  FROM jobs 
  WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
  GROUP BY DATE(created_at) 
  ORDER BY date
");
$stmt->execute([$startDate, $endDate]);
$jobTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("
  SELECT DATE(applied_at) as date, COUNT(*) as count 
  FROM applications 
  WHERE applied_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
  GROUP BY DATE(applied_at) 
  ORDER BY date
");
$stmt->execute([$startDate, $endDate]);
$applicationTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top performing jobs
$stmt = $db->prepare("
  SELECT j.title, j.job_type, c.company_name, COUNT(a.id) as applications_count
  FROM jobs j
  LEFT JOIN applications a ON j.id = a.job_id AND a.applied_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
  LEFT JOIN companies c ON j.company_id = c.id
  WHERE j.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
  GROUP BY j.id
  ORDER BY applications_count DESC
  LIMIT 10
");
$stmt->execute([$startDate, $endDate, $startDate, $endDate]);
$topJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top hiring companies
$stmt = $db->prepare("
  SELECT c.company_name, c.industry, COUNT(j.id) as job_count, 
         (SELECT COUNT(*) FROM applications a2 JOIN jobs j2 ON a2.job_id = j2.id WHERE j2.company_id = c.id) as total_applications
  FROM companies c
  LEFT JOIN jobs j ON c.id = j.company_id AND j.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
  GROUP BY c.id
  HAVING job_count > 0
  ORDER BY job_count DESC
  LIMIT 10
");
$stmt->execute([$startDate, $endDate]);
$topCompanies = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Reports - JobNexus Admin";
include '../includes/header.php';
?>

<div class="dashboard-container">
  <!-- Sidebar -->
  <aside class="dashboard-sidebar">
    <div class="sidebar-header">
      <div class="admin-avatar">
        <?php echo strtoupper(substr($_SESSION['user_email'] ?? 'A', 0, 1)); ?>
      </div>
      <h3><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Admin'); ?></h3>
      <span class="role-badge admin">Administrator</span>
    </div>

    <nav class="sidebar-nav">
      <a href="<?php echo BASE_URL; ?>/admin/index.php" class="nav-item">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/admin/users.php" class="nav-item">
        <i class="fas fa-users"></i>
        <span>Manage Users</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/admin/companies.php" class="nav-item">
        <i class="fas fa-building"></i>
        <span>Companies</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/admin/jobs.php" class="nav-item">
        <i class="fas fa-briefcase"></i>
        <span>All Jobs</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/admin/reports.php" class="nav-item active">
        <i class="fas fa-chart-bar"></i>
        <span>Reports</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/admin/settings.php" class="nav-item">
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
        <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
        <p>View platform statistics and performance metrics</p>
      </div>
      <div class="header-right">
        <button onclick="window.print()" class="btn btn-outline">
          <i class="fas fa-print"></i> Print Report
        </button>
      </div>
    </div>

    <!-- Date Range Filter -->
    <div class="filters-card glass-card">
      <form method="GET" class="filters-form">
        <div class="filter-group">
          <label>Date Range</label>
          <select name="range" class="form-control" onchange="toggleCustomDates(this.value)">
            <option value="7days" <?php echo $dateRange === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
            <option value="30days" <?php echo $dateRange === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
            <option value="90days" <?php echo $dateRange === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
            <option value="year" <?php echo $dateRange === 'year' ? 'selected' : ''; ?>>Last Year</option>
            <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
          </select>
        </div>

        <div class="filter-group custom-dates" id="customDates"
          style="display: <?php echo $dateRange === 'custom' ? 'flex' : 'none'; ?>;">
          <input type="date" name="start" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
          <span>to</span>
          <input type="date" name="end" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
        </div>

        <div class="filter-actions">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-sync"></i> Update
          </button>
        </div>
      </form>
    </div>

    <!-- Overview Stats -->
    <div class="stats-grid">
      <div class="stat-card primary">
        <div class="stat-icon">
          <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($newUsers); ?></h3>
          <p>New Users</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">In selected period</span>
        </div>
      </div>

      <div class="stat-card success">
        <div class="stat-icon">
          <i class="fas fa-briefcase"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($newJobs); ?></h3>
          <p>Jobs Posted</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">In selected period</span>
        </div>
      </div>

      <div class="stat-card info">
        <div class="stat-icon">
          <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($newApplications); ?></h3>
          <p>Applications</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">In selected period</span>
        </div>
      </div>

      <div class="stat-card purple">
        <div class="stat-icon">
          <i class="fas fa-building"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo number_format($newCompanies); ?></h3>
          <p>New Companies</p>
        </div>
        <div class="stat-footer">
          <span class="stat-label">In selected period</span>
        </div>
      </div>
    </div>

    <!-- Breakdown Reports -->
    <div class="reports-grid">
      <!-- Users by Role -->
      <div class="data-table-card glass-card">
        <div class="table-header">
          <h3><i class="fas fa-users"></i> Users by Role</h3>
        </div>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Role</th>
                <th>Count</th>
                <th>Percentage</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $totalRoleUsers = array_sum($usersByRole) ?: 1;
              foreach ($usersByRole as $role => $count):
                ?>
                <tr>
                  <td>
                    <span class="role-badge <?php echo $role; ?>">
                      <?php echo ucfirst($role); ?>
                    </span>
                  </td>
                  <td><?php echo number_format($count); ?></td>
                  <td>
                    <div class="progress-bar-mini">
                      <div class="progress-fill" style="width: <?php echo round(($count / $totalRoleUsers) * 100); ?>%">
                      </div>
                      <span><?php echo round(($count / $totalRoleUsers) * 100, 1); ?>%</span>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($usersByRole)): ?>
                <tr>
                  <td colspan="3" class="text-center">No data for selected period</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Jobs by Status -->
      <div class="data-table-card glass-card">
        <div class="table-header">
          <h3><i class="fas fa-briefcase"></i> Jobs by Status</h3>
        </div>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Status</th>
                <th>Count</th>
                <th>Percentage</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $totalStatusJobs = array_sum($jobsByStatus) ?: 1;
              foreach ($jobsByStatus as $status => $count):
                ?>
                <tr>
                  <td>
                    <span class="status-badge status-<?php echo $status; ?>">
                      <?php echo ucfirst($status); ?>
                    </span>
                  </td>
                  <td><?php echo number_format($count); ?></td>
                  <td>
                    <div class="progress-bar-mini">
                      <div class="progress-fill" style="width: <?php echo round(($count / $totalStatusJobs) * 100); ?>%">
                      </div>
                      <span><?php echo round(($count / $totalStatusJobs) * 100, 1); ?>%</span>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($jobsByStatus)): ?>
                <tr>
                  <td colspan="3" class="text-center">No data for selected period</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Jobs by Type -->
      <div class="data-table-card glass-card">
        <div class="table-header">
          <h3><i class="fas fa-clock"></i> Jobs by Type</h3>
        </div>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Type</th>
                <th>Count</th>
                <th>Percentage</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $totalTypeJobs = array_sum($jobsByType) ?: 1;
              foreach ($jobsByType as $type => $count):
                ?>
                <tr>
                  <td>
                    <span class="job-type-badge <?php echo $type; ?>">
                      <?php echo ucfirst(str_replace('-', ' ', $type)); ?>
                    </span>
                  </td>
                  <td><?php echo number_format($count); ?></td>
                  <td>
                    <div class="progress-bar-mini">
                      <div class="progress-fill" style="width: <?php echo round(($count / $totalTypeJobs) * 100); ?>%">
                      </div>
                      <span><?php echo round(($count / $totalTypeJobs) * 100, 1); ?>%</span>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($jobsByType)): ?>
                <tr>
                  <td colspan="3" class="text-center">No data for selected period</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Applications by Status -->
      <div class="data-table-card glass-card">
        <div class="table-header">
          <h3><i class="fas fa-file-alt"></i> Applications by Status</h3>
        </div>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Status</th>
                <th>Count</th>
                <th>Percentage</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $totalApps = array_sum($appsByStatus) ?: 1;
              foreach ($appsByStatus as $status => $count):
                ?>
                <tr>
                  <td>
                    <span class="status-badge status-<?php echo $status; ?>">
                      <?php echo ucfirst($status); ?>
                    </span>
                  </td>
                  <td><?php echo number_format($count); ?></td>
                  <td>
                    <div class="progress-bar-mini">
                      <div class="progress-fill" style="width: <?php echo round(($count / $totalApps) * 100); ?>%"></div>
                      <span><?php echo round(($count / $totalApps) * 100, 1); ?>%</span>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($appsByStatus)): ?>
                <tr>
                  <td colspan="3" class="text-center">No data for selected period</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Top Performing -->
    <div class="reports-full-width">
      <!-- Top Jobs -->
      <div class="data-table-card glass-card">
        <div class="table-header">
          <h3><i class="fas fa-trophy"></i> Top Performing Jobs</h3>
        </div>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Job Title</th>
                <th>Company</th>
                <th>Type</th>
                <th>Applications</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topJobs as $index => $job): ?>
                <tr>
                  <td><span class="rank-badge"><?php echo $index + 1; ?></span></td>
                  <td><?php echo htmlspecialchars($job['title']); ?></td>
                  <td><?php echo htmlspecialchars($job['company_name'] ?? 'Unknown'); ?></td>
                  <td>
                    <span class="job-type-badge <?php echo $job['job_type']; ?>">
                      <?php echo ucfirst(str_replace('-', ' ', $job['job_type'])); ?>
                    </span>
                  </td>
                  <td><strong><?php echo number_format($job['applications_count']); ?></strong></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($topJobs)): ?>
                <tr>
                  <td colspan="5" class="text-center">No data for selected period</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Top Companies -->
      <div class="data-table-card glass-card">
        <div class="table-header">
          <h3><i class="fas fa-building"></i> Top Hiring Companies</h3>
        </div>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Company</th>
                <th>Industry</th>
                <th>Jobs Posted</th>
                <th>Total Applications</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topCompanies as $index => $company): ?>
                <tr>
                  <td><span class="rank-badge"><?php echo $index + 1; ?></span></td>
                  <td><?php echo htmlspecialchars($company['company_name']); ?></td>
                  <td><?php echo htmlspecialchars($company['industry'] ?? 'N/A'); ?></td>
                  <td><strong><?php echo number_format($company['job_count']); ?></strong></td>
                  <td><?php echo number_format($company['total_applications']); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($topCompanies)): ?>
                <tr>
                  <td colspan="5" class="text-center">No data for selected period</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </main>
</div>

<style>
  /* Reports-specific styles */
  .reports-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
  }

  .reports-full-width {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }

  .progress-bar-mini {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: var(--bg-tertiary);
    border-radius: var(--radius-full);
    padding: 0.25rem;
    height: 24px;
    min-width: 120px;
  }

  .progress-bar-mini .progress-fill {
    height: 16px;
    background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
    border-radius: var(--radius-full);
    min-width: 4px;
  }

  .progress-bar-mini span {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    min-width: 40px;
  }

  .rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    background: var(--bg-tertiary);
    border-radius: var(--radius-full);
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text-primary);
  }

  .rank-badge:nth-child(1) {
    background: linear-gradient(135deg, #ffd700, #ffb347);
    color: #1a1a2e;
  }

  tr:nth-child(1) .rank-badge {
    background: linear-gradient(135deg, #ffd700, #ffb347);
    color: #1a1a2e;
  }

  tr:nth-child(2) .rank-badge {
    background: linear-gradient(135deg, #c0c0c0, #a8a8a8);
    color: #1a1a2e;
  }

  tr:nth-child(3) .rank-badge {
    background: linear-gradient(135deg, #cd7f32, #b87333);
    color: white;
  }

  .custom-dates {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .custom-dates span {
    color: var(--text-muted);
  }

  .text-center {
    text-align: center;
    color: var(--text-muted);
    padding: 2rem !important;
  }

  @media (max-width: 1024px) {
    .reports-grid {
      grid-template-columns: 1fr;
    }
  }

  @media print {

    .dashboard-sidebar,
    .dashboard-header .header-right,
    .filters-card {
      display: none !important;
    }

    .dashboard-main {
      margin-left: 0 !important;
      padding: 1rem !important;
    }

    .stats-grid,
    .reports-grid {
      break-inside: avoid;
    }
  }
</style>

<script>
  function toggleCustomDates(value) {
    const customDates = document.getElementById('customDates');
    customDates.style.display = value === 'custom' ? 'flex' : 'none';
  }
</script>

<?php include '../includes/footer.php'; ?>