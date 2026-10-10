<?php
/**
 * Database Connection & Initialization
 */
require_once __DIR__ . '/config.php';

class DB {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                if (getenv('DB_SSL') === 'true' || getenv('DB_SSL') === '1') {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }

                try {
                    self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                } catch (PDOException $e) {
                    // If database does not exist, try to create it
                    if ($e->getCode() === 1049) {
                        $initDsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
                        $initPdo = new PDO($initDsn, DB_USER, DB_PASS, $options);
                        $initPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                    } else {
                        throw $e;
                    }
                }

                self::ensureTables(self::$instance);

            } catch (PDOException $e) {
                die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
            }
        }
        return self::$instance;
    }

    private static function ensureTables(PDO $pdo): void {
        $sql = "
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
            KEY `idx_expert` (`expert_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
        ";
        $pdo->exec($sql);
    }
}
