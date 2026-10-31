CREATE DATABASE IF NOT EXISTS timetabling_db;
USE timetabling_db;

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
--   lecturer@example.com / lecturer123  (lecturer - teaches three of the seeded courses)
--   student@example.com  / student123   (student)
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
  FOREIGN KEY (lecturer_id) REFERENCES users(id)
);

INSERT INTO courses (code, name, lecturer_id) VALUES
('AMIT3253', 'Cloud Computing for Business', 2),
('AACS2304', 'Computer Networks', 2),
('AACS2063', 'Data Structures and Algorithms', 2),
('AACS3064', 'Software Engineering', NULL);

CREATE TABLE venues (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  building VARCHAR(100) NOT NULL,
  capacity INT NOT NULL DEFAULT 40,
  image_url VARCHAR(500) NULL
);

INSERT INTO venues (name, building, capacity, image_url) VALUES
('Lecture Hall DK A', 'Block A', 200, '/uploads/sample-lecture-hall.jpg'),
('Tutorial Room B204', 'Block B', 40, '/uploads/sample-tutorial-room.jpg'),
('Computer Lab K301', 'Block K', 35, '/uploads/sample-computer-lab.jpg'),
('Seminar Room L105', 'Library', 60, '/uploads/sample-seminar-room.jpg');

-- Fixed teaching slots (not free text), so clash checks compare like with like.
CREATE TABLE time_slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL
);

INSERT INTO time_slots (start_time, end_time) VALUES
('08:00:00', '10:00:00'),
('10:00:00', '12:00:00'),
('12:00:00', '14:00:00'),
('14:00:00', '16:00:00'),
('16:00:00', '18:00:00'),
('18:00:00', '20:00:00'),
('20:00:00', '22:00:00');

-- The regular weekly timetable. day_of_week: 1 = Monday ... 6 = Saturday
-- (no classes on Sunday).
-- A venue can only hold one class per day + slot.
CREATE TABLE timetable_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  day_of_week TINYINT NOT NULL,
  time_slot_id INT NOT NULL,
  venue_id INT NOT NULL,
  class_type ENUM('Lecture', 'Tutorial', 'Practical') NOT NULL DEFAULT 'Lecture',
  UNIQUE KEY uniq_venue_slot (day_of_week, time_slot_id, venue_id),
  FOREIGN KEY (course_id) REFERENCES courses(id),
  FOREIGN KEY (time_slot_id) REFERENCES time_slots(id),
  FOREIGN KEY (venue_id) REFERENCES venues(id)
);

INSERT INTO timetable_entries (course_id, day_of_week, time_slot_id, venue_id, class_type) VALUES
(1, 1, 2, 1, 'Lecture'),
(1, 2, 4, 2, 'Tutorial'),
(1, 3, 1, 3, 'Practical'),
(2, 2, 2, 1, 'Lecture'),
(2, 4, 4, 3, 'Practical'),
(3, 1, 4, 2, 'Tutorial'),
(3, 5, 2, 1, 'Lecture'),
(4, 4, 1, 4, 'Lecture'),
(1, 3, 6, 3, 'Practical'),
(2, 6, 1, 3, 'Practical');

-- Public holidays: shown on the timetable, and replacement classes can't be
-- booked on them. Regular classes that fall on one are flagged in red.
CREATE TABLE public_holidays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  holiday_date DATE NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL
);

INSERT INTO public_holidays (holiday_date, name) VALUES
('2026-08-31', 'Merdeka Day'),
('2026-09-16', 'Malaysia Day'),
('2026-11-08', 'Deepavali'),
('2026-12-25', 'Christmas Day');

CREATE TABLE replacement_classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  course_id INT NOT NULL,
  original_date DATE NOT NULL,
  replacement_date DATE NOT NULL,
  time_slot_id INT NOT NULL,
  venue_id INT NOT NULL,
  class_type ENUM('Lecture', 'Tutorial', 'Practical') NOT NULL DEFAULT 'Lecture',
  reason VARCHAR(500) NOT NULL,
  status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
  admin_note VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (course_id) REFERENCES courses(id),
  FOREIGN KEY (time_slot_id) REFERENCES time_slots(id),
  FOREIGN KEY (venue_id) REFERENCES venues(id)
);

-- Dates are relative to the week the schema is imported: replacing classes
-- from this week with slots next week.
INSERT INTO replacement_classes (user_id, course_id, original_date, replacement_date, time_slot_id, venue_id, class_type, reason, status, admin_note) VALUES
(2, 1, DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), DATE_ADD(CURDATE(), INTERVAL 9 - WEEKDAY(CURDATE()) DAY), 4, 4, 'Lecture', 'Attending an industry conference.', 'Approved', 'Approved - venue confirmed.'),
(2, 2, DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 11 - WEEKDAY(CURDATE()) DAY), 5, 1, 'Lecture', 'Medical leave.', 'Pending', NULL),
(2, 3, DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), DATE_ADD(CURDATE(), INTERVAL 10 - WEEKDAY(CURDATE()) DAY), 2, 2, 'Tutorial', 'Public holiday.', 'Rejected', 'Students have another class at this time - please pick a different slot.');

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
