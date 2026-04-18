<?php
session_start();
include 'config.php';
include 'auth.php';
requireLogin();

if (!isset($_SESSION['permissions'])) {
    setSessionPermissions($conn, $_SESSION['role']);
}
requirePermission('submit_work_report');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];
$user_role = $_SESSION['role'];
$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$maxFileSize = 5 * 1024 * 1024;

$report = null;
if ($report_id > 0) {
    $stmt = $conn->prepare("SELECT wr.*, p.project_name
                            FROM work_reports wr
                            JOIN projects p ON wr.project_id = p.id
                            WHERE wr.id = ? AND wr.employee_id = ? AND wr.status IN ('submitted', 'rework_required')");
    $stmt->bind_param("ii", $report_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $report = $res->fetch_assoc();
    }
    $stmt->close();
}

if (!$report) {
    $error = 'Report not found or cannot be edited at this stage.';
}

if ($report && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $work_summary = isset($_POST['work_summary']) ? trim($_POST['work_summary']) : '';
    $materials_used = isset($_POST['materials_used']) ? trim($_POST['materials_used']) : '';
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';

    if ($title === '' || $work_summary === '' || $materials_used === '' || $start_date === '' || $end_date === '') {
        $error = 'Please fill all required fields.';
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $error = 'End date cannot be before start date.';
    } else {
        $stmt = $conn->prepare("UPDATE work_reports
                                SET title = ?, work_summary = ?, materials_used = ?, start_date = ?, end_date = ?,
                                    status = 'submitted', manager_comment = NULL, manager_reviewed_at = NULL
                                WHERE id = ? AND employee_id = ?");
        $stmt->bind_param("sssssii", $title, $work_summary, $materials_used, $start_date, $end_date, $report_id, $user_id);
        $stmt->execute();
        $stmt->close();

        if (isset($_FILES['photos']) && isset($_FILES['photos']['name']) && is_array($_FILES['photos']['name'])) {
            $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'work-reports' . DIRECTORY_SEPARATOR . $report_id;
            if (!is_dir($baseDir)) {
                mkdir($baseDir, 0775, true);
            }

            $count = count($_FILES['photos']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $tmp = $_FILES['photos']['tmp_name'][$i];
                $type = $_FILES['photos']['type'][$i];
                $size = $_FILES['photos']['size'][$i];
                if (!in_array($type, $allowedTypes) || $size > $maxFileSize) {
                    continue;
                }

                $ext = pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION);
                $safeName = uniqid('wr_', true) . '.' . strtolower($ext);
                $absPath = $baseDir . DIRECTORY_SEPARATOR . $safeName;

                if (move_uploaded_file($tmp, $absPath)) {
                    $relativePath = 'uploads/work-reports/' . $report_id . '/' . $safeName;
                    $fileStmt = $conn->prepare("INSERT INTO work_report_files (work_report_id, file_path, file_type) VALUES (?, ?, ?)");
                    $fileStmt->bind_param("iss", $report_id, $relativePath, $type);
                    $fileStmt->execute();
                    $fileStmt->close();
                }
            }
        }

        $success = 'Report updated and resubmitted for manager verification.';
        $stmt = $conn->prepare("SELECT wr.*, p.project_name
                                FROM work_reports wr
                                JOIN projects p ON wr.project_id = p.id
                                WHERE wr.id = ? AND wr.employee_id = ?");
        $stmt->bind_param("ii", $report_id, $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $report = $res->fetch_assoc();
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Edit Work Report</title>
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
      <a href="work-report-edit.php?id=<?php echo (int)$report_id; ?>" class="active">Edit Report</a>
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
      <h2>Edit Work Report</h2>
      <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

      <?php if ($report): ?>
        <p><strong>Project:</strong> <?php echo htmlspecialchars($report['project_name']); ?></p>
        <form method="POST" enctype="multipart/form-data">
          <label>Title</label>
          <input type="text" name="title" value="<?php echo htmlspecialchars($report['title']); ?>" required />

          <label>Work Summary</label>
          <textarea name="work_summary" rows="4" required><?php echo htmlspecialchars($report['work_summary']); ?></textarea>

          <label>Materials Used</label>
          <textarea name="materials_used" rows="4" required><?php echo htmlspecialchars($report['materials_used']); ?></textarea>

          <label>Start Date</label>
          <input type="date" name="start_date" value="<?php echo htmlspecialchars($report['start_date']); ?>" required />

          <label>End Date</label>
          <input type="date" name="end_date" value="<?php echo htmlspecialchars($report['end_date']); ?>" required />

          <label>Add More Photos (optional)</label>
          <input type="file" name="photos[]" multiple accept=".jpg,.jpeg,.png,.webp" />

          <button type="submit">Update & Resubmit</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
