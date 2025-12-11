-- =====================================================
-- JobNexus - Complete Database Schema
-- Premium Job Portal Application
-- Compatible with phpMyAdmin SQL tab
-- =====================================================

-- Create Database
CREATE DATABASE IF NOT EXISTS jobnexus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jobnexus;

-- =====================================================
-- DROP EXISTING TABLES (for clean reinstall)
-- =====================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS saved_jobs;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS job_categories;
DROP TABLE IF EXISTS companies;
DROP TABLE IF EXISTS experience;
DROP TABLE IF EXISTS education;
DROP TABLE IF EXISTS seeker_profiles;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- USERS TABLE - Core authentication table
-- =====================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'hr', 'seeker') NOT NULL DEFAULT 'seeker',
    is_verified TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_verified (is_verified)
) ENGINE=InnoDB;

-- =====================================================
-- PASSWORD RESETS - For forgot password functionality
-- =====================================================
CREATE TABLE password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- =====================================================
-- SEEKER PROFILES - Job Seeker detailed information
-- =====================================================
CREATE TABLE seeker_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    headline VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    location VARCHAR(255) NULL,
    bio TEXT NULL,
    profile_photo VARCHAR(255) NULL,
    resume_file_path VARCHAR(255) NULL,
    linkedin_url VARCHAR(255) NULL,
    github_url VARCHAR(255) NULL,
    portfolio_url VARCHAR(255) NULL,
    skills JSON NULL,
    target_job_category VARCHAR(100) NULL,
    expected_salary_min DECIMAL(12,2) NULL,
    expected_salary_max DECIMAL(12,2) NULL,
    preferred_location_type ENUM('remote', 'onsite', 'hybrid', 'any') DEFAULT 'any',
    is_available TINYINT(1) DEFAULT 1,
    profile_completion INT DEFAULT 0,
    profile_views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_category (target_job_category),
    INDEX idx_available (is_available)
) ENGINE=InnoDB;

-- =====================================================
-- EDUCATION - Seeker education history
-- =====================================================
CREATE TABLE education (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seeker_id INT NOT NULL,
    degree VARCHAR(255) NOT NULL,
    field_of_study VARCHAR(255) NULL,
    institution VARCHAR(255) NOT NULL,
    location VARCHAR(255) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    is_current TINYINT(1) DEFAULT 0,
    grade VARCHAR(50) NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seeker_id) REFERENCES seeker_profiles(id) ON DELETE CASCADE,
    INDEX idx_seeker (seeker_id)
) ENGINE=InnoDB;

-- =====================================================
-- EXPERIENCE - Seeker work experience
-- =====================================================
CREATE TABLE experience (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seeker_id INT NOT NULL,
    job_title VARCHAR(255) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NULL,
    location_type ENUM('remote', 'onsite', 'hybrid') DEFAULT 'onsite',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    is_current TINYINT(1) DEFAULT 0,
    description TEXT NULL,
    achievements JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seeker_id) REFERENCES seeker_profiles(id) ON DELETE CASCADE,
    INDEX idx_seeker (seeker_id)
) ENGINE=InnoDB;

-- =====================================================
-- COMPANIES - HR/Employer company profiles
-- =====================================================
CREATE TABLE companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hr_user_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    logo VARCHAR(255) NULL,
    cover_image VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    description TEXT NULL,
    industry VARCHAR(100) NULL,
    company_size ENUM('1-10', '11-50', '51-200', '201-500', '501-1000', '1000+') NULL,
    founded_year YEAR NULL,
    headquarters VARCHAR(255) NULL,
    social_linkedin VARCHAR(255) NULL,
    social_twitter VARCHAR(255) NULL,
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT NULL,
    verified_at TIMESTAMP NULL,
    is_featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hr_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_hr_user (hr_user_id),
    INDEX idx_status (verification_status),
    INDEX idx_industry (industry),
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

-- =====================================================
-- JOB CATEGORIES - Predefined job categories
-- =====================================================
CREATE TABLE job_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(50) NULL,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    job_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- JOBS - Job listings
-- =====================================================
CREATE TABLE jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    posted_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    requirements TEXT NULL,
    responsibilities TEXT NULL,
    benefits TEXT NULL,
    category_id INT NULL,
    job_type ENUM('full-time', 'part-time', 'contract', 'internship', 'freelance') DEFAULT 'full-time',
    location_type ENUM('remote', 'onsite', 'hybrid') DEFAULT 'onsite',
    location VARCHAR(255) NULL,
    salary_min DECIMAL(12,2) NULL,
    salary_max DECIMAL(12,2) NULL,
    salary_currency VARCHAR(3) DEFAULT 'USD',
    salary_period ENUM('hourly', 'monthly', 'yearly') DEFAULT 'yearly',
    show_salary TINYINT(1) DEFAULT 1,
    experience_level ENUM('entry', 'mid', 'senior', 'lead', 'executive') DEFAULT 'mid',
    experience_years_min INT NULL,
    experience_years_max INT NULL,
    skills_required JSON NULL,
    application_deadline DATE NULL,
    positions_available INT DEFAULT 1,
    status ENUM('draft', 'pending', 'active', 'paused', 'closed', 'expired') DEFAULT 'draft',
    is_featured TINYINT(1) DEFAULT 0,
    is_urgent TINYINT(1) DEFAULT 0,
    views_count INT DEFAULT 0,
    applications_count INT DEFAULT 0,
    published_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES job_categories(id) ON DELETE SET NULL,
    INDEX idx_company (company_id),
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_location_type (location_type),
    INDEX idx_job_type (job_type),
    INDEX idx_published (published_at),
    FULLTEXT idx_search (title, description, requirements)
) ENGINE=InnoDB;

-- =====================================================
-- APPLICATIONS - Job applications
-- =====================================================
CREATE TABLE applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    seeker_id INT NOT NULL,
    cover_letter TEXT NULL,
    resume_file VARCHAR(255) NULL,
    status ENUM('applied', 'viewed', 'shortlisted', 'interview', 'offered', 'rejected', 'hired', 'withdrawn') DEFAULT 'applied',
    status_notes TEXT NULL,
    rating INT NULL CHECK (rating >= 1 AND rating <= 5),
    hr_notes TEXT NULL,
    viewed_at TIMESTAMP NULL,
    shortlisted_at TIMESTAMP NULL,
    interview_at TIMESTAMP NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (seeker_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (job_id, seeker_id),
    INDEX idx_job (job_id),
    INDEX idx_seeker (seeker_id),
    INDEX idx_status (status),
    INDEX idx_applied (applied_at)
) ENGINE=InnoDB;

-- =====================================================
-- EVENTS/CALENDAR - Interview scheduling
-- =====================================================
CREATE TABLE events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    application_id INT NULL,
    hr_user_id INT NOT NULL,
    seeker_user_id INT NOT NULL,
    event_title VARCHAR(255) NOT NULL,
    event_type ENUM('interview', 'screening', 'technical', 'hr_round', 'final', 'other') DEFAULT 'interview',
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    duration_minutes INT DEFAULT 60,
    timezone VARCHAR(50) DEFAULT 'UTC',
    location VARCHAR(255) NULL,
    meeting_link VARCHAR(500) NULL,
    description TEXT NULL,
    status ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'rescheduled') DEFAULT 'scheduled',
    reminder_sent TINYINT(1) DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL,
    FOREIGN KEY (hr_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seeker_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_hr (hr_user_id),
    INDEX idx_seeker (seeker_user_id),
    INDEX idx_date (event_date),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- =====================================================
-- SAVED JOBS - Bookmarked jobs by seekers
-- =====================================================
CREATE TABLE saved_jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_save (user_id, job_id)
) ENGINE=InnoDB;

-- =====================================================
-- NOTIFICATIONS - System notifications
-- =====================================================
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- ACTIVITY LOG - User activity tracking
-- =====================================================
CREATE TABLE activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- SETTINGS - Application settings
-- =====================================================
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Default Admin User (Password: Admin@123)
INSERT INTO users (email, password_hash, role, is_verified, is_active) VALUES
('admin@jobnexus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 1);

-- Default Job Categories
INSERT INTO job_categories (name, slug, icon) VALUES
('Technology', 'technology', 'fa-laptop-code'),
('Design', 'design', 'fa-palette'),
('Marketing', 'marketing', 'fa-bullhorn'),
('Sales', 'sales', 'fa-chart-line'),
('Finance', 'finance', 'fa-coins'),
('Healthcare', 'healthcare', 'fa-heartbeat'),
('Education', 'education', 'fa-graduation-cap'),
('Engineering', 'engineering', 'fa-cogs'),
('Customer Service', 'customer-service', 'fa-headset'),
('Human Resources', 'human-resources', 'fa-users'),
('Legal', 'legal', 'fa-gavel'),
('Operations', 'operations', 'fa-tasks'),
('Data Science', 'data-science', 'fa-database'),
('Product Management', 'product-management', 'fa-boxes'),
('Content Writing', 'content-writing', 'fa-pen-fancy');

-- Default Settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'JobNexus', 'string', 'Website name'),
('site_tagline', 'Find Your Dream Career', 'string', 'Website tagline'),
('jobs_per_page', '12', 'number', 'Number of jobs per page'),
('allow_registration', 'true', 'boolean', 'Allow new user registration'),
('require_hr_verification', 'true', 'boolean', 'Require admin verification for HR accounts'),
('featured_job_price', '99.99', 'number', 'Price for featuring a job'),
('max_resume_size', '5242880', 'number', 'Max resume file size in bytes (5MB)'),
('allowed_resume_types', '["pdf","doc","docx"]', 'json', 'Allowed resume file types');

-- =====================================================
-- VIEWS FOR DASHBOARD STATISTICS
-- =====================================================

-- View for job statistics
CREATE OR REPLACE VIEW v_job_stats AS
SELECT 
    COUNT(*) as total_jobs,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_jobs,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_jobs,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_jobs,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as jobs_today,
    SUM(CASE WHEN YEARWEEK(created_at) = YEARWEEK(CURDATE()) THEN 1 ELSE 0 END) as jobs_this_week
FROM jobs;

-- View for user statistics
CREATE OR REPLACE VIEW v_user_stats AS
SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'seeker' THEN 1 ELSE 0 END) as total_seekers,
    SUM(CASE WHEN role = 'hr' THEN 1 ELSE 0 END) as total_hr,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_users_today
FROM users;

-- View for application statistics
CREATE OR REPLACE VIEW v_application_stats AS
SELECT 
    COUNT(*) as total_applications,
    SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) as pending_applications,
    SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
    SUM(CASE WHEN status = 'interview' THEN 1 ELSE 0 END) as interviews_scheduled,
    SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hired,
    SUM(CASE WHEN DATE(applied_at) = CURDATE() THEN 1 ELSE 0 END) as applications_today
FROM applications;

-- =====================================================
-- STORED PROCEDURES (phpMyAdmin compatible)
-- Note: In phpMyAdmin, go to "Routines" tab to manage these
-- =====================================================

-- Procedure to update job application count
DROP PROCEDURE IF EXISTS update_job_application_count;
CREATE PROCEDURE update_job_application_count(IN p_job_id INT)
UPDATE jobs SET applications_count = (SELECT COUNT(*) FROM applications WHERE job_id = p_job_id) WHERE id = p_job_id;

-- Procedure to update category job count  
DROP PROCEDURE IF EXISTS update_category_job_count;
CREATE PROCEDURE update_category_job_count(IN p_category_id INT)
UPDATE job_categories SET job_count = (SELECT COUNT(*) FROM jobs WHERE category_id = p_category_id AND status = 'active') WHERE id = p_category_id;

-- =====================================================
-- TRIGGERS (phpMyAdmin compatible - single statement)
-- Note: For complex triggers, use phpMyAdmin "Triggers" tab
-- =====================================================

-- Simple trigger to update application count (single statement version)
DROP TRIGGER IF EXISTS after_application_insert;
CREATE TRIGGER after_application_insert
AFTER INSERT ON applications
FOR EACH ROW
UPDATE jobs SET applications_count = applications_count + 1 WHERE id = NEW.job_id;

-- Trigger for application deletion
DROP TRIGGER IF EXISTS after_application_delete;
CREATE TRIGGER after_application_delete
AFTER DELETE ON applications
FOR EACH ROW
UPDATE jobs SET applications_count = applications_count - 1 WHERE id = OLD.job_id;
