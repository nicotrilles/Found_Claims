-- School Lost and Found System
-- Reconstructed schema for a fresh database.
-- WARNING: Creates an empty database structure; it does not restore old records.
-- Review against your working database before running.

CREATE DATABASE IF NOT EXISTS school_lost_found
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE school_lost_found;

CREATE TABLE students (
    student_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_no VARCHAR(6) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    contact_no VARCHAR(30) NOT NULL,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_students_student_no (student_no)
) ENGINE=InnoDB;

CREATE TABLE staff (
    staff_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    contact_no VARCHAR(30) NOT NULL,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    PRIMARY KEY (staff_id),
    UNIQUE KEY uq_staff_username (username)
) ENGINE=InnoDB;

CREATE TABLE categories (
    category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_categories_name (category_name)
) ENGINE=InnoDB;

CREATE TABLE reports (
    report_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    report_date DATE NOT NULL,
    remarks TEXT NULL,
    PRIMARY KEY (report_id),
    KEY idx_reports_student (student_id),
    KEY idx_reports_staff (staff_id),
    CONSTRAINT fk_reports_student FOREIGN KEY (student_id) REFERENCES students(student_id),
    CONSTRAINT fk_reports_staff FOREIGN KEY (staff_id) REFERENCES staff(staff_id)
) ENGINE=InnoDB;

CREATE TABLE items (
    item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id INT UNSIGNED NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    description TEXT NOT NULL,
    color VARCHAR(50) NOT NULL,
    brand VARCHAR(100) NOT NULL,
    location_found VARCHAR(255) NOT NULL,
    item_status VARCHAR(20) NOT NULL DEFAULT 'Found',
    image_path VARCHAR(255) NULL,
    PRIMARY KEY (item_id),
    KEY idx_items_report (report_id),
    KEY idx_items_category (category_id),
    KEY idx_items_status (item_status),
    CONSTRAINT fk_items_report FOREIGN KEY (report_id) REFERENCES reports(report_id),
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(category_id)
) ENGINE=InnoDB;

CREATE TABLE claims (
    claim_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    claim_date DATE NOT NULL,
    claim_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    verification_details TEXT NOT NULL,
    remarks TEXT NULL,
    decision_staff_id INT UNSIGNED NULL,
    PRIMARY KEY (claim_id),
    KEY idx_claims_item (item_id),
    KEY idx_claims_student (student_id),
    KEY idx_claims_staff (staff_id),
    KEY idx_claims_decision_staff (decision_staff_id),
    KEY idx_claims_status (claim_status),
    CONSTRAINT fk_claims_item FOREIGN KEY (item_id) REFERENCES items(item_id),
    CONSTRAINT fk_claims_student FOREIGN KEY (student_id) REFERENCES students(student_id),
    CONSTRAINT fk_claims_staff FOREIGN KEY (staff_id) REFERENCES staff(staff_id),
    CONSTRAINT fk_claims_decision_staff FOREIGN KEY (decision_staff_id) REFERENCES staff(staff_id)
) ENGINE=InnoDB;

CREATE TABLE item_comments (
    comment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id INT UNSIGNED NOT NULL,
    student_name VARCHAR(100) NOT NULL,
    comment_text TEXT NOT NULL,
    comment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    comment_status VARCHAR(20) NOT NULL DEFAULT 'New',
    PRIMARY KEY (comment_id),
    KEY idx_comments_item (item_id),
    KEY idx_comments_status (comment_status),
    CONSTRAINT fk_comments_item FOREIGN KEY (item_id) REFERENCES items(item_id)
) ENGINE=InnoDB;


-- the categories table is hardcoded with the following values, which can be modified later if needed:
INSERT INTO categories (category_name)
VALUES
    ('Electronics'),
    ('School Supplies'),
    ('Clothing'),
    ('Personal Items'),
    ('Other');
