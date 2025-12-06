<?php
/**
 * JobNexus - Video Meeting Room
 * WebRTC-based video conferencing for interviews
 */

require_once 'config/config.php';
require_once 'classes/Database.php';

// Get meeting token from URL
$meetingToken = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($meetingToken)) {
  setFlash('error', 'Invalid meeting link');
  redirect(BASE_URL);
}

$db = Database::getInstance()->getConnection();

// Get meeting details
$stmt = $db->prepare("
  SELECT e.*, 
         j.title as job_title,
         c.company_name,
         sp.first_name as seeker_first_name,
         sp.last_name as seeker_last_name,
         hr_user.email as hr_email,
         seeker_user.email as seeker_email
  FROM events e
  LEFT JOIN applications a ON e.application_id = a.id
  LEFT JOIN jobs j ON a.job_id = j.id
  LEFT JOIN companies c ON j.company_id = c.id
  LEFT JOIN users hr_user ON e.hr_user_id = hr_user.id
  LEFT JOIN users seeker_user ON e.seeker_user_id = seeker_user.id
  LEFT JOIN seeker_profiles sp ON e.seeker_user_id = sp.user_id
  WHERE e.meeting_token = ?
");
$stmt->execute([$meetingToken]);
$meeting = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$meeting) {
  setFlash('error', 'Meeting not found');
  redirect(BASE_URL);
}

// Check if user is authorized (must be HR or Seeker of this meeting)
$isAuthorized = false;
$userRole = '';
$userName = '';

if (isLoggedIn()) {
  $userId = getCurrentUserId();
  if ($userId == $meeting['hr_user_id']) {
    $isAuthorized = true;
    $userRole = 'interviewer';
    $userName = $meeting['company_name'] ?? 'Interviewer';
  } elseif ($userId == $meeting['seeker_user_id']) {
    $isAuthorized = true;
    $userRole = 'candidate';
    $userName = ($meeting['seeker_first_name'] ?? '') . ' ' . ($meeting['seeker_last_name'] ?? '');
    $userName = trim($userName) ?: 'Candidate';
  }
}

// Check meeting status
$meetingStatus = $meeting['status'];
$isCancelled = $meetingStatus === 'cancelled';
$isCompleted = $meetingStatus === 'completed';

// Check if meeting is within time window (30 mins before to 2 hours after scheduled time)
$meetingDateTime = strtotime($meeting['event_date'] . ' ' . $meeting['event_time']);
$now = time();
$canJoin = ($now >= $meetingDateTime - 1800) && ($now <= $meetingDateTime + 7200); // 30 min before, 2 hours after

$pageTitle = 'Meeting - ' . ($meeting['job_title'] ?? 'Interview');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle); ?> | JobNexus</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #6366f1;
      --primary-dark: #4f46e5;
      --bg-dark: #0f0f1a;
      --bg-card: #1a1a2e;
      --bg-secondary: #252542;
      --text-primary: #ffffff;
      --text-secondary: #a0a0b0;
      --border-color: rgba(255, 255, 255, 0.1);
      --success: #10b981;
      --danger: #ef4444;
      --warning: #f59e0b;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg-dark);
      color: var(--text-primary);
      min-height: 100vh;
    }

    .meeting-container {
      display: flex;
      flex-direction: column;
      height: 100vh;
    }

    /* Header */
    .meeting-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 2rem;
      background: var(--bg-card);
      border-bottom: 1px solid var(--border-color);
    }

    .meeting-info {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .meeting-logo {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary);
    }

    .meeting-logo span {
      color: var(--text-primary);
    }

    .meeting-title {
      font-size: 1rem;
      color: var(--text-secondary);
    }

    .meeting-title strong {
      color: var(--text-primary);
    }

    .meeting-time {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--text-secondary);
      font-size: 0.875rem;
    }

    .leave-btn {
      background: var(--danger);
      color: white;
      border: none;
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.2s;
    }

    .leave-btn:hover {
      background: #dc2626;
      transform: scale(1.02);
    }

    /* Video Grid */
    .video-section {
      flex: 1;
      display: flex;
      padding: 1rem;
      gap: 1rem;
      background: var(--bg-dark);
    }

    .video-grid {
      flex: 1;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
      gap: 1rem;
      align-content: center;
    }

    .video-container {
      position: relative;
      background: var(--bg-card);
      border-radius: 16px;
      overflow: hidden;
      aspect-ratio: 16/9;
      border: 2px solid var(--border-color);
    }

    .video-container.active-speaker {
      border-color: var(--primary);
      box-shadow: 0 0 20px rgba(99, 102, 241, 0.3);
    }

    .video-container video {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .video-container.video-off {
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .video-avatar {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 3rem;
      font-weight: 700;
      color: white;
    }

    .video-label {
      position: absolute;
      bottom: 1rem;
      left: 1rem;
      background: rgba(0, 0, 0, 0.7);
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-size: 0.875rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .video-label .mic-status {
      color: var(--danger);
    }

    .video-label .mic-status.active {
      color: var(--success);
    }

    /* Controls */
    .meeting-controls {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 1rem;
      padding: 1.5rem;
      background: var(--bg-card);
      border-top: 1px solid var(--border-color);
    }

    .control-btn {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
      transition: all 0.2s;
      background: var(--bg-secondary);
      color: var(--text-primary);
    }

    .control-btn:hover {
      background: var(--primary);
      transform: scale(1.05);
    }

    .control-btn.active {
      background: var(--danger);
    }

    .control-btn.active:hover {
      background: #dc2626;
    }

    .control-btn.screen-share.sharing {
      background: var(--success);
    }

    /* Chat Sidebar */
    .chat-sidebar {
      width: 350px;
      background: var(--bg-card);
      border-left: 1px solid var(--border-color);
      display: flex;
      flex-direction: column;
      display: none;
    }

    .chat-sidebar.open {
      display: flex;
    }

    .chat-header {
      padding: 1rem;
      border-bottom: 1px solid var(--border-color);
      font-weight: 600;
    }

    .chat-messages {
      flex: 1;
      overflow-y: auto;
      padding: 1rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .chat-message {
      max-width: 85%;
    }

    .chat-message.own {
      align-self: flex-end;
    }

    .chat-message .sender {
      font-size: 0.75rem;
      color: var(--text-secondary);
      margin-bottom: 0.25rem;
    }

    .chat-message .content {
      background: var(--bg-secondary);
      padding: 0.75rem 1rem;
      border-radius: 12px;
      font-size: 0.875rem;
    }

    .chat-message.own .content {
      background: var(--primary);
    }

    .chat-input {
      padding: 1rem;
      border-top: 1px solid var(--border-color);
      display: flex;
      gap: 0.5rem;
    }

    .chat-input input {
      flex: 1;
      background: var(--bg-secondary);
      border: 1px solid var(--border-color);
      border-radius: 8px;
      padding: 0.75rem 1rem;
      color: var(--text-primary);
      font-size: 0.875rem;
    }

    .chat-input input:focus {
      outline: none;
      border-color: var(--primary);
    }

    .chat-input button {
      background: var(--primary);
      color: white;
      border: none;
      padding: 0.75rem 1rem;
      border-radius: 8px;
      cursor: pointer;
    }

    /* Waiting Room */
    .waiting-room {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 2rem;
    }

    .waiting-room h2 {
      font-size: 2rem;
      margin-bottom: 1rem;
    }

    .waiting-room p {
      color: var(--text-secondary);
      margin-bottom: 2rem;
      max-width: 500px;
    }

    .waiting-room .meeting-details {
      background: var(--bg-card);
      padding: 2rem;
      border-radius: 16px;
      margin-bottom: 2rem;
      min-width: 400px;
    }

    .waiting-room .detail-row {
      display: flex;
      justify-content: space-between;
      padding: 0.75rem 0;
      border-bottom: 1px solid var(--border-color);
    }

    .waiting-room .detail-row:last-child {
      border-bottom: none;
    }

    .waiting-room .detail-label {
      color: var(--text-secondary);
    }

    .waiting-room .detail-value {
      font-weight: 600;
    }

    .join-btn {
      background: var(--primary);
      color: white;
      border: none;
      padding: 1rem 3rem;
      border-radius: 12px;
      font-size: 1.125rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      transition: all 0.2s;
    }

    .join-btn:hover {
      background: var(--primary-dark);
      transform: scale(1.02);
    }

    .join-btn:disabled {
      background: var(--bg-secondary);
      cursor: not-allowed;
    }

    /* Preview Video */
    .preview-section {
      margin-bottom: 2rem;
    }

    .preview-video {
      width: 400px;
      height: 225px;
      background: var(--bg-card);
      border-radius: 16px;
      overflow: hidden;
      margin-bottom: 1rem;
    }

    .preview-video video {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transform: scaleX(-1);
    }

    .preview-controls {
      display: flex;
      justify-content: center;
      gap: 1rem;
    }

    /* Status Messages */
    .status-message {
      padding: 2rem;
      text-align: center;
    }

    .status-message.error {
      color: var(--danger);
    }

    .status-message.warning {
      color: var(--warning);
    }

    .status-icon {
      font-size: 4rem;
      margin-bottom: 1rem;
    }

    /* Login Prompt */
    .login-prompt {
      background: var(--bg-card);
      padding: 2rem;
      border-radius: 16px;
      text-align: center;
    }

    .login-prompt a {
      color: var(--primary);
      text-decoration: none;
    }

    .login-prompt a:hover {
      text-decoration: underline;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .meeting-header {
        flex-direction: column;
        gap: 1rem;
        padding: 1rem;
      }

      .video-grid {
        grid-template-columns: 1fr;
      }

      .chat-sidebar {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        z-index: 100;
      }

      .control-btn {
        width: 48px;
        height: 48px;
        font-size: 1rem;
      }

      .waiting-room .meeting-details {
        min-width: auto;
        width: 100%;
      }

      .preview-video {
        width: 100%;
        max-width: 400px;
      }
    }
  </style>
</head>

<body>
  <div class="meeting-container">
    <?php if ($isCancelled): ?>
      <!-- Meeting Cancelled -->
      <div class="waiting-room">
        <div class="status-message error">
          <div class="status-icon"><i class="fas fa-calendar-xmark"></i></div>
          <h2>Meeting Cancelled</h2>
          <p>This interview has been cancelled. Please contact the employer for more information.</p>
          <a href="<?php echo BASE_URL; ?>" class="join-btn" style="margin-top: 1rem;">
            <i class="fas fa-home"></i> Return Home
          </a>
        </div>
      </div>

    <?php elseif ($isCompleted): ?>
      <!-- Meeting Completed -->
      <div class="waiting-room">
        <div class="status-message">
          <div class="status-icon" style="color: var(--success);"><i class="fas fa-check-circle"></i></div>
          <h2>Meeting Completed</h2>
          <p>This interview has already been completed.</p>
          <a href="<?php echo BASE_URL; ?>" class="join-btn" style="margin-top: 1rem;">
            <i class="fas fa-home"></i> Return Home
          </a>
        </div>
      </div>

    <?php elseif (!isLoggedIn()): ?>
      <!-- Login Required -->
      <div class="waiting-room">
        <div class="meeting-logo">Job<span>Nexus</span></div>
        <h2 style="margin-top: 2rem;">Interview Meeting</h2>
        <p>Please log in to join this meeting.</p>

        <div class="meeting-details">
          <div class="detail-row">
            <span class="detail-label">Position</span>
            <span class="detail-value"><?php echo htmlspecialchars($meeting['job_title'] ?? 'Interview'); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Company</span>
            <span class="detail-value"><?php echo htmlspecialchars($meeting['company_name'] ?? 'N/A'); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Date</span>
            <span class="detail-value"><?php echo date('F j, Y', strtotime($meeting['event_date'])); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Time</span>
            <span class="detail-value"><?php echo date('g:i A', strtotime($meeting['event_time'])); ?></span>
          </div>
        </div>

        <div class="login-prompt">
          <p>You need to be logged in as the interviewer or candidate to join.</p>
          <p style="margin-top: 1rem;">
            <a href="<?php echo BASE_URL; ?>/auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">
              <i class="fas fa-sign-in-alt"></i> Log in to continue
            </a>
          </p>
        </div>
      </div>

    <?php elseif (!$isAuthorized): ?>
      <!-- Not Authorized -->
      <div class="waiting-room">
        <div class="status-message error">
          <div class="status-icon"><i class="fas fa-lock"></i></div>
          <h2>Access Denied</h2>
          <p>You are not authorized to join this meeting. Only the interviewer and candidate can access this room.</p>
          <a href="<?php echo BASE_URL; ?>" class="join-btn" style="margin-top: 1rem;">
            <i class="fas fa-home"></i> Return Home
          </a>
        </div>
      </div>

    <?php elseif (!$canJoin): ?>
      <!-- Meeting Not Started Yet -->
      <div class="waiting-room">
        <div class="meeting-logo">Job<span>Nexus</span></div>
        <h2 style="margin-top: 2rem;">
          <?php if ($now < $meetingDateTime - 1800): ?>
            Meeting Not Started Yet
          <?php else: ?>
            Meeting Has Ended
          <?php endif; ?>
        </h2>

        <div class="meeting-details">
          <div class="detail-row">
            <span class="detail-label">Position</span>
            <span class="detail-value"><?php echo htmlspecialchars($meeting['job_title'] ?? 'Interview'); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Company</span>
            <span class="detail-value"><?php echo htmlspecialchars($meeting['company_name'] ?? 'N/A'); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Scheduled Date</span>
            <span class="detail-value"><?php echo date('F j, Y', strtotime($meeting['event_date'])); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Scheduled Time</span>
            <span class="detail-value"><?php echo date('g:i A', strtotime($meeting['event_time'])); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Duration</span>
            <span class="detail-value"><?php echo $meeting['duration_minutes']; ?> minutes</span>
          </div>
        </div>

        <?php if ($now < $meetingDateTime - 1800): ?>
          <div class="status-message warning">
            <p><i class="fas fa-clock"></i> You can join 30 minutes before the scheduled time.</p>
          </div>
        <?php endif; ?>

        <a href="<?php echo BASE_URL; ?>" class="join-btn" style="margin-top: 1rem;">
          <i class="fas fa-home"></i> Return Home
        </a>
      </div>

    <?php else: ?>
      <!-- Waiting Room / Join Screen -->
      <div class="waiting-room" id="waitingRoom">
        <div class="meeting-logo">Job<span>Nexus</span></div>
        <h2 style="margin-top: 2rem;">Ready to Join?</h2>
        <p>Check your camera and microphone before joining the meeting.</p>

        <div class="preview-section">
          <div class="preview-video">
            <video id="previewVideo" autoplay muted playsinline></video>
          </div>
          <div class="preview-controls">
            <button class="control-btn" id="previewMicBtn" title="Toggle Microphone">
              <i class="fas fa-microphone"></i>
            </button>
            <button class="control-btn" id="previewCamBtn" title="Toggle Camera">
              <i class="fas fa-video"></i>
            </button>
          </div>
        </div>

        <div class="meeting-details">
          <div class="detail-row">
            <span class="detail-label">Position</span>
            <span class="detail-value"><?php echo htmlspecialchars($meeting['job_title'] ?? 'Interview'); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Company</span>
            <span class="detail-value"><?php echo htmlspecialchars($meeting['company_name'] ?? 'N/A'); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Your Role</span>
            <span class="detail-value"><?php echo ucfirst($userRole); ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Time</span>
            <span class="detail-value"><?php echo date('g:i A', strtotime($meeting['event_time'])); ?></span>
          </div>
        </div>

        <button class="join-btn" id="joinBtn">
          <i class="fas fa-video"></i> Join Meeting
        </button>
      </div>

      <!-- Meeting Room (Hidden Initially) -->
      <div id="meetingRoom" style="display: none; flex-direction: column; height: 100%;">
        <header class="meeting-header">
          <div class="meeting-info">
            <div class="meeting-logo">Job<span>Nexus</span></div>
            <div class="meeting-title">
              <strong><?php echo htmlspecialchars($meeting['job_title'] ?? 'Interview'); ?></strong>
              <span> • <?php echo htmlspecialchars($meeting['company_name'] ?? ''); ?></span>
            </div>
          </div>
          <div class="meeting-time">
            <i class="fas fa-clock"></i>
            <span id="meetingTimer">00:00</span>
          </div>
          <button class="leave-btn" id="leaveBtn">
            <i class="fas fa-phone-slash"></i> Leave
          </button>
        </header>

        <div class="video-section">
          <div class="video-grid" id="videoGrid">
            <!-- Local Video -->
            <div class="video-container" id="localVideoContainer">
              <video id="localVideo" autoplay muted playsinline></video>
              <div class="video-label">
                <span><?php echo htmlspecialchars($userName); ?> (You)</span>
                <i class="fas fa-microphone mic-status active" id="localMicStatus"></i>
              </div>
            </div>
            <!-- Remote Video -->
            <div class="video-container video-off" id="remoteVideoContainer">
              <div class="video-avatar" id="remoteAvatar">?</div>
              <video id="remoteVideo" autoplay playsinline style="display: none;"></video>
              <div class="video-label">
                <span id="remoteName">Waiting for participant...</span>
                <i class="fas fa-microphone mic-status" id="remoteMicStatus"></i>
              </div>
            </div>
          </div>

          <!-- Chat Sidebar -->
          <div class="chat-sidebar" id="chatSidebar">
            <div class="chat-header">
              <i class="fas fa-comments"></i> Chat
              <button onclick="toggleChat()"
                style="float: right; background: none; border: none; color: var(--text-secondary); cursor: pointer;">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <div class="chat-messages" id="chatMessages">
              <div class="chat-message">
                <div class="sender">System</div>
                <div class="content">Welcome to the meeting! Chat messages are shared with all participants.</div>
              </div>
            </div>
            <div class="chat-input">
              <input type="text" id="chatInput" placeholder="Type a message..."
                onkeypress="if(event.key==='Enter')sendMessage()">
              <button onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
          </div>
        </div>

        <div class="meeting-controls">
          <button class="control-btn" id="micBtn" title="Toggle Microphone">
            <i class="fas fa-microphone"></i>
          </button>
          <button class="control-btn" id="camBtn" title="Toggle Camera">
            <i class="fas fa-video"></i>
          </button>
          <button class="control-btn screen-share" id="screenBtn" title="Share Screen">
            <i class="fas fa-desktop"></i>
          </button>
          <button class="control-btn" id="chatBtn" title="Toggle Chat">
            <i class="fas fa-comments"></i>
          </button>
          <button class="control-btn active" id="endBtn" title="End Call">
            <i class="fas fa-phone-slash"></i>
          </button>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($isAuthorized && $canJoin && !$isCancelled && !$isCompleted): ?>
    <script>
      // Meeting configuration
      const meetingConfig = {
        token: '<?php echo htmlspecialchars($meetingToken); ?>',
        userName: '<?php echo htmlspecialchars($userName); ?>',
        userRole: '<?php echo $userRole; ?>',
        meetingId: <?php echo $meeting['id']; ?>
      };

      // State
      let localStream = null;
      let isMicOn = true;
      let isCamOn = true;
      let isScreenSharing = false;
      let meetingStartTime = null;
      let timerInterval = null;

      // Elements
      const previewVideo = document.getElementById('previewVideo');
      const localVideo = document.getElementById('localVideo');
      const waitingRoom = document.getElementById('waitingRoom');
      const meetingRoom = document.getElementById('meetingRoom');

      // Initialize preview
      async function initPreview() {
        try {
          localStream = await navigator.mediaDevices.getUserMedia({
            video: true,
            audio: true
          });
          previewVideo.srcObject = localStream;
        } catch (err) {
          console.error('Error accessing media devices:', err);
          alert('Could not access camera/microphone. Please check permissions.');
        }
      }

      // Toggle microphone in preview
      document.getElementById('previewMicBtn').addEventListener('click', function () {
        if (localStream) {
          const audioTrack = localStream.getAudioTracks()[0];
          if (audioTrack) {
            audioTrack.enabled = !audioTrack.enabled;
            isMicOn = audioTrack.enabled;
            this.classList.toggle('active', !isMicOn);
            this.innerHTML = isMicOn ? '<i class="fas fa-microphone"></i>' : '<i class="fas fa-microphone-slash"></i>';
          }
        }
      });

      // Toggle camera in preview
      document.getElementById('previewCamBtn').addEventListener('click', function () {
        if (localStream) {
          const videoTrack = localStream.getVideoTracks()[0];
          if (videoTrack) {
            videoTrack.enabled = !videoTrack.enabled;
            isCamOn = videoTrack.enabled;
            this.classList.toggle('active', !isCamOn);
            this.innerHTML = isCamOn ? '<i class="fas fa-video"></i>' : '<i class="fas fa-video-slash"></i>';
          }
        }
      });

      // Join meeting
      document.getElementById('joinBtn').addEventListener('click', function () {
        waitingRoom.style.display = 'none';
        meetingRoom.style.display = 'flex';

        // Transfer stream to meeting room
        localVideo.srcObject = localStream;

        // Start timer
        meetingStartTime = Date.now();
        timerInterval = setInterval(updateTimer, 1000);

        // Update mic button state
        document.getElementById('micBtn').classList.toggle('active', !isMicOn);
        document.getElementById('micBtn').innerHTML = isMicOn ? '<i class="fas fa-microphone"></i>' : '<i class="fas fa-microphone-slash"></i>';

        // Update cam button state
        document.getElementById('camBtn').classList.toggle('active', !isCamOn);
        document.getElementById('camBtn').innerHTML = isCamOn ? '<i class="fas fa-video"></i>' : '<i class="fas fa-video-slash"></i>';

        // Update local video container
        if (!isCamOn) {
          document.getElementById('localVideoContainer').classList.add('video-off');
        }
      });

      // Update timer
      function updateTimer() {
        const elapsed = Math.floor((Date.now() - meetingStartTime) / 1000);
        const minutes = Math.floor(elapsed / 60).toString().padStart(2, '0');
        const seconds = (elapsed % 60).toString().padStart(2, '0');
        document.getElementById('meetingTimer').textContent = `${minutes}:${seconds}`;
      }

      // Toggle microphone in meeting
      document.getElementById('micBtn').addEventListener('click', function () {
        if (localStream) {
          const audioTrack = localStream.getAudioTracks()[0];
          if (audioTrack) {
            audioTrack.enabled = !audioTrack.enabled;
            isMicOn = audioTrack.enabled;
            this.classList.toggle('active', !isMicOn);
            this.innerHTML = isMicOn ? '<i class="fas fa-microphone"></i>' : '<i class="fas fa-microphone-slash"></i>';
            document.getElementById('localMicStatus').classList.toggle('active', isMicOn);
          }
        }
      });

      // Toggle camera in meeting
      document.getElementById('camBtn').addEventListener('click', function () {
        if (localStream) {
          const videoTrack = localStream.getVideoTracks()[0];
          if (videoTrack) {
            videoTrack.enabled = !videoTrack.enabled;
            isCamOn = videoTrack.enabled;
            this.classList.toggle('active', !isCamOn);
            this.innerHTML = isCamOn ? '<i class="fas fa-video"></i>' : '<i class="fas fa-video-slash"></i>';
            document.getElementById('localVideoContainer').classList.toggle('video-off', !isCamOn);
          }
        }
      });

      // Screen share
      document.getElementById('screenBtn').addEventListener('click', async function () {
        if (!isScreenSharing) {
          try {
            const screenStream = await navigator.mediaDevices.getDisplayMedia({
              video: true
            });
            const videoTrack = screenStream.getVideoTracks()[0];

            // Replace video track
            const sender = localStream.getVideoTracks()[0];
            localVideo.srcObject = screenStream;

            isScreenSharing = true;
            this.classList.add('sharing');

            videoTrack.onended = () => {
              localVideo.srcObject = localStream;
              isScreenSharing = false;
              this.classList.remove('sharing');
            };
          } catch (err) {
            console.error('Error sharing screen:', err);
          }
        } else {
          localVideo.srcObject = localStream;
          isScreenSharing = false;
          this.classList.remove('sharing');
        }
      });

      // Toggle chat
      document.getElementById('chatBtn').addEventListener('click', toggleChat);

      function toggleChat() {
        document.getElementById('chatSidebar').classList.toggle('open');
      }

      // Send message
      function sendMessage() {
        const input = document.getElementById('chatInput');
        const message = input.value.trim();
        if (message) {
          const messagesContainer = document.getElementById('chatMessages');
          messagesContainer.innerHTML += `
            <div class="chat-message own">
              <div class="sender">You</div>
              <div class="content">${escapeHtml(message)}</div>
            </div>
          `;
          messagesContainer.scrollTop = messagesContainer.scrollHeight;
          input.value = '';
        }
      }

      function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      }

      // Leave meeting
      function leaveMeeting() {
        if (confirm('Are you sure you want to leave the meeting?')) {
          if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
          }
          clearInterval(timerInterval);
          window.location.href = '<?php echo BASE_URL; ?>';
        }
      }

      document.getElementById('leaveBtn').addEventListener('click', leaveMeeting);
      document.getElementById('endBtn').addEventListener('click', leaveMeeting);

      // Initialize on load
      initPreview();
    </script>
  <?php endif; ?>
</body>

</html>