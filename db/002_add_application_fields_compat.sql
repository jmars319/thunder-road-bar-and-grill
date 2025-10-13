-- Migration: add legacy application fields to job_applications (compatible)
SET @db = DATABASE();
-- desired_salary
SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'desired_salary';
SET @sql = IF(@c = 0, 'ALTER TABLE job_applications ADD COLUMN desired_salary VARCHAR(100) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- start_date
SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'start_date';
SET @sql = IF(@c = 0, 'ALTER TABLE job_applications ADD COLUMN start_date VARCHAR(50) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- shift_preference
SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'shift_preference';
SET @sql = IF(@c = 0, 'ALTER TABLE job_applications ADD COLUMN shift_preference VARCHAR(100) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- hours_per_week
SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'hours_per_week';
SET @sql = IF(@c = 0, 'ALTER TABLE job_applications ADD COLUMN hours_per_week VARCHAR(50) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- restaurant_experience
SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'restaurant_experience';
SET @sql = IF(@c = 0, 'ALTER TABLE job_applications ADD COLUMN restaurant_experience TEXT DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- other_experience
SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'other_experience';
SET @sql = IF(@c = 0, 'ALTER TABLE job_applications ADD COLUMN other_experience TEXT DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- references_text
SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'references_text';
SET @sql = IF(@c = 0, 'ALTER TABLE job_applications ADD COLUMN references_text TEXT DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- raw_message
SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'raw_message';
SET @sql = IF(@c = 0, 'ALTER TABLE job_applications ADD COLUMN raw_message TEXT DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
