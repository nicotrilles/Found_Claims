-- Migration: optional reporters and non-student claimants
-- Target: school_lost_found (MySQL/MariaDB, InnoDB)
--
-- IMPORTANT:
-- 1. Export/backup the working database before running this migration.
-- 2. Test against a separate copy first.
-- 3. Run this file ONCE. It alters existing tables; it does not recreate them.
-- 4. This migration intentionally does not store ID numbers or ID images.

USE school_lost_found;

-- Allow reports to exist without a student record (anonymous/non-student reporter).
ALTER TABLE reports
    MODIFY student_id INT UNSIGNED NULL,
    ADD COLUMN reporter_type VARCHAR(20) NULL AFTER student_id,
    ADD COLUMN reporter_name VARCHAR(100) NULL AFTER reporter_type,
    ADD COLUMN reporter_contact VARCHAR(30) NULL AFTER reporter_name;

-- Existing reports were submitted through the student workflow.
UPDATE reports
SET reporter_type = 'Student'
WHERE reporter_type IS NULL;

-- Allow claims to exist without a student record (non-student claimant).
ALTER TABLE claims
    MODIFY student_id INT UNSIGNED NULL,
    ADD COLUMN claimant_type VARCHAR(20) NULL AFTER student_id,
    ADD COLUMN claimant_name VARCHAR(100) NULL AFTER claimant_type,
    ADD COLUMN claimant_contact VARCHAR(30) NULL AFTER claimant_name,
    ADD COLUMN id_type VARCHAR(50) NULL AFTER claimant_contact,
    ADD COLUMN id_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER id_type;

-- Preserve the existing claimant's details as a snapshot for staff review.
UPDATE claims
INNER JOIN students
    ON claims.student_id = students.student_id
SET
    claims.claimant_type = 'Student',
    claims.claimant_name = students.full_name,
    claims.claimant_contact = students.contact_no
WHERE claims.student_id IS NOT NULL;

-- Any legacy claim not matched above is still labeled Student only when
-- it has a linked student. New submissions must set claimant_type explicitly.
