-- ---------------------------------------------------------------------------
-- Smart Farm Irrigation Backend — Development/Demo Seeder
-- ---------------------------------------------------------------------------
-- Run this AFTER schema.sql, only on local/dev/staging databases. It seeds
-- a working set of accounts and farm data so the dashboard/mobile app/API
-- docs can be exercised immediately without manual setup.
--
-- DO NOT run this on a production database — the password hashes below
-- correspond to a single shared, publicly-known demo password. Change every
-- seeded password immediately if this ever ends up on a public-facing
-- environment, or better, don't run this file there at all.
--
-- Usage:
--   mysql -u root -p smart_irrigation < database/schema.sql
--   mysql -u root -p smart_irrigation < database/seeder.sql
--
-- All seeded user passwords are: Admin@1234
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Users (password for all three: Admin@1234)
-- ---------------------------------------------------------------------------
INSERT INTO users (name, email, password_hash, role, is_active)
VALUES
    ('Demo Admin', 'admin@smartfarm.local', '$2b$10$oiu3eOInPolT.XOoGAgMp.MI6AbwElCTFy6wx8C/ICGYMq7hbKPMO', 'admin', 1),
    ('Demo Farmer', 'farmer@smartfarm.local', '$2b$10$oiu3eOInPolT.XOoGAgMp.MI6AbwElCTFy6wx8C/ICGYMq7hbKPMO', 'farmer', 1),
    ('Demo Technician', 'technician@smartfarm.local', '$2b$10$oiu3eOInPolT.XOoGAgMp.MI6AbwElCTFy6wx8C/ICGYMq7hbKPMO', 'technician', 1)
ON DUPLICATE KEY UPDATE email = VALUES(email);

-- ---------------------------------------------------------------------------
-- Farm (owned by the demo farmer)
-- ---------------------------------------------------------------------------
INSERT INTO farms (farm_name, location, owner_name, user_id)
SELECT 'Green Valley Farm', 'Coimbatore,IN', 'Demo Farmer', id
FROM users
WHERE email = 'farmer@smartfarm.local'
  AND NOT EXISTS (SELECT 1 FROM farms WHERE farm_name = 'Green Valley Farm');

-- ---------------------------------------------------------------------------
-- Zones (4 per farm, matching the project's max-zone rule)
-- ---------------------------------------------------------------------------
INSERT INTO zones (farm_id, zone_number, zone_name, moisture_threshold, crop_type)
SELECT f.id, z.zone_number, z.zone_name, z.moisture_threshold, z.crop_type
FROM farms f
JOIN (
    SELECT 1 AS zone_number, 'Zone 1 - North Field' AS zone_name, 35.00 AS moisture_threshold, 'Tomato' AS crop_type
    UNION ALL SELECT 2, 'Zone 2 - South Field', 30.00, 'Maize'
    UNION ALL SELECT 3, 'Zone 3 - East Field', 40.00, 'Sugarcane'
    UNION ALL SELECT 4, 'Zone 4 - West Field', 25.00, 'Groundnut'
) z
WHERE f.farm_name = 'Green Valley Farm'
ON DUPLICATE KEY UPDATE zone_name = VALUES(zone_name);

-- ---------------------------------------------------------------------------
-- Pumps (one borewell, one open well)
-- ---------------------------------------------------------------------------
INSERT INTO pumps (farm_id, water_source, pump_name, status)
SELECT f.id, p.water_source, p.pump_name, 'OFF'
FROM farms f
JOIN (
    SELECT 'borewell' AS water_source, 'Borewell Pump 1' AS pump_name
    UNION ALL SELECT 'open_well', 'Open Well Pump 1'
) p
WHERE f.farm_name = 'Green Valley Farm'
ON DUPLICATE KEY UPDATE pump_name = VALUES(pump_name);

-- ---------------------------------------------------------------------------
-- Device (NodeMCU). api_key below is a fixed demo value for local testing
-- only — generate a fresh random key via the real registration endpoint
-- (POST /api/devices/index.php) for anything beyond local dev.
-- ---------------------------------------------------------------------------
INSERT INTO devices (device_id, farm_id, farm_name, firmware_version, api_key, is_active)
SELECT 'esp8266-demo-001', f.id, f.farm_name, '1.0.0',
       'f9c974e88c7154aa95e18cf7ccf889df653b12f72bbbdb0eef51466c783fbac5', 1
FROM farms f
WHERE f.farm_name = 'Green Valley Farm'
ON DUPLICATE KEY UPDATE firmware_version = VALUES(firmware_version);

-- ---------------------------------------------------------------------------
-- Default device commands row (all OFF until automation/schedule/manual
-- control sets them)
-- ---------------------------------------------------------------------------
INSERT INTO commands (device_id)
VALUES ('esp8266-demo-001')
ON DUPLICATE KEY UPDATE device_id = VALUES(device_id);

-- ---------------------------------------------------------------------------
-- Schedule: irrigate Zone 1 from the borewell every morning 06:00-06:30
-- ---------------------------------------------------------------------------
INSERT INTO schedules (farm_id, zone_id, water_source, start_time, end_time, days_of_week, is_active)
SELECT f.id, z.id, 'borewell', '06:00:00', '06:30:00', 'MON,TUE,WED,THU,FRI,SAT,SUN', 1
FROM farms f
JOIN zones z ON z.farm_id = f.id AND z.zone_number = 1
WHERE f.farm_name = 'Green Valley Farm'
  AND NOT EXISTS (
      SELECT 1 FROM schedules s
      WHERE s.farm_id = f.id AND s.zone_id = z.id AND s.water_source = 'borewell' AND s.start_time = '06:00:00'
  );

-- ---------------------------------------------------------------------------
-- A sample weather snapshot so /api/weather/index.php has data immediately
-- ---------------------------------------------------------------------------
INSERT INTO weather_data (farm_id, temperature, humidity, rainfall, rain_probability)
SELECT f.id, 29.50, 64.00, 0.00, 12.00
FROM farms f
WHERE f.farm_name = 'Green Valley Farm'
  AND NOT EXISTS (SELECT 1 FROM weather_data w WHERE w.farm_id = f.id);

-- ---------------------------------------------------------------------------
-- A sample sensor reading so dashboards have something to render right away
-- ---------------------------------------------------------------------------
INSERT INTO sensor_readings
    (device_id, farm_id, moisture_zone1, moisture_zone2, moisture_zone3, moisture_zone4,
     temperature, humidity, water_level, flow_rate)
SELECT 'esp8266-demo-001', f.id, 28.50, 33.00, 45.00, 22.00, 29.50, 64.00, 78.00, 2.500
FROM farms f
WHERE f.farm_name = 'Green Valley Farm'
  AND NOT EXISTS (SELECT 1 FROM sensor_readings sr WHERE sr.device_id = 'esp8266-demo-001');

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Seeded credentials summary
-- ---------------------------------------------------------------------------
-- admin@smartfarm.local       / Admin@1234  (role: admin)
-- farmer@smartfarm.local      / Admin@1234  (role: farmer)
-- technician@smartfarm.local  / Admin@1234  (role: technician)
-- Device: esp8266-demo-001
--   X-Device-Id: esp8266-demo-001
--   X-API-Key:   f9c974e88c7154aa95e18cf7ccf889df653b12f72bbbdb0eef51466c783fbac5
