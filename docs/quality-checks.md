# Quality Check Results

Status updated on 2026-11-25 against the XAMPP copy of Central Volleyball Academy. These results describe the current development environment, not a production launch review.

The nine smoke scripts and browser checks passed on 2026-09-28. A repeat attempt on 2026-11-25 passed PHP syntax, registration validation, and HTML output encoding, but the database-backed smoke tests could not be rerun: both configured local MariaDB accounts were rejected by the local server. No database test rows were written on that attempt. See `config/local.php` locally to check the XAMPP database accounts; its credentials are intentionally excluded from Git.

On 2026-11-25, the local bridge and stylesheet returned 200, registration and admin login returned 200, and an unauthenticated staff route redirected with 303. The homepage returned 500 because its database-backed content query could not authenticate with the current local database account. The bridge now serves the reviewed source from `C:\xampp\htdocs\volleyball\public` at `http://localhost/central_volleyball_academy/`.

## Passed checks

| Area | Result |
| --- | --- |
| PHP syntax | All 79 PHP files pass `php -l`. |
| Registration | Required fields, contact formats, guardian rules, age/date validation, and consent checks pass synthetic validation cases. |
| Duplicate detection and search | Synthetic applications and players verify duplicate matching and phone/program/date filters. SQL-shaped search strings are treated as bound values. Test rows roll back. |
| Admin workflows | Dashboard/read-model checks, coach experience, staff role predicates, review confirmation/stale-state protection, atomic approval/player creation, and program/event lifecycle checks pass. Synthetic rows roll back. |
| Authentication and session security | Active admin/staff and rejected credentials, inactive accounts, generic failure outcomes, login rate limiting, session ID rotation, role checks, logout, CSRF token validation, idle/absolute expiry, and cookie flags pass. Synthetic accounts and rate-limit rows roll back. |
| HTML output security | Hostile markup, quotes, ampersands, and malformed UTF-8 are safely encoded by the shared HTML helper. |
| Gallery upload policy | Byte-derived MIME/extension, invalid content, SVG rejection, oversized images, and missing files are checked. Temporary fixtures are removed. |
| Local HTTP routing and validation errors | `/` and `/registration` return 200; an empty synthetic registration POST with a valid CSRF token returns 422 and an announced `role="alert"` error; unauthenticated `/admin/users` and `/admin/coaches` redirect to sign-in with 303; an unknown route returns 404; an unsupported method returns 405. |
| Response headers | Local pages include CSP, `X-Content-Type-Options`, `X-Frame-Options`, and `Referrer-Policy`. |
| Responsive layout | Homepage and registration viewport emulation at 360×800, 768×1024, 1366×768, and 1920×1080 found no horizontal overflow. All 12 registration controls remain labeled at each size. |
| Accessibility spot checks | Homepage and registration use a main landmark and one page-level heading. Registration has 12 labeled controls, no unnamed buttons/links, no images missing alt text, and keyboard focus moves to the next field with a visible outline. |
| Color contrast tokens | Calculated text/token pairs meet WCAG AA contrast ratios: the lowest checked pair is muted text on the light surface at 5.40:1. Checked pairs also include body text, links, status text, primary/accent buttons, and the focus outline. |

The smoke checks use synthetic data only. Database fixtures are rolled back, and upload-test files are removed. The local Apache checks made no writes.

## Commands

Run from the project root in the VS Code PowerShell terminal:

```powershell
$phpFiles = Get-ChildItem -Recurse -Filter *.php -File
foreach ($file in $phpFiles) { & 'C:\xampp\php\php.exe' -l $file.FullName }

& 'C:\xampp\php\php.exe' .\tests\registration-validation-smoke.php
& 'C:\xampp\php\php.exe' .\tests\registration-duplicate-search-smoke.php
& 'C:\xampp\php\php.exe' .\tests\admin-read-models-smoke.php
& 'C:\xampp\php\php.exe' .\tests\coach-workflow-smoke.php
& 'C:\xampp\php\php.exe' .\tests\admin-workflow-confirmation-smoke.php
& 'C:\xampp\php\php.exe' .\tests\program-event-workflow-smoke.php
& 'C:\xampp\php\php.exe' .\tests\authentication-security-smoke.php
& 'C:\xampp\php\php.exe' .\tests\gallery-upload-policy-smoke.php
& 'C:\xampp\php\php.exe' .\tests\security-output-smoke.php
```

## Checks still needed

- Test on real phones, tablets, and desktop browsers, including text zoom and orientation changes. Viewport emulation does not replace physical-device testing.
- Complete a keyboard-only pass through every public and signed-in admin flow, and review the pages with a screen reader. The current accessibility work is a spot check, not a full WCAG 2.2 AA audit.
- Complete an end-to-end authenticated test with approved synthetic academy accounts, including staff denial from administrator-only pages, form error states, application decisions, player edits, and logout in a browser.
- Submit harmless approved sample media through the actual authenticated multipart upload form and verify publication, private storage, and public streaming. Unit checks do not exercise the browser/server upload transport or every media decoder edge case.
- Recheck HTTPS redirects, HSTS, secure cookies, production response headers, configuration exposure, logging, backup/restore, and supported runtime versions on the chosen staging host.

Local XAMPP reports PHP 8.0.30 and MariaDB 10.4.32. This stack is for synthetic development checks only and is not a supported production target.
