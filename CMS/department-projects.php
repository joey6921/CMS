<?php
session_start();
include 'config.php';
include 'auth.php';
requireLogin();

if (!isset($_SESSION['permissions'])) {
    setSessionPermissions($conn, $_SESSION['role']);
}

requireAnyPermission(['view_all_projects', 'view_assigned_projects']);

$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$user_department = isset($_SESSION['department']) ? $_SESSION['department'] : '';

// Departments we want to show
$departments = [
    'GEB',
    'Gujarat Gas',
    'Municipality',
    'Other'
];

// If user has only assigned-project view, lock to their own department.
$onlyAssignedScope = hasPermission('view_assigned_projects') && !hasPermission('view_all_projects');
if ($onlyAssignedScope && in_array($user_department, $departments)) {
    $departments = [$user_department];
}

// Selected department from dropdown (default to user's department if matches, else first in list)
$selected_department = isset($_GET['department']) ? $_GET['department'] : '';
if ($selected_department === '' || !in_array($selected_department, $departments)) {
    if (in_array($user_department, $departments)) {
        $selected_department = $user_department;
    } else {
        $selected_department = $departments[0];
    }
}

// Map "Other" to a simple condition: projects that are not in the main three
$projects = [];
if ($selected_department === 'Other') {
    $placeholders = "'GEB','Gujarat Gas','Municipality'";
    $sql = "SELECT project_name, department, status, start_date, expected_completion, budget 
            FROM projects 
            WHERE department NOT IN ($placeholders)
            ORDER BY start_date DESC";
    $result = $conn->query($sql);
} else {
    $stmt = $conn->prepare("SELECT project_name, department, status, start_date, expected_completion, budget 
                            FROM projects 
                            WHERE department = ?
                            ORDER BY start_date DESC");
    $stmt->bind_param("s", $selected_department);
    $stmt->execute();
    $result = $stmt->get_result();
}

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
}

if (isset($stmt)) {
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Department Projects</title>
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
      <a href="department-projects.php" class="active">Department Projects</a>
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
      <h1 style="margin-bottom: 10px;">Department Projects</h1>
      <p style="margin-bottom: 20px;">
        Select a department to view projects for planning and coordination.
      </p>

      <?php if (count($departments) > 1): ?>
        <form method="GET" action="department-projects.php" style="margin-bottom: 20px; max-width: 300px;">
          <label for="department">Choose Department:</label>
          <select name="department" id="department" onchange="this.form.submit()">
            <?php foreach ($departments as $dept): ?>
              <option value="<?php echo htmlspecialchars($dept); ?>" <?php if ($dept === $selected_department) echo 'selected'; ?>>
                <?php echo htmlspecialchars($dept); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php else: ?>
        <p style="margin-bottom: 20px; color: #4b5563;">
          Your access is limited to your department: <strong><?php echo htmlspecialchars($selected_department); ?></strong>
        </p>
      <?php endif; ?>

      <div class="govt-table-container">
        <table class="govt-table">
          <thead>
            <tr>
              <th>Project Name</th>
              <th>Department</th>
              <th>Status</th>
              <th>Start Date</th>
              <th>Expected Completion</th>
              <th>Budget</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($projects) === 0): ?>
              <tr>
                <td colspan="6">No projects found for this department.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($projects as $project): ?>
                <tr>
                  <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                  <td><?php echo htmlspecialchars($project['department']); ?></td>
                  <td><?php echo htmlspecialchars($project['status']); ?></td>
                  <td><?php echo htmlspecialchars($project['start_date']); ?></td>
                  <td><?php echo htmlspecialchars($project['expected_completion']); ?></td>
                  <td><?php echo htmlspecialchars($project['budget']); ?></td>
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

