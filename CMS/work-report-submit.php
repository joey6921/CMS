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
$user_department = isset($_SESSION['department']) ? $_SESSION['department'] : '';

$error = '';
$success = '';
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$maxFileSize = 5 * 1024 * 1024;

$projects = [];
if ($user_department !== '') {
    $stmt = $conn->prepare("SELECT id, project_name FROM projects WHERE department = ? ORDER BY id DESC");
    $stmt->bind_param("s", $user_department);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
    $stmt->close();
}

$myReports = [];
$stmt = $conn->prepare("SELECT wr.id, wr.title, wr.status, wr.created_at, p.project_name
                        FROM work_reports wr
                        JOIN projects p ON wr.project_id = p.id
                        WHERE wr.employee_id = ?
                        ORDER BY wr.created_at DESC
                        LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $myReports[] = $row;
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $work_summary = isset($_POST['work_summary']) ? trim($_POST['work_summary']) : '';
    $materials_used = isset($_POST['materials_used']) ? trim($_POST['materials_used']) : '';
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';

    if ($project_id <= 0 || $title === '' || $work_summary === '' || $materials_used === '' || $start_date === '' || $end_date === '') {
        $error = 'Please fill all required fields.';
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $error = 'End date cannot be before start date.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND department = ?");
        $stmt->bind_param("is", $project_id, $user_department);
        $stmt->execute();
        $check = $stmt->get_result();
        $isValidProject = $check->num_rows > 0;
        $stmt->close();

        if (!$isValidProject) {
            $error = 'You can submit reports only for your department projects.';
        } else {
            $stmt = $conn->prepare("INSERT INTO work_reports (project_id, employee_id, title, work_summary, materials_used, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted')");
            $stmt->bind_param("iisssss", $project_id, $user_id, $title, $work_summary, $materials_used, $start_date, $end_date);
            $stmt->execute();
            $report_id = $stmt->insert_id;
            $stmt->close();

            $conn->query("UPDATE projects SET completion_status = 'under_review' WHERE id = " . (int)$project_id);

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

            $success = 'Report submitted successfully. Waiting for manager verification.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Submit Work Report</title>
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
      <a href="work-report-submit.php" class="active">Submit Work Report</a>
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
      <h2>Employee Work Completion Report</h2>
      <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <label>Project</label>
        <select name="project_id" required>
          <option value="">Select Project</option>
          <?php foreach ($projects as $project): ?>
            <option value="<?php echo (int)$project['id']; ?>"><?php echo htmlspecialchars($project['project_name']); ?></option>
          <?php endforeach; ?>
        </select>

        <label>Report Title</label>
        <input type="text" name="title" required />

        <label>Work Summary</label>
        <textarea name="work_summary" rows="4" required></textarea>

        <label>Materials Used</label>
        <textarea name="materials_used" rows="4" required placeholder="Cement 40 bags, Bitumen 2 tons, etc."></textarea>

        <label>Start Date</label>
        <input type="date" name="start_date" required />

        <label>End Date</label>
        <input type="date" name="end_date" required />

        <label>Upload Repair Photos (jpg/png/webp, max 5MB each)</label>
        <input type="file" name="photos[]" multiple accept=".jpg,.jpeg,.png,.webp" />

        <button type="submit">Submit Report</button>
      </form>
    </div>

    <div class="govt-card">
      <h3>My Recent Reports</h3>
      <?php if (count($myReports) === 0): ?>
        <p>No reports submitted yet.</p>
      <?php else: ?>
        <div class="govt-table-container">
          <table class="govt-table">
            <thead>
              <tr>
                <th>Project</th>
                <th>Title</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($myReports as $report): ?>
                <tr>
                  <td><?php echo htmlspecialchars($report['project_name']); ?></td>
                  <td><?php echo htmlspecialchars($report['title']); ?></td>
                  <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $report['status']))); ?></td>
                  <td><?php echo htmlspecialchars($report['created_at']); ?></td>
                  <td>
                    <a href="work-report-view.php?id=<?php echo (int)$report['id']; ?>">View</a>
                    <?php if (in_array($report['status'], ['submitted', 'rework_required'])): ?>
                      | <a href="work-report-edit.php?id=<?php echo (int)$report['id']; ?>">Edit</a>
                    <?php endif; ?>
                  </td>
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
