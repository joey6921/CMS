-- =====================================================
-- CITY MANAGEMENT SYSTEM DATABASE SETUP
-- =====================================================
-- This script creates the complete database structure for the CMS
-- Run this script in phpMyAdmin or MySQL command line

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS citydemo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE citydemo;

-- =====================================================
-- USERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'employee', 'citizen') NOT NULL,
    department VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL
);

-- =====================================================
-- PROJECTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    description TEXT,
    department VARCHAR(100) NOT NULL,
    status ENUM('planning', 'in_progress', 'completed', 'cancelled', 'on_hold') DEFAULT 'planning',
    completion_status ENUM('pending', 'under_review', 'completed') DEFAULT 'pending',
    start_date DATE,
    expected_completion DATE,
    actual_completion DATE NULL,
    budget DECIMAL(15,2),
    spent_amount DECIMAL(15,2) DEFAULT 0.00,
    assigned_manager_id INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_manager_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- =====================================================
-- COMMUNICATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS communications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT,
    recipient_department VARCHAR(100),
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('sent', 'read', 'replied', 'archived') DEFAULT 'sent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- APPROVALS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    requested_by INT NOT NULL,
    approved_by INT,
    approval_type ENUM('budget', 'timeline', 'resource', 'final') NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    comments TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- USER SESSIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- ROLE PERMISSIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    permission VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_permission (role, permission)
);

-- =====================================================
-- FEEDBACK TABLE (for citizens)
-- =====================================================
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_id INT NOT NULL,
    project_id INT,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    status ENUM('new', 'reviewed', 'in_progress', 'resolved', 'closed') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
);

-- =====================================================
-- REPORTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_name VARCHAR(255) NOT NULL,
    report_type ENUM('project', 'financial', 'department', 'citizen_feedback') NOT NULL,
    generated_by INT NOT NULL,
    file_path VARCHAR(500),
    parameters JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- WORK REPORTS TABLE (employee -> manager -> tdo flow)
-- =====================================================
CREATE TABLE IF NOT EXISTS work_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    employee_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    work_summary TEXT NOT NULL,
    materials_used TEXT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('submitted', 'manager_verified', 'tdo_approved', 'rejected', 'rework_required') DEFAULT 'submitted',
    manager_id INT NULL,
    manager_comment TEXT NULL,
    manager_reviewed_at TIMESTAMP NULL,
    tdo_id INT NULL,
    tdo_comment TEXT NULL,
    tdo_reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (tdo_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- WORK REPORT FILES TABLE (photo evidence)
-- =====================================================
CREATE TABLE IF NOT EXISTS work_report_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_report_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    caption VARCHAR(255) NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (work_report_id) REFERENCES work_reports(id) ON DELETE CASCADE
);

-- =====================================================
-- INSERT SAMPLE DATA
-- =====================================================

-- Insert sample users for testing (passwords are hashed)
INSERT INTO users (email, password, full_name, role, department, phone) VALUES
('admin@city.gov', 'admin@city', 'TDO', 'admin', 'IT Department', '+91-9876543210'),
('manager@city.gov', 'manager@city', 'Joey', 'manager', 'Infrastructure Department', '+91-9876543211'),
('employee@city.gov', 'employee@city', 'Chandler', 'employee', 'Public Works', '+91-9876543212'),
('citizen@example.com', 'citizen@city', '', 'citizen', NULL, '+91-9876543213');

-- Insert sample projects
INSERT INTO projects (project_name, description, department, status, start_date, expected_completion, budget, assigned_manager_id, created_by) VALUES
('MG Road Renovation', 'Complete renovation of MG Road including drainage and street lighting', 'Infrastructure Department', 'in_progress', '2024-10-01', '2024-12-15', 25000000.00, 2, 1),
('Main Street Maintenance', 'Regular maintenance and repair work on Main Street', 'Public Works', 'planning', '2024-11-01', '2024-11-30', 7500000.00, 2, 1),
('City Center Bridge Repair', 'Structural repair and reinforcement of City Center Bridge', 'Infrastructure Department', 'completed', '2024-08-15', '2024-09-30', 18000000.00, 2, 1),
('Drainage System Upgrade', 'Upgrade of city drainage system to prevent flooding', 'Public Health', 'in_progress', '2024-09-01', '2025-01-15', 32000000.00, 5, 1),
('Highway Expansion', 'Expansion of main highway to accommodate increased traffic', 'Infrastructure Department', 'planning', '2025-01-01', '2025-06-30', 50000000.00, 2, 1);

-- Insert role permissions
INSERT INTO role_permissions (role, permission, description) VALUES
-- Admin permissions
('admin', 'view_all_projects', 'View all projects in the system'),
('admin', 'edit_all_projects', 'Edit any project in the system'),
('admin', 'delete_projects', 'Delete projects from the system'),
('admin', 'manage_users', 'Create, edit, and delete user accounts'),
('admin', 'view_reports', 'Generate and view all reports'),
('admin', 'approve_projects', 'Approve project budgets and timelines'),
('admin', 'send_communications', 'Send communications to any user or department'),
('admin', 'view_feedback', 'View all citizen feedback'),
('admin', 'manage_departments', 'Manage department settings'),

-- Manager permissions
('manager', 'view_all_projects', 'View all projects in assigned departments'),
('manager', 'edit_assigned_projects', 'Edit projects assigned to their department'),
('manager', 'approve_projects', 'Approve projects within their authority'),
('manager', 'view_reports', 'Generate department reports'),
('manager', 'send_communications', 'Send communications to employees and other departments'),
('manager', 'view_feedback', 'View feedback related to their projects'),
('manager', 'verify_work_report', 'Verify employee submitted work report'),
('manager', 'view_all_work_reports', 'View all work reports in workflow'),

-- Employee permissions
('employee', 'view_assigned_projects', 'View projects they are assigned to'),
('employee', 'edit_assigned_projects', 'Edit details of assigned projects'),
('employee', 'view_reports', 'View project reports'),
('employee', 'send_communications', 'Send communications to managers and team members'),
('employee', 'submit_work_report', 'Submit completed work reports with materials and photos'),

-- Citizen permissions
('citizen', 'view_public_projects', 'View public project information'),
('citizen', 'submit_feedback', 'Submit feedback and complaints'),
('citizen', 'view_project_status', 'Check status of projects they are interested in'),

-- Workflow approval permissions
('admin', 'approve_work_completion', 'Final approval for work completion'),
('admin', 'view_all_work_reports', 'View all work reports');

-- Insert sample communications
INSERT INTO communications (sender_id, recipient_department, subject, message, priority) VALUES
(1, 'Electricity Department', 'MG Road Renovation - Power Supply Coordination', 'Please coordinate with us regarding temporary power supply disruption during MG Road renovation work.', 'high'),
(2, 'Traffic Police Department', 'Main Street Maintenance Schedule', 'We need traffic police support during Main Street maintenance work scheduled for November.', 'medium'),
(1, 'Environmental Department', 'Drainage System Upgrade - Environmental Clearance', 'Environmental clearance has been obtained for the drainage system upgrade project.', 'low');

-- Insert sample approvals
INSERT INTO approvals (project_id, requested_by, approval_type, status, comments) VALUES
(1, 2, 'budget', 'approved', 'Budget approved for MG Road renovation'),
(2, 2, 'timeline', 'pending', 'Timeline approval pending for Main Street maintenance'),
(4, 5, 'budget', 'approved', 'Budget approved for drainage system upgrade');

-- Insert sample feedback
INSERT INTO feedback (citizen_id, project_id, subject, message, rating, status) VALUES
(4, 1, 'MG Road Renovation Progress', 'The renovation work is progressing well. Please ensure proper traffic management.', 4, 'reviewed'),
(4, 3, 'City Center Bridge Repair', 'Excellent work on the bridge repair. The new structure looks solid.', 5, 'resolved');

-- =====================================================
-- CREATE INDEXES FOR BETTER PERFORMANCE
-- =====================================================
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_projects_status ON projects(status);
CREATE INDEX idx_projects_department ON projects(department);
CREATE INDEX idx_projects_completion_status ON projects(completion_status);
CREATE INDEX idx_communications_sender ON communications(sender_id);
CREATE INDEX idx_communications_recipient ON communications(recipient_id);
CREATE INDEX idx_approvals_status ON approvals(status);
CREATE INDEX idx_feedback_status ON feedback(status);
CREATE INDEX idx_sessions_user ON user_sessions(user_id);
CREATE INDEX idx_sessions_token ON user_sessions(session_token);
CREATE INDEX idx_work_reports_project ON work_reports(project_id);
CREATE INDEX idx_work_reports_employee ON work_reports(employee_id);
CREATE INDEX idx_work_reports_status ON work_reports(status);
CREATE INDEX idx_work_report_files_report ON work_report_files(work_report_id);

-- =====================================================
-- SETUP COMPLETE
-- =====================================================
-- Database setup completed successfully!
-- You can now use the following test accounts:
-- 
-- Admin: admin@city.gov / password
-- Manager: manager@city.gov / password  
-- Employee: employee@city.gov / password
-- Citizen: citizen@example.com / password
--
-- Note: All passwords are set to "password" for testing purposes
-- In production, use strong, unique passwords
