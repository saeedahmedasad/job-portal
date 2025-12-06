<?php
/**
 * JobNexus - HR Calendar / Interview Scheduling
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Event.php';
require_once '../classes/Application.php';
require_once '../classes/Company.php';
require_once '../includes/notification-helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_HR) {
  header('Location: ' . BASE_URL . '/auth/login.php?redirect=hr/calendar');
  exit;
}

$db = Database::getInstance()->getConnection();
$userModel = new User();
$eventModel = new Event();
$companyModel = new Company();

$hr = $userModel->findById($_SESSION['user_id']);
$company = $companyModel->findByHRUserId($_SESSION['user_id']);

// Check if company is verified
if (!$company || $company['verification_status'] !== 'verified') {
  header('Location: ' . BASE_URL . '/hr/index.php');
  exit;
}

// Get current month/year for calendar
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

// Validate month/year
if ($month < 1) {
  $month = 12;
  $year--;
}
if ($month > 12) {
  $month = 1;
  $year++;
}

$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$startingDay = date('w', $firstDay);
$monthName = date('F', $firstDay);

// Get events for this month
$startDate = date('Y-m-01', $firstDay);
$endDate = date('Y-m-t', $firstDay);

$stmt = $db->prepare("
    SELECT e.*, CONCAT(sp.first_name, ' ', sp.last_name) as seeker_name, j.title as job_title
    FROM events e
    LEFT JOIN seeker_profiles sp ON e.seeker_user_id = sp.user_id
    LEFT JOIN applications a ON e.application_id = a.id
    LEFT JOIN jobs j ON a.job_id = j.id
    WHERE e.hr_user_id = ? 
    AND e.event_date BETWEEN ? AND ?
    AND e.status = 'scheduled'
    ORDER BY e.event_date ASC, e.event_time ASC
");
$stmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
$events = $stmt->fetchAll();

// Group events by date
$eventsByDate = [];
foreach ($events as $event) {
  $date = $event['event_date'];
  if (!isset($eventsByDate[$date])) {
    $eventsByDate[$date] = [];
  }
  $eventsByDate[$date][] = $event;
}

// Get upcoming events for sidebar
$stmt = $db->prepare("
    SELECT e.*, CONCAT(sp.first_name, ' ', sp.last_name) as seeker_name, u.email as seeker_email, j.title as job_title
    FROM events e
    JOIN users u ON e.seeker_user_id = u.id
    LEFT JOIN seeker_profiles sp ON u.id = sp.user_id
    LEFT JOIN applications a ON e.application_id = a.id
    LEFT JOIN jobs j ON a.job_id = j.id
    WHERE e.hr_user_id = ? AND e.event_date >= CURDATE() AND e.status = 'scheduled'
    ORDER BY e.event_date ASC, e.event_time ASC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$upcomingEvents = $stmt->fetchAll();

// Get shortlisted candidates for scheduling dropdown
$stmt = $db->prepare("
    SELECT a.id as app_id, a.seeker_id, CONCAT(sp.first_name, ' ', sp.last_name) as full_name, j.id as job_id, j.title as job_title
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    LEFT JOIN seeker_profiles sp ON a.seeker_id = sp.user_id
    WHERE j.posted_by = ? AND a.status IN ('shortlisted', 'interview')
    ORDER BY a.applied_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$candidates = $stmt->fetchAll();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'create_event') {
    // Generate unique meeting token
    $meetingToken = bin2hex(random_bytes(16)); // 32 character token
    $meetingLink = BASE_URL . '/meeting.php?id=' . $meetingToken;

    $eventData = [
      'hr_user_id' => $_SESSION['user_id'],
      'seeker_user_id' => (int) $_POST['seeker_id'],
      'application_id' => (int) ($_POST['application_id'] ?? 0) ?: null,
      'event_title' => trim($_POST['event_title'] ?? 'Interview'),
      'event_type' => $_POST['event_type'],
      'event_date' => $_POST['event_date'],
      'event_time' => $_POST['event_time'],
      'duration_minutes' => (int) $_POST['duration'],
      'location' => trim($_POST['location'] ?? ''),
      'meeting_link' => $meetingLink,
      'meeting_token' => $meetingToken,
      'description' => trim($_POST['notes'] ?? ''),
      'status' => 'scheduled'
    ];

    $eventId = $eventModel->create($eventData);

    if ($eventId) {
      // Update application status to 'interview'
      $stmt = $db->prepare("UPDATE applications SET status = 'interview' WHERE seeker_id = ? AND job_id = ?");
      $stmt->execute([$eventData['seeker_user_id'], (int) $_POST['job_id']]);

      // Get job title for notification
      $jobTitle = '';
      foreach ($candidates as $c) {
        if ($c['job_id'] == (int) $_POST['job_id']) {
          $jobTitle = $c['job_title'];
          break;
        }
      }

      // Notify seeker about scheduled interview
      notifyInterviewScheduled(
        $eventData['seeker_user_id'],
        $jobTitle ?: 'a position',
        $eventData['event_date'],
        $eventData['event_time'],
        $eventId
      );

      $message = 'Interview scheduled successfully!';
      $messageType = 'success';

      // Refresh the page to show updated data
      header('Location: ' . BASE_URL . '/hr/calendar.php?month=' . $month . '&year=' . $year . '&scheduled=1');
      exit;
    } else {
      $message = 'Failed to schedule interview.';
      $messageType = 'error';
    }
  } elseif ($action === 'cancel_event') {
    $eventId = (int) $_POST['event_id'];

    // Get event details before cancelling
    $stmt = $db->prepare("
      SELECT e.seeker_user_id, e.event_date, j.title as job_title 
      FROM events e 
      LEFT JOIN applications a ON e.application_id = a.id
      LEFT JOIN jobs j ON a.job_id = j.id
      WHERE e.id = ? AND e.hr_user_id = ?
    ");
    $stmt->execute([$eventId, $_SESSION['user_id']]);
    $eventInfo = $stmt->fetch();

    $stmt = $db->prepare("UPDATE events SET status = 'cancelled' WHERE id = ? AND hr_user_id = ?");
    if ($stmt->execute([$eventId, $_SESSION['user_id']])) {
      // Notify seeker about cancellation
      if ($eventInfo) {
        notifyInterviewCancelled(
          $eventInfo['seeker_user_id'],
          $eventInfo['job_title'] ?: 'a position',
          $eventInfo['event_date']
        );
      }
      header('Location: ' . BASE_URL . '/hr/calendar.php?month=' . $month . '&year=' . $year . '&cancelled=1');
      exit;
    }
  } elseif ($action === 'complete_event') {
    $eventId = (int) $_POST['event_id'];

    $stmt = $db->prepare("UPDATE events SET status = 'completed' WHERE id = ? AND hr_user_id = ?");
    if ($stmt->execute([$eventId, $_SESSION['user_id']])) {
      header('Location: ' . BASE_URL . '/hr/calendar.php?month=' . $month . '&year=' . $year . '&completed=1');
      exit;
    }
  }
}

if (isset($_GET['scheduled'])) {
  $message = 'Interview scheduled successfully!';
  $messageType = 'success';
}

if (isset($_GET['cancelled'])) {
  $message = 'Event cancelled successfully.';
  $messageType = 'success';
}

if (isset($_GET['completed'])) {
  $message = 'Event marked as completed.';
  $messageType = 'success';
}

$pageTitle = 'Calendar';
require_once '../includes/header.php';

// Company already fetched at top with $companyModel->findByHRUserId()
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
      <a href="<?php echo BASE_URL; ?>/hr/calendar.php" class="nav-item active">
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
        <h1><i class="fas fa-calendar-alt"></i> Interview Calendar</h1>
        <p>Schedule and manage candidate interviews</p>
      </div>
      <div class="header-right">
        <button class="btn btn-primary" onclick="openScheduleModal()">
          <i class="fas fa-plus"></i> Schedule Interview
        </button>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?php echo $messageType; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <div class="calendar-layout">
      <!-- Main Calendar -->
      <div class="calendar-main">
        <div class="calendar-card">
          <div class="calendar-nav">
            <a href="?month=<?php echo $month - 1; ?>&year=<?php echo $year; ?>" class="nav-btn">
              <i class="fas fa-chevron-left"></i>
            </a>
            <h2><?php echo $monthName . ' ' . $year; ?></h2>
            <a href="?month=<?php echo $month + 1; ?>&year=<?php echo $year; ?>" class="nav-btn">
              <i class="fas fa-chevron-right"></i>
            </a>
          </div>

          <div class="calendar-grid">
            <div class="calendar-header">
              <span>Sun</span>
              <span>Mon</span>
              <span>Tue</span>
              <span>Wed</span>
              <span>Thu</span>
              <span>Fri</span>
              <span>Sat</span>
            </div>

            <div class="calendar-body">
              <?php
              $currentDay = 1;
              $today = date('Y-m-d');

              // Fill in empty cells before first day
              for ($i = 0; $i < $startingDay; $i++): ?>
                <div class="calendar-day empty"></div>
              <?php endfor;

              // Fill in days
              while ($currentDay <= $daysInMonth):
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $currentDay);
                $isToday = $dateStr === $today;
                $hasEvents = isset($eventsByDate[$dateStr]);
                ?>
                <div
                  class="calendar-day <?php echo $isToday ? 'today' : ''; ?> <?php echo $hasEvents ? 'has-events clickable' : ''; ?>"
                  <?php if ($hasEvents): ?> onclick="showDayEvents('<?php echo $dateStr; ?>')" <?php endif; ?>>
                  <span class="day-number"><?php echo $currentDay; ?></span>
                  <?php if ($hasEvents): ?>
                    <div class="day-events">
                      <?php foreach (array_slice($eventsByDate[$dateStr], 0, 2) as $event): ?>
                        <div class="event-dot <?php echo $event['event_type']; ?>"
                          title="<?php echo htmlspecialchars($event['seeker_name']); ?> - <?php echo date('g:i A', strtotime($event['event_time'])); ?>">
                        </div>
                      <?php endforeach; ?>
                      <?php if (count($eventsByDate[$dateStr]) > 2): ?>
                        <span class="more-events">+<?php echo count($eventsByDate[$dateStr]) - 2; ?></span>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
                <?php
                $currentDay++;
              endwhile;

              // Fill remaining cells
              $remainingCells = 7 - (($startingDay + $daysInMonth) % 7);
              if ($remainingCells < 7):
                for ($i = 0; $i < $remainingCells; $i++): ?>
                  <div class="calendar-day empty"></div>
                <?php endfor;
              endif;
              ?>
            </div>
          </div>

          <div class="calendar-legend">
            <span class="legend-item">
              <span class="legend-dot interview"></span> Interview
            </span>
            <span class="legend-item">
              <span class="legend-dot screening"></span> Screening
            </span>
            <span class="legend-item">
              <span class="legend-dot technical"></span> Technical
            </span>
            <span class="legend-item">
              <span class="legend-dot hr_round"></span> HR Round
            </span>
            <span class="legend-item">
              <span class="legend-dot final"></span> Final
            </span>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="calendar-sidebar">
        <div class="sidebar-card">
          <h3><i class="fas fa-calendar-check"></i> Upcoming Interviews</h3>

          <?php if (empty($upcomingEvents)): ?>
            <div class="empty-sidebar">
              <p>No upcoming interviews</p>
            </div>
          <?php else: ?>
            <div class="upcoming-list">
              <?php foreach ($upcomingEvents as $event): ?>
                <div class="upcoming-item">
                  <div class="event-date-badge">
                    <span class="day"><?php echo date('d', strtotime($event['event_date'])); ?></span>
                    <span class="month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                  </div>
                  <div class="event-details">
                    <h4><?php echo htmlspecialchars($event['seeker_name']); ?></h4>
                    <p class="job"><?php echo htmlspecialchars($event['job_title'] ?? 'Interview'); ?></p>
                    <div class="event-meta">
                      <span class="time">
                        <i class="fas fa-clock"></i>
                        <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                      </span>
                      <span class="<?php echo $event['event_type']; ?>">
                        <?php
                        $typeIcons = [
                          'interview' => 'fa-user-tie',
                          'screening' => 'fa-phone',
                          'technical' => 'fa-laptop-code',
                          'hr_round' => 'fa-users',
                          'final' => 'fa-handshake',
                          'other' => 'fa-calendar'
                        ];
                        $icon = $typeIcons[$event['event_type']] ?? 'fa-calendar';
                        ?>
                        <i class="fas <?php echo $icon; ?>"></i>
                        <?php echo ucfirst(str_replace('_', ' ', $event['event_type'])); ?>
                      </span>
                    </div>
                  </div>
                  <div class="event-actions">
                    <form method="POST" style="display: inline;">
                      <input type="hidden" name="action" value="complete_event">
                      <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                      <button type="submit" class="btn-icon success" title="Mark Complete">
                        <i class="fas fa-check"></i>
                      </button>
                    </form>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Cancel this interview?');">
                      <input type="hidden" name="action" value="cancel_event">
                      <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                      <button type="submit" class="btn-icon danger" title="Cancel">
                        <i class="fas fa-times"></i>
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="sidebar-card tips-card">
          <h3><i class="fas fa-lightbulb"></i> Interview Tips</h3>
          <ul>
            <li>Send calendar invites 24-48 hours before</li>
            <li>Prepare questions in advance</li>
            <li>Test video/audio before video interviews</li>
            <li>Follow up within 24 hours</li>
          </ul>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Schedule Interview Modal -->
<div class="modal" id="scheduleModal">
  <div class="modal-backdrop" onclick="closeScheduleModal()"></div>
  <div class="modal-content modal-lg">
    <div class="modal-header">
      <h3><i class="fas fa-calendar-plus"></i> Schedule Interview</h3>
      <button class="btn-icon" onclick="closeScheduleModal()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create_event">
      <div class="modal-body">
        <?php if (empty($candidates)): ?>
          <div class="empty-form-state">
            <i class="fas fa-users"></i>
            <h4>No Candidates to Schedule</h4>
            <p>Shortlist candidates from applications first before scheduling interviews.</p>
            <a href="<?php echo BASE_URL; ?>/hr/applications.php" class="btn btn-primary">
              View Applications
            </a>
          </div>
        <?php else: ?>
          <div class="form-group">
            <label for="candidate">Select Candidate <span class="required">*</span></label>
            <select id="candidate" name="seeker_id" class="form-control" required onchange="updateJobId(this)">
              <option value="">Choose a candidate...</option>
              <?php foreach ($candidates as $c): ?>
                <option value="<?php echo $c['seeker_id']; ?>" data-job="<?php echo $c['job_id']; ?>"
                  data-app="<?php echo $c['app_id']; ?>">
                  <?php echo htmlspecialchars($c['full_name']); ?> - <?php echo htmlspecialchars($c['job_title']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="job_id" id="job_id" value="">
            <input type="hidden" name="application_id" id="application_id" value="">
          </div>

          <div class="form-group">
            <label for="event_title">Interview Title <span class="required">*</span></label>
            <input type="text" id="event_title" name="event_title" class="form-control" required
              placeholder="e.g., Initial Screening Interview">
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="event_type">Interview Type <span class="required">*</span></label>
              <select id="event_type" name="event_type" class="form-control" required>
                <option value="interview">General Interview</option>
                <option value="screening">Initial Screening</option>
                <option value="technical">Technical Interview</option>
                <option value="hr_round">HR Round</option>
                <option value="final">Final Interview</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="form-group">
              <label for="duration">Duration <span class="required">*</span></label>
              <select id="duration" name="duration" class="form-control" required>
                <option value="30">30 minutes</option>
                <option value="45">45 minutes</option>
                <option value="60" selected>1 hour</option>
                <option value="90">1.5 hours</option>
                <option value="120">2 hours</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="event_date">Date <span class="required">*</span></label>
              <input type="date" id="event_date" name="event_date" class="form-control" required
                min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
              <label for="event_time">Time <span class="required">*</span></label>
              <input type="time" id="event_time" name="event_time" class="form-control" required>
            </div>
          </div>

          <div class="form-group">
            <label for="location">Location / Address</label>
            <input type="text" id="location" name="location" class="form-control"
              placeholder="Office address for in-person interviews">
          </div>

          <div class="form-group">
            <label>Video Meeting</label>
            <div class="meeting-info-box"
              style="background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 8px; padding: 1rem;">
              <i class="fas fa-video" style="color: var(--primary-color); margin-right: 0.5rem;"></i>
              <span>A JobNexus meeting link will be automatically generated when you schedule this interview.</span>
            </div>
          </div>

          <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" class="form-control" rows="3"
              placeholder="Any additional information for the candidate..."></textarea>
          </div>
        <?php endif; ?>
      </div>
      <?php if (!empty($candidates)): ?>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" onclick="closeScheduleModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-calendar-check"></i> Schedule Interview
          </button>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Day Events Modal -->
<div class="modal" id="dayEventsModal">
  <div class="modal-backdrop" onclick="closeDayEventsModal()"></div>
  <div class="modal-content modal-lg">
    <div class="modal-header">
      <h3><i class="fas fa-calendar-day"></i> <span id="dayEventsTitle">Events</span></h3>
      <button class="btn-icon" onclick="closeDayEventsModal()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="modal-body" id="dayEventsContent">
      <!-- Events will be populated via JavaScript -->
    </div>
  </div>
</div>

<!-- Events data for JavaScript -->
<script>
  const eventsData = <?php echo json_encode($eventsByDate); ?>;
</script>

<style>
  /* Calendar Page */
  .calendar-page {
    min-height: calc(100vh - 70px);
    padding: 2rem;
    margin-top: 70px;
    background: var(--bg-dark);
  }

  .calendar-container {
    max-width: 1300px;
    margin: 0 auto;
  }

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
  }

  .page-header h1 {
    font-family: var(--font-heading);
    font-size: 2rem;
    margin-bottom: 0.25rem;
  }

  .page-header p {
    color: var(--text-muted);
  }

  /* Calendar Layout */
  .calendar-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
  }

  /* Calendar Card - Glass effect like seeker */
  .calendar-card,
  .glass-card {
    background: rgba(30, 30, 30, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
  }

  .calendar-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }

  .calendar-nav h2 {
    font-size: 1.5rem;
    color: var(--primary-color);
    margin: 0;
  }

  .nav-btn {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
  }

  .nav-btn:hover {
    background: var(--primary-color);
    color: var(--bg-dark);
    border-color: var(--primary-color);
  }

  /* Calendar Grid */
  .calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
    margin-bottom: 0.5rem;
  }

  .calendar-header span {
    text-align: center;
    padding: 0.5rem;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.875rem;
    font-weight: 600;
  }

  .calendar-body {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
  }

  .calendar-day {
    aspect-ratio: 1;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 0.5rem;
    padding: 0.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    position: relative;
    transition: all 0.3s ease;
    cursor: default;
  }

  .calendar-day.empty {
    background: transparent;
  }

  .calendar-day:not(.empty):hover {
    background: rgba(255, 255, 255, 0.08);
  }

  .calendar-day.today {
    background: rgba(0, 230, 118, 0.15);
    border: 2px solid var(--primary-color);
  }

  .calendar-day.has-events {
    background: rgba(0, 230, 118, 0.1);
  }

  .calendar-day.today .day-number {
    color: var(--primary-color);
    font-weight: 700;
  }

  .day-number {
    font-size: 0.875rem;
    font-weight: 600;
  }

  .day-events {
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
    background: var(--accent-primary);
  }

  .event-dot.interview {
    background: #00E676;
  }

  .event-dot.screening {
    background: #2196F3;
  }

  .event-dot.technical {
    background: #FF9800;
  }

  .event-dot.hr_round {
    background: #9C27B0;
  }

  .event-dot.final {
    background: #F44336;
  }

  .event-dot.other {
    background: #607D8B;
  }

  .more-events {
    font-size: 0.65rem;
    color: var(--text-muted);
  }

  .calendar-legend {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
  }

  .legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-muted);
  }

  .legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
  }

  .legend-dot.interview {
    background: #00E676;
  }

  .legend-dot.screening {
    background: #2196F3;
  }

  .legend-dot.technical {
    background: #FF9800;
  }

  .legend-dot.hr_round {
    background: #9C27B0;
  }

  .legend-dot.final {
    background: #F44336;
  }

  .legend-dot.other {
    background: #607D8B;
  }

  .calendar-day.clickable {
    cursor: pointer;
  }

  .calendar-day.clickable:hover {
    transform: scale(1.05);
    box-shadow: 0 0 10px rgba(0, 230, 118, 0.3);
  }

  /* Sidebar - matching seeker calendar */
  .calendar-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }

  .sidebar-card {
    background: rgba(30, 30, 30, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
  }

  .sidebar-card h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    font-size: 1rem;
    color: var(--text-primary);
  }

  .sidebar-card h3 i {
    color: var(--primary-color);
  }

  .empty-sidebar {
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
  }

  .empty-sidebar i {
    font-size: 3rem;
    color: rgba(255, 255, 255, 0.2);
    margin-bottom: 1rem;
    display: block;
  }

  .upcoming-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .upcoming-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s ease;
  }

  .upcoming-item:hover {
    border-color: rgba(0, 230, 118, 0.3);
    transform: translateX(4px);
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
    flex-shrink: 0;
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
    line-height: 1;
  }

  .event-details {
    flex: 1;
    min-width: 0;
  }

  .event-details h4 {
    font-size: 1rem;
    margin: 0 0 0.25rem;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .event-details .job {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--primary-color);
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

  .event-meta .time {
    color: var(--primary-color);
  }

  .event-meta .interview {
    color: #00E676;
  }

  .event-meta .screening {
    color: #2196F3;
  }

  .event-meta .technical {
    color: #FF9800;
  }

  .event-meta .hr_round {
    color: #9C27B0;
  }

  .event-meta .final {
    color: #F44336;
  }

  .event-meta .other {
    color: #607D8B;
  }

  .event-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .event-actions .btn-icon {
    width: 28px;
    height: 28px;
    background: transparent;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.75rem;
  }

  .event-actions .btn-icon.success:hover {
    background: #4CAF50;
    border-color: #4CAF50;
    color: white;
  }

  .event-actions .btn-icon.danger:hover {
    background: #F44336;
    border-color: #F44336;
    color: white;
  }

  /* Tips Card */
  .tips-card {
    background: rgba(255, 193, 7, 0.05);
    border-color: rgba(255, 193, 7, 0.2);
  }

  .tips-card h3 {
    color: #FFC107;
  }

  .tips-card ul {
    list-style: none;
    padding: 0;
    margin: 0;
  }

  .tips-card li {
    padding: 0.5rem 0;
    padding-left: 1.5rem;
    position: relative;
    color: var(--text-muted);
    font-size: 0.85rem;
  }

  .tips-card li::before {
    content: '•';
    position: absolute;
    left: 0;
    color: var(--primary-color);
  }

  /* Modal - matching seeker calendar */
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

  .modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
  }

  .modal-content {
    position: relative;
    background: rgba(30, 30, 30, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
  }

  .modal-content.modal-lg {
    max-width: 600px;
  }

  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }

  .modal-header h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    color: var(--text-primary);
  }

  .modal-header h3 i {
    color: var(--primary-color);
  }

  .modal-close {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: var(--text-muted);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
  }

  .modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
    color: var(--text-primary);
  }

  .modal-body {
    padding: 1.5rem;
  }

  .modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
  }

  .empty-form-state {
    text-align: center;
    padding: 2rem;
  }

  .empty-form-state i {
    font-size: 3rem;
    color: rgba(255, 255, 255, 0.2);
    margin-bottom: 1rem;
    display: block;
  }

  .empty-form-state h4 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
  }

  .empty-form-state p {
    color: var(--text-muted);
    margin-bottom: 1rem;
  }

  /* Day Events Modal Content - matching seeker calendar */
  .day-event-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 0.75rem;
    margin-bottom: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-left: 4px solid var(--primary-color);
    transition: all 0.3s ease;
  }

  .day-event-item:hover {
    border-color: rgba(0, 230, 118, 0.3);
    background: rgba(255, 255, 255, 0.05);
  }

  .day-event-item.interview {
    border-left-color: #00E676;
  }

  .day-event-item.screening {
    border-left-color: #2196F3;
  }

  .day-event-item.technical {
    border-left-color: #FF9800;
  }

  .day-event-item.hr_round {
    border-left-color: #9C27B0;
  }

  .day-event-item.final {
    border-left-color: #F44336;
  }

  .day-event-item.other {
    border-left-color: #607D8B;
  }

  .day-event-time {
    flex-shrink: 0;
    text-align: center;
    min-width: 70px;
    padding: 0.5rem;
    background: rgba(0, 230, 118, 0.1);
    border-radius: 0.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }

  .day-event-time .time {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-color);
  }

  .day-event-time .duration {
    font-size: 0.75rem;
    color: var(--text-muted);
  }

  .day-event-info {
    flex: 1;
  }

  .day-event-info h4 {
    font-size: 1rem;
    margin-bottom: 0.25rem;
    color: var(--text-primary);
  }

  .day-event-info .seeker {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
  }

  .day-event-info .type-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: capitalize;
  }

  .type-badge.interview {
    background: rgba(0, 230, 118, 0.15);
    color: #00E676;
  }

  .type-badge.screening {
    background: rgba(33, 150, 243, 0.15);
    color: #2196F3;
  }

  .type-badge.technical {
    background: rgba(255, 152, 0, 0.15);
    color: #FF9800;
  }

  .type-badge.hr_round {
    background: rgba(156, 39, 176, 0.15);
    color: #9C27B0;
  }

  .type-badge.final {
    background: rgba(244, 67, 54, 0.15);
    color: #F44336;
  }

  .type-badge.other {
    background: rgba(96, 125, 139, 0.15);
    color: #607D8B;
  }

  .day-event-location {
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .day-event-location i {
    color: var(--primary-color);
  }

  /* Form Styles - matching seeker calendar */
  .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
  }

  .form-group {
    margin-bottom: 1rem;
  }

  .form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-primary);
  }

  .required {
    color: #F44336;
  }

  .form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    color: var(--text-primary);
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

  /* Buttons */
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    font-size: 0.9rem;
    text-decoration: none;
  }

  .btn-primary {
    background: linear-gradient(135deg, #00E676, #00C853);
    color: #000 !important;
    box-shadow: 0 4px 15px rgba(0, 230, 118, 0.3);
  }

  .btn-primary:hover {
    background: linear-gradient(135deg, #00ff88, #00E676);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 230, 118, 0.4);
  }

  .btn-outline {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #fff;
  }

  .btn-outline:hover {
    border-color: #00E676;
    color: #00E676;
  }

  .btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #fff;
  }

  .btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
  }

  /* Responsive */
  @media (max-width: 992px) {
    .calendar-layout {
      grid-template-columns: 1fr;
    }

    .calendar-sidebar {
      order: -1;
    }
  }

  @media (max-width: 768px) {
    .calendar-page {
      padding: 1rem;
    }

    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }

    .calendar-header {
      flex-direction: column;
      gap: 1rem;
    }

    .calendar-day {
      padding: 0.25rem;
      font-size: 0.8rem;
      min-height: 60px;
    }

    .form-row {
      grid-template-columns: 1fr;
    }

    .modal-content {
      margin: 1rem;
      max-height: calc(100vh - 2rem);
    }
  }
</style>

<script>
  function openScheduleModal() {
    document.getElementById('scheduleModal').classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.remove('active');
    document.body.style.overflow = '';
  }

  function openDayEventsModal() {
    document.getElementById('dayEventsModal').classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeDayEventsModal() {
    document.getElementById('dayEventsModal').classList.remove('active');
    document.body.style.overflow = '';
  }

  function showDayEvents(dateStr) {
    const events = eventsData[dateStr] || [];
    const date = new Date(dateStr + 'T12:00:00');
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const formattedDate = date.toLocaleDateString('en-US', options);

    document.getElementById('dayEventsTitle').textContent = formattedDate;

    let html = '';
    if (events.length === 0) {
      html = '<p class="text-center text-muted">No events scheduled for this day.</p>';
    } else {
      events.forEach(event => {
        const eventTime = new Date('2000-01-01T' + event.event_time);
        const formattedTime = eventTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        const eventType = event.event_type || 'interview';
        const typeLabel = eventType.replace('_', ' ');

        html += `
          <div class="day-event-item ${eventType}">
            <div class="day-event-time">
              <div class="time">${formattedTime}</div>
              <div class="duration">${event.duration_minutes || 60} min</div>
            </div>
            <div class="day-event-info">
              <h4>${escapeHtml(event.event_title || 'Interview')}</h4>
              <p class="seeker"><i class="fas fa-user"></i> ${escapeHtml(event.seeker_name || 'Candidate')}</p>
              ${event.job_title ? `<p class="seeker"><i class="fas fa-briefcase"></i> ${escapeHtml(event.job_title)}</p>` : ''}
              <span class="type-badge ${eventType}">${typeLabel}</span>
              ${event.location ? `<div class="day-event-location"><i class="fas fa-map-marker-alt"></i>${escapeHtml(event.location)}</div>` : ''}
              ${event.meeting_link ? `<div class="day-event-location"><i class="fas fa-video"></i><a href="${escapeHtml(event.meeting_link)}" target="_blank" style="color: var(--primary-color);">Join Meeting</a></div>` : ''}
            </div>
          </div>
        `;
      });
    }

    document.getElementById('dayEventsContent').innerHTML = html;
    openDayEventsModal();
  }

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function updateJobId(select) {
    const option = select.options[select.selectedIndex];
    document.getElementById('job_id').value = option.dataset.job || '';
    document.getElementById('application_id').value = option.dataset.app || '';
  }

  // Close modal on escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeScheduleModal();
      closeDayEventsModal();
    }
  });
</script>

<?php require_once '../includes/footer.php'; ?>