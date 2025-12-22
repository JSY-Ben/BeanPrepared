CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  external_user_id VARCHAR(64) NOT NULL UNIQUE,
  platform ENUM('ios','android') NOT NULL,
  onesignal_player_id VARCHAR(64) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notification_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(64) NOT NULL,
  description VARCHAR(255) DEFAULT NULL
);

CREATE TABLE user_notification_types (
  user_id INT NOT NULL,
  notification_type_id INT NOT NULL,
  PRIMARY KEY (user_id, notification_type_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (notification_type_id) REFERENCES notification_types(id) ON DELETE CASCADE
);

CREATE TABLE user_notification_leads (
  user_id INT NOT NULL,
  lead_minutes INT NOT NULL,
  PRIMARY KEY (user_id, lead_minutes),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  description TEXT DEFAULT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME DEFAULT NULL,
  website VARCHAR(255) DEFAULT NULL,
  organiser_email VARCHAR(160) DEFAULT NULL,
  organiser_phone VARCHAR(40) DEFAULT NULL,
  is_one_off TINYINT(1) NOT NULL DEFAULT 1,
  repeat_interval INT DEFAULT NULL,
  repeat_unit ENUM('daily','weekly','monthly') DEFAULT NULL,
  repeat_until DATE DEFAULT NULL,
  notification_type_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (notification_type_id) REFERENCES notification_types(id) ON DELETE RESTRICT
);

CREATE TABLE event_notifications_sent (
  event_id INT NOT NULL,
  lead_minutes INT NOT NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id, lead_minutes),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE event_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  is_organizer TINYINT(1) NOT NULL DEFAULT 0,
  contact_consent TINYINT(1) DEFAULT NULL,
  is_one_off TINYINT(1) NOT NULL DEFAULT 1,
  repeat_interval INT DEFAULT NULL,
  repeat_unit ENUM('daily','weekly','monthly') DEFAULT NULL,
  repeat_until DATE DEFAULT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_notes TEXT DEFAULT NULL,
  description TEXT NOT NULL,
  website VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO notification_types (slug, name, description) VALUES
  ('general', 'General Updates', 'General news and updates'),
  ('events', 'Event Alerts', 'Reminders for upcoming events'),
  ('announcements', 'Announcements', 'Major announcements from the team');
