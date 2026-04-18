<?php
session_start();
include 'config.php';
include 'auth.php';
requireLogin();

if (!isset($_SESSION['permissions'])) {
    setSessionPermissions($conn, $_SESSION['role']);
}
requirePermission('submit_feedback');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];
$user_role = $_SESSION['role'];
$error = '';
$success = '';

$projects = [];
$res = $conn->query("SELECT id, project_name FROM projects ORDER BY project_name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $projects[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = isset($_POST['project_id']) && $_POST['project_id'] !== '' ? (int)$_POST['project_id'] : null;
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;

    if ($subject === '' || $message === '' || $rating < 1 || $rating > 5) {
        $error = 'Please fill all required fields correctly.';
    } else {
        $stmt = $conn->prepare("INSERT INTO feedback (citizen_id, project_id, subject, message, rating, status) VALUES (?, ?, ?, ?, ?, 'new')");
        $stmt->bind_param("iissi", $user_id, $project_id, $subject, $message, $rating);
        $stmt->execute();
        $stmt->close();
        $success = 'Complaint submitted successfully.';
    }
}

$myComplaints = [];
$stmt = $conn->prepare("SELECT f.subject, f.status, f.created_at, p.project_name
                        FROM feedback f
                        LEFT JOIN projects p ON f.project_id = p.id
                        WHERE f.citizen_id = ?
                        ORDER BY f.created_at DESC
                        LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) {
    $myComplaints[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Citizen Complaint Portal</title>
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
      <a href="citizen-complaint.php" class="active">Complaint Portal</a>
      <?php if (hasAnyPermission(['view_all_projects', 'view_assigned_projects'])): ?>
        <a href="department-projects.php">Department Projects</a>
      <?php endif; ?>
      <?php if (hasPermission('send_communications')): ?>
        <a href="department-communication.php">Inter-Department Communication</a>
      <?php endif; ?>
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
      <h2>Submit Complaint / Feedback</h2>
      <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
      <form method="POST">
        <label>Project (optional)</label>
        <select name="project_id">
          <option value="">General Complaint</option>
          <?php foreach ($projects as $project): ?>
            <option value="<?php echo (int)$project['id']; ?>"><?php echo htmlspecialchars($project['project_name']); ?></option>
          <?php endforeach; ?>
        </select>
        <label>Subject</label>
        <input type="text" name="subject" required />
        <label>Complaint Details</label>
        <textarea name="message" rows="4" required></textarea>
        <label>Rating (1-5)</label>
        <input type="number" name="rating" min="1" max="5" required />
        <button type="submit">Submit Complaint</button>
      </form>
    </div>

    <div class="govt-card">
      <h3>My Recent Complaints</h3>
      <?php if (count($myComplaints) === 0): ?>
        <p>No complaints submitted yet.</p>
      <?php else: ?>
        <div class="govt-table-container">
          <table class="govt-table">
            <thead><tr><th>Subject</th><th>Project</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($myComplaints as $item): ?>
                <tr>
                  <td><?php echo htmlspecialchars($item['subject']); ?></td>
                  <td><?php echo htmlspecialchars($item['project_name'] ?: 'General'); ?></td>
                  <td><?php echo htmlspecialchars(ucfirst($item['status'])); ?></td>
                  <td><?php echo htmlspecialchars($item['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
