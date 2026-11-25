# Security Implementation Status

## In place

- `public/` is the document root and the front controller selects from explicit routes.
- HTML output uses `Html::escape()` where values enter page text or attributes.
- Database values in application queries are bound through PDO prepared statements.
- The app database account is separate from the migration account. The web account has only table-level read/write grants for authentication and no schema-management permission. The migration account alone can create/alter schema objects.
- Login CSRF, session hardening, generic credential errors, rate limiting, role checks, and minimal audit events are documented in [Staff Authentication](authentication.md).
- Responses set `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, and a same-origin Content Security Policy. Images and video are served only from same-origin routes.
- `Strict-Transport-Security` is set only when `APP_ENV=production` and the request reaches PHP over HTTPS. The hosting server must still redirect HTTP to HTTPS; proxy headers are not trusted implicitly.
- Public error pages do not show exception messages. The PHP error log receives the exception class, not request bodies or credentials.
- Staff-account, player, and registration search inputs are bounded; status filters use allowlists. List pages use database `LIMIT`/`OFFSET`, and page numbers are bounded against result counts.
- The `admin` and `staff` roles can use academy workflows. Only administrators can manage staff accounts or read the audit log. Account changes are CSRF-protected, audited, preserve one active administrator, and prevent self-demotion/deactivation.
- The web database account can replace role-mapping rows so each account has its selected role; it still cannot delete primary user records or alter the schema.
- Public registrations use server-side name/date/contact checks, a CSRF token, and a short-lived rate bucket keyed by a secret HMAC of the network address. Raw network addresses are not stored in the limiter; stale buckets are removed after one day.
- Before inserting a registration, the app checks for the same applicant name, birth date, and a matching applicant/guardian contact across active applications and member records. A keyed advisory lock serializes simultaneous checks; its digest is not stored. New and repeated submissions share one public confirmation, and a repeat creates no application row.
- Admin registration/player phone, program, and date filters are bounded, use prepared SQL, and preserve database pagination. Applicant and member contact values remain available only through authenticated staff routes.
- A registration remains pending until staff review. Every application status decision uses a separate confirmation POST with a session-bound expiring token, CSRF validation, and a row-locked check that the application status did not change. Approval and player creation are transactional; application decisions, player updates, academy-content updates, and gallery changes write audit events without copying personal details into the log.
- Player and application records are behind active staff authorization. The public site does not show the player roster; it displays only plain academy content and gallery media explicitly published by staff.
- Gallery uploads allow only JPEG, PNG, WebP, MP4, and WebM after server-side MIME checks. Images are checked for valid headers and bounded dimensions. Random storage keys replace client filenames; media bytes stay outside `public/`, and the public stream serves only published database rows with a fixed MIME allowlist and `nosniff`.
- Gallery items default to draft. Publishing requires a staff checkbox confirming permission to share. This is not a substitute for the academy's parent/guardian media-release process.
- Coach profiles default to draft and are excluded from the public homepage until a staff member confirms the profile details are academy-approved and publishes them. Coach experience is optional plain text and follows the same approval gate. Coach photos can reference only currently published gallery images.
- Programs and events default to drafts and use protected admin routes, CSRF-protected writes, prepared SQL, and audit events. Publishing requires an academy-approval confirmation. Public reads filter to published programs/events, show cancellations only for previously published events, assign only published coaches, and use only published gallery images.
- Local secrets live in ignored `config/local.php`, above the public document root. Runtime session files live under private `storage/sessions/` unless `SESSION_SAVE_PATH` is configured.
- Local verification now includes synthetic authentication/session checks, role and inactive-account predicates, logout and CSRF-token behavior, SQL-shaped search inputs, hostile HTML text encoding, upload MIME/size policy cases, protected-route redirects, and response-header inspection. Full browser workflows and deployment-host checks remain; see [quality check results](quality-checks.md).

## Still required before launch

- Upgrade the local and production PHP/database stack to maintained releases. The current XAMPP reports PHP 8.0.30 and MariaDB 10.4.32.
- Choose hosting and configure HTTPS, server-level HTTP-to-HTTPS redirects, production secrets, a private session path, backups, log retention, and least-privileged production accounts.
- Tune and monitor public registration rate limits and add a documented administrator recovery process appropriate to the chosen hosting environment.
- Review security headers against any future third-party scripts, images, fonts, analytics, or payment providers before adding them to Content Security Policy.
- Obtain and implement academy-approved privacy/consent, guardian, public-roster, media-release, and records-retention rules. The adult-age threshold defaults to 18 and must be confirmed for the academy's location.
- Complete the remaining signed-in browser workflows, physical-device and screen-reader review, authenticated multipart upload/streaming check, and staging-host security verification before deployment.

Keep development data synthetic. Do not use the unsupported local stack for real applicant/player information or production.
