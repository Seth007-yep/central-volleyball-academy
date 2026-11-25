# Starter Code Walkthrough

This guide follows the current public and admin request paths, including editable academy content, coach profiles, programs, events, player registration, and private gallery media.

## What happens when `/` is requested

```text
Browser requests a route
  → public/index.php loads the bootstrap and fallback/Composer autoloader
  → config/routes.php matches an explicit method and path
  → protected admin routes pass through AdminAuthMiddleware
  → a controller coordinates the request with services and repositories
  → repositories use prepared statements with the PDO connection
  → views escape dynamic HTML and load the shared stylesheet
```

### Files to read in order

1. [`public/.htaccess`](../public/.htaccess) tells Apache not to list directories and forwards unknown paths to the front controller.
2. [`public/index.php`](../public/index.php) is the only public PHP entry point. It normalizes the URL path, sets a few response headers, and serves `/`, a 404, or a 405 response.
3. [`bootstrap/app.php`](../bootstrap/app.php) loads configuration and ensures PHP errors are not displayed publicly by default.
4. [`config/app.php`](../config/app.php) reads the optional `APP_ENV` and `APP_DEBUG` process environment variables. PHP does not parse `.env` files on its own.
5. [`app/Support/Html.php`](../app/Support/Html.php) centralizes context-appropriate HTML escaping for text and quoted attributes.
6. [`config/routes.php`](../config/routes.php) lists explicit public/admin routes. [`public/index.php`](../public/index.php) dispatches them and adds the current security headers.
7. [`app/Controllers/Public/HomeController.php`](../app/Controllers/Public/HomeController.php) reads the editable academy profile, published coach profiles/media/programs, and upcoming published events. It fetches program schedules and assigned coaches in batches so the page does not query once per program card. [`app/Views/public/home.php`](../app/Views/public/home.php) escapes every stored value.
8. [`app/Controllers/Public/RegistrationController.php`](../app/Controllers/Public/RegistrationController.php) validates the form, applies a short-lived HMAC network limit, serializes the duplicate check with a keyed advisory lock, and creates only non-duplicate applications. New and repeated posts share a generic confirmation. [`RegistrationValidator.php`](../app/Support/RegistrationValidator.php) contains the field and age/guardian checks.
9. [`app/Controllers/Admin/RegistrationsController.php`](../app/Controllers/Admin/RegistrationsController.php) and [`RegistrationRepository.php`](../app/Repositories/RegistrationRepository.php) handle private review, name/email/phone/program/date/status filters, and pagination. Approval and player creation are one transaction; staff actions are recorded without applicant details.
10. [`app/Controllers/Admin/MembersController.php`](../app/Controllers/Admin/MembersController.php) and [`MemberRepository.php`](../app/Repositories/MemberRepository.php) provide private player name/email/phone/program/date-of-birth/status filters and dashboard total/approved member counts.
11. [`app/Controllers/Admin/AcademyContentController.php`](../app/Controllers/Admin/AcademyContentController.php), [`CoachesController.php`](../app/Controllers/Admin/CoachesController.php), [`ProgramsController.php`](../app/Controllers/Admin/ProgramsController.php), [`EventsController.php`](../app/Controllers/Admin/EventsController.php), and [`GalleryController.php`](../app/Controllers/Admin/GalleryController.php) implement protected content-management pages. Coach/event photos must already be published gallery images. Upload bytes remain outside `public/`; [`GalleryMediaController.php`](../app/Controllers/Public/GalleryMediaController.php) streams only published items publicly.
12. The database structure for academy content, registrations, players, and the gallery is in [`003_create_academy_features.sql`](../database/migrations/003_create_academy_features.sql); coach profiles are in [`004_create_coaches.sql`](../database/migrations/004_create_coaches.sql); programs, schedules, coach assignments, and events are in [`005_create_programs_and_events.sql`](../database/migrations/005_create_programs_and_events.sql). Migration [`006_staff_roles_and_coach_experience.sql`](../database/migrations/006_staff_roles_and_coach_experience.sql) adds the Academy Staff role and coach experience. Duplicate matching reuses existing registration/member identity fields and stores no extra identifier.
13. The admin and public forms use the shared responsive styles in [`public/assets/css/site.css`](../public/assets/css/site.css). See the [design-system guide](design-system.md).
14. [`config/database.php`](../config/database.php) reads process environment values or an ignored local-only configuration, and [`app/Support/Database.php`](../app/Support/Database.php) constructs PDO.

## A few security details

- `htmlspecialchars()` is applied at the point where a value enters HTML. When database-backed content arrives, keep this output escaping even if that content was validated when saved.
- `ENT_QUOTES` encodes both quote types, and `ENT_SUBSTITUTE` replaces malformed byte sequences rather than producing invalid output.
- The route selection uses the URL path only. Query-string values do not select files or become PHP include paths.
- The 404 and 405 views are fixed files. No request value is concatenated into a filesystem path.
- `APP_DEBUG` defaults to `false`. Production should set it explicitly to `false`; do not enable detailed error output on a public server.
- `config/local.php` is ignored by Git and must stay above `public/`; it contains generated local credentials and an HMAC key, not application source.
- `.htaccess` is effective only when Apache allows overrides and `mod_rewrite` is enabled. The XAMPP virtual host notes cover that setup.
- Database connection code uses values from process environment variables; it does not read a `.env` file. It configures prepared statements, but each query still needs to bind user-provided values.

## Current limits

Program and event management is implemented locally, including admin editing, approval before publication, and public display of published content. The academy still needs to provide the approved program and event details and confirm its timezone before publishing dates. Event sign-up links, payments, and email delivery are not implemented. Administrators can manage staff accounts and assign the Administrator or Academy Staff role; both roles can use academy workflows, while user management and audit-log access remain administrator-only. Fine-grained per-action permissions are not implemented. Application status decisions use a separate session-bound confirmation step. Coach profile management includes approved experience text, but actual approved coach information is still needed. Player names stay private from public routes by default. The registration page is marked for development until the academy approves privacy, guardian, and retention rules. Gallery publication requires a staff permission attestation; the academy still needs an approved media-release process. Yellow and blue are the selected colors, while exact hex values remain provisional until official branding is supplied.

The `composer.json` file declares the intended PHP version and PSR-4 namespace. Composer is not installed locally yet, so `bootstrap/autoload.php` uses a small project-only PSR-4 fallback until Composer can generate `vendor/autoload.php`.

## Local smoke checks

The master-plan smoke checks can be run from the VS Code terminal with the bundled XAMPP PHP CLI. The full coverage and the checks that remain manual are listed in the [quality-check results](quality-checks.md).

```powershell
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

The checks use synthetic input only. Database-backed workflows roll their fixtures back, the read-model check is read-only, and the upload-policy check removes its temporary files.
