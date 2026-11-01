CREATE DATABASE IF NOT EXISTS self_service_db;
USE self_service_db;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  id_number VARCHAR(20) NULL,
  faculty VARCHAR(150) NULL,
  programme VARCHAR(150) NULL,
  date_of_birth DATE NULL,
  image_url VARCHAR(500) NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed accounts (change these passwords in any real deployment):
--   admin@example.com   / admin123    (admin)
--   student@example.com / student123  (student with results already entered)
-- There is no public registration: an admin creates every account.
INSERT INTO users (name, email, password_hash, id_number, faculty, programme, date_of_birth, is_admin) VALUES
('Admin', 'admin@example.com', '$2y$10$HI3gLmyD4OGmfNLAGUIL8.eBhhKu5nzL7wTDws.6mUNO9V44kyM5q', NULL, NULL, NULL, NULL, 1),
('Demo Student', 'student@example.com', '$2y$10$3kkHog6vhyue9Syxkx8ohe8Q6.wBphgtVxgwRB7cLPzLWBtjrC8Kq', '2409123', 'Faculty of Computing and Information Technology', 'Diploma in Information Technology', '2005-03-14', 0);

CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  credit_hours TINYINT NOT NULL DEFAULT 3
);

INSERT INTO courses (code, name, credit_hours) VALUES
('AACS1173', 'Programming Concepts and Design', 4),
('AMIT1013', 'Introduction to Information Technology', 3),
('AACS2203', 'Database Management', 3),
('AMIT2123', 'Web Design and Development', 3),
('AACS2304', 'Computer Networks', 4),
('AMIT3253', 'Cloud Computing for Business', 3);

-- A year has two long semesters (14 weeks) and one short semester (7 weeks).
-- start_date is the Monday of week 1.
CREATE TABLE semesters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code CHAR(6) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  start_date DATE NOT NULL,
  weeks TINYINT NOT NULL
);

INSERT INTO semesters (code, name, start_date, weeks) VALUES
('202601', 'Long Semester', '2026-01-19', 14),
('202605', 'Short Semester', '2026-05-25', 7),
('202609', 'Long Semester', '2026-09-21', 14);

CREATE TABLE results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  course_id INT NOT NULL,
  semester_id INT NOT NULL,
  grade VARCHAR(2) NOT NULL,
  grade_point DECIMAL(3,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_result (user_id, course_id, semester_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (course_id) REFERENCES courses(id),
  FOREIGN KEY (semester_id) REFERENCES semesters(id)
);

INSERT INTO results (user_id, course_id, semester_id, grade, grade_point) VALUES
(2, 1, 1, 'A-', 3.67),
(2, 2, 1, 'A', 4.00),
(2, 3, 1, 'B+', 3.33),
(2, 4, 2, 'A', 4.00),
(2, 5, 2, 'B', 3.00);

CREATE TABLE document_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(500) NOT NULL,
  fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  processing_days TINYINT NOT NULL DEFAULT 3,
  max_copies TINYINT NOT NULL DEFAULT 3,
  image_url VARCHAR(500) NULL
);

INSERT INTO document_types (name, description, fee, processing_days, max_copies, image_url) VALUES
('Academic Transcript', 'Official transcript of all results to date, certified by the Registry.', 10.00, 5, 5, '/uploads/sample-transcript.jpg'),
('Confirmation of Study Letter', 'Confirms you are a registered student, e.g. for visa, bank or EPF applications.', 5.00, 3, 3, '/uploads/sample-confirmation-letter.jpg'),
('Attendance Letter', 'States your attendance percentage for the current semester, e.g. for scholarship sponsors.', 5.00, 3, 3, '/uploads/sample-attendance-letter.jpg'),
('Fee Statement', 'Itemised statement of fees charged and paid for the current academic year.', 0.00, 2, 2, '/uploads/sample-fee-statement.jpg');

CREATE TABLE document_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  document_type_id INT NOT NULL,
  copies TINYINT NOT NULL DEFAULT 1,
  purpose VARCHAR(255) NOT NULL,
  delivery_method VARCHAR(50) NOT NULL,
  total_fee DECIMAL(10,2) NOT NULL,
  status ENUM('Pending', 'Processing', 'Ready', 'Collected', 'Rejected') NOT NULL DEFAULT 'Pending',
  admin_note VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (document_type_id) REFERENCES document_types(id)
);

INSERT INTO document_requests (user_id, document_type_id, copies, purpose, delivery_method, total_fee, status, admin_note) VALUES
(2, 2, 1, 'Bank account opening', 'Email (Soft Copy)', 5.00, 'Ready', 'Sent to your student email.'),
(2, 1, 2, 'Internship application', 'Collect at Registry Counter', 20.00, 'Processing', NULL);

CREATE TABLE feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  topic VARCHAR(100) NOT NULL,
  comment TEXT NOT NULL,
  rating TINYINT NOT NULL DEFAULT 5,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE contact_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  subject VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- PHP sessions are stored here instead of on local disk, so that any EC2
-- instance behind an ALB/ASG can read a session written by a different
-- instance. See auth.php's DbSessionHandler.
CREATE TABLE sessions (
  id VARCHAR(128) PRIMARY KEY,
  data MEDIUMTEXT NOT NULL,
  last_activity INT NOT NULL,
  INDEX idx_last_activity (last_activity)
);
