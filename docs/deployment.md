# Deploying Rento to real hosting

A checklist for moving the site from MAMP (`http://localhost:8888/Rento/`) to a real server. Nothing here
has been deployed yet. Work through it top to bottom, then run the production check (step 12) until it
reports **0 failed**.

## 1. Server requirements

| Part | Version | Notes |
|---|---|---|
| PHP | 8.2 or 8.3 (developed on 8.3.14) | Extensions: `pdo_mysql`, `mbstring`, `openssl`, `sodium`, `gd` (with WebP), `intl`, `dom`, `fileinfo`, `curl`, `json` |
| MySQL | 8.0 (developed on 8.0.40), or MariaDB 10.6+ | `utf8mb4`; server time zone **Asia/Kathmandu** |
| Web server | Apache 2.4 with `mod_rewrite`, `mod_alias`, `mod_headers`, `AllowOverride All` | The rules are in `.htaccess` files. For nginx, translate them (see step 4) |
| Python | 3.12 (for smart pricing) | In a virtual environment in `ml/.venv` |
| Composer | 2.x | Installs PHPMailer into `vendor/` |
| TLS | A certificate, e.g. Let's Encrypt (certbot) | HTTPS only |

Set the **server time zone to Asia/Kathmandu** (`timedatectl set-timezone Asia/Kathmandu`). PHP sets it
itself (`includes/config.php`), but MySQL's `CURDATE()` and the Python pricer use the server's local time.

## 2. Copy the files

Copy the project **without**: `.git/`, `ml/.venv/`, `vendor/`, `logs/*.log`, `includes/config.local.php`,
the `python/` research data if you don't need it, and `sql_updates.sql`. For example:

```sh
rsync -av --exclude .git --exclude ml/.venv --exclude vendor --exclude 'logs/*.log' \
  --exclude includes/config.local.php ./ deploy@server:/var/www/rento/
```

Copy the uploaded images too: `admin/img/vehicleimages/` and `assets/images/content/`.

## 3. Site at the domain root

On MAMP the site lives under `/Rento/`. On a domain root, replace `/Rento/` with `/` in `.htaccess`
(ErrorDocument, RewriteBase, the redirect rules and the vendor block):

```sh
sed -i 's#/Rento/#/#g' .htaccess
```

The PHP code works out its own base path; nothing else needs changing. `robots.txt` and `sitemap.xml` are
only read by search engines at a domain root.

## 4. Web server

- The `DocumentRoot` is the project folder. Use `AllowOverride All` for it, so `.htaccess` files in the root,
  `includes/`, `logs/`, `admin/` and others are applied. They block config, logs, migrations, Python,
  docs, Composer files and hidden files.
- Redirect all http traffic to https, and send HSTS once https works:
  `Header always set Strict-Transport-Security "max-age=31536000"`.
- **Behind a proxy or load balancer that ends TLS**, make PHP see HTTPS, or session cookies won't get
  the Secure flag: `SetEnvIf X-Forwarded-Proto https HTTPS=on` (only when the proxy is trusted).
- **nginx:** there is no `.htaccess`. Deny `/includes/`, `/logs/`, `/database/`, `/ml/`, `/python/`, `/docs/`,
  `/tools/`, `/vendor/`, dot-files, and `*.sql|csv|py|md|log|ini|sh|lock` files. Map `/car/<slug>-<id>` to
  `vehical-details.php?vhid=<id>`, `/package/<slug>-<id>` to `package-details.php?pid=<id>`,
  `/sitemap.xml` to `sitemap.php`, `/robots.txt` to `robots.php`, and `/<page>` to `/<page>.php`.

## 5. Database

1. Create the database with `utf8mb4`, and **two users**:
   - an **app user** with `SELECT, INSERT, UPDATE, DELETE` on the database only;
   - an **admin user** for migrations and backups (ALTER, CREATE and so on), never used by the site.

   ```sql
   CREATE DATABASE rento CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
   CREATE USER 'rento'@'localhost' IDENTIFIED BY '<long random password>';
   GRANT SELECT, INSERT, UPDATE, DELETE ON rento.* TO 'rento'@'localhost';
   ```
2. Load the data: dump the MAMP database (it already has migrations 001–022 applied) and import it:
   `mysqldump --single-transaction rento > rento.sql`, then `mysql rento < rento.sql` on the server as
   the admin user.
3. Future migrations (`database/migrations/NNN_*.sql`) run as the admin user, in order, after a backup.
4. The app user connects as `rento@localhost` over the socket, and the Python pricer connects over TCP
   (`127.0.0.1`). Create the user for both hosts if MySQL treats them differently.

## 6. Configuration and secrets

Nothing secret is in git. Give the site its settings in **one** of two ways:

- **Environment variables** (preferred), set in the vhost (`SetEnv`) or PHP-FPM pool (`env[...]`), not in a
  web-readable file:
  `RENTO_DB_HOST`, `RENTO_DB_PORT`, `RENTO_DB_USER`, `RENTO_DB_PASS`, `RENTO_DB_NAME`,
  `RENTO_DEV_MODE=0`, `RENTO_APP_KEY`.
- **`includes/config.local.php`** (mode 640, owner deploy user, group web server):

  ```php
  <?php
  return [
      'DB_HOST' => 'localhost', 'DB_PORT' => 3306, 'DB_USER' => 'rento', 'DB_PASS' => '...', 'DB_NAME' => 'rento',
      'DEV_MODE' => false,
      'APP_KEY' => '...', // base64 of 32 random bytes: php -r 'echo base64_encode(random_bytes(32)), "\n";'
  ];
  ```

**`APP_DEV_MODE` must be off** (`RENTO_DEV_MODE=0` / `'DEV_MODE' => false`). In dev mode, password reset
links appear on screen when email is off.
`APP_KEY` encrypts the SMTP password in the database. Keep the same key when you move servers, or
re-enter the SMTP password in admin → Email.

## 7. PHP settings (php.ini)

```ini
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/rento-error.log
expose_php = Off
session.cookie_httponly = 1
session.cookie_secure = 1        ; the app also sets it when the request is https
session.use_strict_mode = 1
upload_max_filesize = 10M        ; vehicle and site photos
post_max_size = 12M
date.timezone = Asia/Kathmandu
```

## 8. Libraries and Python

```sh
cd /var/www/rento
composer install --no-dev --optimize-autoloader        # PHPMailer
python3.12 -m venv ml/.venv
ml/.venv/bin/pip install -r ml/requirements.txt       # PyMySQL (+ the ML model libraries)
(cd ml && .venv/bin/python -m unittest -q test_pricing_rules)   # 8 tests
```

`PRICING_PYTHON_BIN` in `includes/config.php` points to `ml/.venv/bin/python` relative to the site, so it
works wherever the site lives.

## 9. Nightly price recompute: cron instead of launchd

On the Mac this runs from launchd (`tools/com.rento.price-forecast.plist`, installed 2026-09-27). On Linux,
add a cron job for the deploy user:

```cron
CRON_TZ=Asia/Kathmandu
30 2 * * * cd /var/www/rento && /usr/bin/php tools/run-forecast.php nightly >> logs/forecast-cron.log 2>&1
```

The site also recomputes by itself after bookings and admin changes. The nightly run moves the 60-day window
forward. Admin → Smart Pricing shows the last run.

## 10. File permissions

```sh
chown -R deploy:www-data /var/www/rento
find /var/www/rento -type d -exec chmod 755 {} \;
find /var/www/rento -type f -exec chmod 644 {} \;
chmod 775 logs admin/img/vehicleimages assets/images/content   # the web server writes here
chmod 640 includes/config.local.php                            # if you use it
chmod 750 ml/.venv/bin/*                                       # the pricer runs as the web server / cron user
```

The web server needs to write only to `logs/`, `admin/img/vehicleimages/` and `assets/images/content/`.

## 11. Email, backups and logging

- **Email:** admin → Email. Enter your provider's SMTP details, save, then "Send test email". Add SPF and
  DKIM records for the From domain, or messages land in spam. Set admin → Change password → Account email.
- **Backups:**
  - A nightly `mysqldump --single-transaction` (as the admin user), kept 30 days, with a copy off the
    server.
  - Back up the two upload folders and `includes/config.local.php` (or the environment) too.
  - Test a restore once.
- **Error logging:** PHP errors go to `error_log` (step 7). The app writes `logs/forecast.log`
  (pricing runs) and `logs/mail.log` (email sends, with masked addresses). Rotate them with logrotate and
  check them after launch.

## 12. Production check

```sh
php tools/check-production.php https://www.your-domain.com/
```

It warns about anything unsafe and exits with code 1 while something fails:
- dev mode on
- a root or privileged database user
- a weak database password or missing APP_KEY
- no https or no site address
- `display_errors` or `expose_php` on
- email not set up
- the pricer not installed, or prices never computed
- template text on the legal pages
- internal files reachable over the web

Admin → **System check** runs the same checks inside the web server, where it also sees HTTPS and the web
server's `php.ini`. Use its "Also test protected files" button.

## 13. Before opening to customers

- [ ] Admin → Site content → Site & SEO → **Site address** = `https://www.your-domain.com/`
- [ ] Terms and Privacy: fill in every `[TO CONFIRM]`, have them reviewed, publish the drafts (admin → Manage Pages)
- [ ] Admin → **Contact info**: the real address, phone and email (it still has template data)
- [ ] Real photos for vehicles and packages, then activate them
- [ ] Email set up and tested; admin account email set
- [ ] `php tools/check-production.php https://…` reports 0 failed
- [ ] Submit `https://www.your-domain.com/sitemap.xml` in Google Search Console
- [ ] One test booking and one enquiry end to end, then delete them
