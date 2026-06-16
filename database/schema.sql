-- Smart Farm Irrigation System - MySQL 8 Schema
-- Run this once on a fresh database. Compatible with cPanel/shared hosting (utf8mb4, InnoDB).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','farmer','technician') NOT NULL DEFAULT 'farmer',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- farms
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS farms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_name VARCHAR(150) NOT NULL,
    location VARCHAR(255) NULL,
    owner_name VARCHAR(150) NULL,
    user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_farms_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- devices (NodeMCU ESP8266)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(100) NOT NULL UNIQUE,
    farm_id INT UNSIGNED NULL,
    farm_name VARCHAR(150) NULL,
    firmware_version VARCHAR(50) NULL,
    api_key VARCHAR(64) NOT NULL,
    last_seen DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_devices_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- zones (4 irrigation zones per farm)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS zones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id INT UNSIGNED NOT NULL,
    zone_number TINYINT UNSIGNED NOT NULL, -- 1..4
    zone_name VARCHAR(100) NOT NULL,
    moisture_threshold DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    crop_type VARCHAR(100) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_zones_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_farm_zone (farm_id, zone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- pumps (bore_pump / well_pump per farm)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pumps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id INT UNSIGNED NOT NULL,
    water_source ENUM('borewell','open_well') NOT NULL,
    pump_name VARCHAR(100) NULL,
    status ENUM('ON','OFF') NOT NULL DEFAULT 'OFF',
    last_changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pumps_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_farm_source (farm_id, water_source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- pump_runtime_log (for reports: pump runtime)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pump_runtime_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id INT UNSIGNED NOT NULL,
    water_source ENUM('borewell','open_well') NOT NULL,
    action ENUM('ON','OFF') NOT NULL,
    triggered_by ENUM('manual','automation','schedule') NOT NULL DEFAULT 'manual',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pumplog_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- sensor_readings
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sensor_readings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(100) NOT NULL,
    farm_id INT UNSIGNED NULL,
    moisture_zone1 DECIMAL(5,2) NULL,
    moisture_zone2 DECIMAL(5,2) NULL,
    moisture_zone3 DECIMAL(5,2) NULL,
    moisture_zone4 DECIMAL(5,2) NULL,
    temperature DECIMAL(5,2) NULL,
    humidity DECIMAL(5,2) NULL,
    water_level DECIMAL(5,2) NULL,
    flow_rate DECIMAL(8,3) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_device_created (device_id, created_at),
    KEY idx_farm_created (farm_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- commands (latest desired state per device, polled every 10s by NodeMCU)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS commands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(100) NOT NULL UNIQUE,
    bore_pump ENUM('ON','OFF') NOT NULL DEFAULT 'OFF',
    well_pump ENUM('ON','OFF') NOT NULL DEFAULT 'OFF',
    zone1 ENUM('ON','OFF') NOT NULL DEFAULT 'OFF',
    zone2 ENUM('ON','OFF') NOT NULL DEFAULT 'OFF',
    zone3 ENUM('ON','OFF') NOT NULL DEFAULT 'OFF',
    zone4 ENUM('ON','OFF') NOT NULL DEFAULT 'OFF',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- schedules
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id INT UNSIGNED NOT NULL,
    zone_id INT UNSIGNED NOT NULL,
    water_source ENUM('borewell','open_well') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    days_of_week VARCHAR(30) NOT NULL DEFAULT 'MON,TUE,WED,THU,FRI,SAT,SUN',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedules_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_schedules_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- weather_data
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS weather_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id INT UNSIGNED NOT NULL,
    temperature DECIMAL(5,2) NULL,
    humidity DECIMAL(5,2) NULL,
    rainfall DECIMAL(6,2) NULL,
    rain_probability DECIMAL(5,2) NULL,
    fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_weather_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- alerts
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id INT UNSIGNED NOT NULL,
    device_id VARCHAR(100) NULL,
    alert_type ENUM('LOW_WATER','DRY_SOIL','PUMP_FAILURE','NO_FLOW','SENSOR_FAILURE') NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_resolved TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    CONSTRAINT fk_alerts_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- irrigation_history (for reports)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS irrigation_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id INT UNSIGNED NOT NULL,
    zone_id INT UNSIGNED NULL,
    water_source ENUM('borewell','open_well') NOT NULL,
    triggered_by ENUM('manual','automation','schedule') NOT NULL DEFAULT 'manual',
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    water_consumed DECIMAL(10,2) NULL COMMENT 'liters, derived from flow_rate over duration',
    CONSTRAINT fk_irrhist_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_irrhist_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- rate_limits (simple sliding-window counter, used by RateLimiter service)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bucket_key VARCHAR(150) NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 1,
    window_start TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_bucket (bucket_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- NOTE: No default admin user is seeded here on purpose (a hardcoded password
-- hash in a public SQL dump is a credential leak waiting to happen).
-- Run `php backend/scripts/create_admin.php` after import to create the
-- first admin account with a properly generated bcrypt hash.
