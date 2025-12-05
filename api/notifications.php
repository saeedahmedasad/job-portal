<?php
/**
 * JobNexus - Notifications API
 * AJAX endpoint for user notifications
 */

// Disable HTML error output for clean JSON
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once '../config/config.php';
require_once '../classes/Database.php';

try {
  $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Database connection error']);
  exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Authentication required']);
  exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
  switch ($method) {
    case 'GET':
      $action = $_GET['action'] ?? 'list';

      if ($action === 'list') {
        $limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : 10;
        $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        if ($unreadOnly) {
          $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT $limit";

        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $notifications]);
      }

      if ($action === 'count') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $count = $stmt->fetchColumn();

        echo json_encode(['success' => true, 'data' => ['unread_count' => (int) $count]]);
      }
      break;

    case 'POST':
      $input = json_decode(file_get_contents('php://input'), true);
      $action = $input['action'] ?? '';

      if ($action === 'mark_read') {
        $notificationId = isset($input['id']) ? intval($input['id']) : 0;

        if ($notificationId) {
          $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
          $stmt->execute([$notificationId, $userId]);
        } else {
          // Mark all as read
          $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
          $stmt->execute([$userId]);
        }

        echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
      }

      if ($action === 'delete') {
        $notificationId = isset($input['id']) ? intval($input['id']) : 0;

        if ($notificationId) {
          $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
          $stmt->execute([$notificationId, $userId]);
          echo json_encode(['success' => true, 'message' => 'Notification deleted']);
        } else {
          http_response_code(400);
          echo json_encode(['success' => false, 'message' => 'Notification ID required']);
        }
      }
      break;

    default:
      http_response_code(405);
      echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  }
} catch (PDOException $e) {
  // Table might not exist - return empty array gracefully
  if (strpos($e->getMessage(), 'notifications') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
    echo json_encode(['success' => true, 'data' => []]);
  } else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error']);
}
