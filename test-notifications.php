<?php
/**
 * Test notification API - delete after debugging
 */
require_once 'config/config.php';
require_once 'classes/Database.php';

echo "<h2>Notification Test</h2>";

// Check session
echo "<h3>Session Info:</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "User ID in session: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "<br>";
echo "Is logged in: " . (isLoggedIn() ? 'YES' : 'NO') . "<br>";

if (isLoggedIn()) {
  $userId = $_SESSION['user_id'];
  $db = Database::getInstance()->getConnection();

  // Check notifications table
  echo "<h3>Notifications for user $userId:</h3>";
  try {
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Count: " . count($notifications) . "<br>";
    echo "<pre>";
    print_r($notifications);
    echo "</pre>";
  } catch (Exception $e) {
    echo "Error: " . $e->getMessage();
  }

  // Direct API simulation
  echo "<h3>Direct API Simulation:</h3>";
  $apiResult = json_encode(['success' => true, 'data' => $notifications]);
  echo "<pre>$apiResult</pre>";

  // Test API call
  echo "<h3>AJAX API Test (click button):</h3>";
  echo "<button onclick=\"testAPI()\">Test API via AJAX</button>";
  echo "<pre id=\"apiResult\">Click button above...</pre>";
  ?>
  <script>
    function testAPI() {
      var url = '<?php echo BASE_URL; ?>/api/notifications.php?action=list&limit=10';
      document.getElementById('apiResult').textContent = 'Fetching: ' + url + '\n';

      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.withCredentials = true;

      xhr.onload = function () {
        document.getElementById('apiResult').textContent += 'Status: ' + xhr.status + '\n';
        document.getElementById('apiResult').textContent += 'Response: ' + xhr.responseText;
      };

      xhr.onerror = function () {
        document.getElementById('apiResult').textContent += 'Network Error!';
      };

      xhr.send();
    }

    // Auto-run on page load
    window.onload = testAPI;
  </script>
  <?php
} else {
  echo "<p>Not logged in. Please <a href='" . BASE_URL . "/auth/login.php'>login</a> first.</p>";
}
?>