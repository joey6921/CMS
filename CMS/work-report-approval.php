<?php
session_start();
include 'config.php';
include 'auth.php';
requireLogin();

if (!isset($_SESSION['permissions'])) {
    setSessionPermissions($conn, $_SESSION['role']);
}
requirePermission('approve_work_completion');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];
$user_role = $_SESSION['role'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_id = isset($_POST['report_id']) ? (int) $_POST['report_id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    if ($report_id > 0 && in_array($action, ['approve', 'reject'])) {
        $status = ($action === 'approve') ? 'tdo_approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE work_reports
                                SET status = ?, tdo_id = ?, tdo_comment = ?, tdo_reviewed_at = NOW()
                                WHERE id = ? AND status = 'manager_verified'");
        $stmt->bind_param("sisi", $status, $user_id, $comment, $report_id);
        $stmt->execute();
        $stmt->close();

        $projectStmt = $conn->prepare("SELECT project_id FROM work_reports WHERE id = ?");
        $projectStmt->bind_param("i", $report_id);
        $projectStmt->execute();
        $projectResult = $projectStmt->get_result();
        if ($projectResult->num_rows > 0) {
            $project = $projectResult->fetch_assoc();
            $project_id = (int)$project['project_id'];
            $projectStatus = ($action === 'approve') ? 'completed' : 'pending';
            $upd = $conn->prepare("UPDATE projects SET completion_status = ? WHERE id = ?");
            $upd->bind_param("si", $projectStatus, $project_id);
            $upd->execute();
            $upd->close();
        }
        $projectStmt->close();
    } else {
        $error = 'Invalid approval request.';
    }
}

$reports = [];
$sql = "SELECT wr.id, wr.title, wr.work_summary, wr.materials_used, wr.start_date, wr.end_date,
               wr.manager_comment, wr.created_at, p.project_name, u.full_name AS employee_name
        FROM work_reports wr
        JOIN projects p ON wr.project_id = p.id
        JOIN users u ON wr.employee_id = u.id
        WHERE wr.status = 'manager_verified'
        ORDER BY wr.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>TDO Final Approval</title>
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
      <?php if (hasPermission('send_communications')): ?>
        <a href="department-communication.php">Inter-Department Communication</a>
      <?php endif; ?>
      <?php if (hasPermission('submit_work_report')): ?>
        <a href="home.php#employee-workflow">Submit Work</a>
      <?php endif; ?>
      <?php if (hasPermission('verify_work_report')): ?>
        <a href="home.php#manager-workflow">Manager Review</a>
      <?php endif; ?>
      <a href="work-report-approval.php" class="active">TDO Approval</a>
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
      <h2>Manager Verified Reports</h2>
      <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <?php if (count($reports) === 0): ?>
        <p>No reports waiting for final approval.</p>
      <?php endif; ?>
    </div>

    <?php foreach ($reports as $report): ?>
      <div class="govt-card">
        <h3><?php echo htmlspecialchars($report['title']); ?></h3>
        <p><strong>Project:</strong> <?php echo htmlspecialchars($report['project_name']); ?></p>
        <p><strong>Employee:</strong> <?php echo htmlspecialchars($report['employee_name']); ?></p>
        <p><strong>Manager Comment:</strong> <?php echo htmlspecialchars($report['manager_comment'] ?: 'No comment'); ?></p>
        <p><strong>Summary:</strong> <?php echo nl2br(htmlspecialchars($report['work_summary'])); ?></p>
        <p><strong>Materials:</strong> <?php echo nl2br(htmlspecialchars($report['materials_used'])); ?></p>
        <p><strong>Date Range:</strong> <?php echo htmlspecialchars($report['start_date']); ?> to <?php echo htmlspecialchars($report['end_date']); ?></p>
        <p><a href="work-report-view.php?id=<?php echo (int)$report['id']; ?>">View Full Report with Photos</a></p>

        <form method="POST" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
          <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>" />
          <div style="flex:1; min-width:260px;">
            <label>TDO Comment</label>
            <input type="text" name="comment" placeholder="Final approval notes" />
          </div>
          <button class="govt-btn" type="submit" name="action" value="approve">Approve Completion</button>
          <button class="govt-btn" type="submit" name="action" value="reject">Reject</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</body>
</html>
