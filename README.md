# City Management System - Database Setup Guide

## 🚀 Complete Step-by-Step Implementation Guide

### Prerequisites
- XAMPP installed and running
- Apache and MySQL services started
- Basic knowledge of web development

---

## 📋 Method 1: Using the Automated PHP Setup Script (Recommended)

### Step 1: Start XAMPP Services
1. Open XAMPP Control Panel
2. Start **Apache** and **MySQL** services
3. Ensure both services show "Running" status

### Step 2: Access the Setup Script
1. Open your web browser
2. Navigate to: `http://localhost/CMS/setup_database.php`
3. The script will automatically:
   - Create the `citydemo` database
   - Create all necessary tables
   - Insert sample data
   - Set up test accounts

### Step 3: Verify Setup
1. Check that you see "Database setup completed successfully!"
2. Note the test accounts provided
3. Click "Go to Login Page" to test

---

## 📋 Method 2: Manual Setup via phpMyAdmin

### Step 1: Access phpMyAdmin
1. Open browser and go to: `http://localhost/phpmyadmin`
2. Click on "New" to create a database

### Step 2: Create Database
1. Database name: `citydemo`
2. Collation: `utf8mb4_unicode_ci`
3. Click "Create"

### Step 3: Import SQL File
1. Select the `citydemo` database
2. Click "Import" tab
3. Choose file: `setup_database.sql`
4. Click "Go" to execute

---

## 📋 Method 3: Command Line Setup

### Step 1: Open Command Prompt
1. Press `Win + R`, type `cmd`, press Enter
2. Navigate to XAMPP MySQL directory:
   ```cmd
   cd C:\xampp\mysql\bin
   ```

### Step 2: Connect to MySQL
```cmd
mysql -u root -p
```
(Leave password empty and press Enter)

### Step 3: Execute SQL Commands
```sql
source C:\xampp\htdocs\CMS\setup_database.sql;
```

---

## 🔧 Configuration Files

### Database Configuration (`config.php`)
```php
<?php
$conn = new mysqli("localhost", "root", "", "citydemo");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
?>
```

### Login System (`login.php`)
- Role-based authentication
- Password hashing support
- Session management
- Error handling

---

## 👥 Test Accounts

After setup, use these accounts to test different roles:

| Role | Email | Password | Access Level |
|------|-------|----------|--------------|
| **Admin** | admin@city.gov | password | Full system access |
| **Manager** | manager@city.gov | password | Department management |
| **Employee** | employee@city.gov | password | Project management |
| **Citizen** | citizen@example.com | password | Public access |

---

## 🗄️ Database Structure

### Core Tables Created:
1. **users** - User accounts and roles
2. **projects** - Infrastructure projects
3. **communications** - Inter-department messages
4. **approvals** - Project approval workflow
5. **user_sessions** - Session management
6. **role_permissions** - Role-based access control
7. **feedback** - Citizen feedback system
8. **reports** - Generated reports

### Key Features:
- ✅ Role-based authentication
- ✅ Password hashing
- ✅ Session management
- ✅ Foreign key relationships
- ✅ Indexes for performance
- ✅ Sample data for testing

---

## 🚨 Troubleshooting

### Common Issues:

#### 1. "Connection failed" Error
- **Solution**: Ensure MySQL service is running in XAMPP
- Check if port 3306 is available

#### 2. "Database doesn't exist" Error
- **Solution**: Run the setup script first
- Or manually create `citydemo` database

#### 3. "Access denied" Error
- **Solution**: Check MySQL username/password in config.php
- Default XAMPP: username=`root`, password=`` (empty)

#### 4. "Table already exists" Warning
- **Solution**: This is normal for existing installations
- The script uses `CREATE TABLE IF NOT EXISTS`

---

## 🔒 Security Considerations

### For Production Use:
1. **Change Default Passwords**
   ```php
   // Hash passwords properly
   $hashed_password = password_hash($password, PASSWORD_DEFAULT);
   ```

2. **Update Database Credentials**
   ```php
   // Use strong database credentials
   $conn = new mysqli("localhost", "secure_user", "strong_password", "citydemo");
   ```

3. **Enable SSL/HTTPS**
4. **Regular Database Backups**
5. **Update PHP and MySQL regularly**

---

## 📱 Testing the System

### Step 1: Login Test
1. Go to `http://localhost/CMS/login.php`
2. Select a role from dropdown
3. Enter test credentials
4. Verify redirect to appropriate dashboard

### Step 2: Role-Based Access Test
1. Login as different roles
2. Check navigation menu changes
3. Verify role-specific content
4. Test logout functionality

### Step 3: Database Verification
1. Open phpMyAdmin
2. Check `citydemo` database
3. Verify all tables exist
4. Check sample data

---

## 🎯 Next Steps

1. **Customize Content**: Modify dashboard content for your city
2. **Add Real Data**: Replace sample data with actual projects
3. **Enhance Security**: Implement additional security measures
4. **Add Features**: Extend functionality based on requirements
5. **Deploy**: Move to production server when ready

---

## 📞 Support

If you encounter issues:
1. Check XAMPP error logs
2. Verify file permissions
3. Ensure all files are in correct locations
4. Test database connection manually

**File Locations:**
- Main files: `C:\xampp\htdocs\CMS\`
- Database setup: `C:\xampp\htdocs\CMS\setup_database.php`
- SQL file: `C:\xampp\htdocs\CMS\setup_database.sql`

---

*This guide provides everything needed to set up the City Management System database in XAMPP. Follow the steps carefully for a successful installation.*
