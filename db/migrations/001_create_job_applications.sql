-- Migration: create job_applications table
CREATE TABLE IF NOT EXISTS job_applications (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(200) NOT NULL,
  `last_name` VARCHAR(200) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `address` TEXT,
  `age` INT DEFAULT NULL,
  `eligible_to_work` VARCHAR(50) DEFAULT NULL,
  `position_desired` VARCHAR(255) DEFAULT NULL,
  `employment_type` VARCHAR(100) DEFAULT NULL,
  `why_work_here` TEXT,
  `availability` TEXT,
  `resume_storage_name` VARCHAR(255) DEFAULT NULL,
  `resume_original_name` VARCHAR(255) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `sent` TINYINT(1) DEFAULT 0,
  `status` ENUM('new','reviewed','archived') DEFAULT 'new',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
