<?php
session_start();

// Include database configuration
include 'config.php';
include 'auth.php';

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_POST) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Validate input
    if (empty($email) || empty($password) || empty($role)) {
        $error_message = "All fields are required.";
    } else {
        // Check if user exists with the given credentials and role
        $stmt = $conn->prepare("SELECT id, email, password, role, full_name, department FROM users WHERE email = ? AND role = ?");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verify password using password_verify for hashed passwords
            if (password_verify($password, $user['password']) || $password === $user['password']) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['department'] = $user['department'];
                setSessionPermissions($conn, $user['role']);
                
                // Redirect based on role
                switch($role) {
                    case 'admin':
                        header('Location: home.php');
                        exit();
                    case 'manager':
                        header('Location: home.php');
                        exit();
                    case 'employee':
                        header('Location: home.php');
                        exit();
                    case 'citizen':
                        header('Location: home.php');
                        exit();
                    default:
                        header('Location: home.php');
                        exit();
                }
            } else {
                $error_message = "Invalid password.";
            }
        } else {
            $error_message = "Invalid email, password, or role combination.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Official Login - Department of Transportation Portal</title>
  <link rel="stylesheet" href="assets/styles.css" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

  <div class="container">
    <h2>Secure Login Portal</h2>
    
    <?php if ($error_message): ?>
      <div class="alert alert-error">
        <?php echo htmlspecialchars($error_message); ?>
      </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
      <div class="alert alert-success">
        <?php echo htmlspecialchars($success_message); ?>
      </div>
    <?php endif; ?>
    
    <form method="POST" action="">
      <input type="email" name="email" placeholder="Official Email or Citizen ID" required />
      <input type="password" name="password" placeholder="Password" required />
      
      <select name="role" required>
        <option value="">Select Your Role</option>
        <option value="admin">Administrator</option>
        <option value="manager">Department Manager</option>
        <option value="employee">Government Employee</option>
        <option value="citizen">Citizen</option>
      </select>
      
      <label><input type="checkbox" /> Keep me signed in (For private computers only)</label>
      <button type="submit">Sign In to Your Account</button>
      
      <a href="#" style="text-align: center; margin-top: 15px;">
        Forgot Password?
      </a>
    </form>
    
    <div class="role-descriptions">
      <h4>Role Descriptions:</h4>
      <ul>
        <li><strong>Administrator:</strong> Full system access and user management</li>
        <li><strong>Department Manager:</strong> Project oversight and approval authority</li>
        <li><strong>Government Employee:</strong> Project management and reporting</li>
        <li><strong>Citizen:</strong> Public access to project information and feedback</li>
      </ul>
    </div>
  </div>
</body>
</html>