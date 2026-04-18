<?php
session_start();
include 'config.php';
include 'auth.php';
requireLogin();

if (!isset($_SESSION['permissions'])) {
    setSessionPermissions($conn, $_SESSION['role']);
}

requirePermission('send_communications');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];
$user_role = $_SESSION['role'];
$user_department = isset($_SESSION['department']) ? $_SESSION['department'] : '';

// Basic lists for dropdowns
$departments = ['GEB', 'Gujarat Gas', 'Municipality'];
if (in_array($user_role, ['admin', 'manager'])) {
    $departments[] = 'All Departments';
}
$priorities = ['low', 'medium', 'high', 'urgent'];

$error = '';

// Handle send form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_department = isset($_POST['recipient_department']) ? $_POST['recipient_department'] : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $priority = isset($_POST['priority']) ? $_POST['priority'] : 'medium';

    if ($recipient_department === '' || $subject === '' || $message === '') {
        $error = 'Please fill all fields before sending.';
    } else {
        if (!in_array($priority, $priorities)) {
            $priority = 'medium';
        }

        // Keep "All Departments" as explicit value for shared broadcasts
        $recipient_department_db = $recipient_department;

        $stmt = $conn->prepare("INSERT INTO communications (sender_id, recipient_department, subject, message, priority) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $recipient_department_db, $subject, $message, $priority);
        $stmt->execute();
        $stmt->close();

        header("Location: department-communication.php");
        exit();
    }
}

// Load recent messages
$communications = [];
$canSeeAllMessages = in_array($user_role, ['admin', 'manager']);

if ($canSeeAllMessages) {
    $sql = "SELECT c.subject, c.message, c.priority, c.created_at, c.recipient_department,
                   u.full_name AS sender_name, u.department AS sender_department
            FROM communications c
            LEFT JOIN users u ON c.sender_id = u.id
            ORDER BY c.created_at DESC
            LIMIT 20";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $communications[] = $row;
        }
    }
} else {
    $stmt = $conn->prepare("SELECT c.subject, c.message, c.priority, c.created_at, c.recipient_department,
                                   u.full_name AS sender_name, u.department AS sender_department
                            FROM communications c
                            LEFT JOIN users u ON c.sender_id = u.id
                            WHERE c.sender_id = ? OR c.recipient_department = ? OR c.recipient_department = 'All Departments'
                            ORDER BY c.created_at DESC
                            LIMIT 20");
    $stmt->bind_param("is", $user_id, $user_department);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $communications[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Department Communication - City Management</title>
  <link rel="stylesheet" href="assets/styles.css" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
  <header class="govt-header">
    <div class="govt-header-content">
      <div class="govt-header-row">
        <div class="govt-header-emblem">MC</div>
        <div class="govt-header-text">
          <div class="govt-header-title">Municipal Corporation</div>
          <div class="govt-header-subtitle">City Road & Infrastructure Management Portal</div>
        </div>
      </div>
    </div>
  </header>

  <nav class="govt-nav">
    <div class="govt-nav-content">
      <a href="home.php">Dashboard</a>
      <a href="road-details.php">Infrastructure Projects</a>
      <?php if (hasPermission('submit_feedback')): ?>
        <a href="citizen-complaint.php">Complaint Portal</a>
      <?php endif; ?>
      <?php if (hasAnyPermission(['view_all_projects', 'view_assigned_projects'])): ?>
        <a href="department-projects.php">Department Projects</a>
      <?php endif; ?>
      <a href="department-communication.php" class="active">Inter-Department Communication</a>
      <?php if (hasPermission('submit_work_report')): ?>
        <a href="home.php#employee-workflow">Submit Work</a>
      <?php endif; ?>
      <?php if (hasPermission('verify_work_report')): ?>
        <a href="home.php#manager-workflow">Manager Review</a>
      <?php endif; ?>
      <?php if (hasPermission('approve_work_completion')): ?>
        <a href="home.php#tdo-workflow">TDO Approval</a>
      <?php endif; ?>
      <div class="nav-user">
        <span class="nav-user-text">
          Welcome, <?php echo htmlspecialchars($user_name); ?>
          <span class="nav-role-badge"><?php echo htmlspecialchars(ucfirst($user_role)); ?></span>
        </span>
        <a href="logout.php">Logout</a>
      </div>
    </div>
  </nav>

  <div class="govt-container">
    <div class="govt-card">
      <h1 style="margin-bottom: 10px;">Inter-Department Communication</h1>
      <p style="margin-bottom: 15px;">
        Use this page to send basic messages between departments like GEB, Gujarat Gas and Municipality.
      </p>

      <?php if ($user_department): ?>
        <p style="font-size: 0.9rem; color: #4b5563; margin-bottom: 15px;">
          You are logged in from department: <strong><?php echo htmlspecialchars($user_department); ?></strong>
        </p>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-error">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="department-communication.php" style="margin-bottom: 25px;">
        <div style="margin-bottom: 10px;">
          <label for="recipient_department">Send To Department:</label>
          <select name="recipient_department" id="recipient_department">
            <option value="">-- Select Department --</option>
            <?php foreach ($departments as $dept): ?>
              <option value="<?php echo htmlspecialchars($dept); ?>">
                <?php echo htmlspecialchars($dept); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="margin-bottom: 10px;">
          <label for="subject">Subject:</label>
          <input type="text" name="subject" id="subject" placeholder="Short subject" />
        </div>

        <div style="margin-bottom: 10px;">
          <label for="message">Message:</label>
          <textarea name="message" id="message" rows="4" placeholder="Write your message here..."></textarea>
        </div>

        <div style="margin-bottom: 15px;">
          <label for="priority">Priority:</label>
          <select name="priority" id="priority">
            <?php foreach ($priorities as $p): ?>
              <option value="<?php echo htmlspecialchars($p); ?>">
                <?php echo htmlspecialchars(ucfirst($p)); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit">Send Message</button>
      </form>
    </div>

    <div class="govt-card">
      <h2>Recent Messages (Last 20)</h2>
      <div class="govt-table-container">
        <table class="govt-table">
          <thead>
            <tr>
              <th>Time</th>
              <th>From</th>
              <th>To Department</th>
              <th>Priority</th>
              <th>Subject</th>
              <th>Message</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($communications) === 0): ?>
              <tr>
                <td colspan="6">No messages yet. Send one using the form above.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($communications as $c): ?>
                <tr>
                  <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                  <td>
                    <?php
                      $fromText = $c['sender_name'] ? $c['sender_name'] : 'Unknown';
                      if ($c['sender_department']) {
                          $fromText .= ' (' . $c['sender_department'] . ')';
                      }
                      echo htmlspecialchars($fromText);
                    ?>
                  </td>
                  <td><?php echo htmlspecialchars($c['recipient_department']); ?></td>
                  <td><?php echo htmlspecialchars(ucfirst($c['priority'])); ?></td>
                  <td><?php echo htmlspecialchars($c['subject']); ?></td>
                  <td><?php echo htmlspecialchars($c['message']); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
