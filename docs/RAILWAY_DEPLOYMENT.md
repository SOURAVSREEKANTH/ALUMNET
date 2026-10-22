# Railway Demo Deployment

This guide prepares a **college-project demo deployment** of AlumniConnect. It is not a production-readiness certification. Railway currently supports Dockerfile-based services, MySQL, service-variable references, and persistent volumes; this project uses those features. Check Railway's current pricing and plan limits before creating services.

## What is included in this repository

- `Dockerfile` builds PHP 8.5 with Apache and PDO MySQL. Apache serves only `public/`, honors the front-controller rewrite rules, and listens on Railway's `PORT`.
- `.dockerignore` prevents local credentials, private uploads, logs, and Git files from being copied into the image.
- `docker/` contains the Apache virtual host, PHP upload/session settings, and a port-aware entrypoint.
- `database/deploy-schema.php` is a CLI-only initializer for an **empty** MySQL database. It refuses to run if any table already exists.

No Railway account, project, or service is created by these files. Nothing is published until you connect the repository and deploy it in your Railway account.

## Deploy steps

1. Push the project to GitHub and create a Railway project from that repository. Railway should detect the root `Dockerfile`; review the build logs to confirm it is building the Dockerfile.
2. Add a Railway MySQL service to the same project/environment.
3. In the PHP app service's **Variables** settings, add:

   | App variable | Value |
   |---|---|
   | `DB_HOST` | Reference the MySQL service's `MYSQLHOST` |
   | `DB_PORT` | Reference its `MYSQLPORT` |
   | `DB_NAME` | Reference its `MYSQLDATABASE` |
   | `DB_USER` | Reference its `MYSQLUSER` |
   | `DB_PASSWORD` | Reference its `MYSQLPASSWORD` |
   | `APP_KEY` | A new, private, 64-character hexadecimal value |
   | `SESSION_SECURE` | `true` |

   For references, use Railway's variable picker or syntax such as `${{MySQL.MYSQLHOST}}`, replacing `MySQL` with the exact database service name. Keep secrets in Railway variables; never add real credentials to Git or a committed `.env` file.

4. To initialize a new empty database, temporarily set the app service's **Pre-deploy Command** to:

   ```text
   php database/deploy-schema.php
   ```

   Deploy once and confirm the pre-deploy logs say the initial schema was applied successfully. Remove the pre-deploy command after successful initialization: it intentionally aborts when the database is non-empty, so leaving it configured would fail later deploys. Do not use this initializer on an existing or data-bearing database; use the documented migrations for upgrades.

   The Railway MySQL service credentials must be allowed to create tables for this initial setup. For a longer-running hosted app, create a restricted database account after initialization and change the app's `DB_USER`/`DB_PASSWORD` variables accordingly.

5. Attach a Railway volume to the PHP app service at:

   ```text
   /var/www/html/storage/memories
   ```

   Photo uploads are private, but are files rather than database blobs; without the volume they can disappear when the service is redeployed or replaced.

6. Optional: seed the four demo roles using the Railway CLI. Link the CLI to the project, select the PHP app service/environment, open its shell with `railway ssh`, and run:

   ```sh
   php database/seed-demo.php
   ```

   The command prints randomly generated passwords in that interactive shell. Save them privately and do not paste them into deployment logs, GitHub, screenshots, or a public presentation. Do not use `--reset-passwords` unless you intentionally want to rotate all demo passwords. If you skip seeding, public registration still supports student and alumni accounts, but organizer/admin-only features will not have demo users.

7. Generate a Railway public domain for the PHP service. Open the HTTPS URL and check the landing page, then test registration/login and core workflows using demo-only data. Do not publish or distribute administrator/demo credentials.

### Generate `APP_KEY`

Use a PHP CLI with `random_bytes()` and keep the result private:

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Enter the generated value directly into the Railway `APP_KEY` variable. Do not paste it into this repository or share it in screenshots.

## Important cautions

- This is a microproject demo, not a service for real alumni or student records. Use synthetic demo data only.
- A volume preserves uploaded files but is not itself a backup. Keep disposable demo content, and verify Railway's current backup/retention features and pricing.
- Secure cookies are enabled by `SESSION_SECURE=true`; use the HTTPS Railway domain.
- Review Railway deployment logs for build/start failures and database connection issues. Never paste logs containing private values into public channels.
- Before considering any real-user pilot, review authorization and privacy, establish a restricted database account, backups and recovery, data retention and deletion, institution-approved alumni verification, accessibility, and incident handling.
- The local PowerShell startup scripts and local `config/local.php` are for Windows development; they are not used in this Docker deployment.

## Official Railway references

- [Dockerfiles](https://docs.railway.com/builds/dockerfiles)
- [MySQL](https://docs.railway.com/databases/mysql)
- [Variables](https://docs.railway.com/variables)
- [Volumes](https://docs.railway.com/volumes)
- [CLI](https://docs.railway.com/cli)
