-- Migration: add legacy application fields to job_applications
ALTER TABLE job_applications
  ADD COLUMN desired_salary VARCHAR(100) DEFAULT NULL,
  ADD COLUMN start_date VARCHAR(50) DEFAULT NULL,
  ADD COLUMN shift_preference VARCHAR(100) DEFAULT NULL,
  ADD COLUMN hours_per_week VARCHAR(50) DEFAULT NULL,
  ADD COLUMN restaurant_experience TEXT DEFAULT NULL,
  ADD COLUMN other_experience TEXT DEFAULT NULL,
  ADD COLUMN references TEXT DEFAULT NULL,
  ADD COLUMN raw_message TEXT DEFAULT NULL;
