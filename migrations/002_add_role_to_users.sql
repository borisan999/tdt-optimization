-- Migration: Add role to users and uploaded_by to datasets
-- Date: 2026-03-07

-- Add role to users table if it doesn't exist
-- Note: MySQL 5.7+ doesn't support IF NOT EXISTS in ALTER TABLE directly,
-- but for script reproducibility we use the standard syntax.
ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'admin';

-- Ensure existing datasets have an uploaded_by reference
-- We use uploaded_by which already exists in the schema but was NULLable
ALTER TABLE datasets MODIFY COLUMN uploaded_by INT DEFAULT NULL;
