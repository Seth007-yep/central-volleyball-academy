# Staff Authentication

## Implemented routes

| Method and path | Behavior |
| --- | --- |
| `GET /admin/login` | Render the staff sign-in form and a session-bound CSRF token. |
| `POST /admin/login` | Validate the token, rate limit, verify the password hash, and sign in an active Administrator or Academy Staff account. |
| `GET /admin` | Require a valid session, active account, and supported admin/staff role on every request. |
| `GET /admin/users`, `POST /admin/users/save` | Admin-only, searchable staff management: create accounts, update identities, assign roles, activate/deactivate, and set a replacement password. |
| `GET /admin/audit-logs` | Admin-only filtered, paginated list of administrative access events. |
| `GET /admin/academy`, `POST /admin/academy` | Edit approved public academy and contact information. |
| `GET /admin/coaches`, `GET /admin/coaches/edit`, `POST /admin/coaches/save` | Add and update draft-first coach profiles. Publishing requires academy approval. |
| `/admin/registrations`, `/admin/players`, `/admin/gallery` | Protected academy staff pages for reviewing applications, searching/filtering private applications and player records, and moderating gallery media. Application decisions require a separate confirmation POST. |
| `POST /admin/logout` | Validate CSRF, record the logout, and destroy the session. |

Every protected route checks the signed-in account and current role in the database. The Administrator and Academy Staff roles can use academy workflows; only Administrators can manage staff accounts and view the audit log. The UI supports these two roles. More granular per-action permissions remain future work. Program and event management are available under `/admin/programs` and `/admin/events`.

## Request flow

`public/index.php` matches an explicit route from `config/routes.php`. The sign-in controller validates the POST token and calls `AuthService`. `UserRepository` uses prepared statements to read an account and its separate role membership. `LoginAttemptRepository` applies the database-backed rate limit. `AuditLogRepository` records only the action, actor (when known), and user entity ID. Successful sign-in regenerates the session ID; `AdminAuthMiddleware` rechecks active status and minimum role on every protected request. The staff account screen prevents self-deactivation/demotion and serializes role changes so an active administrator always remains.

## Safeguards

- Passwords are stored only as `password_hash()` output and checked with `password_verify()`. The setup command requires 12 to 72 bytes; the upper bound avoids bcrypt's input truncation behavior under the current `PASSWORD_DEFAULT` implementation.
- Unknown, inactive, unsupported-role, and wrong-password cases share a generic error. Unknown accounts still go through a dummy password-hash check to reduce timing differences.
- Five failures in a 15-minute window lock either the HMAC-derived email bucket or the HMAC-derived network bucket for 15 minutes. The database stores the hashes, not the raw email or IP. Old rate-limit rows are removed after one day.
- CSRF tokens use `random_bytes()` and `hash_equals()` for login and logout POST requests.
- Sessions use strict mode, cookie-only IDs, `HttpOnly`, `SameSite=Lax`, a 30-minute idle timeout, an eight-hour absolute lifetime, and ID rotation at login. The cookie is `Secure` on HTTPS and always in production configuration.
- The private session directory is `storage/sessions` by default. Configure `SESSION_SAVE_PATH` to a private, writable path on another host.
- Admin authorization is a database check on each protected request; hiding a link is not treated as access control.
- Initial and replacement passwords are hashed before storage and are never written to audit records. Staff account changes and role assignments create audit events.
- Application status decisions are held in the session between steps for ten minutes. The final confirmation token and CSRF token are checked server-side, and the application status is rechecked under a row lock before the decision is written.
- Audit rows do not contain passwords, session IDs, raw email addresses, or IP addresses.
- The login form escapes retained email text before rendering it. SQL values are bound through PDO prepared statements.

## Local setup

The ignored `config/local.php` stores randomly generated local-only database passwords and the HMAC key. `bin/configure-local-db.ps1` creates separate application and migration accounts and writes that file. After migrations, run `bin/grant-app-permissions.ps1`: the web account receives only the table-level reads/writes it needs, while the migration account alone has schema permissions. Do not copy the local file into Git or a public web root.

After the database exists and migrations are current, create the first admin interactively from a PowerShell terminal:

```powershell
.\bin\create-admin.ps1 -PhpPath 'C:\xampp\php\php.exe'
```

The password is collected as a secure PowerShell prompt and passed to the PHP process through a temporary environment variable. The CLI script prompts for display name and email, hashes the password, assigns the initial `admin` role in a transaction, and records the setup action. There is no default account or public staff-registration route. One active administrator account currently exists in the local development database; the account details and password are not included in this guide.

## Current limits

The account screen can set a replacement password, but email-based recovery, email delivery, multifactor authentication, and finer per-action permissions are not implemented. The local test server uses PHP 8.0.30 and MariaDB 10.4.32; this flow is for local learning only, not production or real personal data.
