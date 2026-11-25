# Production Deployment Preparation

## Status

Phase 9 has started as a preparation phase. The application is still local; no hosting account, production domain, DNS, or production database has been selected. Do not put real athlete, applicant, or staff data into the current XAMPP environment.

## Current stack and release target

- The current XAMPP reports PHP 8.0.30 and MariaDB 10.4.32. Neither is an acceptable production runtime for this project.
- `composer.json` requires PHP `^8.4`, which permits PHP 8.5. At the time this plan was written, PHP 8.5 is supported through 2029-12-31; PHP's official schedule should be checked again when a host is selected: [PHP supported versions](https://www.php.net/supported-versions.php).
- MariaDB 10.4 community maintenance ended in 2024. Select a maintained MySQL or MariaDB release offered by the chosen host, and verify both its maintenance window and this application's compatibility before migration: [MariaDB maintenance schedule](https://mariadb.org/about/).
- The application currently uses PDO's MySQL driver. The selected PHP runtime must provide `pdo_mysql` and sessions. Confirm any other required PHP extensions from the final code and hosting runtime before release.

## Application deployment shape

Use a maintained PHP web host with a managed MySQL/MariaDB database where practical. Configure the web server's document root to the repository's `public/` directory. Keep `app/`, `bootstrap/`, `config/`, `database/`, `bin/`, and `storage/` outside the document root. The local `C:\xampp\htdocs\central_volleyball_academy` bridge and its `Require local` restriction are for this development computer only; do not upload that bridge as the production site.

The application uses Apache rewrite rules in `public/.htaccess`. Confirm that the selected Apache host honors the file, or add an equivalent front-controller rule to the host's server configuration. Requests to the public domain should be served from the site root, so URL helpers can build paths from the request script path.

## Production configuration

The PHP application reads process environment variables; it does not load `.env` files. Configure secrets in the hosting control panel or a private service configuration outside the web root and source checkout.

Web process settings:

```text
APP_ENV=production
APP_DEBUG=false
DB_HOST=<database host>
DB_PORT=<database port>
DB_DATABASE=<database name>
DB_USERNAME=<restricted application account>
DB_PASSWORD=<secret>
SESSION_SAVE_PATH=<private writable directory>
```

The web account must have only the table privileges needed by the application. Migration credentials are separate: `DB_MIGRATION_USERNAME` and `DB_MIGRATION_PASSWORD` should be supplied only to a protected deployment/CLI operation that runs `bin/migrate.php`, then removed from that process. Do not give DDL privileges to the web account.

`config/local.php` is ignored by Git and used only when `APP_ENV` is unset or `local`; it is not the production configuration mechanism. Do not copy local XAMPP credentials into production.

## Release sequence

1. Confirm the domain owner, hosting provider, deployment method, data region, supported PHP/database versions, and backup/recovery commitments.
2. Provision a staging site and empty database with the same supported runtime and web-server behavior planned for production.
3. Deploy a reviewed source snapshot from the private GitHub backup requested by the owner. Confirm the repository connection and define a repeatable release/rollback method before a live release.
4. Point the web root to `public/`; keep configuration, migrations, logs, and session files private. Set file permissions so the web process can write only to the required runtime directories.
5. Configure production environment values and the least-privileged application database account. Supply migration credentials only to the CLI migration step.
6. Take a database backup, apply reviewed migrations, and verify the resulting schema before serving traffic.
7. Configure DNS and a valid TLS certificate. Redirect HTTP to HTTPS at the web-server or hosting layer. The app enables HSTS only when `APP_ENV=production` and PHP sees `$_SERVER['HTTPS']`; TLS termination at a proxy must be configured so that PHP receives a trustworthy HTTPS indication. The app does not trust forwarded headers by default.
8. Configure private session storage and protected error logs. Verify that users receive generic errors while server-side logs retain enough detail for support without recording passwords or request secrets.
9. Run the release checklist below on staging, then repeat it against production before public launch.

## Current project-specific checks

- No scheduled/cron job is implemented today.
- Local registration, gallery uploads, player workflows, and coach profile management are implemented, but have not completed production hardening and are not launch-ready. Email delivery is not implemented.
- Authentication has no default administrator. Create the first production administrator only through a secure, authorized CLI setup after the production database and migrations are ready.
- The public homepage still needs academy-approved programs, events, coach details, branding, and contact copy before launch; do not publish invented content.
- The codebase has not yet been validated against a maintained PHP or database release. Run relevant functional, security, accessibility, and responsive checks in staging before production.

## Staging release checklist

- [ ] Host and runtime versions are supported and recorded.
- [ ] Only `public/` is web-accessible; private directories and secrets are not.
- [ ] HTTPS works and HTTP redirects to HTTPS.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, and secure database settings are active.
- [ ] App database account cannot create or alter schema objects.
- [ ] Migrations succeed using the separate CLI account.
- [ ] Session cookies are secure over HTTPS; session files are private and writable.
- [ ] Login, authorization, logout, error handling, and public pages work on the target host.
- [ ] A backup can be restored into an isolated database.
- [ ] DNS, rollback method, log access, incident contact, and recovery owner are documented.

## Decisions still required

| Decision | Needed from |
| --- | --- |
| Hosting provider, plan/budget, and data region | Project owner / academy |
| Production domain and who owns its registrar/DNS | Academy |
| Managed MySQL/MariaDB product and supported version | Project owner, after host selection |
| Backup frequency, retention, recovery point/time objectives, and restore owner | Academy |
| Source release and rollback method for the requested GitHub backup | Project owner |
| Privacy, consent, records-retention, and launch content approvals | Academy |

Phase 9 remains in progress until these decisions are made, a staging deployment is configured, and the release checklist is completed. Production launch is a separate Phase 10 gate.
