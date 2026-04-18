<?php
session_start();

// Include database configuration
include 'config.php';
include 'auth.php';
requireLogin();

// Rebuild permissions if missing (for older sessions)
if (!isset($_SESSION['permissions'])) {
    setSessionPermissions($conn, $_SESSION['role']);
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['full_name'];
$user_department = isset($_SESSION['department']) ? $_SESSION['department'] : '';
$error_message = '';
$success_message = '';

$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$maxFileSize = 5 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'submit_work_report' && hasPermission('submit_work_report')) {
        $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $work_summary = isset($_POST['work_summary']) ? trim($_POST['work_summary']) : '';
        $materials_used = isset($_POST['materials_used']) ? trim($_POST['materials_used']) : '';
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
        $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';

        if ($project_id <= 0 || $title === '' || $work_summary === '' || $materials_used === '' || $start_date === '' || $end_date === '') {
            $error_message = 'Please fill all report fields.';
        } elseif (strtotime($end_date) < strtotime($start_date)) {
            $error_message = 'End date cannot be before start date.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND department = ?");
            $stmt->bind_param("is", $project_id, $user_department);
            $stmt->execute();
            $valid = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$valid) {
                $error_message = 'You can submit reports only for your department projects.';
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
                            $f = $conn->prepare("INSERT INTO work_report_files (work_report_id, file_path, file_type) VALUES (?, ?, ?)");
                            $f->bind_param("iss", $report_id, $relativePath, $type);
                            $f->execute();
                            $f->close();
                        }
                    }
                }
                $success_message = 'Work report submitted successfully.';
            }
        }
    } elseif ($action === 'manager_review' && hasPermission('verify_work_report')) {
        $report_id = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
        $decision = isset($_POST['decision']) ? $_POST['decision'] : '';
        $comment = isset($_POST['manager_comment']) ? trim($_POST['manager_comment']) : '';
        if ($report_id > 0 && in_array($decision, ['verify', 'rework'])) {
            $status = $decision === 'verify' ? 'manager_verified' : 'rework_required';
            $stmt = $conn->prepare("UPDATE work_reports wr
                                    JOIN projects p ON wr.project_id = p.id
                                    SET wr.status = ?, wr.manager_id = ?, wr.manager_comment = ?, wr.manager_reviewed_at = NOW()
                                    WHERE wr.id = ? AND wr.status IN ('submitted','rework_required') AND p.department = ?");
            $stmt->bind_param("sisis", $status, $user_id, $comment, $report_id, $user_department);
            $stmt->execute();
            $stmt->close();
            $success_message = 'Manager action saved.';
        } else {
            $error_message = 'Invalid manager review request.';
        }
    } elseif ($action === 'tdo_review' && hasPermission('approve_work_completion')) {
        $report_id = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
        $decision = isset($_POST['decision']) ? $_POST['decision'] : '';
        $comment = isset($_POST['tdo_comment']) ? trim($_POST['tdo_comment']) : '';
        if ($report_id > 0 && in_array($decision, ['approve', 'reject'])) {
            $status = $decision === 'approve' ? 'tdo_approved' : 'rejected';
            $stmt = $conn->prepare("UPDATE work_reports SET status = ?, tdo_id = ?, tdo_comment = ?, tdo_reviewed_at = NOW() WHERE id = ? AND status = 'manager_verified'");
            $stmt->bind_param("sisi", $status, $user_id, $comment, $report_id);
            $stmt->execute();
            $stmt->close();

            $ps = $conn->prepare("SELECT project_id FROM work_reports WHERE id = ?");
            $ps->bind_param("i", $report_id);
            $ps->execute();
            $row = $ps->get_result()->fetch_assoc();
            $ps->close();
            if ($row) {
                $projectStatus = $decision === 'approve' ? 'completed' : 'pending';
                $up = $conn->prepare("UPDATE projects SET completion_status = ? WHERE id = ?");
                $up->bind_param("si", $projectStatus, $row['project_id']);
                $up->execute();
                $up->close();
            }
            $success_message = 'Final decision saved.';
        } else {
            $error_message = 'Invalid final approval request.';
        }
    } elseif ($action === 'submit_complaint' && hasPermission('submit_feedback')) {
        $project_id = isset($_POST['complaint_project_id']) && $_POST['complaint_project_id'] !== '' ? (int)$_POST['complaint_project_id'] : null;
        $subject = isset($_POST['complaint_subject']) ? trim($_POST['complaint_subject']) : '';
        $message = isset($_POST['complaint_message']) ? trim($_POST['complaint_message']) : '';
        $rating = isset($_POST['complaint_rating']) ? (int)$_POST['complaint_rating'] : 0;
        if ($subject === '' || $message === '' || $rating < 1 || $rating > 5) {
            $error_message = 'Please fill complaint form correctly.';
        } else {
            $stmt = $conn->prepare("INSERT INTO feedback (citizen_id, project_id, subject, message, rating, status) VALUES (?, ?, ?, ?, ?, 'new')");
            $stmt->bind_param("iissi", $user_id, $project_id, $subject, $message, $rating);
            $stmt->execute();
            $stmt->close();
            $success_message = 'Complaint submitted successfully.';
        }
    } else {
        $error_message = 'Unauthorized action.';
    }
}

$pendingSubmitCount = 0;
$pendingManagerCount = 0;
$pendingTdoCount = 0;

$employeeProjects = [];
$myReports = [];
$managerReports = [];
$tdoReports = [];
$complaintProjects = [];
$myComplaints = [];

if (hasPermission('submit_work_report') && $user_department !== '') {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM projects WHERE department = ? AND completion_status IN ('pending', 'under_review')");
    $stmt->bind_param("s", $user_department);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $pendingSubmitCount = (int)$res->fetch_assoc()['c'];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT id, project_name FROM projects WHERE department = ? ORDER BY id DESC");
    $stmt->bind_param("s", $user_department);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $employeeProjects[] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT wr.id, wr.title, wr.status, wr.created_at, p.project_name
                            FROM work_reports wr JOIN projects p ON wr.project_id = p.id
                            WHERE wr.employee_id = ? ORDER BY wr.created_at DESC LIMIT 8");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $myReports[] = $row;
    }
    $stmt->close();
}

if (hasPermission('verify_work_report') && $user_department !== '') {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c
                            FROM work_reports wr
                            JOIN projects p ON wr.project_id = p.id
                            WHERE wr.status IN ('submitted', 'rework_required') AND p.department = ?");
    $stmt->bind_param("s", $user_department);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $pendingManagerCount = (int)$res->fetch_assoc()['c'];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT wr.id, wr.title, wr.status, wr.start_date, wr.end_date, wr.created_at, p.project_name, u.full_name AS employee_name
                            FROM work_reports wr
                            JOIN projects p ON wr.project_id = p.id
                            JOIN users u ON wr.employee_id = u.id
                            WHERE wr.status IN ('submitted', 'rework_required') AND p.department = ?
                            ORDER BY wr.created_at DESC");
    $stmt->bind_param("s", $user_department);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $managerReports[] = $row;
    }
    $stmt->close();
}

if (hasPermission('approve_work_completion')) {
    $res = $conn->query("SELECT COUNT(*) AS c FROM work_reports WHERE status = 'manager_verified'");
    if ($res && $res->num_rows > 0) {
        $pendingTdoCount = (int)$res->fetch_assoc()['c'];
    }

    $sql = "SELECT wr.id, wr.title, wr.manager_comment, wr.start_date, wr.end_date, wr.created_at, p.project_name, u.full_name AS employee_name
            FROM work_reports wr
            JOIN projects p ON wr.project_id = p.id
            JOIN users u ON wr.employee_id = u.id
            WHERE wr.status = 'manager_verified'
            ORDER BY wr.created_at DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $tdoReports[] = $row;
        }
    }
}

if (hasPermission('submit_feedback')) {
    $res = $conn->query("SELECT id, project_name FROM projects ORDER BY project_name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $complaintProjects[] = $row;
        }
    }

    $stmt = $conn->prepare("SELECT f.subject, f.status, f.created_at, p.project_name
                            FROM feedback f
                            LEFT JOIN projects p ON f.project_id = p.id
                            WHERE f.citizen_id = ? ORDER BY f.created_at DESC LIMIT 8");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $myComplaints[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Dashboard - City Management System</title>
  <link rel="stylesheet" href="assets/styles.css" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
  <!-- Official Government Header -->
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

  <!-- Official Navigation -->
  <nav class="govt-nav">
    <div class="govt-nav-content">
      <a href="home.php" class="active">Dashboard</a>
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
      <?php if (hasPermission('submit_work_report')): ?><a href="#employee-workflow">Submit Work</a><?php endif; ?>
      <?php if (hasPermission('verify_work_report')): ?><a href="#manager-workflow">Manager Review</a><?php endif; ?>
      <?php if (hasPermission('approve_work_completion')): ?><a href="#tdo-workflow">TDO Approval</a><?php endif; ?>
      
      <div class="nav-user">
        <span class="nav-user-text">
          Welcome, <?php echo htmlspecialchars($user_name); ?> 
          <span class="nav-role-badge">
            <?php echo ucfirst($user_role); ?>
          </span>
        </span>
        <a href="logout.php" onclick="return logout()">Logout</a>
      </div>
    </div>
  </nav>

  <div class="govt-container">
    <?php if ($error_message): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <!-- Welcome Section -->
    <div class="govt-card dashboard-hero">
      <span class="role-pill"><?php echo ucfirst($user_role); ?> Dashboard</span>
      <h1 class="govt-title-main" style="text-align:left; margin-bottom: 6px;">
        <?php 
        switch($user_role) {
          case 'admin':
            echo 'Administrative Dashboard';
            break;
          case 'manager':
            echo 'Management Dashboard';
            break;
          case 'employee':
            echo 'Employee Dashboard';
            break;
          case 'citizen':
            echo 'Citizen Portal';
            break;
          default:
            echo 'Infrastructure Management Dashboard';
        }
        ?>
      </h1>
      <p class="govt-subtitle-main" style="text-align:left; margin-bottom: 0;">
        Welcome <?php echo htmlspecialchars($user_name); ?>! 
        <?php 
        switch($user_role) {
          case 'admin':
            echo 'You have full administrative access to the City Infrastructure Management Portal.';
            break;
          case 'manager':
            echo 'Manage and oversee infrastructure projects and departmental communications.';
            break;
          case 'employee':
            echo 'Access project information and manage assigned infrastructure tasks.';
            break;
          case 'citizen':
            echo 'View public infrastructure projects and submit feedback to the city.';
            break;
          default:
            echo 'Welcome to the Official City Infrastructure Management Portal.';
        }
        ?>
      </p>
    </div>

    <!-- Quick Actions -->
    <div class="govt-card">
      <h2>Quick Actions</h2>
      <div class="quick-actions">
        <?php if (hasPermission('submit_feedback')): ?>
          <button class="govt-btn" onclick="location.href='#citizen-workflow'">Submit Complaint</button>
          <button class="govt-btn" onclick="location.href='road-details.php'">Track Project Status</button>
        <?php else: ?>
          <button class="govt-btn" onclick="location.href='road-details.php'">View Projects</button>
        <?php endif; ?>
        <?php if (hasAnyPermission(['view_all_projects', 'view_assigned_projects'])): ?>
          <button class="govt-btn" onclick="location.href='department-projects.php'">Department Projects</button>
        <?php endif; ?>
        <?php if (hasPermission('send_communications')): ?>
          <button class="govt-btn" onclick="location.href='department-communication.php'">Send Communication</button>
        <?php endif; ?>
        <?php if (hasPermission('submit_work_report')): ?>
          <button class="govt-btn" onclick="location.href='#employee-workflow'">Submit Work Report</button>
        <?php endif; ?>
        <?php if (hasPermission('verify_work_report')): ?>
          <button class="govt-btn" onclick="location.href='#manager-workflow'">Verify Reports</button>
        <?php endif; ?>
        <?php if (hasPermission('approve_work_completion')): ?>
          <button class="govt-btn" onclick="location.href='#tdo-workflow'">Final Approval</button>
        <?php endif; ?>
      </div>
    </div>

    <?php if (hasAnyPermission(['submit_work_report', 'verify_work_report', 'approve_work_completion'])): ?>
    <div class="govt-card">
      <h2>Work Completion Workflow</h2>
      <div class="stats-grid">
        <?php if (hasPermission('submit_work_report')): ?>
          <div class="stat-card">
            <div class="stat-number" style="color: var(--primary-600);"><?php echo $pendingSubmitCount; ?></div>
            <div class="stat-label">Projects Ready For Report</div>
          </div>
        <?php endif; ?>
        <?php if (hasPermission('verify_work_report')): ?>
          <div class="stat-card">
            <div class="stat-number" style="color: var(--warning);"><?php echo $pendingManagerCount; ?></div>
            <div class="stat-label">Reports Pending Manager Verification</div>
          </div>
        <?php endif; ?>
        <?php if (hasPermission('approve_work_completion')): ?>
          <div class="stat-card">
            <div class="stat-number" style="color: var(--secondary-600);"><?php echo $pendingTdoCount; ?></div>
            <div class="stat-label">Reports Pending TDO Approval</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (hasPermission('submit_work_report')): ?>
    <div class="govt-card" id="employee-workflow">
      <h2>Employee Work Submission</h2>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="submit_work_report" />
        <label>Project</label>
        <select name="project_id" required>
          <option value="">Select Project</option>
          <?php foreach ($employeeProjects as $project): ?>
            <option value="<?php echo (int)$project['id']; ?>"><?php echo htmlspecialchars($project['project_name']); ?></option>
          <?php endforeach; ?>
        </select>
        <label>Report Title</label>
        <input type="text" name="title" required />
        <label>Work Summary</label>
        <textarea name="work_summary" rows="3" required></textarea>
        <label>Materials Used</label>
        <textarea name="materials_used" rows="3" required></textarea>
        <label>Start Date</label>
        <input type="date" name="start_date" required />
        <label>End Date</label>
        <input type="date" name="end_date" required />
        <label>Upload Photos (jpg/png/webp, max 5MB each)</label>
        <input type="file" name="photos[]" multiple accept=".jpg,.jpeg,.png,.webp" />
        <button type="submit" class="govt-btn">Submit Work Report</button>
      </form>
      <?php if (count($myReports) > 0): ?>
      <div class="govt-table-container" style="margin-top: 18px;">
        <table class="govt-table">
          <thead><tr><th>Project</th><th>Title</th><th>Status</th><th>Submitted</th><th>Evidence</th></tr></thead>
          <tbody>
            <?php foreach ($myReports as $report): ?>
              <tr>
                <td><?php echo htmlspecialchars($report['project_name']); ?></td>
                <td><?php echo htmlspecialchars($report['title']); ?></td>
                <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $report['status']))); ?></td>
                <td><?php echo htmlspecialchars($report['created_at']); ?></td>
                <td><a href="work-report-view.php?id=<?php echo (int)$report['id']; ?>">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (hasPermission('verify_work_report')): ?>
    <div class="govt-card" id="manager-workflow">
      <h2>Manager Review Queue</h2>
      <?php if (count($managerReports) === 0): ?>
        <p>No reports pending for review.</p>
      <?php else: ?>
        <?php foreach ($managerReports as $report): ?>
          <div style="border:1px solid var(--gray-200); border-radius:12px; padding:14px; margin-bottom:12px;">
            <p><strong><?php echo htmlspecialchars($report['title']); ?></strong> - <?php echo htmlspecialchars($report['project_name']); ?></p>
            <p>Employee: <?php echo htmlspecialchars($report['employee_name']); ?> | Status: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $report['status']))); ?></p>
            <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap;">
              <input type="hidden" name="action" value="manager_review" />
              <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>" />
              <input type="text" name="manager_comment" placeholder="Manager comment" style="flex:1; min-width:220px;" />
              <button class="govt-btn" type="submit" name="decision" value="verify">Verify</button>
              <button class="govt-btn" type="submit" name="decision" value="rework">Send Rework</button>
              <a href="work-report-view.php?id=<?php echo (int)$report['id']; ?>" style="align-self:center;">View Details</a>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (hasPermission('approve_work_completion')): ?>
    <div class="govt-card" id="tdo-workflow">
      <h2>TDO Final Approval Queue</h2>
      <?php if (count($tdoReports) === 0): ?>
        <p>No reports waiting for final approval.</p>
      <?php else: ?>
        <?php foreach ($tdoReports as $report): ?>
          <div style="border:1px solid var(--gray-200); border-radius:12px; padding:14px; margin-bottom:12px;">
            <p><strong><?php echo htmlspecialchars($report['title']); ?></strong> - <?php echo htmlspecialchars($report['project_name']); ?></p>
            <p>Employee: <?php echo htmlspecialchars($report['employee_name']); ?></p>
            <p>Manager Comment: <?php echo htmlspecialchars($report['manager_comment'] ?: 'No comment'); ?></p>
            <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap;">
              <input type="hidden" name="action" value="tdo_review" />
              <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>" />
              <input type="text" name="tdo_comment" placeholder="Final comment" style="flex:1; min-width:220px;" />
              <button class="govt-btn" type="submit" name="decision" value="approve">Approve</button>
              <button class="govt-btn" type="submit" name="decision" value="reject">Reject</button>
              <a href="work-report-view.php?id=<?php echo (int)$report['id']; ?>" style="align-self:center;">View Details</a>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (hasPermission('submit_feedback')): ?>
    <div class="govt-card" id="citizen-workflow">
      <h2>Citizen Complaint Portal</h2>
      <form method="POST">
        <input type="hidden" name="action" value="submit_complaint" />
        <label>Project (optional)</label>
        <select name="complaint_project_id">
          <option value="">General Complaint</option>
          <?php foreach ($complaintProjects as $project): ?>
            <option value="<?php echo (int)$project['id']; ?>"><?php echo htmlspecialchars($project['project_name']); ?></option>
          <?php endforeach; ?>
        </select>
        <label>Subject</label>
        <input type="text" name="complaint_subject" required />
        <label>Complaint Details</label>
        <textarea name="complaint_message" rows="3" required></textarea>
        <label>Rating (1 to 5)</label>
        <input type="number" name="complaint_rating" min="1" max="5" required />
        <button type="submit" class="govt-btn">Submit Complaint</button>
      </form>
      <?php if (count($myComplaints) > 0): ?>
      <div class="govt-table-container" style="margin-top: 18px;">
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
    <?php endif; ?>

  </div>

  <footer class="govt-footer">
    <p>&copy; 2024 Municipal Corporation. All rights reserved. | Official Government Portal</p>
  </footer>

  <script>
    <?php if (isset($_GET['unauthorized']) && $_GET['unauthorized'] === '1'): ?>
      alert('Unauthorized access: you do not have permission for that page/action.');
    <?php endif; ?>

    function logout() {
      if (confirm('Are you sure you want to logout from the official portal?')) {
        alert('Logging out... Session cleared.');
        return true;
      }
      return false;
    }

  </script>
</body>
</html>