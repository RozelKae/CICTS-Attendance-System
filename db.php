-- ============================================
-- CITCS ATTENDANCE MONITORING SYSTEM (RFID VERSION)
-- Database Schema with Sample Data
-- ============================================

DROP DATABASE IF EXISTS citcs_attendance;
CREATE DATABASE citcs_attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE citcs_attendance;

-- ============================================
-- TABLE: users
-- ============================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    user_type ENUM('student', 'professor', 'secretary') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_type),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================
-- TABLE: students (UPDATED WITH RFID COLUMNS)
-- ============================================
CREATE TABLE students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    student_number VARCHAR(20) UNIQUE NOT NULL,
    rfid_uid VARCHAR(50) UNIQUE NULL COMMENT 'RFID card unique identifier',
    rfid_registered_at TIMESTAMP NULL COMMENT 'When RFID was registered',
    rfid_registered_by INT NULL COMMENT 'Who registered the RFID (user_id)',
    rfid_status ENUM('active', 'deactivated') DEFAULT 'active' COMMENT 'Card status',
    rfid_deactivated_at TIMESTAMP NULL COMMENT 'When card was deactivated',
    rfid_deactivation_reason VARCHAR(255) NULL COMMENT 'Why card was deactivated',
    year_level TINYINT NOT NULL,
    program VARCHAR(50) NOT NULL,
    section VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (rfid_registered_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_student_number (student_number),
    INDEX idx_rfid_uid (rfid_uid),
    INDEX idx_program_section (program, section)
) ENGINE=InnoDB;

-- ============================================
-- TABLE: professors
-- ============================================
CREATE TABLE professors (
    professor_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    employee_number VARCHAR(20) UNIQUE NOT NULL,
    department VARCHAR(50) DEFAULT 'CITCS',
    position VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_employee_number (employee_number)
) ENGINE=InnoDB;

-- ============================================
-- TABLE: courses
-- ============================================
CREATE TABLE courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    course_description TEXT,
    units DECIMAL(2,1) NOT NULL,
    INDEX idx_course_code (course_code)
) ENGINE=InnoDB;

-- ============================================
-- TABLE: classes
-- ============================================
CREATE TABLE classes (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    professor_id INT NOT NULL,
    section VARCHAR(20) NOT NULL,
    school_year VARCHAR(9) NOT NULL,
    semester ENUM('1', '2', 'Summer') NOT NULL,
    room VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES professors(professor_id) ON DELETE CASCADE,
    INDEX idx_school_year_semester (school_year, semester),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================
-- TABLE: class_schedules
-- ============================================
CREATE TABLE class_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
    UNIQUE KEY unique_schedule (class_id, day_of_week, start_time),
    INDEX idx_day_time (day_of_week, start_time)
) ENGINE=InnoDB;

-- ============================================
-- TABLE: class_enrollments
-- ============================================
CREATE TABLE class_enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    enrollment_date DATE DEFAULT (CURRENT_DATE),
    status ENUM('enrolled', 'dropped') DEFAULT 'enrolled',
    dropped_date DATE NULL,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (class_id, student_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================
-- TABLE: attendance_sessions (REMOVED QR CODE COLUMN)
-- ============================================
CREATE TABLE attendance_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    schedule_id INT NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('scheduled', 'open', 'closed', 'cancelled') DEFAULT 'scheduled',
    opened_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    cancelled_by INT NULL,
    cancelled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES class_schedules(schedule_id) ON DELETE CASCADE,
    FOREIGN KEY (cancelled_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_session (class_id, session_date, start_time),
    INDEX idx_session_date (session_date),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================
-- TABLE: attendance_records
-- ============================================
CREATE TABLE attendance_records (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    time_in TIMESTAMP NULL,
    status ENUM('present', 'late', 'absent', 'excused') NOT NULL,
    is_manual BOOLEAN DEFAULT FALSE,
    recorded_by INT NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES attendance_sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (session_id, student_id),
    INDEX idx_status (status),
    INDEX idx_student_session (student_id, session_id)
) ENGINE=InnoDB;

-- ============================================
-- TABLE: excuse_letters
-- ============================================
CREATE TABLE excuse_letters (
    excuse_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    excuse_type ENUM('Medical', 'Emergency', 'School Business', 'Personal', 'Other') NOT NULL,
    description TEXT NOT NULL,
    submitted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approval_date TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_student_status (student_id, status),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================
-- TABLE: excuse_dates
-- ============================================
CREATE TABLE excuse_dates (
    excuse_date_id INT AUTO_INCREMENT PRIMARY KEY,
    excuse_id INT NOT NULL,
    attendance_id INT NOT NULL,
    FOREIGN KEY (excuse_id) REFERENCES excuse_letters(excuse_id) ON DELETE CASCADE,
    FOREIGN KEY (attendance_id) REFERENCES attendance_records(attendance_id) ON DELETE CASCADE,
    UNIQUE KEY unique_excuse_attendance (excuse_id, attendance_id)
) ENGINE=InnoDB;

-- ============================================
-- TABLE: excuse_attachments
-- ============================================
CREATE TABLE excuse_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    excuse_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (excuse_id) REFERENCES excuse_letters(excuse_id) ON DELETE CASCADE,
    INDEX idx_excuse_id (excuse_id)
) ENGINE=InnoDB;

-- ============================================
-- TABLE: audit_logs
-- ============================================
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('create', 'update', 'delete') NOT NULL,
    table_affected VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_action (user_id, action_type),
    INDEX idx_timestamp (timestamp),
    INDEX idx_table_record (table_affected, record_id)
) ENGINE=InnoDB;

-- ============================================
-- SAMPLE DATA
-- ============================================

-- Insert Users (password: password123 - hashed with bcrypt)
INSERT INTO users (username, password, email, first_name, last_name, user_type) VALUES
('secretary1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'secretary@citcs.edu', 'Maria', 'Santos', 'secretary'),
('prof_cruz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'jcruz@citcs.edu', 'Juan', 'Cruz', 'professor'),
('prof_reyes', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'areyes@citcs.edu', 'Ana', 'Reyes', 'professor'),
('2021-001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'jdelacruz@student.citcs.edu', 'Jose', 'Dela Cruz', 'student'),
('2021-002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mgonzales@student.citcs.edu', 'Maria', 'Gonzales', 'student'),
('2021-003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pvillar@student.citcs.edu', 'Pedro', 'Villar', 'student'),
('2021-004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'lramos@student.citcs.edu', 'Lucia', 'Ramos', 'student'),
('2022-001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rmendoza@student.citcs.edu', 'Ricardo', 'Mendoza', 'student'),
('2022-002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cflores@student.citcs.edu', 'Carmen', 'Flores', 'student');

-- Insert Professors
INSERT INTO professors (user_id, employee_number, department, position) VALUES
(2, 'PROF-2015-001', 'CITCS', 'Associate Professor'),
(3, 'PROF-2018-002', 'CITCS', 'Assistant Professor');

-- Insert Students with RFID UIDs (some registered, some not)
INSERT INTO students (user_id, student_number, rfid_uid, rfid_registered_at, rfid_registered_by, rfid_status, year_level, program, section) VALUES
(4, '2021-001', 'RFID-TEST-001-A1B2C3D4', '2024-12-01 10:00:00', 4, 'active', 3, 'BSIT', '3A'),
(5, '2021-002', 'RFID-TEST-002-E5F6G7H8', '2024-12-01 10:05:00', 5, 'active', 3, 'BSIT', '3A'),
(6, '2021-003', 'RFID-TEST-003-I9J0K1L2', '2024-12-01 10:10:00', 6, 'active', 3, 'BSIT', '3A'),
(7, '2021-004', NULL, NULL, NULL, 'active', 3, 'BSCS', '3B'),
(8, '2022-001', 'RFID-TEST-005-M3N4O5P6', '2024-12-02 09:00:00', 8, 'active', 2, 'BSIT', '2A'),
(9, '2022-002', NULL, NULL, NULL, 'active', 2, 'BSCS', '2A');

-- Insert Courses
INSERT INTO courses (course_code, course_name, course_description, units) VALUES
('IT 311', 'Database Management Systems', 'Advanced concepts in database design and management', 3.0),
('IT 312', 'Web Development 2', 'Full-stack web development using modern frameworks', 3.0),
('CS 301', 'Data Structures and Algorithms', 'Advanced data structures and algorithm analysis', 3.0),
('IT 211', 'Object-Oriented Programming', 'OOP concepts using Java and C++', 3.0);

-- Insert Classes
INSERT INTO classes (course_id, professor_id, section, school_year, semester, room, status) VALUES
(1, 1, 'BSIT-3A', '2024-2025', '1', 'LAB-301', 'active'),
(2, 2, 'BSIT-3A', '2024-2025', '1', 'LAB-302', 'active'),
(3, 1, 'BSCS-3B', '2024-2025', '1', 'ROOM-401', 'active'),
(4, 2, 'BSIT-2A', '2024-2025', '1', 'LAB-201', 'active');

-- Insert Class Schedules
INSERT INTO class_schedules (class_id, day_of_week, start_time, end_time) VALUES
(1, 'Monday', '08:00:00', '10:00:00'),
(1, 'Wednesday', '08:00:00', '10:00:00'),
(2, 'Tuesday', '10:00:00', '12:00:00'),
(2, 'Thursday', '10:00:00', '12:00:00'),
(3, 'Monday', '13:00:00', '15:00:00'),
(3, 'Friday', '13:00:00', '15:00:00'),
(4, 'Wednesday', '13:00:00', '15:00:00'),
(4, 'Friday', '13:00:00', '15:00:00');

-- Insert Enrollments
INSERT INTO class_enrollments (class_id, student_id, status) VALUES
(1, 1, 'enrolled'),
(1, 2, 'enrolled'),
(1, 3, 'enrolled'),
(2, 1, 'enrolled'),
(2, 2, 'enrolled'),
(2, 3, 'enrolled'),
(3, 4, 'enrolled'),
(4, 5, 'enrolled'),
(4, 6, 'enrolled');

-- Insert Sample Attendance Sessions (for testing - NO QR CODES)
INSERT INTO attendance_sessions (class_id, schedule_id, session_date, start_time, end_time, status) VALUES
(1, 1, '2024-12-09', '08:00:00', '10:00:00', 'closed'),
(1, 2, '2024-12-11', '08:00:00', '10:00:00', 'closed'),
(2, 3, '2024-12-10', '10:00:00', '12:00:00', 'closed');

-- Insert Sample Attendance Records
INSERT INTO attendance_records (session_id, student_id, time_in, status, is_manual, recorded_by) VALUES
(1, 1, '2024-12-09 08:05:00', 'present', FALSE, NULL),
(1, 2, '2024-12-09 08:18:00', 'late', FALSE, NULL),
(1, 3, NULL, 'absent', FALSE, NULL),
(2, 1, '2024-12-11 08:03:00', 'present', FALSE, NULL),
(2, 2, '2024-12-11 08:07:00', 'present', FALSE, NULL),
(2, 3, '2024-12-11 08:25:00', 'late', FALSE, NULL),
(3, 1, '2024-12-10 10:02:00', 'present', FALSE, NULL),
(3, 2, NULL, 'absent', FALSE, NULL),
(3, 3, '2024-12-10 10:05:00', 'present', FALSE, NULL);

-- Insert Sample Excuse Letter
INSERT INTO excuse_letters (student_id, excuse_type, description, status) VALUES
(3, 'Medical', 'Had a fever and needed to rest as advised by doctor', 'pending');

-- Insert Excuse Dates (linking to attendance record)
INSERT INTO excuse_dates (excuse_id, attendance_id) VALUES
(1, 3);

-- ============================================
-- USEFUL QUERIES FOR TESTING
-- ============================================

-- View students with RFID status
-- SELECT s.student_number, u.first_name, u.last_name, s.rfid_uid, s.rfid_status, s.rfid_registered_at
-- FROM students s JOIN users u ON s.user_id = u.user_id;

-- View students without RFID cards
-- SELECT s.student_number, u.first_name, u.last_name
-- FROM students s JOIN users u ON s.user_id = u.user_id
-- WHERE s.rfid_uid IS NULL;

-- ============================================
-- END OF SCHEMA
-- ============================================