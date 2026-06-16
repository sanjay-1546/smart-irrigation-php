# Shared Hosting (Hostinger / cPanel) Deployment Guide

This repo is laid out so that its root is the web root — deploy it by
pointing your hosting account's Git deployment straight at `public_html`
(or a subdomain's document root). No `backend/` subfolder to account for.

## 1. Connect the repo via Git deployment

**Hostinger (hPanel → Git):**
1. hPanel → Advanced → Git.
2. Add the repository URL and branch (e.g. `main`).
3. Set the deployment directory to `public_html` (or your domain's
   document root).
4. Deploy. Hostinger clones the repo directly into that directory.

**cPanel (Git Version Control):**
1. cPanel → Git Version Control → Create.
2. Repository URL + target path = your domain's document root.
3. Use "Manage" → Pull or Deploy after each push to sync changes.

## 2. Create the MySQL database

In hPanel/cPanel → **MySQL Databases**:
1. Create a database, e.g. `u123_irrigation`.
2. Create a database user and a strong password.
3. Attach the user to the database with **All Privileges**.
4. Open **phpMyAdmin**, select the database, go to **Import**, and upload
   `database/schema.sql`.

## 3. Configure `.env`

Via File Manager, copy `.env.example` to `.env` in the document root and
edit it with the provided DB host (usually `localhost`), database name,
user, and password. Generate a `JWT_SECRET`:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

(Run via Terminal if your plan includes SSH access, or generate it locally
and paste the value in.)

`.env` is excluded from the web by `.htaccess` (dotfiles are denied) — it
does not need to be (and should not be) committed to the repo.

## 4. PHP version

Set the domain/folder to PHP 8.2 (hPanel → Advanced → PHP Configuration, or
cPanel → MultiPHP Manager), and confirm `pdo_mysql`, `curl`, and `json`
extensions are enabled (on by default on most shared plans).

## 5. Create the first admin user

If SSH/Terminal access is available:

```bash
php ~/public_html/scripts/create_admin.php "Your Name" you@example.com
```

If only File Manager access is available, temporarily expose a one-off
admin-creation endpoint protected by a secret you control, run it once, then
delete it — do not leave such an endpoint live.

## 6. Set up the Cron Job

hPanel → Advanced → Cron Jobs, or cPanel → Cron Jobs. Add a job that runs
every 5–10 minutes:

```
*/5 * * * * php /home/user/public_html/scripts/cron.php >> /home/user/public_html/logs/cron.log 2>&1
```

This refreshes weather data, re-runs the automation engine, and activates
scheduled irrigation windows.

## 7. HTTPS

Enable AutoSSL (hPanel/cPanel → SSL) so the NodeMCU, dashboard, and mobile
app all talk to the API over HTTPS. Update `APP_BASE_URL` in `.env` and any
device firmware endpoint URLs accordingly.

## 8. Verify

- `https://yourdomain.com/index.php` → `{"status":"running",...}`
- `https://yourdomain.com/api/docs/index.php` → Swagger UI
- `https://yourdomain.com/anything-bogus` → custom 404 page
- `POST https://yourdomain.com/api/auth/login.php` with your admin
  credentials → JWT token

## 9. Security checklist before going live

- [ ] `.env` is not web-accessible (verify with
      `curl https://yourdomain.com/.env` → 403)
- [ ] `config/`, `services/`, `middleware/`, `models/`, `logs/`, `scripts/`,
      and `database/` return 403 when hit directly
- [ ] `APP_DEBUG=false` in production
- [ ] `JWT_SECRET` is a unique, random 64-char value (not the example)
- [ ] Database user has privileges only on the irrigation database
- [ ] Cron log rotation is in place (`logs/` can grow over time)
- [ ] Device `api_key`s are stored only on the device and never logged
- [ ] `.git` directory is not web-accessible (Git-deployed hosts sometimes
      leave it inside the document root — `.htaccess` denies dotfiles, but
      double-check with `curl https://yourdomain.com/.git/config` → 403/404)
