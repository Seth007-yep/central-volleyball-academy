# Database Schema

## Scope

[`001_create_staff_identity.sql`](../database/migrations/001_create_staff_identity.sql) creates the first staff access tables:

- `roles` stores role names. The migration adds the initial `admin` role.
- `users` stores staff account identity, an application-generated password hash, account status, and timestamps.
- `user_roles` associates accounts and roles without assuming every authenticated user is an administrator.
- `audit_logs` records who performed an action, the action name, its entity, and when it happened.

It does not create an administrator account or set a password. The initial `admin` role is only a role definition.

[`002_create_login_attempts.sql`](../database/migrations/002_create_login_attempts.sql) adds the short-lived login-rate-limit buckets required by staff sign-in. The `schema_migrations` table is maintained by [`bin/migrate.php`](../bin/migrate.php) and is not an application feature table.

[`003_create_academy_features.sql`](../database/migrations/003_create_academy_features.sql) adds:

- `academy_content`: one editable plain-text public profile/contact record.
- `registrations`: limited applicant details, guardian contact for minors, review status, and private staff notes.
- `members`: private player records created from approved applications; records use reversible statuses.
- `gallery_items`: metadata and generated storage keys for media bytes kept outside `public/`.
- `registration_rate_limits`: short-lived HMAC network buckets without raw IP addresses.

[`004_create_coaches.sql`](../database/migrations/004_create_coaches.sql) adds `coaches` for staff-managed coach names, roles, biographies, qualifications, public visibility, and an optional foreign key to a gallery image. Coach records default to draft; only published profiles joined to published image records can be displayed publicly. [`006_staff_roles_and_coach_experience.sql`](../database/migrations/006_staff_roles_and_coach_experience.sql) adds optional experience text to coaches and seeds the Academy Staff role alongside the initial Administrator role.

[`005_create_programs_and_events.sql`](../database/migrations/005_create_programs_and_events.sql) adds:

- `programs` for staff-managed title, category, age group, plain-text description, optional fee/capacity details, display order, and draft/published/archived status.
- `program_schedules` for up to ten short schedule notes per program, and `program_coaches` for published-coach assignments.
- `events` for approved event descriptions, local start/end date-times, locations, optional approved gallery images, registration status, and draft/published/cancelled/archived workflow. A previously published cancellation remains visible with its cancellation state.

The exact registration fields, age threshold, guardian rules, and data-retention policy still require academy approval. See [academy feature notes](academy-features.md).

## Security choices

- The `password_hash` field stores the output of PHP `password_hash()` only. The CLI setup command hashes passwords in PHP; never insert a human-readable password into SQL.
- No default administrator is seeded. [`bin/create-admin.ps1`](../bin/create-admin.ps1) prompts for the initial account details, collects the password securely, and lets PHP hash it before insertion.
- Email is unique; the application should normalize addresses before saving and signing in. The database collation is case-insensitive for ordinary Latin email text.
- Role membership is stored separately from the user account. Authorization checks belong in application middleware/service code, not only in the navigation UI.
- The staff UI assigns one supported role per account. The restricted application account may replace rows in `user_roles`, but it cannot delete the primary `users` account or schema objects.
- Audit rows deliberately omit raw request bodies and personal details. The current authentication code records a controlled set of action/entity names.
- Foreign keys preserve referential integrity. Staff accounts and roles should be deactivated or retired rather than physically deleted through the application.

## Local database compatibility

The bundled XAMPP client reports MariaDB 10.4.32. MariaDB's published maintenance schedule lists MariaDB 10.4 maintenance ending June 18, 2024. The local development database `central_volleyball_academy` was created on `127.0.0.1`, and the initial migration was applied there so it is visible in phpMyAdmin. This is only a local learning database with no real staff or applicant data. Do not use this server for production; upgrade to a maintained MySQL/MariaDB release before using real data or deploying.

The SQL uses InnoDB, `utf8mb4`, foreign-key, `ENUM`, and timestamp features. It has been executed successfully on the local MariaDB 10.4.32 server. Validate it again against the selected maintained server before production use.

## Migration workflow

Migrations are ordered and applied once. Before applying a migration:

1. Confirm the target host/version and make sure the connection points to the intended development database.
2. Back up the database if it contains anything that must be retained.
3. Apply the migration using the database account chosen for development.
4. Record the migration version after successful application.
5. Verify the resulting tables, indexes, and foreign keys.

The local database includes the staff/auth tables, academy feature tables, coach, program, and event tables, and `schema_migrations`. Migration `001_create_staff_identity` was recorded as a baseline; migrations `002_create_login_attempts` through `006_staff_roles_and_coach_experience` are applied by the runner. Run later changes through `bin/migrate.php`; do not edit a live schema manually.

## PDO connection layer

The application has [`config/database.php`](../config/database.php) for reading `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` from the process environment. In local mode it can fall back to the ignored `config/local.php` generated by `bin/configure-local-db.ps1`. [`Database.php`](../app/Support/Database.php) constructs PDO with `utf8mb4`, exception errors, associative fetches, native prepared statements, and a connection timeout.

The public homepage now reads academy text and published gallery metadata; registration writes an application record. Admin pages load private records only after `AdminAuthMiddleware` authorizes the request. The first-admin setup CLI uses the restricted application account for user/role inserts. Do not use MariaDB's root account for web requests.

For local Apache, process environment settings can override the private local config, for example:

```apache
SetEnv DB_HOST 127.0.0.1
SetEnv DB_PORT 3306
SetEnv DB_DATABASE central_volleyball_academy
SetEnv DB_USERNAME cva_app
# Keep DB_PASSWORD private; config/local.php is the current XAMPP development option.
```

The local `cva_app` account has table-level grants for the implemented features, but no schema-management permission and no delete permission on member, registration, gallery, coach, program, or event records. It can replace program schedule/coach links and delete expired registration-rate buckets. The separate `cva_migrator` account can create and alter schema objects and is only used by the CLI migration runner. Generated passwords and the HMAC key remain in ignored `config/local.php`; do not commit or copy that file to a public web root.
