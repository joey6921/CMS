<?php
session_start();
include 'config.php';
include 'auth.php';
requireLogin();

if (!isset($_SESSION['permissions'])) {
    setSessionPermissions($conn, $_SESSION['role']);
}

$user_name = $_SESSION['full_name'];
$user_role = $_SESSION['role'];
$workflowProjects = [];
$projectDetails = [];

$result = $conn->query("SELECT project_name, department, completion_status FROM projects ORDER BY id DESC LIMIT 8");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $workflowProjects[] = $row;
    }
}

$projectDetailsResult = $conn->query("SELECT project_name, department, status, start_date, expected_completion, budget
                                      FROM projects
                                      ORDER BY start_date DESC
                                      LIMIT 10");
if ($projectDetailsResult) {
    while ($row = $projectDetailsResult->fetch_assoc()) {
        $projectDetails[] = $row;
    }
}

$recentReports = [];
$reportSql = "SELECT wr.id, wr.title, wr.status, p.project_name, wr.created_at
              FROM work_reports wr
              JOIN projects p ON wr.project_id = p.id
              ORDER BY wr.created_at DESC
              LIMIT 6";
$reportResult = $conn->query($reportSql);
if ($reportResult) {
    while ($row = $reportResult->fetch_assoc()) {
        $recentReports[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Road Details - City Management</title>
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
      <a href="road-details.php" class="active">Infrastructure Projects</a>
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
      <h1>Infrastructure Project Details</h1>
      <p>Live project data from the system.</p>
      <?php if (count($projectDetails) > 0): ?>
        <div class="govt-table-container">
          <table class="govt-table">
            <thead>
              <tr>
                <th>Project</th>
                <th>Department</th>
                <th>Status</th>
                <th>Start Date</th>
                <th>Expected Completion</th>
                <?php if (!hasPermission('view_public_projects')): ?>
                  <th>Budget</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($projectDetails as $project): ?>
                <tr>
                  <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                  <td><?php echo htmlspecialchars($project['department']); ?></td>
                  <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $project['status']))); ?></td>
                  <td><?php echo htmlspecialchars($project['start_date']); ?></td>
                  <td><?php echo htmlspecialchars($project['expected_completion']); ?></td>
                  <?php if (!hasPermission('view_public_projects')): ?>
                    <td><?php echo htmlspecialchars($project['budget']); ?></td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p>No projects found.</p>
      <?php endif; ?>
    </div>

    <?php if (count($workflowProjects) > 0): ?>
      <div class="govt-card">
        <h2>Work Completion Status</h2>
        <div class="govt-table-container">
          <table class="govt-table">
            <thead>
              <tr>
                <th>Project</th>
                <th>Department</th>
                <th>Completion Workflow</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($workflowProjects as $project): ?>
                <tr>
                  <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                  <td><?php echo htmlspecialchars($project['department']); ?></td>
                  <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $project['completion_status']))); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!hasPermission('view_public_projects') && count($recentReports) > 0): ?>
      <div class="govt-card">
        <h2>Latest Work Report Evidence</h2>
        <div class="govt-table-container">
          <table class="govt-table">
            <thead>
              <tr>
                <th>Project</th>
                <th>Report</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Evidence</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentReports as $report): ?>
                <tr>
                  <td><?php echo htmlspecialchars($report['project_name']); ?></td>
                  <td><?php echo htmlspecialchars($report['title']); ?></td>
                  <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $report['status']))); ?></td>
                  <td><?php echo htmlspecialchars($report['created_at']); ?></td>
                  <td><a href="work-report-view.php?id=<?php echo (int)$report['id']; ?>">View Photos</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
