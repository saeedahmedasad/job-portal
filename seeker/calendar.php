<?php
/**
 * JobNexus - Seeker Interview Calendar
 * View scheduled interviews and events
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Event.php';
require_once '../classes/User.php';
require_once '../classes/SeekerProfile.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_SEEKER) {
  header('Location: ../auth/login.php');
  exit;
}

$db = Database::getInstance()->getConnection();
$event = new Event();
$user = new User();
$profileModel = new SeekerProfile();

$userId = $_SESSION['user_id'];
$profile = $profileModel->findByUserId($userId);

// Get current month/year or from query params
$currentMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$currentYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Validate month/year
if ($currentMonth < 1) {
  $currentMonth = 12;
  $currentYear--;
} elseif ($currentMonth > 12) {
  $currentMonth = 1;
  $currentYear++;
}

// Get events for this seeker (from applications)
$stmt = $db->prepare("
    SELECT e.*, j.title as job_title, j.location, c.company_name, c.logo as company_logo,
           u.email as hr_email
    FROM events e
    JOIN applications a ON e.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    JOIN companies c ON j.company_id = c.id
    JOIN users u ON e.hr_user_id = u.id
    WHERE a.seeker_id = ? 
    AND MONTH(e.event_date) = ? 
    AND YEAR(e.event_date) = ?
    AND e.status = 'scheduled'
    ORDER BY e.event_date ASC, e.event_time ASC
");
$stmt->execute([$userId, $currentMonth, $currentYear]);
$monthEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group events by date
$eventsByDate = [];
foreach ($monthEvents as $evt) {
  $date = date('Y-m-d', strtotime($evt['event_date']));
  if (!isset($eventsByDate[$date])) {
    $eventsByDate[$date] = [];
  }
  $eventsByDate[$date][] = $evt;
}

// Get upcoming interviews (next 30 days)
$stmt = $db->prepare("
    SELECT e.*, j.title as job_title, j.location, c.company_name, c.logo as company_logo,
           u.email as hr_email
    FROM events e
    JOIN applications a ON e.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    JOIN companies c ON j.company_id = c.id
    JOIN users u ON e.hr_user_id = u.id
    WHERE e.seeker_user_id = ? 
    AND e.event_date >= CURDATE()
    AND e.event_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND e.status = 'scheduled'
    ORDER BY e.event_date ASC, e.event_time ASC
    LIMIT 10
");
$stmt->execute([$userId]);
$upcomingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get past interviews (last 30 days) - show completed, exclude cancelled
$stmt = $db->prepare("
    SELECT e.*, j.title as job_title, c.company_name, a.status as application_status
    FROM events e
    JOIN applications a ON e.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    JOIN companies c ON j.company_id = c.id
    WHERE e.seeker_user_id = ? 
    AND e.event_date < CURDATE()
    AND e.status != 'cancelled'
    ORDER BY e.event_date DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$pastEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calendar helper functions
function getDaysInMonth($month, $year)
{
  return cal_days_in_month(CAL_GREGORIAN, $month, $year);
}

function getFirstDayOfMonth($month, $year)
{
  return date('w', strtotime("$year-$month-01"));
}

$daysInMonth = getDaysInMonth($currentMonth, $currentYear);
$firstDay = getFirstDayOfMonth($currentMonth, $currentYear);
$monthName = date('F', strtotime("$currentYear-$currentMonth-01"));

// Navigation months
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) {
  $prevMonth = 12;
  $prevYear--;
}

$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth > 12) {
  $nextMonth = 1;
  $nextYear++;
}

$pageTitle = "My Interviews - JobNexus";
include '../includes/header.php';
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
      <a href="<?php echo BASE_URL; ?>/seeker/calendar.php" class="nav-item active">
        <i class="fas fa-calendar-alt"></i>
        <span>Calendar</span>
      </a>
      <a href="<?php echo BASE_URL; ?>/seeker/resume.php" class="nav-item">
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
        <h1><i class="fas fa-calendar-alt"></i> My Interviews</h1>
        <p>View and prepare for your scheduled interviews</p>
      </div>
    </div>

    <!-- Upcoming Interviews Alert -->
    <?php if (!empty($upcomingEvents)): ?>
      <div class="alert-banner success">
        <i class="fas fa-calendar-check"></i>
        <div>
          <strong>You have <?php echo count($upcomingEvents); ?> upcoming
            interview<?php echo count($upcomingEvents) > 1 ? 's' : ''; ?>!</strong>
          <p>Make sure to prepare and be ready on time.</p>
        </div>
      </div>
    <?php endif; ?>

    <div class="calendar-layout">
      <!-- Calendar Section -->
      <div class="calendar-section">
        <div class="glass-card">
          <div class="calendar-header">
            <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="btn btn-icon">
              <i class="fas fa-chevron-left"></i>
            </a>
            <h2><?php echo $monthName . ' ' . $currentYear; ?></h2>
            <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="btn btn-icon">
              <i class="fas fa-chevron-right"></i>
            </a>
          </div>

          <div class="calendar-grid">
            <div class="calendar-weekdays">
              <div>Sun</div>
              <div>Mon</div>
              <div>Tue</div>
              <div>Wed</div>
              <div>Thu</div>
              <div>Fri</div>
              <div>Sat</div>
            </div>
            <div class="calendar-days">
              <?php
              // Empty cells before first day
              for ($i = 0; $i < $firstDay; $i++) {
                echo '<div class="calendar-day empty"></div>';
              }

              // Days of the month
              for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateStr = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                $isToday = $dateStr === date('Y-m-d');
                $hasEvents = isset($eventsByDate[$dateStr]);
                $dayEvents = $hasEvents ? $eventsByDate[$dateStr] : [];

                $classes = 'calendar-day';
                if ($isToday)
                  $classes .= ' today';
                if ($hasEvents)
                  $classes .= ' has-event';

                echo '<div class="' . $classes . '" data-date="' . $dateStr . '">';
                echo '<span class="day-number">' . $day . '</span>';

                if ($hasEvents) {
                  echo '<div class="event-dots">';
                  foreach (array_slice($dayEvents, 0, 3) as $evt) {
                    $color = $evt['event_type'] === 'interview' ? 'primary' : 'warning';
                    echo '<span class="event-dot ' . $color . '" title="' . htmlspecialchars($evt['event_title']) . '"></span>';
                  }
                  if (count($dayEvents) > 3) {
                    echo '<span class="more-events">+' . (count($dayEvents) - 3) . '</span>';
                  }
                  echo '</div>';
                }

                echo '</div>';
              }

              // Empty cells after last day
              $totalCells = $firstDay + $daysInMonth;
              $remainingCells = 7 - ($totalCells % 7);
              if ($remainingCells < 7) {
                for ($i = 0; $i < $remainingCells; $i++) {
                  echo '<div class="calendar-day empty"></div>';
                }
              }
              ?>
            </div>
          </div>

          <!-- Calendar Legend -->
          <div class="calendar-legend">
            <div class="legend-item">
              <span class="event-dot primary"></span>
              <span>Interview</span>
            </div>
            <div class="legend-item">
              <span class="event-dot warning"></span>
              <span>Assessment/Other</span>
            </div>
            <div class="legend-item">
              <span class="today-indicator"></span>
              <span>Today</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Events List Section -->
      <div class="events-section">
        <!-- Upcoming Interviews -->
        <div class="glass-card">
          <h3><i class="fas fa-clock"></i> Upcoming Interviews</h3>

          <?php if (empty($upcomingEvents)): ?>
            <div class="empty-state">
              <i class="fas fa-calendar-times"></i>
              <p>No upcoming interviews scheduled</p>
              <a href="../jobs/index.php" class="btn btn-primary btn-sm">Browse Jobs</a>
            </div>
          <?php else: ?>
            <div class="event-list">
              <?php foreach ($upcomingEvents as $evt): ?>
                <?php
                $eventDate = new DateTime($evt['event_date']);
                $today = new DateTime('today');
                $daysUntil = $today->diff($eventDate)->days;
                $isToday = $eventDate->format('Y-m-d') === $today->format('Y-m-d');
                $isTomorrow = $daysUntil === 1 && $eventDate > $today;
                ?>
                <div class="event-card <?php echo $isToday ? 'urgent' : ''; ?>">
                  <div class="event-date-badge">
                    <span class="month"><?php echo $eventDate->format('M'); ?></span>
                    <span class="day"><?php echo $eventDate->format('d'); ?></span>
                    <?php if ($isToday): ?>
                      <span class="today-label">TODAY</span>
                    <?php elseif ($isTomorrow): ?>
                      <span class="tomorrow-label">TOMORROW</span>
                    <?php endif; ?>
                  </div>
                  <div class="event-details">
                    <h4><?php echo htmlspecialchars($evt['event_title']); ?></h4>
                    <p class="job-title"><?php echo htmlspecialchars($evt['job_title']); ?></p>
                    <p class="company-name">
                      <i class="fas fa-building"></i>
                      <?php echo htmlspecialchars($evt['company_name']); ?>
                    </p>
                    <div class="event-meta">
                      <span><i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($evt['event_time'])); ?></span>
                      <?php if ($evt['location']): ?>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($evt['location']); ?></span>
                      <?php endif; ?>
                    </div>
                    <?php if ($evt['meeting_link']): ?>
                      <a href="<?php echo htmlspecialchars($evt['meeting_link']); ?>" target="_blank"
                        class="btn btn-primary btn-sm">
                        <i class="fas fa-video"></i> Join Meeting
                      </a>
                    <?php endif; ?>
                  </div>
                  <div class="event-actions">
                    <button class="btn btn-icon" onclick="showEventDetails(<?php echo $evt['id']; ?>)" title="View Details">
                      <i class="fas fa-info-circle"></i>
                    </button>
                    <button class="btn btn-icon" onclick="addToCalendar(<?php echo htmlspecialchars(json_encode($evt)); ?>)"
                      title="Add to Calendar">
                      <i class="fas fa-calendar-plus"></i>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Interview Tips -->
        <div class="glass-card tips-card">
          <h3><i class="fas fa-lightbulb"></i> Interview Tips</h3>
          <ul class="tips-list">
            <li>
              <i class="fas fa-check-circle"></i>
              <span>Research the company before your interview</span>
            </li>
            <li>
              <i class="fas fa-check-circle"></i>
              <span>Prepare answers for common questions</span>
            </li>
            <li>
              <i class="fas fa-check-circle"></i>
              <span>Test your video/audio setup beforehand</span>
            </li>
            <li>
              <i class="fas fa-check-circle"></i>
              <span>Have questions ready for the interviewer</span>
            </li>
            <li>
              <i class="fas fa-check-circle"></i>
              <span>Join the call 5 minutes early</span>
            </li>
          </ul>
        </div>

        <!-- Past Interviews -->
        <?php if (!empty($pastEvents)): ?>
          <div class="glass-card">
            <h3><i class="fas fa-history"></i> Past Interviews</h3>
            <div class="past-events-list">
              <?php foreach ($pastEvents as $evt): ?>
                <div class="past-event-item">
                  <div class="past-event-date">
                    <?php echo date('M d', strtotime($evt['event_date'])); ?>
                  </div>
                  <div class="past-event-info">
                    <strong><?php echo htmlspecialchars($evt['job_title']); ?></strong>
                    <span><?php echo htmlspecialchars($evt['company_name']); ?></span>
                  </div>
                  <div class="past-event-status">
                    <span class="status-badge <?php echo $evt['application_status']; ?>">
                      <?php echo ucfirst($evt['application_status']); ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<!-- Event Details Modal -->
<div id="eventModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Interview Details</h3>
      <button class="modal-close" onclick="closeModal('eventModal')">&times;</button>
    </div>
    <div class="modal-body" id="eventModalBody">
      <!-- Content loaded dynamically -->
    </div>
  </div>
</div>

<style>
  .calendar-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
  }

  .calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }

  .calendar-header h2 {
    color: var(--primary-color);
    margin: 0;
  }

  .calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
    margin-bottom: 0.5rem;
  }

  .calendar-weekdays div {
    text-align: center;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.875rem;
    padding: 0.5rem;
  }

  .calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
  }

  .calendar-day {
    aspect-ratio: 1;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 0.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    padding: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
  }

  .calendar-day:hover:not(.empty) {
    background: rgba(255, 255, 255, 0.08);
  }

  .calendar-day.empty {
    background: transparent;
    cursor: default;
  }

  .calendar-day.today {
    background: rgba(0, 230, 118, 0.15);
    border: 2px solid var(--primary-color);
  }

  .calendar-day.has-event {
    background: rgba(0, 230, 118, 0.1);
  }

  .day-number {
    font-size: 0.875rem;
    font-weight: 600;
  }

  .calendar-day.today .day-number {
    color: var(--primary-color);
  }

  .event-dots {
    display: flex;
    gap: 0.25rem;
    margin-top: 0.25rem;
    flex-wrap: wrap;
    justify-content: center;
  }

  .event-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
  }

  .event-dot.primary {
    background: var(--primary-color);
  }

  .event-dot.warning {
    background: #ffc107;
  }

  .more-events {
    font-size: 0.625rem;
    color: rgba(255, 255, 255, 0.6);
  }

  .calendar-legend {
    display: flex;
    gap: 1.5rem;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
  }

  .legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
  }

  .today-indicator {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    border: 2px solid var(--primary-color);
    background: rgba(0, 230, 118, 0.15);
  }

  /* Events Section */
  .events-section {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }

  .events-section h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    color: var(--text-primary);
  }

  .events-section h3 i {
    color: var(--primary-color);
  }

  .event-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .event-card {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s ease;
  }

  .event-card:hover {
    border-color: rgba(0, 230, 118, 0.3);
    transform: translateX(4px);
  }

  .event-card.urgent {
    border-color: var(--primary-color);
    background: rgba(0, 230, 118, 0.1);
  }

  .event-date-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 60px;
    padding: 0.5rem;
    background: rgba(0, 230, 118, 0.1);
    border-radius: 0.5rem;
  }

  .event-date-badge .month {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: var(--primary-color);
  }

  .event-date-badge .day {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
  }

  .event-date-badge .today-label,
  .event-date-badge .tomorrow-label {
    font-size: 0.625rem;
    font-weight: 700;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    margin-top: 0.25rem;
  }

  .event-date-badge .today-label {
    background: var(--primary-color);
    color: #000;
  }

  .event-date-badge .tomorrow-label {
    background: #ffc107;
    color: #000;
  }

  .event-details {
    flex: 1;
  }

  .event-details h4 {
    margin: 0 0 0.25rem;
    color: var(--text-primary);
    font-size: 1rem;
  }

  .event-details .job-title {
    font-weight: 600;
    color: var(--primary-color);
    margin: 0.25rem 0;
  }

  .event-details .company-name {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
    margin: 0.25rem 0;
  }

  .event-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.6);
    margin: 0.5rem 0;
  }

  .event-meta span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
  }

  .event-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  /* Tips Card */
  .tips-card {
    background: linear-gradient(135deg, rgba(0, 230, 118, 0.1) 0%, rgba(0, 230, 118, 0.02) 100%);
  }

  .tips-list {
    list-style: none;
    padding: 0;
    margin: 0;
  }

  .tips-list li {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  }

  .tips-list li:last-child {
    border-bottom: none;
  }

  .tips-list li i {
    color: var(--primary-color);
    margin-top: 0.125rem;
  }

  .tips-list li span {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.875rem;
  }

  /* Past Events */
  .past-events-list {
    display: flex;
    flex-direction: column;
  }

  .past-event-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  }

  .past-event-item:last-child {
    border-bottom: none;
  }

  .past-event-date {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    min-width: 50px;
  }

  .past-event-info {
    flex: 1;
  }

  .past-event-info strong {
    display: block;
    font-size: 0.875rem;
    color: var(--text-primary);
  }

  .past-event-info span {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.6);
  }

  .past-event-status .status-badge {
    font-size: 0.625rem;
    padding: 0.25rem 0.5rem;
  }

  /* Empty State */
  .empty-state {
    text-align: center;
    padding: 2rem;
  }

  .empty-state i {
    font-size: 3rem;
    color: rgba(255, 255, 255, 0.2);
    margin-bottom: 1rem;
  }

  .empty-state p {
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 1rem;
  }

  /* Alert Banner */
  .alert-banner {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 0.75rem;
    margin-bottom: 2rem;
  }

  .alert-banner.success {
    background: rgba(0, 230, 118, 0.15);
    border: 1px solid rgba(0, 230, 118, 0.3);
  }

  .alert-banner i {
    font-size: 1.5rem;
    color: var(--primary-color);
  }

  .alert-banner strong {
    display: block;
    color: var(--text-primary);
  }

  .alert-banner p {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
    margin: 0.25rem 0 0;
  }

  /* Responsive */
  @media (max-width: 1024px) {
    .calendar-layout {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 768px) {
    .event-card {
      flex-direction: column;
    }

    .event-date-badge {
      flex-direction: row;
      gap: 0.5rem;
    }

    .event-actions {
      flex-direction: row;
    }

    .calendar-legend {
      flex-wrap: wrap;
    }
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
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
  }

  .modal.active {
    display: flex;
  }

  .modal-content {
    position: relative;
    background: var(--bg-card, #1e1e1e);
    border-radius: 16px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    border: 1px solid var(--border-color, #2a2a2a);
    margin: 1rem;
  }

  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color, #2a2a2a);
  }

  .modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text-primary, #fff);
  }

  .modal-close {
    background: none;
    border: none;
    color: var(--text-muted, #666);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
  }

  .modal-close:hover {
    color: var(--error, #ff5252);
  }

  .modal-body {
    padding: 1.5rem;
  }

  /* Event Detail Content */
  .event-detail-content {
    color: var(--text-primary, #fff);
  }

  .event-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color, #2a2a2a);
  }

  .event-detail-header h4 {
    margin: 0;
    font-size: 1.25rem;
    color: var(--primary-color, #00E676);
  }

  .event-type-badge {
    padding: 0.25rem 0.75rem;
    background: rgba(0, 230, 118, 0.15);
    color: var(--primary-color, #00E676);
    border-radius: 20px;
    font-size: 0.75rem;
    text-transform: capitalize;
  }

  .event-detail-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1.5rem;
  }

  .detail-row {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
  }

  .detail-row > i {
    width: 20px;
    color: var(--primary-color, #00E676);
    margin-top: 0.25rem;
  }

  .detail-row > div {
    flex: 1;
  }

  .detail-row strong {
    display: block;
    font-size: 0.75rem;
    color: var(--text-muted, #666);
    margin-bottom: 0.25rem;
    text-transform: uppercase;
  }

  .detail-row p {
    margin: 0;
    color: var(--text-primary, #fff);
    font-size: 0.95rem;
  }

  .meeting-link {
    color: var(--primary-color, #00E676);
    word-break: break-all;
  }

  .event-detail-actions {
    display: flex;
    gap: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color, #2a2a2a);
  }

  .event-detail-actions .btn {
    flex: 1;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s;
  }

  .event-detail-actions .btn-primary {
    background: var(--primary-color, #00E676);
    color: #000;
    border: none;
  }

  .event-detail-actions .btn-outline {
    background: transparent;
    border: 1px solid var(--border-color, #2a2a2a);
    color: var(--text-primary, #fff);
  }

  .event-detail-actions .btn-outline:hover {
    border-color: var(--primary-color, #00E676);
    color: var(--primary-color, #00E676);
  }

  /* Day Events List */
  .day-events-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .day-event-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
  }

  .day-event-card:hover {
    background: rgba(0, 230, 118, 0.1);
    transform: translateX(4px);
  }

  .day-event-time {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--primary-color, #00E676);
    min-width: 80px;
  }

  .day-event-info {
    flex: 1;
  }

  .day-event-info h5 {
    margin: 0 0 0.25rem;
    font-size: 0.95rem;
    color: var(--text-primary, #fff);
  }

  .day-event-info p {
    margin: 0;
    font-size: 0.8rem;
    color: var(--text-muted, #666);
  }

  .day-event-card > i {
    color: var(--text-muted, #666);
  }

  .text-center {
    text-align: center;
  }

  .text-muted {
    color: var(--text-muted, #666);
  }
</style>

<script>
  // Store all events data for JavaScript access
  const eventsData = <?php echo json_encode($monthEvents); ?>;
    const upcomingEventsData = <?php echo json_encode($upcomingEvents); ?>;
    const eventsByDate = <?php echo json_encode($eventsByDate); ?>;

  function showEventDetails(eventId) {
    const modal = document.getElementById('eventModal');
    const body = document.getElementById('eventModalBody');

    // Find event in upcoming events or month events
    let event = upcomingEventsData.find(e => e.id == eventId) || eventsData.find(e => e.id == eventId);

    if (!event) {
      body.innerHTML = `<div class="event-detail-content"><p>Event not found.</p></div>`;
      modal.classList.add('active');
      return;
    }

    const eventDate = new Date(event.event_date + 'T12:00:00');
    const formattedDate = eventDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    const eventTime = new Date('2000-01-01T' + event.event_time);
    const formattedTime = eventTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

    body.innerHTML = `
      <div class="event-detail-content">
        <div class="event-detail-header">
          <h4>${escapeHtml(event.event_title || 'Interview')}</h4>
          <span class="event-type-badge">${escapeHtml(event.event_type || 'interview')}</span>
        </div>
        
        <div class="event-detail-section">
          <div class="detail-row">
            <i class="fas fa-briefcase"></i>
            <div>
              <strong>Position</strong>
              <p>${escapeHtml(event.job_title || 'N/A')}</p>
            </div>
          </div>
          
          <div class="detail-row">
            <i class="fas fa-building"></i>
            <div>
              <strong>Company</strong>
              <p>${escapeHtml(event.company_name || 'N/A')}</p>
            </div>
          </div>
          
          <div class="detail-row">
            <i class="fas fa-calendar"></i>
            <div>
              <strong>Date</strong>
              <p>${formattedDate}</p>
            </div>
          </div>
          
          <div class="detail-row">
            <i class="fas fa-clock"></i>
            <div>
              <strong>Time</strong>
              <p>${formattedTime} (${event.duration_minutes || 60} minutes)</p>
            </div>
          </div>
          
          ${event.location ? `
          <div class="detail-row">
            <i class="fas fa-map-marker-alt"></i>
            <div>
              <strong>Location</strong>
              <p>${escapeHtml(event.location)}</p>
            </div>
          </div>
          ` : ''}
          
          ${event.meeting_link ? `
          <div class="detail-row">
            <i class="fas fa-video"></i>
            <div>
              <strong>Meeting Link</strong>
              <p><a href="${escapeHtml(event.meeting_link)}" target="_blank" class="meeting-link">${escapeHtml(event.meeting_link)}</a></p>
            </div>
          </div>
          ` : ''}
          
          ${event.description ? `
          <div class="detail-row">
            <i class="fas fa-info-circle"></i>
            <div>
              <strong>Notes</strong>
              <p>${escapeHtml(event.description)}</p>
            </div>
          </div>
          ` : ''}
        </div>
        
        <div class="event-detail-actions">
          ${event.meeting_link ? `<a href="${escapeHtml(event.meeting_link)}" target="_blank" class="btn btn-primary"><i class="fas fa-video"></i> Join Meeting</a>` : ''}
          <button onclick="addToCalendar(${JSON.stringify(event).replace(/"/g, '&quot;')})" class="btn btn-outline"><i class="fas fa-calendar-plus"></i> Add to Calendar</button>
        </div>
      </div>
    `;

    modal.classList.add('active');
  }

  function showDayEvents(dateStr) {
    const modal = document.getElementById('eventModal');
    const body = document.getElementById('eventModalBody');
    
    const events = eventsByDate[dateStr] || [];
    const date = new Date(dateStr + 'T12:00:00');
    const formattedDate = date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    // Update modal title
    document.querySelector('#eventModal .modal-header h3').textContent = formattedDate;
    
    if (events.length === 0) {
      body.innerHTML = `<div class="event-detail-content"><p class="text-center text-muted">No interviews scheduled for this day.</p></div>`;
    } else {
      let html = '<div class="day-events-list">';
      events.forEach(event => {
        const eventTime = new Date('2000-01-01T' + event.event_time);
        const formattedTime = eventTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        
        html += `
          <div class="day-event-card" onclick="showEventDetails(${event.id})">
            <div class="day-event-time">${formattedTime}</div>
            <div class="day-event-info">
              <h5>${escapeHtml(event.event_title || 'Interview')}</h5>
              <p>${escapeHtml(event.job_title || '')} at ${escapeHtml(event.company_name || '')}</p>
            </div>
            <i class="fas fa-chevron-right"></i>
          </div>
        `;
      });
      html += '</div>';
      body.innerHTML = html;
    }
    
    modal.classList.add('active');
  }

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
    // Reset modal title
    document.querySelector('#eventModal .modal-header h3').textContent = 'Interview Details';
  }

  function addToCalendar(event) {
    // Generate ICS file for download
    const startDate = new Date(event.event_date + 'T' + event.event_time);
    const endDate = new Date(startDate.getTime() + (event.duration_minutes || 60) * 60 * 1000);

    const formatDate = (date) => {
      return date.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
    };

    const icsContent = `BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
DTSTART:${formatDate(startDate)}
DTEND:${formatDate(endDate)}
SUMMARY:${event.event_title || 'Interview'}
DESCRIPTION:Interview for ${event.job_title || 'Position'} at ${event.company_name || 'Company'}
LOCATION:${event.location || event.meeting_link || 'Online'}
END:VEVENT
END:VCALENDAR`;

    const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `interview-${(event.job_title || 'interview').replace(/\s+/g, '-')}.ics`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    if (typeof showNotification === 'function') {
      showNotification('Calendar file downloaded!', 'success');
    }
  }

  // Click on calendar day to show events
  document.querySelectorAll('.calendar-day.has-event').forEach(day => {
    day.addEventListener('click', function () {
      const date = this.dataset.date;
      showDayEvents(date);
    });
  });
</script>

<?php include '../includes/footer.php'; ?>