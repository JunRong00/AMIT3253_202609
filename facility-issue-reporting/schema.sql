CREATE DATABASE IF NOT EXISTS facility_issues_db;
USE facility_issues_db;

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

CREATE TABLE locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  building VARCHAR(100) NOT NULL,
  name VARCHAR(100) NOT NULL,
  floor VARCHAR(30) NOT NULL,
  image_url VARCHAR(500) NULL
);

INSERT INTO locations (building, name, floor, image_url) VALUES
('Block A', 'Lecture Hall DK A', 'Ground Floor', '/uploads/sample-lecture-hall.jpg'),
('Block B', 'Tutorial Room B204', 'Level 2', '/uploads/sample-tutorial-room.jpg'),
('Block K', 'Computer Lab K301', 'Level 3', '/uploads/sample-computer-lab.jpg'),
('Library', 'Study Area & Discussion Pods', 'Level 1', '/uploads/sample-library.jpg'),
('Sports Complex', 'Gymnasium', 'Ground Floor', '/uploads/sample-sports-complex.jpg');

CREATE TABLE issues (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  location_id INT NOT NULL,
  category VARCHAR(50) NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  priority ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium',
  status ENUM('Open', 'In Progress', 'Resolved', 'Closed') NOT NULL DEFAULT 'Open',
  admin_note VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (location_id) REFERENCES locations(id)
);

INSERT INTO issues (user_id, location_id, category, title, description, priority, status, admin_note) VALUES
(2, 1, 'Air-Conditioning', 'Air-cond not cold in the back rows', 'The last five rows of the hall are very warm during the afternoon lectures.', 'Medium', 'In Progress', 'Technician scheduled to check the compressor.'),
(2, 3, 'Projector & AV Equipment', 'Projector flickering', 'The projector flickers every few seconds when connected via HDMI.', 'High', 'Open', NULL),
(2, 4, 'Wi-Fi & Network', 'Weak Wi-Fi near discussion pods', 'Wi-Fi drops frequently at the discussion pods near the windows.', 'Low', 'Resolved', 'Additional access point installed.');

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
