# Smart Farm Irrigation Backend

A production-ready PHP 8.2 / MySQL 8 REST API backend for a Smart Farm
Irrigation System. Serves NodeMCU ESP8266 devices, a web dashboard, and a
Flutter mobile app from a single, shared-hosting-friendly codebase.

## Features

- **JWT authentication** with role-based access (`admin`, `farmer`, `technician`)
- **Farm / device / zone / pump management** — up to 4 irrigation zones per farm
- **Sensor ingestion** (`/api/upload_sensor.php`) and **command polling**
  (`/api/get_commands.php`, polled by NodeMCU every 10s), authenticated via
  a per-device API key
- **Automation engine** — irrigate on low moisture, skip irrigation when
  rain probability > 70%, stop all pumps when water level < 20%, raise
  alerts on zero flow rate or sensor/pump failure
- **Irrigation scheduler** with day-of-week + time-window rules
- **OpenWeatherMap integration** for temperature, humidity, rainfall, rain probability
- **Alerts**: low water, dry soil, pump failure, no flow, sensor failure
- **Reports**: daily / weekly / monthly water consumption, pump runtime, irrigation history
- **Swagger-style API docs** at `/api/docs/index.php`
- **Security**: prepared statements everywhere, input validation/sanitization, CSRF tokens, rate limiting, security headers, custom 404 page

## Tech stack

PHP 8.2 · MySQL 8 · PDO · REST · JWT (dependency-free HS256 implementation) · Apache + `.htaccess` · cPanel/Hostinger Git-deploy compatible

## Project layout

The repo root **is** the web root — deploy by pointing your host's
document root (or Git deployment target) straight at it.

```
.
├── index.php              # health check
├── 404.php                # custom 404 (JSON for API clients, HTML otherwise)
├── bootstrap.php           # shared request bootstrap (autoload, CORS, errors)
├── .htaccess               # routing guards, security headers, 404 wiring
├── .env.example            # copy to .env and fill in
├── api/                     # all REST endpoints, one folder per resource
│   ├── auth/                 login, logout, register, me, csrf_token
│   ├── farms/                farm CRUD
│   ├── devices/               device registration/listing
│   ├── zones/                 zone CRUD (max 4 per farm)
│   ├── pumps/                  pump config + ON/OFF control
│   ├── schedules/              irrigation schedule CRUD
│   ├── weather/                 fetch/store/read weather
│   ├── alerts/                  list/resolve alerts
│   ├── reports/                 daily/weekly/monthly reports
│   ├── docs/                    Swagger UI + openapi.json
│   ├── upload_sensor.php        NodeMCU sensor ingestion
│   └── get_commands.php         NodeMCU command polling
├── config/                  env loader + PDO connection factory
├── services/                JWT, AuthService, AutomationEngine, WeatherService, RateLimiter, Logger, Response, Validator
├── middleware/               AuthMiddleware (JWT+roles), DeviceAuthMiddleware (API key), RateLimitMiddleware, CsrfMiddleware
├── models/                   thin PDO wrappers per table
├── scripts/                  create_admin.php (CLI), cron.php (weather/automation/scheduler tick)
├── database/                 schema.sql (full MySQL 8 schema)
├── uploads/, logs/           runtime data (blocked from direct web access)
├── INSTALL.md                local/general install guide
└── DEPLOYMENT.md              Hostinger/cPanel shared-hosting deployment guide
```

## Quick start

```bash
git clone <repo-url>
cd smart-irrigation-php
cp .env.example .env            # fill in DB + JWT_SECRET + OpenWeatherMap key
mysql -u root -p -e "CREATE DATABASE smart_irrigation CHARACTER SET utf8mb4;"
mysql -u root -p smart_irrigation < database/schema.sql
php scripts/create_admin.php "Your Name" you@example.com
php -S localhost:8000
```

Visit `http://localhost:8000/api/docs/index.php` for interactive API docs.

Full instructions: see [INSTALL.md](INSTALL.md) for local/general setup and
[DEPLOYMENT.md](DEPLOYMENT.md) for deploying to Hostinger/cPanel shared
hosting via Git.

## Device integration (NodeMCU)

1. Register the device as an admin/technician via `POST /api/devices/index.php` — note the `api_key` returned (shown once).
2. The device sends `X-Device-Id` and `X-API-Key` headers on every request.
3. Push sensor readings: `POST /api/upload_sensor.php`
4. Poll for desired pump/zone state every 10s: `GET /api/get_commands.php`

## Automation rules

```
IF moisture < threshold        THEN irrigate that zone
IF rain_probability > 70       THEN skip irrigation
IF water_level < 20%           THEN stop all pumps
IF flow_rate = 0 (pump ON)     THEN raise a NO_FLOW alert
```

Implemented in [services/AutomationEngine.php](services/AutomationEngine.php), evaluated on every sensor upload and re-checked by the cron tick.

## License

Internal/private project — no license granted for redistribution unless stated otherwise by the project owner.
