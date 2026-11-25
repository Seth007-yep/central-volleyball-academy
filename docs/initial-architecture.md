# Central Volleyball Academy: Initial Architecture Proposal

**Status:** Active architecture and implementation reference
**Scope:** Initial discovery decisions, technical approach, and current local implementation status.

## Discovery findings

- The project directory was empty at the start of discovery. The current local project includes the application, database migrations, and local XAMPP configuration; GitHub remains intentionally deferred.
- The project specification identifies PHP 8.x, MySQL/MariaDB, HTML, CSS, and JavaScript, with XAMPP for local development.
- The available XAMPP CLI reports PHP 8.0.30; Composer is not installed or on `PATH`.
- PHP 8.0 has reached end of life. Set the project runtime target to PHP 8.4 or newer, then align the local XAMPP and hosting versions before adding production functionality.
- The user confirmed the official name: **Central Volleyball Academy**.
- The official logo, exact colors, address, contact details, programs, schedules, membership rules, and legal/privacy content are not yet available. They must remain placeholders until supplied.

## Proposed project structure

Keep a small, conventional PHP application. Serve only `public/`; keep source, configuration, logs, and uploaded files outside the web root.

```text
central-volleyball-academy/
├── app/
│   ├── Controllers/
│   │   ├── Admin/
│   │   └── Public/
│   ├── Middleware/
│   ├── Repositories/
│   ├── Services/
│   ├── Support/
│   └── Views/
│       ├── admin/
│       ├── layouts/
│       └── public/
├── bootstrap/
│   └── app.php
├── config/
│   └── routes.php
├── database/
│   ├── migrations/
│   └── seeds/
├── docs/
├── public/
│   ├── assets/
│   │   ├── css/
│   │   ├── images/
│   │   └── js/
│   ├── .htaccess
│   └── index.php
├── storage/
│   ├── logs/
│   └── uploads/
├── tests/
├── .gitignore
├── .env.example
├── composer.json
└── README.md
```

`public/index.php` is the front controller. Route definitions dispatch to controllers; middleware checks authentication, authorization, and CSRF where required; services handle workflows; repositories contain PDO queries; and views render escaped server-side HTML. Keep business logic out of the front controller and views. The `tests/` directory contains focused local integration smoke checks; add a maintained test framework only after one is available in the selected development runtime.

The production and XAMPP Apache virtual host should point its document root at `public/`. On XAMPP, configure a local virtual host rather than exposing the project root through `htdocs`. Deny direct web access to `storage/`, `config/`, `database/`, and `vendor/` as an additional safeguard.

## Proposed database outline

Use InnoDB, `utf8mb4`, primary and foreign keys, explicit indexes for common searches, and migrations checked into Git. This is a conceptual starting point, not a final schema; confirm fields, retention rules, and workflows before creating migrations.

| Area | Proposed tables | Notes |
| --- | --- | --- |
| Staff access | `users`, `roles`, `user_roles`, `login_attempts` | Store password hashes only. Seed the initial `admin` role; add granular permissions when real role requirements are known. |
| Applications | `registrations`, `registration_guardians` | Keep application status and the submitted details needed to review it. Suggested statuses: `pending`, `under_review`, `approved`, `rejected`, `needs_information`, `cancelled`. |
| Membership | `members`, `member_guardians`, `member_programs` | Create/link a member when an application is approved. Deactivate or suspend records instead of deleting them. |
| Academy programs | `programs`, `program_schedules`, `coaches`, `program_coaches` | Keep published content manageable by staff; confirm whether a program can have multiple coaches and schedules. |
| Public content | `events`, `announcements`, `media`, `contact_messages`, `settings` | Publish/unpublish content explicitly. Store uploaded files outside the web root and keep metadata in `media`. |
| Accountability | `audit_logs` | Record actor, action, target, timestamp, and safe metadata for important administrative changes. Avoid secrets and unnecessary sensitive details in log payloads. |

Important integrity and privacy choices:

- A registration is a durable application record. Approval should transactionally create or link a member and record the administrative action.
- Keep submitted application data separate from the current member profile where a historical snapshot is needed; document which fields are copied and when.
- Use foreign keys and database constraints for relationships and statuses. Normalize email addresses before enforcing uniqueness where appropriate.
- Collect only fields needed for the confirmed workflow. In particular, do not make gender a required field without a demonstrated business need.
- Decide how long rejected applications, inactive members, contact messages, and audit records are retained before launch.
- Do not add payments, analytics, or other data collection until the academy confirms a need and its privacy implications.

## Authentication and authorization

Use PHP's `password_hash()` and `password_verify()` with PHP's supported password defaults. Use PHP sessions with strict mode, cookie-only sessions, `HttpOnly`, `SameSite=Lax`, and `Secure` cookies on HTTPS. Regenerate the session ID after login and privilege changes; provide a real logout endpoint that invalidates the session.

Protect the admin area at the routing/middleware layer. Authentication answers who signed in; authorization separately checks whether that account may perform each action. Do not rely on hiding buttons in the UI. Start with an `admin` role, but centralize checks so later roles can be added without duplicating security logic.

Rate-limit login attempts and return generic login errors that do not reveal whether an email address exists. Create the first administrator through a documented local/hosting CLI setup step that prompts for credentials; do not ship a default password or public registration route for staff accounts. Log successful and failed administrative access events without recording passwords, session IDs, or raw secrets.

## Registration workflow

1. Render the registration form with only fields approved by the academy.
2. On submission, validate all fields on the server, normalize values, enforce size limits, and verify the requested program is available.
3. Check likely duplicates without exposing another applicant's personal data.
4. Insert the registration and any guardian details in a transaction with `pending` status.
5. Show a confirmation that does not disclose private submitted information. Send email only after the academy chooses a provider and confirms message content and handling of minors' information.
6. Staff review the application, record notes/actions, and set an allowed status. An approval transaction creates or links the member and records an audit event.

The exact form fields, age/minor policy, guardian requirements, duplicate-match rules, consent wording, and notification behavior are open product decisions. Do not infer them from the technical outline.

## Request and security architecture

```text
Browser
  → Apache serves public assets or forwards the request to public/index.php
  → front controller matches an explicit route
  → middleware applies session, authentication, authorization, and CSRF checks
  → controller validates the request and calls a service
  → service coordinates repositories and a database transaction when needed
  → PDO prepared statements read/write MySQL or MariaDB
  → view renders output with context-appropriate HTML escaping
```

Security baseline for implementation:

- Use PDO prepared statements for every value-bearing query; do not interpolate user input into SQL. Keep production database accounts least-privileged.
- Validate input on the server and escape output at render time. Rich text, if approved, needs a strict allowlist sanitizer; never render arbitrary submitted HTML.
- Require CSRF tokens on state-changing form requests. Use POST for mutations and apply authorization to each protected operation.
- Use generic production error pages; write useful diagnostic details to protected logs while excluding credentials, tokens, and unnecessary personal data.
- Configure production HTTPS and secure headers, including a considered Content Security Policy, `X-Content-Type-Options`, and frame/referrer protections. Do not trust headers as a substitute for safe code.
- If uploads are required, allow only necessary image formats, enforce size limits, verify actual file type, generate server-side filenames, reject executable content, and serve files through controlled URLs. Do not accept arbitrary HTML, PHP, or SVG uploads.
- Keep secrets out of source control. Read production values from the hosting environment; local XAMPP values belong in local Apache environment configuration or a private configuration file outside the repository and web root.
- Keep database backups encrypted/access-controlled, off the application host, and test restoration before launch.

## Dependencies

Start with no application framework and no third-party runtime package dependency. Use PHP PDO and built-in password/session functions. Use Composer for PSR-4 autoloading and dependency management; the starter `composer.json` declares PHP `^8.4` and the project namespace without adding third-party packages. Composer itself is not currently available in the local environment, so dependency installation and Composer-based autoloading remain for setup after the runtime is upgraded.

Add a package only for a confirmed need, such as a maintained mail library after email delivery requirements are settled. Select and document any testing or image-processing package in its relevant phase. Do not add a general-purpose framework or a dotenv parser by default. The supported PHP, Apache, and database versions should be chosen together when the local and hosting environments are confirmed.

## Local XAMPP workflow

1. Install a maintained XAMPP/PHP bundle compatible with the confirmed deployment PHP version. The installed XAMPP currently reports PHP 8.0.30, which is unsupported; do not use that version for production. Verify Apache, PHP, MySQL/MariaDB, Composer, and required PHP extensions after choosing the supported version.
2. Create a local Apache virtual host whose document root is the project's `public/` directory. Add the local hostname to the Windows hosts file and enable the needed Apache rewrite configuration.
3. Create a local database and restricted local database user; apply versioned migrations rather than editing production schemas manually.
4. Set local database/session configuration outside Git and outside `public/`. Use fake development data only.
5. Run Apache and MySQL through the XAMPP control panel and visit the local hostname. Keep phpMyAdmin and development error details unavailable from the public internet.
6. Confirm the first admin setup procedure and backups before the eventual production deployment.

Exact version numbers and local machine steps should be recorded after the target hosting environment and the developer's installed XAMPP version are known.

## Git and GitHub workflow

The folder is not currently a Git repository and has no configured remote. During project setup, initialize Git, add a `.gitignore` before application configuration, and create a private GitHub repository only after the owner selects or creates it. Do not push credentials, local configuration, `vendor/`, logs, uploads, cache files, or real applicant/member data.

Keep `README.md`, migration files, and documentation in Git. `.env.example` may describe variable names with empty or clearly fake values, but the application should not depend on committing `.env`. Work in small, reviewable changes and use the specification's conventional commit style (for example, `docs: add initial architecture proposal`). Back up the repository separately; GitHub is not a database backup.

## Decisions needed before implementation

- Official logo and exact brand colors; address, phone, email, and social links.
- Academy history, mission, programs, schedules, coach details, events, and approved photographs.
- Registration fields, age/minor and guardian rules, consent text, duplicate handling, application review states, and retention periods.
- Membership approval/enrollment rules and whether capacity or waitlists are required.
- Staff accounts, role boundaries, and who may export member records.
- Email notifications, sender domain, and provider, if email is required.
- Hosting provider, supported runtime/database versions, domain, HTTPS, backup policy, and deployment access.
- Whether analytics or other third-party services are wanted.

## Project status at the time of this architecture draft

This is a historical checkpoint from the architecture phase, not the current feature tracker. See [development progress](development-progress.md) and [local setup](local-setup.md) for current implementation and XAMPP state. At this checkpoint, the discovery proposal had been reviewed, GitHub setup was deferred, the responsive homepage and design-system styles existed, and a local XAMPP database had staff-identity and login-rate-limit migrations recorded.

At this checkpoint, staff authentication included a secure login form, session rotation and timeouts, CSRF protection, HMAC-based rate limiting, generic errors, separate role authorization, a first-admin CLI setup script, and a protected admin area. The dashboard, staff-account list, and audit log had also been added. Later feature and account changes are tracked in the current progress document.

The local versions recorded at this checkpoint were PHP 8.0.30 and MariaDB 10.4.32, and Composer was not installed. Do not use this stack for production or real personal information. Hosting, domain, HTTPS, backups, and production migration remain undecided.
