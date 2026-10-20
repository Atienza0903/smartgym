-- SmartGym Operations - Gleah's Fitness Gym
-- MySQL / MariaDB schema + default data
-- Import via phpMyAdmin or: mysql -u root -p < database/smartgym.sql

CREATE DATABASE IF NOT EXISTS smartgym CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartgym;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS maintenance_logs, equipment, reservations, workout_slots, access_logs, members, membership_plans, users, settings;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff','member') NOT NULL DEFAULT 'member',
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE membership_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  duration_days INT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE members (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL UNIQUE,
  member_code VARCHAR(20) NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NULL,
  phone VARCHAR(30) NULL,
  gender ENUM('Male','Female','Other') NULL,
  birthdate DATE NULL,
  address VARCHAR(255) NULL,
  emergency_contact VARCHAR(150) NULL,
  rfid_uid VARCHAR(32) NULL UNIQUE,
  plan_id INT UNSIGNED NULL,
  membership_start DATE NULL,
  membership_end DATE NULL,
  status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  is_inside TINYINT(1) NOT NULL DEFAULT 0,
  last_scan_at DATETIME NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_members_plan FOREIGN KEY (plan_id) REFERENCES membership_plans(id) ON DELETE SET NULL,
  INDEX idx_members_inside (is_inside)
) ENGINE=InnoDB;

CREATE TABLE access_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NULL,
  rfid_uid VARCHAR(32) NOT NULL,
  direction ENUM('IN','OUT','DENIED') NOT NULL,
  reason VARCHAR(150) NULL,
  device_id VARCHAR(50) NULL,
  source ENUM('rfid','manual','simulator','system') NOT NULL DEFAULT 'rfid',
  scanned_at DATETIME NOT NULL,
  CONSTRAINT fk_logs_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
  INDEX idx_logs_time (scanned_at),
  INDEX idx_logs_member (member_id, scanned_at)
) ENGINE=InnoDB;

CREATE TABLE workout_slots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  slot_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  capacity INT UNSIGNED NOT NULL DEFAULT 20,
  trainer VARCHAR(100) NULL,
  notes VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_slots_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_slots_date (slot_date, start_time)
) ENGINE=InnoDB;

CREATE TABLE reservations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slot_id INT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NOT NULL,
  status ENUM('booked','attended','cancelled','no_show') NOT NULL DEFAULT 'booked',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_slot_member (slot_id, member_id),
  CONSTRAINT fk_res_slot FOREIGN KEY (slot_id) REFERENCES workout_slots(id) ON DELETE CASCADE,
  CONSTRAINT fk_res_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE equipment (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(60) NULL,
  serial_no VARCHAR(80) NULL,
  location VARCHAR(80) NULL,
  status ENUM('operational','needs_repair','under_maintenance','out_of_service') NOT NULL DEFAULT 'operational',
  purchase_date DATE NULL,
  last_maintenance DATE NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE maintenance_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  equipment_id INT UNSIGNED NOT NULL,
  reported_by INT UNSIGNED NULL,
  issue TEXT NOT NULL,
  priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status ENUM('reported','in_progress','resolved') NOT NULL DEFAULT 'reported',
  action_taken TEXT NULL,
  cost DECIMAL(10,2) NULL,
  reported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  CONSTRAINT fk_maint_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
  CONSTRAINT fk_maint_user FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE settings (
  `key` VARCHAR(60) PRIMARY KEY,
  `value` TEXT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Default data
-- Default logins:  admin@smartgym.local / admin123
--                  staff@smartgym.local / staff123
--                  member@smartgym.local / member123
-- CHANGE THESE PASSWORDS AFTER FIRST LOGIN.
-- ---------------------------------------------------------------
INSERT INTO settings (`key`, `value`) VALUES
('gym_name', 'Gleah''s Fitness Gym'),
('max_capacity', '30'),
('open_time', '06:00'),
('close_time', '22:00'),
('scan_cooldown_seconds', '5'),
('device_api_key', 'CHANGE-ME-DEVICE-KEY');

INSERT INTO users (full_name, email, phone, password_hash, role) VALUES
('Gym Administrator', 'admin@smartgym.local', '09170000001', '$2y$10$D4sO77odu4dqNGWfM2zgMOkWenXxa7.GjxYTP4OHKOxMsjTCbJITm', 'admin'),
('Front Desk Staff', 'staff@smartgym.local', '09170000002', '$2y$10$q9j99xvPUOZ2Babt4mGsBOXEOon9V7qlIR5P6pID.5VJIcCnHSKL.', 'staff'),
('Juan Dela Cruz', 'member@smartgym.local', '09170000003', '$2y$10$hyL/Cy8F/6rbX1dzLL/V7uUz3xRiHXLIEtnK3icova0Cqz9/mwM/q', 'member');

INSERT INTO membership_plans (name, duration_days, price, description) VALUES
('Daily Pass', 1, 80.00, 'Single-day gym access'),
('Monthly', 30, 1000.00, 'Unlimited access for 30 days'),
('Quarterly', 90, 2700.00, 'Unlimited access for 3 months'),
('Annual', 365, 9500.00, 'Unlimited access for 1 year'),
('Student Monthly', 30, 800.00, 'Discounted monthly plan for students (valid ID)');

INSERT INTO members (user_id, member_code, full_name, email, phone, gender, rfid_uid, plan_id, membership_start, membership_end, status) VALUES
(3, 'GFG-0001', 'Juan Dela Cruz', 'member@smartgym.local', '09170000003', 'Male', 'A1B2C3D4', 2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'active');

INSERT INTO equipment (name, category, serial_no, location, status, purchase_date) VALUES
('Treadmill #1', 'Cardio', 'TM-001', 'Cardio Area', 'operational', '2024-01-15'),
('Treadmill #2', 'Cardio', 'TM-002', 'Cardio Area', 'operational', '2024-01-15'),
('Stationary Bike', 'Cardio', 'SB-001', 'Cardio Area', 'operational', '2024-03-10'),
('Bench Press Station', 'Strength', 'BP-001', 'Free Weights', 'operational', '2023-11-02'),
('Smith Machine', 'Strength', 'SM-001', 'Free Weights', 'operational', '2023-11-02'),
('Cable Crossover', 'Strength', 'CC-001', 'Machine Area', 'operational', '2024-02-20'),
('Leg Press Machine', 'Strength', 'LP-001', 'Machine Area', 'operational', '2024-02-20'),
('Dumbbell Rack (5-50 lbs)', 'Free Weights', 'DR-001', 'Free Weights', 'operational', '2023-10-01');
