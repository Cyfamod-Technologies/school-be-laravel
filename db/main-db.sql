-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 06, 2023 at 11:20 AM
-- Server version: 10.4.24-MariaDB
-- PHP Version: 7.4.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `main-db_sql`
--

-- SCHOOL TABLE (must come first)
CREATE TABLE schools (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    subdomain VARCHAR(255) NOT NULL UNIQUE,
    address TEXT NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    logo_url VARCHAR(512) DEFAULT NULL,
    established_at DATE DEFAULT NULL,
    owner_name VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- USER TABLE
CREATE TABLE users (
    id CHAR(36) NOT NULL PRIMARY KEY,
    school_id CHAR(36) NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('staff', 'parent', 'super_admin', 'accountant', 'admin') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    last_login TIMESTAMP NULL DEFAULT NULL,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    remember_token VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SESSION TABLE
CREATE TABLE sessions (
    id CHAR(36) NOT NULL PRIMARY KEY, -- UUID
    school_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'archived', 'upcoming') NOT NULL DEFAULT 'upcoming',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sessions_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY uk_sessions_school_slug (school_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- TERMS TABLE
CREATE TABLE terms (
    id CHAR(36) NOT NULL PRIMARY KEY, -- UUID
    school_id CHAR(36) NOT NULL,
    session_id CHAR(36) NOT NULL,
    name ENUM('1st', '2nd', '3rd') NOT NULL,
    slug VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'archived', 'upcoming') NOT NULL DEFAULT 'upcoming',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_terms_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_terms_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    UNIQUE KEY uk_terms_session_slug (session_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- CLASS TABLE
-- SCHOOL_CLASS TABLE
CREATE TABLE school_classes (
    id CHAR(36) NOT NULL PRIMARY KEY, -- UUID
    school_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_school_classes_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY uk_school_classes_school_slug (school_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- CLASS-ARMS TABLE
CREATE TABLE class_arms (
    id CHAR(36) NOT NULL PRIMARY KEY, -- UUID
    school_class_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_class_arms_school_class FOREIGN KEY (school_class_id) REFERENCES school_classes(id) ON DELETE CASCADE,
    UNIQUE KEY uk_class_arms_school_class_slug (school_class_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- CLASS-SECTIONS TABLE
CREATE TABLE class_sections (
    id CHAR(36) NOT NULL PRIMARY KEY, -- UUID
    class_arm_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_class_sections_class_arm FOREIGN KEY (class_arm_id) REFERENCES class_arms(id) ON DELETE CASCADE,
    UNIQUE KEY uk_class_sections_arm_slug (class_arm_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- PARENT TABLE
CREATE TABLE parents (
    id CHAR(36) NOT NULL PRIMARY KEY, -- UUID
    school_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    occupation VARCHAR(255) DEFAULT NULL,
    nationality VARCHAR(255) DEFAULT NULL,
    state_of_origin VARCHAR(255) DEFAULT NULL,
    local_government_area VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_parents_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_parents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_parents_school (school_id),
    INDEX idx_parents_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- STUDENT TABLE
CREATE TABLE students (
    id CHAR(36) NOT NULL PRIMARY KEY, -- UUID
    school_id CHAR(36) NOT NULL,
    admission_no VARCHAR(100) NOT NULL UNIQUE,
    first_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255) DEFAULT NULL,
    last_name VARCHAR(255) NOT NULL,
    gender ENUM('M', 'F', 'O') NOT NULL,
    date_of_birth DATE NOT NULL,
    nationality VARCHAR(255) DEFAULT NULL,
    state_of_origin VARCHAR(255) DEFAULT NULL,
    lga_of_origin VARCHAR(255) DEFAULT NULL,
    house VARCHAR(100) NOT NULL DEFAULT 'none',
    club VARCHAR(100) NOT NULL DEFAULT 'none',
    current_session_id CHAR(36) NOT NULL,
    current_term_id CHAR(36) NOT NULL,
    school_class_id CHAR(36) NOT NULL,
    class_arm_id CHAR(36) NOT NULL,
    class_section_id CHAR(36) DEFAULT NULL,
    parent_id CHAR(36) NOT NULL,
    admission_date DATE NOT NULL,
    photo_url VARCHAR(512) DEFAULT NULL,
    status ENUM('active', 'inactive', 'graduated', 'withdrawn') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_students_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_students_session FOREIGN KEY (current_session_id) REFERENCES sessions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_students_term FOREIGN KEY (current_term_id) REFERENCES terms(id) ON DELETE RESTRICT,
    CONSTRAINT fk_students_school_class FOREIGN KEY (school_class_id) REFERENCES school_classes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_students_class_arm FOREIGN KEY (class_arm_id) REFERENCES class_arms(id) ON DELETE RESTRICT,
    CONSTRAINT fk_students_class_section FOREIGN KEY (class_section_id) REFERENCES class_sections(id) ON DELETE SET NULL,
    CONSTRAINT fk_students_parent FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE RESTRICT,

    INDEX idx_students_school (school_id),
    INDEX idx_students_school_class (school_class_id),
    INDEX idx_students_class_arm (class_arm_id),
    INDEX idx_students_class_section (class_section_id),
    INDEX idx_students_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STAFF TABLE
CREATE TABLE staff (
    id CHAR(36) NOT NULL PRIMARY KEY, -- UUID
    school_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    staff_number VARCHAR(100) DEFAULT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255) DEFAULT NULL,
    gender ENUM('male', 'female', 'other') DEFAULT NULL,
    date_of_birth DATE DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    qualification VARCHAR(255) DEFAULT NULL,
    designation VARCHAR(255) DEFAULT NULL,
    employment_date DATE DEFAULT NULL,
    nationality VARCHAR(255) DEFAULT NULL,
    state_of_origin VARCHAR(255) DEFAULT NULL,
    local_government_area VARCHAR(255) DEFAULT NULL,
    profile_picture VARCHAR(512) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_staff_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY uk_staff_staff_number (staff_number),
    INDEX idx_staff_school (school_id),
    INDEX idx_staff_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- SUBJECT TABLE
CREATE TABLE subjects (
    id CHAR(36) NOT NULL PRIMARY KEY, -- UUID
    school_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_subjects_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY uk_subjects_school_name (school_id, name),
    INDEX idx_subjects_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- GRADING-SCALE TABLE
CREATE TABLE grading_scales (
    id CHAR(36) NOT NULL PRIMARY KEY, -- UUID
    school_id CHAR(36) NOT NULL,
    session_id CHAR(36) DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_gs_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_gs_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL,
    UNIQUE KEY uk_gs_school_name (school_id, name),
    INDEX idx_gs_school (school_id),
    INDEX idx_gs_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GRADE-RANGES TABLE
CREATE TABLE grade_ranges (
    id CHAR(36) NOT NULL PRIMARY KEY, -- UUID
    grading_scale_id CHAR(36) NOT NULL,
    min_score DECIMAL(5,2) NOT NULL,
    max_score DECIMAL(5,2) NOT NULL,
    grade_label VARCHAR(50) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    grade_point DECIMAL(4,2) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_grading_scale FOREIGN KEY (grading_scale_id) REFERENCES grading_scales(id) ON DELETE CASCADE,
    INDEX idx_grade_ranges_scale (grading_scale_id),
    UNIQUE KEY uk_grading_scale_label (grading_scale_id, grade_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- RESULT TABLE
CREATE TABLE results (
    id CHAR(36) NOT NULL PRIMARY KEY, -- UUID
    student_id CHAR(36) NOT NULL,
    subject_id CHAR(36) NOT NULL,
    term_id CHAR(36) NOT NULL,
    session_id CHAR(36) NOT NULL,
    total_score DECIMAL(5,2) NOT NULL,
    position_in_subject INT DEFAULT NULL,
    lowest_in_class DECIMAL(5,2) DEFAULT NULL,
    highest_in_class DECIMAL(5,2) DEFAULT NULL,
    class_average DECIMAL(5,2) DEFAULT NULL,
    grade_id CHAR(36) DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_results_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
    CONSTRAINT fk_results_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE RESTRICT,
    CONSTRAINT fk_results_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_results_grade FOREIGN KEY (grade_id) REFERENCES grade_ranges(id) ON DELETE SET NULL,
    INDEX idx_results_student (student_id),
    INDEX idx_results_subject (subject_id),
    INDEX idx_results_term (term_id),
    INDEX idx_results_session (session_id),
    INDEX idx_results_grade (grade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ASSESSMENT-COMPONENT TABLE
CREATE TABLE assessment_components (
    id CHAR(36) NOT NULL PRIMARY KEY, -- UUID
    school_id CHAR(36) NOT NULL,
    session_id CHAR(36) NOT NULL,
    term_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    weight DECIMAL(5,2) NOT NULL,
    `order` INT NOT NULL,
    label VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_ac_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_ac_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_ac_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    INDEX idx_ac_school (school_id),
    INDEX idx_ac_session (session_id),
    INDEX idx_ac_term (term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- 1. SkillCategory Table
CREATE TABLE skill_categories (
    id CHAR(36) NOT NULL PRIMARY KEY,                     -- UUID stored as CHAR(36)
    school_id CHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_skill_categories_school FOREIGN KEY (school_id) REFERENCES schools(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_skill_categories_school_id ON skill_categories(school_id);


-- 2. SkillType Table
CREATE TABLE skill_types (
    id CHAR(36) NOT NULL PRIMARY KEY,
    skill_category_id CHAR(36) NOT NULL,
    school_id CHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    weight DECIMAL(5,2) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_skill_types_category FOREIGN KEY (skill_category_id) REFERENCES skill_categories(id),
    CONSTRAINT fk_skill_types_school FOREIGN KEY (school_id) REFERENCES schools(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_skill_types_skill_category_id ON skill_types(skill_category_id);
CREATE INDEX idx_skill_types_school_id ON skill_types(school_id);


-- 3. SkillRating Table
CREATE TABLE skill_ratings (
    id CHAR(36) NOT NULL PRIMARY KEY,
    student_id CHAR(36) NOT NULL,
    term_id CHAR(36) NOT NULL,
    session_id CHAR(36) NOT NULL,
    skill_type_id CHAR(36) NOT NULL,
    rating_value TINYINT NOT NULL COMMENT 'Rating score e.g. 1-5',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_skill_ratings_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_skill_ratings_term FOREIGN KEY (term_id) REFERENCES terms(id),
    CONSTRAINT fk_skill_ratings_session FOREIGN KEY (session_id) REFERENCES sessions(id),
    CONSTRAINT fk_skill_ratings_skill_type FOREIGN KEY (skill_type_id) REFERENCES skill_types(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_skill_ratings_student ON skill_ratings(student_id);
CREATE INDEX idx_skill_ratings_term_session ON skill_ratings(term_id, session_id);


-- 4. term_summaries Table
CREATE TABLE term_summaries (
    id CHAR(36) NOT NULL PRIMARY KEY,
    student_id CHAR(36) NOT NULL,
    session_id CHAR(36) NOT NULL,
    term_id CHAR(36) NOT NULL,
    total_marks_obtained INT NOT NULL,
    total_marks_possible INT NOT NULL,
    average_score DECIMAL(5,2) NOT NULL,
    position_in_class INT NOT NULL,
    class_average_score DECIMAL(5,2) NOT NULL,
    days_present INT NULL,
    days_absent INT NULL,
    final_grade VARCHAR(10) NULL,
    overall_comment TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_term_summaries_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_term_summaries_session FOREIGN KEY (session_id) REFERENCES sessions(id),
    CONSTRAINT fk_term_summaries_term FOREIGN KEY (term_id) REFERENCES terms(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_term_summaries_student ON term_summaries(student_id);
CREATE INDEX idx_term_summaries_term_session ON term_summaries(term_id, session_id);


-- 5. attendances Table
CREATE TABLE attendances (
    id CHAR(36) NOT NULL PRIMARY KEY,
    student_id CHAR(36) NOT NULL,
    session_id CHAR(36) NOT NULL,
    term_id CHAR(36) NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_attendances_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_attendances_session FOREIGN KEY (session_id) REFERENCES sessions(id),
    CONSTRAINT fk_attendances_term FOREIGN KEY (term_id) REFERENCES terms(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_attendances_student_date ON attendances(student_id, date);
CREATE INDEX idx_attendances_term_session ON attendances(term_id, session_id);


-- 6. fee_payments Table
CREATE TABLE fee_payments (
    id CHAR(36) NOT NULL PRIMARY KEY,
    student_id CHAR(36) NOT NULL,
    session_id CHAR(36) NOT NULL,
    term_id CHAR(36) NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL,
    amount_due DECIMAL(12,2) NOT NULL,
    balance DECIMAL(12,2) AS (amount_due - amount_paid) VIRTUAL,
    status ENUM('paid', 'partial', 'unpaid') NOT NULL DEFAULT 'unpaid',
    payment_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_fee_payments_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_fee_payments_session FOREIGN KEY (session_id) REFERENCES sessions(id),
    CONSTRAINT fk_fee_payments_term FOREIGN KEY (term_id) REFERENCES terms(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_fee_payments_student ON fee_payments(student_id);
CREATE INDEX idx_fee_payments_term_session ON fee_payments(term_id, session_id);


-- 7. result_pins Table
CREATE TABLE result_pins (
    id CHAR(36) NOT NULL PRIMARY KEY,
    student_id CHAR(36) NOT NULL,
    pin_code VARCHAR(50) NOT NULL,
    status ENUM('unused', 'used', 'expired') NOT NULL DEFAULT 'unused',
    expiry_date DATE NULL,
    use_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_result_pins_student FOREIGN KEY (student_id) REFERENCES students(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_result_pins_student ON result_pins(student_id);
CREATE INDEX idx_result_pins_status ON result_pins(status);


-- 8. messages Table
CREATE TABLE messages (
    id CHAR(36) NOT NULL PRIMARY KEY,
    thread_id CHAR(36) NOT NULL,
    sender_id CHAR(36) NOT NULL,
    receiver_id CHAR(36) NOT NULL,
    sender_role ENUM('parent', 'teacher', 'admin') NOT NULL,
    receiver_role ENUM('parent', 'teacher', 'admin') NOT NULL,
    message_body TEXT NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id),
    CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_messages_thread ON messages(thread_id);
CREATE INDEX idx_messages_sender_receiver ON messages(sender_id, receiver_id);
CREATE INDEX idx_messages_is_read ON messages(is_read);

-- Linking tables starts here

-- student_enrollments Table
CREATE TABLE student_enrollments (
    id CHAR(36) NOT NULL PRIMARY KEY,
    student_id CHAR(36) NOT NULL,
    class_section_id CHAR(36) NOT NULL,
    session_id CHAR(36) NOT NULL,
    term_id CHAR(36) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_student_enrollments_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_student_enrollments_class_section FOREIGN KEY (class_section_id) REFERENCES class_sections(id),
    CONSTRAINT fk_student_enrollments_session FOREIGN KEY (session_id) REFERENCES sessions(id),
    CONSTRAINT fk_student_enrollments_term FOREIGN KEY (term_id) REFERENCES terms(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_student_enrollments_student ON student_enrollments(student_id);
CREATE INDEX idx_student_enrollments_class_section ON student_enrollments(class_section_id);
CREATE INDEX idx_student_enrollments_term_session ON student_enrollments(term_id, session_id);


-- subject_class_assignments Table
-- subject_school_class_assignments Table
CREATE TABLE subject_school_class_assignments (
    id CHAR(36) NOT NULL PRIMARY KEY,
    subject_id CHAR(36) NOT NULL,
    school_class_id CHAR(36) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_subject_school_class_assignments_subject FOREIGN KEY (subject_id) REFERENCES subjects(id),
    CONSTRAINT fk_subject_school_class_assignments_school_class FOREIGN KEY (school_class_id) REFERENCES school_classes(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_subject_school_class_assignments_subject ON subject_school_class_assignments(subject_id);
CREATE INDEX idx_subject_school_class_assignments_school_class ON subject_school_class_assignments(school_class_id);


-- subject_teacher_assignments Table
CREATE TABLE subject_teacher_assignments (
    id CHAR(36) NOT NULL PRIMARY KEY,
    subject_id CHAR(36) NOT NULL,
    staff_id CHAR(36) NOT NULL,
    class_section_id CHAR(36) NOT NULL,
    session_id CHAR(36) NOT NULL,
    term_id CHAR(36) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_subject_teacher_assignments_subject FOREIGN KEY (subject_id) REFERENCES subjects(id),
    CONSTRAINT fk_subject_teacher_assignments_staff FOREIGN KEY (staff_id) REFERENCES staff(id),
    CONSTRAINT fk_subject_teacher_assignments_class_section FOREIGN KEY (class_section_id) REFERENCES class_sections(id),
    CONSTRAINT fk_subject_teacher_assignments_session FOREIGN KEY (session_id) REFERENCES sessions(id),
    CONSTRAINT fk_subject_teacher_assignments_term FOREIGN KEY (term_id) REFERENCES terms(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_subject_teacher_assignments_staff ON subject_teacher_assignments(staff_id);
CREATE INDEX idx_subject_teacher_assignments_class_section ON subject_teacher_assignments(class_section_id);
CREATE INDEX idx_subject_teacher_assignments_term_session ON subject_teacher_assignments(term_id, session_id);


-- class_teachers Table
CREATE TABLE class_teachers (
    id CHAR(36) NOT NULL PRIMARY KEY,
    staff_id CHAR(36) NOT NULL,
    class_section_id CHAR(36) NOT NULL,
    session_id CHAR(36) NOT NULL,
    term_id CHAR(36) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_class_teachers_staff FOREIGN KEY (staff_id) REFERENCES staff(id),
    CONSTRAINT fk_class_teachers_class_section FOREIGN KEY (class_section_id) REFERENCES class_sections(id),
    CONSTRAINT fk_class_teachers_session FOREIGN KEY (session_id) REFERENCES sessions(id),
    CONSTRAINT fk_class_teachers_term FOREIGN KEY (term_id) REFERENCES terms(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_class_teachers_staff ON class_teachers(staff_id);
CREATE INDEX idx_class_teachers_class_section ON class_teachers(class_section_id);
CREATE INDEX idx_class_teachers_term_session ON class_teachers(term_id, session_id);


-- message_threads Table
CREATE TABLE message_threads (
    id CHAR(36) NOT NULL PRIMARY KEY,
    sender_id CHAR(36) NOT NULL,
    receiver_id CHAR(36) NOT NULL,
    sender_role ENUM('parent', 'staff') NOT NULL,
    receiver_role ENUM('parent', 'staff') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_message_threads_sender FOREIGN KEY (sender_id) REFERENCES users(id),
    CONSTRAINT fk_message_threads_receiver FOREIGN KEY (receiver_id) REFERENCES users(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_message_threads_sender_receiver ON message_threads(sender_id, receiver_id);
CREATE INDEX idx_message_threads_roles ON message_threads(sender_role, receiver_role);


-- school_user_assignments Table
CREATE TABLE school_user_assignments (
    id CHAR(36) NOT NULL PRIMARY KEY,
    school_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_school_user_assignments_school FOREIGN KEY (school_id) REFERENCES schools(id),
    CONSTRAINT fk_school_user_assignments_user FOREIGN KEY (user_id) REFERENCES users(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_school_user_assignments_school ON school_user_assignments(school_id);
CREATE INDEX idx_school_user_assignments_user ON school_user_assignments(user_id);


-- school_skill_types Table
CREATE TABLE school_skill_types (
    id CHAR(36) NOT NULL PRIMARY KEY,
    school_id CHAR(36) NOT NULL,
    skill_category_id CHAR(36) NOT NULL,
    skill_type_id CHAR(36) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_school_skill_types_school FOREIGN KEY (school_id) REFERENCES schools(id),
    CONSTRAINT fk_school_skill_types_skill_category FOREIGN KEY (skill_category_id) REFERENCES skill_categories(id),
    CONSTRAINT fk_school_skill_types_skill_type FOREIGN KEY (skill_type_id) REFERENCES skill_types(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_school_skill_types_school ON school_skill_types(school_id);
CREATE INDEX idx_school_skill_types_skill_category ON school_skill_types(skill_category_id);
CREATE INDEX idx_school_skill_types_skill_type ON school_skill_types(skill_type_id);


-- mysql://root:ipala@2025@199.192.27.235:3306/sas





-- laravel default


CREATE TABLE personal_access_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id CHAR(36) NOT NULL,
    name TEXT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT DEFAULT NULL,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_tokenable (tokenable_type, tokenable_id)
);
CREATE TABLE cache (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    `value` MEDIUMTEXT NOT NULL,
    `expiration` INT NOT NULL
);
CREATE TABLE cache_locks (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    `owner` VARCHAR(255) NOT NULL,
    `expiration` INT NOT NULL
);
CREATE TABLE jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL,
    reserved_at INT UNSIGNED DEFAULT NULL,
    available_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    INDEX jobs_queue_index (queue)
);
CREATE TABLE job_batches (
    id VARCHAR(255) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    total_jobs INT NOT NULL,
    pending_jobs INT NOT NULL,
    failed_jobs INT NOT NULL,
    failed_job_ids LONGTEXT NOT NULL,
    options MEDIUMTEXT DEFAULT NULL,
    cancelled_at INT DEFAULT NULL,
    created_at INT NOT NULL,
    finished_at INT DEFAULT NULL
);
CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);



-- upgrade







-- RBAC Tables
CREATE TABLE IF NOT EXISTS `roles` (
  `id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_has_permissions` (
  `role_id` char(36) NOT NULL,
  `permission_id` char(36) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `role_has_permissions_permission_id_foreign` (`permission_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Analytics & Reports
CREATE TABLE IF NOT EXISTS `analytics_data` (
  `id` char(36) NOT NULL,
  `school_id` char(36) NOT NULL,
  `school_class_id` char(36) NOT NULL,
  `subject_id` char(36) NOT NULL,
  `average_score` float NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `analytics_data_school_id_foreign` (`school_id`),
  KEY `analytics_data_school_class_id_foreign` (`school_class_id`),
  KEY `analytics_data_subject_id_foreign` (`subject_id`),
  CONSTRAINT `analytics_data_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `analytics_data_school_class_id_foreign` FOREIGN KEY (`school_class_id`) REFERENCES `school_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `analytics_data_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `performance_reports` (
  `id` char(36) NOT NULL,
  `student_id` char(36) NOT NULL,
  `report_data` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `performance_reports_student_id_foreign` (`student_id`),
  CONSTRAINT `performance_reports_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student Status Management
-- ALTER TABLE `students` ADD `status` varchar(255) NOT NULL DEFAULT 'active';

-- External Integrations
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `key` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security & Auditing
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
