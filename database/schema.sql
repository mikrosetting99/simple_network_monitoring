CREATE DATABASE IF NOT EXISTS db_monitoring CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_monitoring;

-- `groups` adalah kata cadangan di MySQL 8, jadi selalu di-backtick.
CREATE TABLE IF NOT EXISTS `groups` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS devices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NULL,
  name VARCHAR(100) NOT NULL,
  ip_address VARCHAR(255) NOT NULL,
  interval_sec INT DEFAULT 60,
  fail_threshold TINYINT DEFAULT 3,
  is_active TINYINT(1) DEFAULT 1,
  maintenance_until DATETIME NULL,
  current_status ENUM('up','down','unknown') DEFAULT 'unknown',
  fail_count INT DEFAULT 0,
  last_checked_at DATETIME NULL,
  last_latency_ms DECIMAL(7,2) NULL,
  down_since DATETIME NULL,
  INDEX idx_active (is_active, last_checked_at),
  FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS ping_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  device_id INT NOT NULL,
  checked_at DATETIME NOT NULL,
  status ENUM('up','down') NOT NULL,
  latency_ms DECIMAL(7,2) NULL,
  packet_loss TINYINT DEFAULT 0,
  INDEX idx_device_time (device_id, checked_at),
  INDEX idx_time (checked_at),
  FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS incidents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  device_id INT NOT NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  duration_sec INT NULL,
  seen_at DATETIME NULL,
  note VARCHAR(255) NULL,
  INDEX idx_device_start (device_id, started_at),
  FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- Agregat per jam hasil cleanup.php (raw ping_logs hanya disimpan 14 hari).
CREATE TABLE IF NOT EXISTS ping_hourly (
  device_id INT NOT NULL,
  hour_start DATETIME NOT NULL,
  samples INT NOT NULL,
  up_count INT NOT NULL,
  lat_samples INT NOT NULL,
  avg_latency DECIMAL(7,2) NULL,
  avg_loss DECIMAL(5,2) NULL,
  PRIMARY KEY (device_id, hour_start),
  FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- Pengaturan yang bisa diubah dari halaman Pengaturan + status siklus worker.
CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(50) PRIMARY KEY,
  v VARCHAR(255) NOT NULL
);
