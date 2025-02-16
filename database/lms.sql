-- Create database
CREATE DATABASE IF NOT EXISTS stma_lms;
USE stma_lms;

-- Users table (for all user types)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    user_type ENUM('admin', 'teacher', 'student'),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Students table
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    lrn VARCHAR(12) UNIQUE,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    grade_level INT,
    section VARCHAR(20),
    gender ENUM('male', 'female'),
    birth_date DATE,
    contact_number VARCHAR(20),
    address TEXT,
    guardian_name VARCHAR(100),
    guardian_contact VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Teachers table
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    employee_id VARCHAR(20) UNIQUE,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    email VARCHAR(100),
    contact_number VARCHAR(20),
    specialization VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Subjects table
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_code VARCHAR(20) UNIQUE,
    subject_name VARCHAR(100),
    description TEXT,
    grade_level INT,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Classes table
CREATE TABLE classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_id INT,
    teacher_id INT,
    grade_level INT,
    section VARCHAR(20),
    school_year VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);

-- Class enrollments table
CREATE TABLE class_enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT,
    student_id INT,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'dropped') DEFAULT 'active',
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Grades table
CREATE TABLE grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT,
    student_id INT,
    quarter INT,
    grade DECIMAL(5,2),
    remarks TEXT,
    date_submitted TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Insert default admin account
INSERT INTO users (username, password, user_type) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Default password: password 

-- Admin Account
INSERT INTO users (username, password, user_type) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Username: admin
-- Password: password

-- Sample Teacher Account
INSERT INTO users (username, password, user_type) 
VALUES ('teacher1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher');

INSERT INTO teachers (user_id, employee_id, first_name, last_name, email, contact_number, specialization) 
VALUES (
    (SELECT id FROM users WHERE username = 'teacher1'),
    'TCH-2024-001',
    'John',
    'Doe',
    'john.doe@stma.edu.ph',
    '09123456789',
    'Mathematics'
);
-- Username: teacher1
-- Password: password

-- Sample Student Account
INSERT INTO users (username, password, user_type) 
VALUES ('123456789012', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

INSERT INTO students (
    user_id, 
    lrn, 
    first_name, 
    last_name, 
    grade_level, 
    section, 
    gender, 
    birth_date,
    contact_number,
    address,
    guardian_name,
    guardian_contact
) 
VALUES (
    (SELECT id FROM users WHERE username = '123456789012'),
    '123456789012',
    'Jane',
    'Smith',
    7,
    'A',
    'female',
    '2010-05-15',
    '09987654321',
    'Bacoor, Cavite',
    'Mary Smith',
    '09123456789'
);
-- LRN: 123456789012
-- Password: password

-- Add some sample subjects
INSERT INTO subjects (subject_code, subject_name, description, grade_level) VALUES
('MATH7', 'Mathematics 7', 'Grade 7 Mathematics', 7),
('SCI7', 'Science 7', 'Grade 7 Science', 7),
('ENG7', 'English 7', 'Grade 7 English', 7);

-- Add sample class
INSERT INTO classes (subject_id, teacher_id, grade_level, section, school_year) VALUES
(
    (SELECT id FROM subjects WHERE subject_code = 'MATH7'),
    (SELECT id FROM teachers WHERE employee_id = 'TCH-2024-001'),
    7,
    'A',
    '2024-2025'
);

-- Enroll sample student in class
INSERT INTO class_enrollments (class_id, student_id) VALUES
(
    1,
    (SELECT id FROM students WHERE lrn = '123456789012')
);