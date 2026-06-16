# Installation Guide

## Requirements

- PHP 8.2+ with `pdo_mysql`, `curl`, `json` extensions enabled
- MySQL 8.0+
- Apache with `mod_rewrite` and `mod_headers` enabled

## 1. Get the code

```bash
git clone <repo-url>
cd smart-irrigation-php
```

The repo root *is* the web root — this layout is designed to be deployed
directly as a Git-connected site (e.g. Hostinger's Git deployment, which
clones straight into `public_html`).

## 2. Create the database

```bash
mysql -u root -p -e "CREATE DATABASE smart_irrigation CHARACTER SET utf8mb4;"
mysql -u root -p smart_irrigation < database/schema.sql
```

### Optional: load demo data

For local/dev work, `database/seeder.sql` populates a working set of users,
a farm, 4 zones, 2 pumps, a device, a schedule, and sample sensor/weather
rows so you can exercise the API immediately:

```bash
mysql -u root -p smart_irrigation < database/seeder.sql
```

**Do not run this against a production database** — every seeded account
shares the same publicly-documented demo password. See the bottom of
`database/seeder.sql` for the full list of seeded credentials.

## 3. Configure environment

```bash
cp .env.example .env
```

Edit `.env`:

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — your MySQL credentials
- `JWT_SECRET` — generate with `php -r "echo bin2hex(random_bytes(32));"`
- `OPENWEATHER_API_KEY` — get a free key at https://openweathermap.org/api
- `APP_BASE_URL` — the public URL where the API is served

## 4. Create the first admin user

Never ship a hardcoded admin password in the SQL dump. Instead:

```bash
php scripts/create_admin.php "Your Name" you@example.com
```

You'll be prompted for a password (min 8 characters).

## 5. Serve the app

### Local development (PHP built-in server)

```bash
php -S localhost:8000
```

Visit `http://localhost:8000/api/docs/index.php` for the API docs.

### Apache (local or shared hosting)

Point your virtual host's document root at the repo root. The included
`.htaccess` blocks direct access to `config/`, `services/`, `middleware/`,
`models/`, `logs/`, `scripts/`, `database/`, and dotfiles, and routes 404s
through `404.php`.

## 6. Set up the cron tick (weather refresh + automation + scheduler)

Add a cron job that runs every 5–10 minutes:

```bash
php /full/path/to/scripts/cron.php >> /full/path/to/logs/cron.log 2>&1
```

## 7. Register your first device

Authenticate as admin (`POST /api/auth/login.php`), then:

```bash
curl -X POST https://yourdomain.com/api/devices/index.php \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"device_id":"esp8266-001","farm_id":1,"firmware_version":"1.0.0"}'
```

Save the returned `api_key` — it is shown once and used by the NodeMCU as
`X-Device-Id` / `X-API-Key` headers when calling `/api/upload_sensor.php`
and `/api/get_commands.php`.

## API Documentation

A Swagger UI is served at `/api/docs/index.php`, backed by
`/api/docs/openapi.json`.

## Custom error page

Unknown routes are handled by `404.php` (wired via `ErrorDocument 404` in
`.htaccess`). It returns JSON for API-style requests (`/api/...` or an
`Accept: application/json` header) and an HTML page for everything else.
