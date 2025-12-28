-- Remove CREATE DATABASE statement - InfinityFree creates this for you
-- Just start creating tables in your already-selected database

-- Create users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'chairman', 'secretary', 'treasurer', 'music_director', 'spiritual_leader', 'publicity_leader', 'member') DEFAULT 'member',
    `phone` VARCHAR(20),
    `voice_part` ENUM('soprano', 'alto', 'tenor', 'bass') DEFAULT NULL,
    `address` TEXT,
    `date_of_birth` DATE DEFAULT NULL,
    `profile_pic` VARCHAR(255) DEFAULT 'default.png',
    `join_date` DATE NOT NULL,
    `department` VARCHAR(50),
    `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    `last_login` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample admin user (password: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `phone`, `join_date`, `department`) 
VALUES ('admin', 'admin@lhm.com', '$2y$10$YourHashedPasswordHere', 'System Administrator', 'admin', '+1234567890', CURDATE(), 'Administration');

-- Insert sample member user (password: member123)
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `phone`, `join_date`, `department`) 
VALUES ('member1', 'member1@lhm.com', '$2y$10$YourHashedPasswordHere', 'John Doe', 'member', '+1234567891', CURDATE(), 'Music');

-- Create indexes for better performance
CREATE INDEX `idx_role` ON `users`(`role`);
CREATE INDEX `idx_status` ON `users`(`status`);
CREATE INDEX `idx_department` ON `users`(`department`);

-- Create itinerary table
CREATE TABLE IF NOT EXISTS `itinerary` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `quarter` ENUM('Q1', 'Q2', 'Q3', 'Q4') NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `event_date` DATE NOT NULL,
    `event_time` TIME NOT NULL,
    `venue` VARCHAR(200),
    `type` ENUM('Meeting', 'Practice', 'Service', 'Event', 'Retreat', 'Conference') DEFAULT 'Meeting',
    `sponsored` ENUM('yes', 'no') DEFAULT 'no',
    `contribution_amount` DECIMAL(10,2) DEFAULT 0.00,
    `contribution_required` DECIMAL(10,2) DEFAULT 0.00,
    `status` ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Create duty_roster table
CREATE TABLE IF NOT EXISTS `duty_roster` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `week_start` DATE NOT NULL,
    `week_end` DATE NOT NULL,
    `member1_id` INT,
    `member2_id` INT,
    `notes` TEXT,
    `status` ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`member1_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`member2_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Create songs table
CREATE TABLE IF NOT EXISTS `songs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `artist` VARCHAR(200),
    `album` VARCHAR(200),
    `category` VARCHAR(100),
    `pdf_filename` VARCHAR(500) NOT NULL,
    `original_filename` VARCHAR(500),
    `file_size` INT,
    `lyrics` TEXT,
    `year` INT,
    `bpm` INT,
    `key_signature` VARCHAR(10),
    `duration` VARCHAR(20),
    `added_by` INT,
    `status` ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    `download_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`added_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Create song_log table for tracking
CREATE TABLE IF NOT EXISTS `song_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `song_id` INT,
    `user_id` INT,
    `action` VARCHAR(50),
    `details` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`song_id`) REFERENCES `songs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Create social_accounts table
CREATE TABLE IF NOT EXISTS `social_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `platform` VARCHAR(50) NOT NULL,
    `account_name` VARCHAR(200) NOT NULL,
    `account_url` VARCHAR(500),
    `followers_count` INT DEFAULT 0,
    `monthly_revenue` DECIMAL(10,2) DEFAULT 0.00,
    `currency` VARCHAR(3) DEFAULT 'KES',
    `last_updated` DATE,
    `status` ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    `notes` TEXT,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Create bank_accounts table
CREATE TABLE IF NOT EXISTS `bank_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bank_name` VARCHAR(100) NOT NULL,
    `account_name` VARCHAR(200) NOT NULL,
    `account_number` VARCHAR(50),
    `account_type` VARCHAR(50),
    `current_balance` DECIMAL(12,2) DEFAULT 0.00,
    `currency` VARCHAR(3) DEFAULT 'KES',
    `last_updated` DATE,
    `status` ENUM('active', 'inactive', 'closed') DEFAULT 'active',
    `notes` TEXT,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Create revenue_log table for tracking changes
CREATE TABLE IF NOT EXISTS `revenue_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `social_account_id` INT,
    `user_id` INT,
    `action` VARCHAR(50),
    `old_followers` INT,
    `new_followers` INT,
    `old_revenue` DECIMAL(10,2),
    `new_revenue` DECIMAL(10,2),
    `month_year` VARCHAR(7), -- Format: YYYY-MM
    `details` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`social_account_id`) REFERENCES `social_accounts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Create bank_transaction_log table
CREATE TABLE IF NOT EXISTS `bank_transaction_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bank_account_id` INT,
    `user_id` INT,
    `action` VARCHAR(50),
    `old_balance` DECIMAL(12,2),
    `new_balance` DECIMAL(12,2),
    `transaction_type` VARCHAR(50),
    `details` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Create suggestions table
CREATE TABLE IF NOT EXISTS `suggestions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `content` TEXT NOT NULL,
    `type` ENUM('suggestion', 'question', 'comment') DEFAULT 'suggestion',
    `parent_id` INT DEFAULT NULL, -- For replies (NULL means main post)
    `likes` INT DEFAULT 0,
    `dislikes` INT DEFAULT 0,
    `status` ENUM('active', 'hidden', 'resolved') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_id`) REFERENCES `suggestions`(`id`) ON DELETE CASCADE
);

-- Create suggestion_likes table to track user likes/dislikes
CREATE TABLE IF NOT EXISTS `suggestion_likes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `suggestion_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `reaction` ENUM('like', 'dislike'),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_reaction` (`suggestion_id`, `user_id`),
    FOREIGN KEY (`suggestion_id`) REFERENCES `suggestions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Create reports table
CREATE TABLE IF NOT EXISTS `reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department` VARCHAR(255) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `pdf_filename` VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `file_size` INT NOT NULL,
    `added_by` INT NOT NULL,
    `download_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`added_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Create attendance table
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `member_id` INT NOT NULL,
    `practice_date` DATE NOT NULL,
    `status` ENUM('present', 'absent') NOT NULL,
    `notes` TEXT,
    `recorded_by` INT NOT NULL,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`member_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_attendance` (`member_id`, `practice_date`)
);

-- Create indexes for attendance table
CREATE INDEX `idx_practice_date` ON `attendance`(`practice_date`);
CREATE INDEX `idx_member_date` ON `attendance`(`member_id`, `practice_date`);

-- Create announcements table
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `content` TEXT NOT NULL,
    `category` ENUM('general', 'meeting', 'practice', 'event', 'important', 'reminder') DEFAULT 'general',
    `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    `author_id` INT NOT NULL,
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `status` ENUM('draft', 'active', 'expired', 'archived') DEFAULT 'draft',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Create page_permissions table
CREATE TABLE IF NOT EXISTS `page_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role` VARCHAR(50) NOT NULL,
    `page_name` VARCHAR(100) NOT NULL,
    `can_view` TINYINT(1) DEFAULT 0,
    `can_edit` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert page permissions data (add your actual permissions)
INSERT INTO `page_permissions` (`role`, `page_name`, `can_view`, `can_edit`) VALUES
('admin', 'dashboard.php', 1, 1),
('admin', 'attendance.php', 1, 1),
('admin', 'members.php', 1, 1),
('secretary', 'attendance.php', 1, 1),
('secretary', 'members.php', 1, 1),
('chairman', 'attendance.php', 1, 1);

-- Create user_settings table
CREATE TABLE IF NOT EXISTS `user_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `theme_mode` ENUM('light', 'dark', 'auto') DEFAULT 'light',
    `notifications` TINYINT(1) DEFAULT 1,
    `email_notifications` TINYINT(1) DEFAULT 1,
    `dashboard_refresh_rate` INT DEFAULT 30,
    `items_per_page` INT DEFAULT 20,
    `timezone` VARCHAR(50) DEFAULT 'UTC',
    `date_format` VARCHAR(20) DEFAULT 'Y-m-d',
    `time_format` ENUM('12', '24') DEFAULT '24',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);