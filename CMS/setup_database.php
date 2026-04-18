<?php
/**
 * Database Setup Script for City Management System
 * This script automatically creates the database and tables
 * Run this file once to set up your database
 */

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'citydemo';

// Create connection without specifying database
$conn = new mysqli($host, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>City Management System - Database Setup</h2>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";

try {
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✓ Database '$database' created successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating database: " . $conn->error . "</p>";
    }

    // Select database
    $conn->select_db($database);

    // Read and execute SQL file
    $sqlFile = 'setup_database.sql';
    if (file_exists($sqlFile)) {
        $sqlContent = file_get_contents($sqlFile);
        
        // Split SQL into individual statements
        $statements = explode(';', $sqlContent);
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                if ($conn->query($statement) === TRUE) {
                    $successCount++;
                } else {
                    $errorCount++;
                    echo "<p style='color: red;'>✗ Error executing statement: " . $conn->error . "</p>";
                }
            }
        }
        
        echo "<p style='color: green;'>✓ Successfully executed $successCount SQL statements</p>";
        if ($errorCount > 0) {
            echo "<p style='color: orange;'>⚠ $errorCount statements had errors (may be expected for existing data)</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ SQL file 'setup_database.sql' not found</p>";
    }

    // Test database connection
    $testQuery = "SELECT COUNT(*) as user_count FROM users";
    $result = $conn->query($testQuery);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "<p style='color: green;'>✓ Database setup completed successfully!</p>";
        echo "<p style='color: blue;'>📊 Found {$row['user_count']} users in the database</p>";
        
        // Display test accounts
        echo "<div style='background: #f0f9ff; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>Test Accounts Created:</h3>";
        echo "<ul>";
        echo "<li><strong>Admin:</strong> admin@city.gov / password</li>";
        echo "<li><strong>Manager:</strong> manager@city.gov / password</li>";
        echo "<li><strong>Employee:</strong> employee@city.gov / password</li>";
        echo "<li><strong>Citizen:</strong> citizen@example.com / password</li>";
        echo "</ul>";
        echo "<p style='color: #666; font-size: 14px;'>Note: All passwords are set to 'password' for testing purposes</p>";
        echo "</div>";
        
        echo "<div style='background: #f0fdf4; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>Next Steps:</h3>";
        echo "<ol>";
        echo "<li>Go to <a href='login.php'>login.php</a> to test the login system</li>";
        echo "<li>Try logging in with different roles to see role-based access</li>";
        echo "<li>Check the dashboard for role-specific content</li>";
        echo "<li>In production, change all passwords to strong, unique passwords</li>";
        echo "</ol>";
        echo "</div>";
        
    } else {
        echo "<p style='color: red;'>✗ Database setup may have failed. Please check the SQL file.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

$conn->close();

echo "<div style='margin-top: 30px; padding: 15px; background: #fefce8; border-radius: 5px;'>";
echo "<h3>Database Information:</h3>";
echo "<ul>";
echo "<li><strong>Host:</strong> $host</li>";
echo "<li><strong>Database:</strong> $database</li>";
echo "<li><strong>Username:</strong> $username</li>";
echo "<li><strong>Tables Created:</strong> users, projects, communications, approvals, user_sessions, role_permissions, feedback, reports</li>";
echo "</ul>";
echo "</div>";

echo "<p style='text-align: center; margin-top: 30px;'>";
echo "<a href='login.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a>";
echo "</p>";

echo "</div>";
?>
