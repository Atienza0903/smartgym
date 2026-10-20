CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  password VARCHAR(255) NULL,
  role ENUM('Admin', 'Staff', 'Member') NOT NULL DEFAULT 'Member',
  rfid_tag_uid VARCHAR(80) UNIQUE,
  membership_status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reservations (
  reservation_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  slot_date DATE NOT NULL,
  slot_time_start TIME NOT NULL,
  slot_time_end TIME NOT NULL,
  status ENUM('Pending', 'Confirmed', 'Cancelled') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reservation_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_reservation_slot (slot_date, slot_time_start)
);

CREATE TABLE IF NOT EXISTS attendance_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  log_type ENUM('IN', 'OUT') NOT NULL,
  source VARCHAR(40) NOT NULL DEFAULT 'RFID',
  device_id VARCHAR(80) NULL,
  CONSTRAINT fk_attendance_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_attendance_timestamp (timestamp),
  INDEX idx_attendance_user (user_id)
);

CREATE TABLE IF NOT EXISTS equipment_maintenance (
  equipment_id INT AUTO_INCREMENT PRIMARY KEY,
  equipment_name VARCHAR(120) NOT NULL,
  reported_by INT NOT NULL,
  issue_description TEXT NOT NULL,
  maintenance_status ENUM('Damaged', 'Under Repair', 'Fixed') NOT NULL DEFAULT 'Damaged',
  date_reported DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_maintenance_reporter FOREIGN KEY (reported_by) REFERENCES users(user_id) ON DELETE RESTRICT,
  INDEX idx_maintenance_status (maintenance_status)
);
