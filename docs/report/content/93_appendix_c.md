# APPENDIX C: INSTALLATION AND DEPLOYMENT GUIDE

This appendix summarises how to run Rento locally and how to move it to real hosting. The full guides are kept in the repository as README.md and docs/deployment.md. Passwords and keys are shown as placeholders and must never be committed to the repository.

## C.1 Running the System Locally

1. Install MAMP (Apache, PHP 8.3 and MySQL 8.0), place the project in the folder htdocs/Rento and start the servers; by default Apache listens on port 8888 and MySQL on port 8889.
2. Create the database rento and import a dump of the current database. Later schema changes are applied from database/migrations (001 to 022), in order, with an administrative MySQL account, after a backup.
3. Create a database user for the application with only the SELECT, INSERT, UPDATE and DELETE privileges on rento, and put its name and password and an application key in includes/config.local.php, which is excluded from version control. Environment variables named RENTO_DB_HOST, RENTO_DB_USER, RENTO_DB_PASS, RENTO_DB_NAME, RENTO_DEV_MODE and RENTO_APP_KEY take precedence over this file.
4. Install the libraries: composer install --no-dev for PHPMailer, and python3.12 -m venv ml/.venv followed by ml/.venv/bin/pip install -r ml/requirements.txt for the pricer.
5. Compute the first prices with php tools/run-forecast.php "first run". On macOS, the nightly recomputation is installed with the launchd file tools/com.rento.price-forecast.plist.
6. Open http://localhost:8888/Rento/ for the website and http://localhost:8888/Rento/admin/ for the administration area.

## C.2 Running the Tests

- PHP unit and integration tests: vendor/bin/phpunit (the integration tests need the local site to be running; they create and delete a temporary user).
- Python tests: ml/.venv/bin/python -m pytest tests/python ml.
- Evaluations: the scripts in tests/evaluation and ml/train_evaluate.py write their results to docs/report/results.

## C.3 Deployment to Real Hosting

Table: Table C.1 Deployment checklist {widths=1.55,4.2}
| Step | Summary |
|---|---|
| Server | PHP 8.2 or 8.3 with pdo_mysql, mbstring, openssl, sodium, gd with WebP, intl, dom, fileinfo and curl; MySQL 8.0 or MariaDB 10.6; Apache 2.4 with mod_rewrite and AllowOverride All; Python 3.12; Composer 2; an HTTPS certificate; server time zone Asia/Kathmandu |
| Files | Copy the project without .git, ml/.venv, vendor, log files and includes/config.local.php; copy the two upload folders. At a domain root, replace /Rento/ with / in .htaccess |
| Web server | Keep the .htaccess rules that block internal folders; redirect HTTP to HTTPS and enable HSTS; for nginx, translate the rules as listed in the guide |
| Database | Create an application user with SELECT, INSERT, UPDATE and DELETE only, and a separate administrative user for migrations and backups; import the database |
| Configuration | Set the database settings, RENTO_DEV_MODE=0 and a new random application key as environment variables or in a protected configuration file |
| PHP settings | display_errors off, errors logged to a file, secure session cookies, upload limits of about 10 MB |
| Libraries and pricer | composer install --no-dev; create the Python virtual environment; replace launchd with a nightly cron job at 02:30 Nepal time running tools/run-forecast.php |
| Permissions | The web server may write only to logs and the two upload folders |
| Email, backups, logs | Configure SMTP with SPF and DKIM; nightly database backups kept off the server; rotate the PHP, forecast and mail logs |
| Production check | Run php tools/check-production.php with the site address, and the System check page, until no check fails |
| Before opening | Set the site address, complete and publish the legal pages, and replace the template contact information |
