<?php
/**
 * JobNexus - Notification Helper Functions
 * Use these functions to create notifications throughout the application
 */

require_once __DIR__ . '/../classes/Database.php';

/**
 * Create a notification for a user
 */
function createNotification($userId, $type, $title, $message, $link = null)
{
  $db = Database::getInstance()->getConnection();
  $stmt = $db->prepare("
        INSERT INTO notifications (user_id, type, title, message, link, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
  return $stmt->execute([$userId, $type, $title, $message, $link]);
}

/**
 * Notify seeker when their application status changes
 */
function notifyApplicationStatusChange($seekerId, $jobTitle, $newStatus, $applicationId)
{
  $statusMessages = [
    'viewed' => 'Your application has been viewed',
    'shortlisted' => 'Congratulations! You\'ve been shortlisted',
    'interview' => 'You\'ve been scheduled for an interview',
    'offered' => 'Great news! You\'ve received a job offer',
    'rejected' => 'Application update for',
    'hired' => 'Congratulations! You\'ve been hired'
  ];

  $title = $statusMessages[$newStatus] ?? 'Application Update';
  $message = "Your application for \"{$jobTitle}\" status has been updated to: " . ucfirst($newStatus);
  $link = "/seeker/applications.php?id={$applicationId}";

  return createNotification($seekerId, 'application', $title, $message, $link);
}

/**
 * Notify HR when new application is received
 */
function notifyNewApplication($hrUserId, $seekerName, $jobTitle, $applicationId)
{
  $title = "New Application Received";
  $message = "{$seekerName} applied for \"{$jobTitle}\"";
  $link = "/hr/applications.php?id={$applicationId}";

  return createNotification($hrUserId, 'application', $title, $message, $link);
}

/**
 * Notify about interview scheduling
 */
function notifyInterviewScheduled($userId, $jobTitle, $date, $time, $eventId)
{
  $formattedDate = date('M j, Y', strtotime($date));
  $formattedTime = date('g:i A', strtotime($time));

  $title = "Interview Scheduled";
  $message = "Interview for \"{$jobTitle}\" on {$formattedDate} at {$formattedTime}";
  $link = "/seeker/calendar.php";

  return createNotification($userId, 'interview', $title, $message, $link);
}

/**
 * Notify about interview cancellation
 */
function notifyInterviewCancelled($userId, $jobTitle, $date)
{
  $formattedDate = date('M j, Y', strtotime($date));

  $title = "Interview Cancelled";
  $message = "Your interview for \"{$jobTitle}\" on {$formattedDate} has been cancelled";
  $link = "/seeker/calendar.php";

  return createNotification($userId, 'interview', $title, $message, $link);
}

/**
 * Notify about interview rescheduling
 */
function notifyInterviewRescheduled($userId, $jobTitle, $newDate, $newTime)
{
  $formattedDate = date('M j, Y', strtotime($newDate));
  $formattedTime = date('g:i A', strtotime($newTime));

  $title = "Interview Rescheduled";
  $message = "Interview for \"{$jobTitle}\" has been rescheduled to {$formattedDate} at {$formattedTime}";
  $link = "/seeker/calendar.php";

  return createNotification($userId, 'interview', $title, $message, $link);
}

/**
 * Notify about new job matching seeker's profile
 */
function notifyNewJobMatch($seekerId, $jobTitle, $companyName, $jobId)
{
  $title = "New Job Match";
  $message = "{$companyName} is hiring: \"{$jobTitle}\"";
  $link = "/jobs/view.php?id={$jobId}";

  return createNotification($seekerId, 'job', $title, $message, $link);
}

/**
 * Notify HR about company verification status
 */
function notifyCompanyVerification($hrUserId, $status, $reason = null)
{
  if ($status === 'verified') {
    $title = "Company Verified";
    $message = "Your company has been verified. You can now post jobs!";
  } else {
    $title = "Verification Update";
    $message = "Your company verification status: " . ucfirst($status);
    if ($reason) {
      $message .= ". Reason: {$reason}";
    }
  }
  $link = "/hr/company.php";

  return createNotification($hrUserId, 'system', $title, $message, $link);
}

/**
 * Notify about profile view (for seekers)
 */
function notifyProfileViewed($seekerId, $companyName)
{
  $title = "Profile Viewed";
  $message = "{$companyName} viewed your profile";
  $link = "/seeker/profile.php";

  return createNotification($seekerId, 'system', $title, $message, $link);
}

/**
 * System notification
 */
function notifySystem($userId, $title, $message, $link = null)
{
  return createNotification($userId, 'system', $title, $message, $link);
}

/**
 * Get unread notification count for a user
 */
function getUnreadNotificationCount($userId)
{
  $db = Database::getInstance()->getConnection();
  $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
  $stmt->execute([$userId]);
  return (int) $stmt->fetchColumn();
}

/**
 * Mark all notifications as read for a user
 */
function markAllNotificationsRead($userId)
{
  $db = Database::getInstance()->getConnection();
  $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
  return $stmt->execute([$userId]);
}
