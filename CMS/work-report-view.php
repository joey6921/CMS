<?php
session_start();
include 'config.php';
include 'auth.php';
requireLogin();

if (!isset($_SESSION['permissions'])) {
    setSessionPermissions($conn, $_SESSION['role']);
}

requireAnyPermission(['submit_work_report', 'verify_work_report', 'approve_work_completion', 'view_all_work_reports']);

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['full_name'];
$user_department = isset($_SESSION['department']) ? $_SESSION['department'] : '';
$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$report = null;
$files = [];

if ($report_id <= 0) {
    $error = 'Invalid report id.';
} else {
    $stmt = $conn->prepare("SELECT wr.*, p.project_name, p.department AS project_department, u.full_name AS employee_name
                            FROM work_reports wr
                            JOIN projects p ON wr.project_id = p.id
                            JOIN users u ON wr.employee_id = u.id
                            WHERE wr.id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $report = $res->fetch_assoc();
    } else {
        $error = 'Report not found.';
    }
    $stmt->close();

    if ($report) {
        $allowed = false;
        if ($user_role === 'admin') {
            $allowed = true;
        } elseif (hasPermission('approve_work_completion')) {
            $allowed = true;
        } elseif (hasPermission('verify_work_report') && $report['project_department'] === $user_department) {
            $allowed = true;
        } elseif (hasPermission('submit_work_report') && (int)$report['employee_id'] === (int)$user_id) {
            $allowed = true;
        }

        if (!$allowed) {
            $error = 'You are not allowed to view this report.';
            $report = null;
        } else {
            $fileStmt = $conn->prepare("SELECT file_path, file_type, caption, uploaded_at FROM work_report_files WHERE work_report_id = ? ORDER BY id DESC");
            $fileStmt->bind_param("i", $report_id);
            $fileStmt->execute();
            $fileRes = $fileStmt->get_result();
            while ($row = $fileRes->fetch_assoc()) {
                $files[] = $row;
            }
            $fileStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Work Report Details</title>
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
      <?php if (hasPermission('approve_work_completion')): ?>
        <a href="home.php#tdo-workflow">TDO Approval</a>
      <?php endif; ?>
      <a href="work-report-view.php?id=<?php echo (int)$report_id; ?>" class="active">Report Details</a>
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
      <h2>Work Report Details</h2>
      <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if ($report): ?>
        <p><strong>Project:</strong> <?php echo htmlspecialchars($report['project_name']); ?></p>
        <p><strong>Department:</strong> <?php echo htmlspecialchars($report['project_department']); ?></p>
        <p><strong>Employee:</strong> <?php echo htmlspecialchars($report['employee_name']); ?></p>
        <p><strong>Status:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $report['status']))); ?></p>
        <p><strong>Title:</strong> <?php echo htmlspecialchars($report['title']); ?></p>
        <p><strong>Summary:</strong> <?php echo nl2br(htmlspecialchars($report['work_summary'])); ?></p>
        <p><strong>Materials Used:</strong> <?php echo nl2br(htmlspecialchars($report['materials_used'])); ?></p>
        <p><strong>Date Range:</strong> <?php echo htmlspecialchars($report['start_date']); ?> to <?php echo htmlspecialchars($report['end_date']); ?></p>
      <?php endif; ?>
    </div>

    <?php if ($report): ?>
      <div class="govt-card">
        <h3>Uploaded Evidence Photos</h3>
        <?php if (count($files) === 0): ?>
          <p>No files uploaded for this report.</p>
        <?php else: ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;">
            <?php foreach ($files as $file): ?>
              <div style="border:1px solid #ddd;border-radius:8px;padding:8px;">
                <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" rel="noopener noreferrer">
                  <img src="<?php echo htmlspecialchars($file['file_path']); ?>" alt="Work Evidence" style="width:100%;height:150px;object-fit:cover;border-radius:6px;" />
                </a>
                <p style="font-size:12px;margin-top:8px;"><?php echo htmlspecialchars($file['file_type']); ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
