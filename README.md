# AlumniConnect

**Your college. Your people. Your next chapter.**

AlumniConnect is a PHP and MySQL alumni-network and meetup-tracking project for a college Web Programming microproject. It includes the public landing page, authentication, member profiles and directory, events/attendance, mentorship and opportunities, event discussions, private moderated photo memories, notifications, and administrator operations.

## Project status

**Implemented for the current prototype scope:** authentication, profiles and directory, events and attendance, mentorship and opportunities, event discussions and memories, notifications, and administrator account/role controls, alumni verification, photo moderation, operational metrics, and audit history. A Railway demo deployment configuration and guide are prepared, but the app has not been deployed. Broader member content-report moderation and quality checks remain; do not use real member data. See the [detailed project guide](./docs/PROJECT_GUIDE_REPORT.md), [Railway deployment guide](./docs/RAILWAY_DEPLOYMENT.md), [project phases report](./docs/PROJECT_PHASES_REPORT.md), and [task list](./TASKS.md) for details.

## Run locally

PHP 8.5.11 x64 NTS was downloaded from the official PHP for Windows distribution into the current user's local application data; it is not bundled in this project. No Composer package, JS framework, or animation library was added.

Start the isolated local MySQL instance in one PowerShell window:

```powershell
powershell -ExecutionPolicy Bypass -File .\run-mysql-local.ps1
```

Keep that window open. In a second PowerShell window, start PHP from the project directory:

```powershell
powershell -ExecutionPolicy Bypass -File .\run-local.ps1
```

Then open <http://127.0.0.1:8000/>. Keep the PowerShell window open while using the site; press **Ctrl+C** to stop the server.

The script uses the downloaded user-local PHP runtime if present, otherwise PHP in `C:\xampp\php\php.exe` or PHP 8+ on PATH. It requires PDO MySQL. PHP's built-in server is for local development only, not production hosting.

### Current local database

This workspace uses a separate MySQL 8 instance bound to loopback at `127.0.0.1:3307`; it is not an XAMPP service and is not reachable from other machines. `run-mysql-local.ps1` starts it using the existing data directory under LocalAppData. Its development database is `alumniconnect_dev`. The generated app connection and key are stored in the ignored `config/local.php`; do not commit or share that file. Keep both server windows open while using authenticated pages.

### Set up another local database

For a fresh MySQL or XAMPP MySQL database, ensure the service is running and enable PDO MySQL in the PHP CLI you will use. The setup script creates a new database and least-privilege app user; it refuses to overwrite an existing database, app user, or `config/local.php`.

```powershell
$env:MYSQL_ADMIN_HOST = "127.0.0.1"
$env:MYSQL_ADMIN_PORT = "3306"
$env:MYSQL_ADMIN_USER = "root"
# Optional: set MYSQL_ADMIN_PASSWORD in this PowerShell session if the admin account has a password.
$env:APP_DB_NAME = "alumniconnect"
$php = "C:\xampp\php\php.exe"
$phpArgs = @()
& $php @phpArgs .\database\setup-local.php
```

If using the downloaded PHP runtime instead of XAMPP, enable its bundled PDO MySQL extension for the setup command:

```powershell
$php = Join-Path $env:LOCALAPPDATA "AlumniConnect\php-8.5.11\php.exe"
$ext = Join-Path (Split-Path $php) "ext"
$phpArgs = @("-d", "extension_dir=$ext", "-d", "extension=php_pdo_mysql.dll")
& $php @phpArgs .\database\setup-local.php
```

After setup, seed four local demo roles. Save the generated credentials when printed; passwords are random and are not committed or displayed again unless explicitly rotated:

```powershell
& $php @phpArgs .\database\seed-demo.php
```

Use `--reset-passwords` to rotate demo passwords and print new ones. Demo email addresses are `alumni.demo@example.test`, `student.demo@example.test`, `organizer.demo@example.test`, and `admin.demo@example.test`. The alumni demo is pending administrator verification. Public registration intentionally permits only alumni and student roles.

For a database created before Phase 4, start MySQL and apply the member-profile migration before rerunning the demo seeder. Use a local MySQL administrator account. This creates the new table and backfills profiles without overwriting existing rows:

```powershell
$env:MYSQL_ADMIN_HOST = "127.0.0.1"
$env:MYSQL_ADMIN_PORT = "3307"
$env:MYSQL_ADMIN_USER = "root"
# Set MYSQL_ADMIN_PASSWORD only if your local database administrator has a password.
$php = Join-Path $env:LOCALAPPDATA "AlumniConnect\php-8.5.11\php.exe"
$ext = Join-Path (Split-Path $php) "ext"
$phpArgs = @("-d", "extension_dir=$ext", "-d", "extension=php_pdo_mysql.dll")
& $php @phpArgs .\database\migrate-phase4.php
```

For an existing database created before Phase 5, apply the event migration using the same administrator connection settings. It creates event, RSVP, and waitlist tables without changing existing member data; rerunning it is safe:

```powershell
$env:MYSQL_ADMIN_HOST = "127.0.0.1"
$env:MYSQL_ADMIN_PORT = "3307"
$env:MYSQL_ADMIN_USER = "root"
# Set MYSQL_ADMIN_PASSWORD only if your local database administrator has a password.
$php = Join-Path $env:LOCALAPPDATA "AlumniConnect\php-8.5.11\php.exe"
$ext = Join-Path (Split-Path $php) "ext"
$phpArgs = @("-d", "extension_dir=$ext", "-d", "extension=php_pdo_mysql.dll")
& $php @phpArgs .\database\migrate-phase5.php
```

The standard `alumni_app` database account deliberately has no schema-administration privileges; run migrations as a local MySQL administrator. Fresh databases created from `database/schema.sql` already include the event tables.

For an existing database created before Phase 6, apply the attendance migration with the local MySQL administrator account. It adds hashed event passes and attendance records without altering your existing events, members, or RSVPs; running it more than once is safe:

```powershell
$env:MYSQL_ADMIN_HOST = "127.0.0.1"
$env:MYSQL_ADMIN_PORT = "3307"
$env:MYSQL_ADMIN_USER = "root"
# Set MYSQL_ADMIN_PASSWORD only if your local database administrator has a password.
$php = Join-Path $env:LOCALAPPDATA "AlumniConnect\php-8.5.11\php.exe"
$ext = Join-Path (Split-Path $php) "ext"
$phpArgs = @("-d", "extension_dir=$ext", "-d", "extension=php_pdo_mysql.dll")
& $php @phpArgs .\database\migrate-phase6.php
```

For an existing database created before Phase 7, apply the mentorship, opportunities, and meetup-preference migration with the local MySQL administrator account. It adds the new tables without changing existing member or event records; running it more than once is safe:

```powershell
$env:MYSQL_ADMIN_HOST = "127.0.0.1"
$env:MYSQL_ADMIN_PORT = "3307"
$env:MYSQL_ADMIN_USER = "root"
# Set MYSQL_ADMIN_PASSWORD only if your local database administrator has a password.
$php = Join-Path $env:LOCALAPPDATA "AlumniConnect\php-8.5.11\php.exe"
$ext = Join-Path (Split-Path $php) "ext"
$phpArgs = @("-d", "extension_dir=$ext", "-d", "extension=php_pdo_mysql.dll")
& $php @phpArgs .\database\migrate-phase7.php
```

For an existing database created before Phase 8, add event discussions, private photo-memory metadata, and notifications with the local MySQL administrator account:

```powershell
$env:MYSQL_ADMIN_HOST = "127.0.0.1"
$env:MYSQL_ADMIN_PORT = "3307"
$env:MYSQL_ADMIN_USER = "root"
# Set MYSQL_ADMIN_PASSWORD only if your local database administrator has a password.
$php = Join-Path $env:LOCALAPPDATA "AlumniConnect\php-8.5.11\php.exe"
$ext = Join-Path (Split-Path $php) "ext"
$phpArgs = @("-d", "extension_dir=$ext", "-d", "extension=php_pdo_mysql.dll")
& $php @phpArgs .\database\migrate-phase8.php
```

For a database that predates Phase 9 notifications, extend notification types for moderation and alumni-review updates:

```powershell
$env:MYSQL_ADMIN_HOST = "127.0.0.1"
$env:MYSQL_ADMIN_PORT = "3307"
$env:MYSQL_ADMIN_USER = "root"
# Set MYSQL_ADMIN_PASSWORD only if your local database administrator has a password.
$php = Join-Path $env:LOCALAPPDATA "AlumniConnect\php-8.5.11\php.exe"
$ext = Join-Path (Split-Path $php) "ext"
$phpArgs = @("-d", "extension_dir=$ext", "-d", "extension=php_pdo_mysql.dll")
& $php @phpArgs .\database\migrate-phase9.php
```

Uploaded event memories are limited to JPEG/PNG images no larger than 2 MB and are stored under `storage\memories`, outside the public document root. New photos remain private to their uploader and moderators until approved. Keep `storage\memories` excluded from source control and include it in a deliberate, privacy-aware backup policy before any pilot.

The member's event pass displays a locally generated QR code; organizers can scan it with a supported browser/device camera or enter the code manually. `public\assets\js\vendor\qrcode.min.js` is a locally vendored, MIT-licensed QR encoder (license included next to the file). No external service receives pass codes.

For another PHP runtime, enable its PDO MySQL extension as above or run the migration with its PHP executable and `PDO_MYSQL` enabled. Fresh databases created from the updated `database/schema.sql` already include the table; running the migration remains safe and backfills any older accounts. Department and graduation-year choices are institution-specific and must be added to `departments` and `graduation_batches`; the app intentionally does not invent college catalog entries.

For example, after replacing these sample values with the institution's approved catalog, add a department and its graduation year with SQL:

```sql
INSERT INTO departments (name, slug) VALUES ('Your Department', 'your-department');
SET @department_id = LAST_INSERT_ID();
INSERT INTO graduation_batches (department_id, graduation_year)
VALUES (@department_id, 2024);
```

### Manual server command

If PHP 8+ with PDO MySQL is on PATH, run:

```powershell
php -S 127.0.0.1:8000 -t public public\router.php
```

Use `run-local.ps1` in preference to the manual command when using the downloaded runtime so the PDO MySQL extension is loaded.

## Runtime and imagery

- Current runtime verified: PHP 8.5.11 CLI, x64 NTS.
- MySQL Server 8.0.46 is used by this workspace through an isolated loopback-only instance; the app database connection and core schema have been verified.
- XAMPP/Apache is not installed or required for local development; deployment configuration and production hardening remain out of scope.
- The two local editorial photos are from Unsplash and used under its [free license](https://unsplash.com/license). They are illustrative, not photos of the user's college or actual AlumniConnect events.
- The page uses system fonts, CSS, and vanilla JavaScript. No third-party runtime service is needed to render it.

## Documentation

- [PROJECT_GUIDE.md](./PROJECT_GUIDE.md) — project concepts and implementation approach
- [docs/PROJECT_GUIDE_REPORT.md](./docs/PROJECT_GUIDE_REPORT.md) — detailed feature, architecture, file-by-file, setup, and readiness report
- [docs/RAILWAY_DEPLOYMENT.md](./docs/RAILWAY_DEPLOYMENT.md) — demo deployment configuration, database initialization, and volume setup for Railway
- [docs/PROJECT_DESCRIPTION.md](./docs/PROJECT_DESCRIPTION.md) — detailed description of the project, current features, architecture, and roadmap
- [TASKS.md](./TASKS.md) — roadmap and progress
- [docs/ARCHITECTURE.md](./docs/ARCHITECTURE.md) — system boundaries, routes, visual design, and feature dependencies
- [docs/DATABASE.md](./docs/DATABASE.md) — implemented core schema, planned feature tables, and integrity rules
- [docs/TESTING.md](./docs/TESTING.md) — checks actually run and remaining test strategy

## Security and demo-account note

The implemented foundation uses PHP sessions, `password_hash()` / `password_verify()`, PDO prepared statements, server-side role assignment, CSRF protection, login throttling, local-only demo account seeding, profile visibility, and opt-in email sharing. It is a development foundation, not a production-ready deployment. Demo credentials must never be production credentials; do not commit local configuration, and use HTTPS with secure cookies in deployment.
