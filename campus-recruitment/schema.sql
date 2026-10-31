CREATE DATABASE IF NOT EXISTS recruitment_db;
USE recruitment_db;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  id_number VARCHAR(20) NULL,
  faculty VARCHAR(150) NULL,
  date_of_birth DATE NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed accounts (change these passwords in any real deployment):
--   admin@example.com   / admin123    (admin)
--   student@example.com / student123  (regular user)
INSERT INTO users (name, email, password_hash, id_number, faculty, date_of_birth, is_admin) VALUES
('Admin', 'admin@example.com', '$2y$10$HI3gLmyD4OGmfNLAGUIL8.eBhhKu5nzL7wTDws.6mUNO9V44kyM5q', NULL, NULL, NULL, 1),
('Demo Student', 'student@example.com', '$2y$10$3kkHog6vhyue9Syxkx8ohe8Q6.wBphgtVxgwRB7cLPzLWBtjrC8Kq', '2409123', 'Faculty of Computing and Information Technology', '2005-03-14', 0);

CREATE TABLE openings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  organisation VARCHAR(150) NOT NULL,
  category ENUM('Campus Job', 'Internship', 'Club & Society', 'Scholarship') NOT NULL,
  description TEXT NOT NULL,
  location VARCHAR(150) NOT NULL,
  vacancies INT NOT NULL DEFAULT 1,
  deadline DATE NOT NULL,
  image_url VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Deadlines are relative to the day the schema is imported, so the demo
-- always has open openings (plus one that has already closed).
INSERT INTO openings (title, organisation, category, description, location, vacancies, deadline, image_url) VALUES
('Print Hub Assistant (Part-Time)', 'Campus Print Hub', 'Campus Job', 'Help students with printing, binding and scanning during peak hours. 10-15 hours per week, RM8/hour.', 'Block D, Ground Floor', 3, DATE_ADD(CURDATE(), INTERVAL 14 DAY), '/uploads/sample-print-hub.jpg'),
('Library Assistant (Part-Time)', 'TAR UMT Library', 'Campus Job', 'Shelve returned books and assist at the counter. Weekday evenings, RM8/hour.', 'Library, Level 1', 2, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '/uploads/sample-library-assistant.jpg'),
('IT Support Intern', 'Centre for ICT Services', 'Internship', 'A 12-week internship supporting staff and students with hardware, Wi-Fi and account issues. Allowance RM800/month.', 'Block K, Level 1', 2, DATE_ADD(CURDATE(), INTERVAL 21 DAY), '/uploads/sample-it-support.jpg'),
('Software Developer Intern', 'TechNova Solutions (Industry Partner)', 'Internship', 'Build internal web tools with PHP and MySQL alongside the engineering team. Allowance RM1,200/month.', 'Kuala Lumpur (Hybrid)', 1, DATE_ADD(CURDATE(), INTERVAL 30 DAY), '/uploads/sample-software-intern.jpg'),
('Music Club Committee Member', 'TAR UMT Music Club', 'Club & Society', 'Join the committee that organises jam sessions, Battle of the Bands and the year-end concert.', 'Student Activity Centre', 4, DATE_ADD(CURDATE(), INTERVAL 10 DAY), '/uploads/sample-music-club.jpg'),
('Cultural Night Performer', 'Cultural Society', 'Club & Society', 'Perform traditional dance or music at the annual Cultural Night. No experience needed - training provided.', 'Main Hall', 12, DATE_ADD(CURDATE(), INTERVAL -3 DAY), '/uploads/sample-cultural-society.jpg'),
('Merit Scholarship 2026/2027', 'Scholarship & Financial Aid Unit', 'Scholarship', 'Full tuition fee waiver for students with a CGPA of 3.75 and above who are active in co-curricular activities.', 'Online', 5, DATE_ADD(CURDATE(), INTERVAL 45 DAY), '/uploads/sample-scholarship.jpg');

CREATE TABLE applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  opening_id INT NOT NULL,
  phone VARCHAR(20) NOT NULL,
  cover_note TEXT NOT NULL,
  status ENUM('Submitted', 'Shortlisted', 'Accepted', 'Rejected') NOT NULL DEFAULT 'Submitted',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_opening (user_id, opening_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (opening_id) REFERENCES openings(id)
);

INSERT INTO applications (user_id, opening_id, phone, cover_note, status) VALUES
(2, 3, '012-345 6789', 'I have helped my faculty set up labs for two semesters and enjoy troubleshooting hardware.', 'Shortlisted'),
(2, 5, '012-345 6789', 'I play guitar and helped organise last year''s jam sessions.', 'Submitted');

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
