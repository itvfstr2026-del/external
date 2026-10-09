-- Vignan's Question Paper Feedback Portal Database Schema
CREATE DATABASE IF NOT EXISTS `vignan_qp_feedback` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `vignan_qp_feedback`;

-- Table: people (External Experts, Paper Setters, and Admins)
CREATE TABLE IF NOT EXISTS `people` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role` ENUM('expert', 'setter', 'admin') NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(100) DEFAULT '',
    `designation_institution` VARCHAR(255) DEFAULT '',
    `department` VARCHAR(255) DEFAULT '',
    `access_code` VARCHAR(50) NOT NULL,
    `signature` MEDIUMBLOB NULL,
    `seal` MEDIUMBLOB NULL,
    `created_at` DATETIME NOT NULL,
    UNIQUE KEY `unique_role_email` (`role`, `email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: courses
CREATE TABLE IF NOT EXISTS `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department` VARCHAR(150) NOT NULL,
    `programme` VARCHAR(150) NOT NULL,
    `regulation` VARCHAR(50) DEFAULT '',
    `academic_year` VARCHAR(50) NOT NULL,
    `semester` VARCHAR(50) NOT NULL,
    `course_code` VARCHAR(50) NOT NULL,
    `course_name` VARCHAR(255) NOT NULL,
    `setter_id` INT NOT NULL,
    `expert_id` INT NULL,
    `created_at` DATETIME NOT NULL,
    UNIQUE KEY `unique_course` (`academic_year`, `semester`, `department`, `programme`, `course_code`),
    KEY `idx_setter` (`setter_id`),
    KEY `idx_expert` (`expert_id`),
    CONSTRAINT `fk_course_setter` FOREIGN KEY (`setter_id`) REFERENCES `people` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_course_expert` FOREIGN KEY (`expert_id`) REFERENCES `people` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: feedback (External Expert Scrutiny Form)
CREATE TABLE IF NOT EXISTS `feedback` (
    `course_id` INT PRIMARY KEY,
    `expert_name` VARCHAR(255) DEFAULT '',
    `designation_institution` VARCHAR(255) DEFAULT '',
    `email` VARCHAR(255) DEFAULT '',
    `contact` VARCHAR(100) DEFAULT '',
    `ratings` LONGTEXT DEFAULT NULL,
    `remarks` LONGTEXT DEFAULT NULL,
    `recommendation` VARCHAR(255) DEFAULT '',
    `overall_remarks` TEXT DEFAULT NULL,
    `form_date` DATE DEFAULT NULL,
    `signature` MEDIUMBLOB NULL,
    `seal` MEDIUMBLOB NULL,
    `status` ENUM('draft', 'submitted') NOT NULL DEFAULT 'draft',
    `submitted_at` DATETIME NULL,
    `updated_at` DATETIME NOT NULL,
    CONSTRAINT `fk_feedback_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: action_report (Paper Setter Action Taken Report)
CREATE TABLE IF NOT EXISTS `action_report` (
    `course_id` INT PRIMARY KEY,
    `setter_name` VARCHAR(255) DEFAULT '',
    `department` VARCHAR(255) DEFAULT '',
    `contact` VARCHAR(100) DEFAULT '',
    `email` VARCHAR(255) DEFAULT '',
    `declarations` LONGTEXT DEFAULT NULL,
    `summary` TEXT DEFAULT NULL,
    `retained` LONGTEXT DEFAULT NULL,
    `form_date` DATE DEFAULT NULL,
    `signature` MEDIUMBLOB NULL,
    `status` ENUM('draft', 'submitted') NOT NULL DEFAULT 'draft',
    `submitted_at` DATETIME NULL,
    `updated_at` DATETIME NOT NULL,
    CONSTRAINT `fk_atr_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
