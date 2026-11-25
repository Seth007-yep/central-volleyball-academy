# Development phases and progress

This checklist follows the supplied Central Volleyball Academy master plan. Phase status records what exists in this local learning project; it does not imply launch readiness.

| Phase | Status | Current result and remaining work |
| --- | --- | --- |
| 1. Discovery | Initial discovery complete; academy decisions remain open | The project name is confirmed. Official branding, public content, registration rules, staff permissions, hosting, and retention policies still need academy input. See [initial architecture](initial-architecture.md). |
| 2. Project setup | Local foundation in place; GitHub backup pending | PHP project, XAMPP database, private local configuration, migrations, and project structure exist. The owner has now requested a private GitHub backup; account connection is pending. Local PHP 8.0.30 and MariaDB 10.4.32 are unsupported and not suitable for production. |
| 3. Design system | Starter system in place | Shared colors, type, spacing, buttons, forms, tables, alerts, focus styles, and responsive rules are in `public/assets/css/site.css`. Exact brand colors and assets remain unconfirmed. |
| 4. Public website | Core public features in progress | The homepage reads editable academy/about/contact content, published coach profiles, published gallery media, published programs with schedules and coach assignments, upcoming published events, and links to online registration. The academy must supply and approve the actual content. |
| 5. Authentication | Local staff authentication and basic role management implemented | Administrator and Academy Staff accounts can sign in. Admins can create/update accounts, assign roles, activate/deactivate accounts, and set replacement passwords; only admins can manage accounts or view audit logs. Session protection, CSRF, rate limits, generic errors, logout, and first-admin CLI setup are in place. |
| 6. Admin dashboard | Core management features in progress | Admins and staff can edit academy information and coach biographies/roles/experience/qualifications/photos; review applications with duplicate detection and a separate decision-confirmation step; filter applications and players by phone, program interest, date, and status; approve applications into player records; update player records; manage gallery photos/videos; and create, edit, publish, or archive programs and events. Dashboard counts include total/approved members, pending registrations, active programs, and upcoming events. Broader settings workflows remain open. |
| 7. Security hardening | Re-review required after new features | Public form validation, CSRF, a short-lived HMAC-based registration limit, output escaping, prepared SQL, protected admin routes, atomic audit logging, and private media storage are implemented for the current scope. Verify these workflows before any real personal data is used. |
| 8. Testing | Local checks partly verified; full release review remains | All 79 PHP files pass syntax checks. Nine smoke scripts and the browser/accessibility checks passed on 2026-09-28. A repeat attempt on 2026-11-25 also passed registration validation and HTML encoding, but database-backed smoke tests could not run because both configured local MariaDB accounts were rejected. Physical-device, screen-reader/WCAG, signed-in browser workflow, multipart upload, and staging-host security checks remain. See [quality check results](quality-checks.md). |
| 9. Deployment | Preparation documented; deployment deferred | Provider-neutral deployment and backup/recovery plans exist, but production work waits until the public/admin features and policy decisions are complete. No host, domain, DNS, supported database target, backup policy, or staging environment has been selected. See [deployment preparation](deployment.md) and [backup and recovery](backup-and-recovery.md). |
| 10. Launch | Not started | Requires completion and review of the product features, content, privacy/legal requirements, security, deployment, and backup/restore work. |

## Admin pages available now

- `/admin`: active staff count and recent access activity.
- `/admin/users`: administrator-only staff account create/update, role assignment, status management, and password reset, with searchable, status-filtered pagination. Supported roles are Administrator and Academy Staff; only administrators can use this page.
- `/admin/audit-logs`: allowlisted activity filter and database pagination. Audit retention needs an academy decision.
- `/admin/academy`: edit the public introduction, about text, mission, vision, coaching philosophy, and contact details.
- `/admin/registrations`: search by name/email and filter by phone, program interest, received-date range, and status; approval creates a private player record.
- `/admin/players`: search by name/email and filter by phone, program interest, date-of-birth range, and status; edit and deactivate player records.
- `/admin/gallery`: upload draft photos/videos, edit captions, and publish or archive items after confirming permission to share them.
- `/admin/coaches`: add and edit academy-approved coach biographies, experience, roles, qualifications, and photos; choose a published gallery image and publish the profile only after confirming approval.
- `/admin/programs`: create and edit program details, schedule notes, published-coach assignments, optional fee/capacity information, and draft/published/archived state.
- `/admin/events`: create and edit event descriptions, local start/end times, location, registration state, optional approved gallery image, and draft/published/cancelled/archived state. Confirm the academy's timezone before publishing dates and times.

Player and applicant records are private to signed-in staff. The public site does not publish player names. The registration form is marked for development use until the academy approves its privacy notice, guardian process, adult-age threshold, access rules, and retention period. Publish no identifiable player media without academy permission.
