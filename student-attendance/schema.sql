CREATE DATABASE IF NOT EXISTS attendance_db;
USE attendance_db;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  id_number VARCHAR(20) NULL,
  faculty VARCHAR(150) NULL,
  date_of_birth DATE NULL,
  role ENUM('student', 'lecturer') NOT NULL DEFAULT 'student',
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed accounts (change these passwords in any real deployment):
--   admin@example.com    / admin123     (admin)
--   lecturer@example.com / lecturer123  (lecturer - teaches the seeded courses)
--   student@example.com  / student123   (student - enrolled in two courses)
-- Students register themselves; lecturer accounts are created by an admin.
INSERT INTO users (name, email, password_hash, id_number, faculty, date_of_birth, role, is_admin) VALUES
('Admin', 'admin@example.com', '$2y$10$HI3gLmyD4OGmfNLAGUIL8.eBhhKu5nzL7wTDws.6mUNO9V44kyM5q', NULL, NULL, NULL, 'student', 1),
('Dr. Demo Lecturer', 'lecturer@example.com', '$2y$10$fpapO3ZtveFhM52pbu9WneC3iXWBUo/hTiJAO4x0DOfcknwdrgGG2', 'S1001', 'Faculty of Computing and Information Technology', '1985-06-20', 'lecturer', 0),
('Demo Student', 'student@example.com', '$2y$10$3kkHog6vhyue9Syxkx8ohe8Q6.wBphgtVxgwRB7cLPzLWBtjrC8Kq', '2409123', 'Faculty of Computing and Information Technology', '2005-03-14', 'student', 0);

CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  lecturer_id INT NULL,
  image_url VARCHAR(500) NULL,
  FOREIGN KEY (lecturer_id) REFERENCES users(id)
);

INSERT INTO courses (code, name, lecturer_id, image_url) VALUES
('AMIT3253', 'Cloud Computing for Business', 2, '/uploads/sample-cloud-computing.jpg'),
('AACS2304', 'Computer Networks', 2, '/uploads/sample-networks.jpg'),
('AACS2063', 'Data Structures and Algorithms', 2, '/uploads/sample-algorithms.png'),
('AACS3064', 'Software Engineering', NULL, '/uploads/sample-software-engineering.jpg');

CREATE TABLE enrolments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  course_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_enrolment (user_id, course_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (course_id) REFERENCES courses(id)
);

INSERT INTO enrolments (user_id, course_id) VALUES (3, 1), (3, 2);

CREATE TABLE class_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  session_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  venue VARCHAR(100) NOT NULL,
  class_type ENUM('Lecture', 'Tutorial', 'Practical') NOT NULL DEFAULT 'Lecture',
  check_in_code CHAR(6) NULL,
  is_open TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_code (check_in_code),
  FOREIGN KEY (course_id) REFERENCES courses(id)
);

-- Dates are relative to the day the schema is imported. Session 3 is today
-- and already open for check-in with code DEMO42, so the check-in flow can be
-- tried straight away.
INSERT INTO class_sessions (course_id, session_date, start_time, end_time, venue, class_type, check_in_code, is_open) VALUES
(1, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '10:00:00', '12:00:00', 'Lecture Hall DK A', 'Lecture', 'H4KP7T', 0),
(2, DATE_SUB(CURDATE(), INTERVAL 6 DAY), '14:00:00', '16:00:00', 'Computer Lab K301', 'Practical', 'Q8MN3R', 0),
(1, CURDATE(), MAKETIME(HOUR(NOW()), 0, 0), ADDTIME(MAKETIME(HOUR(NOW()), 0, 0), '02:00:00'), 'Lecture Hall DK A', 'Lecture', 'DEMO42', 1),
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', '16:00:00', 'Computer Lab K301', 'Practical', NULL, 0),
(1, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '10:00:00', '12:00:00', 'Lecture Hall DK A', 'Lecture', NULL, 0);

CREATE TABLE attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  user_id INT NOT NULL,
  status ENUM('Present', 'Late') NOT NULL DEFAULT 'Present',
  checked_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_attendance (session_id, user_id),
  FOREIGN KEY (session_id) REFERENCES class_sessions(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

INSERT INTO attendance (session_id, user_id, status, checked_in_at) VALUES
(1, 3, 'Present', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '10:04:00'));

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
