# Academy Website and Management Features

## Current requirements from the academy owner

- Official project name: Central Volleyball Academy.
- Visual colors: yellow and blue. They are represented by CSS variables in `public/assets/css/site.css`; adjust the exact shades when official brand values are supplied.
- Staff can update the academy introduction, about text, mission, vision, coaching philosophy, and public contact details.
- Staff can add coach biographies, roles, experience, qualifications, and profile photos using academy-approved information. Coach profiles are private drafts until explicitly approved and published.
- Staff can search and update player records, review online applications, and manage a public photo/video gallery.
- Visitors can submit a player application online.

## Registration fields and workflow

The first local form collects applicant first and last name, date of birth, email or phone, program interest, and optional previous volleyball experience. It does not ask for gender, home address, or emergency-contact details. Applicants below the configured adult-age threshold must provide a parent/guardian name, relationship, and at least one guardian contact method.

`REGISTRATION_ADULT_AGE` sets that threshold and currently defaults to 18. The academy must confirm the correct age and guardian rules for its location before accepting real applications. Applicants are not automatically accepted: public submissions start as `pending`, staff can mark them under review, approved, rejected, needing information, or cancelled, and approval creates a player record in the same database transaction.

Before inserting an application, the server checks active/reviewed applications and existing player records for the same first name, last name, and date of birth plus a matching applicant or guardian email/phone. Phone punctuation and name/email case are normalized. A keyed MySQL advisory lock serializes simultaneous checks for the same name and birth date. Rejected/cancelled applications may be resubmitted unless a player record already exists. New and repeated submissions receive the same generic confirmation so the public page does not reveal whether a record matched. Staff should review possible same-name/same-birth-date cases.

The protected application list supports name/email search, applicant/guardian phone, program-interest text, received-date range, status, and pagination. The protected player list supports name/email search, player/guardian phone, program-interest text, date-of-birth range, status, and pagination. These filters run in database queries. Program interest remains plain text, so its filter matches text rather than a normalized program ID.

The registration page currently displays a development-only warning. There is no email confirmation, online payment, program capacity check, public applicant-status page, or legally reviewed privacy/consent notice. The academy must approve the privacy notice, lawful collection, guardian process, access boundaries, and retention period before live intake.

Valid submissions are limited to ten per network per hour. The database stores only a secret-keyed HMAC of the network address, not the raw address, and removes old rate-limit rows after a day. This limit is a basic abuse control; production monitoring and tuning depend on the selected host and expected traffic.

## Player records

Approval creates a private player/member record linked to its application. Staff can search and edit the limited application details and set the player status to active, inactive, or suspended. The app does not physically delete application/player records. The public website does not list player names by default; publishing a roster needs explicit academy approval and documented permission for minors.

## Academy information

The singleton `academy_content` record stores plain text for the homepage introduction, about text, mission, vision, coaching philosophy, and public email, phone, and location. HTML is not accepted as rich text; views escape every stored value. Blank public contact fields stay hidden instead of being filled with invented details.

## Coach profiles

Staff can add or edit each coach's name, role, biography, experience, qualifications, display order, and optional profile photograph. Experience and qualifications are optional plain text and are escaped on output. New profiles are drafts; publishing requires an explicit confirmation that the information is academy-approved for public display. Profile photographs are selected from published gallery images, so staff must upload and approve a photo in the Gallery section first. The public homepage displays only published coach profiles. No coach biography, role, experience, qualification, or photograph is prefilled or invented.

## Staff accounts and roles

Administrators can create accounts, change staff names and email addresses, assign the Administrator or Academy Staff role, activate or deactivate accounts, and set a replacement password. Passwords are stored only as hashes; a new account requires an initial password that the administrator must share through a private channel. At least one active administrator must remain, and administrators cannot demote or deactivate their own account.

Each account has one role. Both roles can use the academy management workflows. Only administrators can manage staff accounts and view the audit log. These are the two roles currently supported by the interface; additional permission groups require a deliberate permission design before they are added.

## Application decision confirmation

Changing an application status now opens a separate confirmation screen. The selected action and notes are held in the signed-in session, expire after ten minutes, and cannot be changed by editing the confirmation form. A second CSRF-protected POST applies the decision. The application is locked and its original status is checked before the write; approval and player creation remain one transaction.

## Photo and video gallery

Staff may upload JPEG, PNG, or WebP images up to 8 MB and MP4 or WebM videos up to 50 MB. The app checks MIME type using PHP Fileinfo, checks image headers and dimensions, replaces the supplied filename with a random storage key, and stores file bytes under private `storage/uploads/gallery/` outside `public/`. The front controller streams only published items and supports byte ranges for video playback. Drafts can be previewed only from the authenticated admin gallery.

Uploads start as drafts. Publishing requires an explicit staff confirmation that the academy has permission to share the media. The app does not currently store a signed media release or prove parental permission; the academy must keep and follow its approved consent process. Gallery captions and descriptions are plain text and escaped. Archived media is hidden without deleting the file.

For XAMPP video uploads up to 50 MB, PHP's `upload_max_filesize` and `post_max_size` must be configured at or above the gallery limits in `C:\xampp\php\php.ini`, then Apache restarted. See [local setup](local-setup.md).

## Training programs

Staff can create, edit, publish, or archive a program with a title, category, optional age group, plain-text description, up to ten short schedule notes, and assignments to published coach profiles. Optional fee details and capacity can be left blank until the academy confirms them. New programs start as drafts; publishing requires an explicit confirmation that the details are academy-approved. Only published programs, their schedule notes, and still-published assigned coaches appear on the public homepage. The online registration form is not yet tied to program capacity or enrollment.

## Events and activities

Staff can create and edit activities with title, description, start and end date/time, location, registration state (open, closed, or not required), and an optional photo from the published gallery. Event states are draft, published, cancelled, and archived. Publishing requires academy approval; a previously public event marked cancelled remains on the upcoming-events section with a cancellation label, while drafts and archived events remain private. Times are entered as local wall-clock values, so confirm the academy timezone and configure the server consistently before publishing real event dates. The current event record does not include an external sign-up URL or attendee list.

## Code map

- `database/migrations/003_create_academy_features.sql`: additive tables for content, applications, players, gallery metadata, and short-lived registration limits.
- `database/migrations/004_create_coaches.sql`: stores staff-managed coach profiles and a reference to an approved gallery photograph.
- `database/migrations/005_create_programs_and_events.sql`: stores staff-managed programs, schedule notes, coach assignments, and events.
- `database/migrations/006_staff_roles_and_coach_experience.sql`: adds the Academy Staff role and optional coach experience text.
- `app/Support/RegistrationValidator.php`: server-side field, date, adult/guardian, contact, and consent checks.
- `app/Controllers/Public/RegistrationController.php`: CSRF verification, HMAC rate limiting, application creation, and post/redirect/get confirmation.
- `app/Controllers/Admin/AcademyContentController.php`: editable public information with audit logging.
- `app/Controllers/Admin/CoachesController.php` and `app/Repositories/CoachRepository.php`: draft-first coach profiles and approved public display.
- `app/Controllers/Admin/ProgramsController.php` and `app/Repositories/ProgramRepository.php`: program editing, approved coach assignments, schedule replacement, and public-only published reads.
- `app/Controllers/Admin/EventsController.php` and `app/Repositories/EventRepository.php`: local date/time validation, approved gallery-image selection, publication/cancellation workflow, and upcoming public reads.
- `app/Controllers/Admin/RegistrationsController.php` and `app/Repositories/RegistrationRepository.php`: application review and atomic player creation on approval.
- `app/Controllers/Admin/StaffAccountsController.php` and `app/Repositories/UserRepository.php`: administrator-only staff account management, role assignment, account status, and password hashing.
- `app/Repositories/RegistrationRepository.php`: duplicate matching across active applications/player records and database-side application filters.
- `app/Controllers/Admin/MembersController.php`: paginated, private player search and updates.
- `app/Repositories/MemberRepository.php`: database-side player filters and dashboard total/approved member counts.
- `app/Controllers/Admin/GalleryController.php` and `app/Controllers/Public/GalleryMediaController.php`: upload validation, private storage, moderation, and safe media streaming.
- `app/Views/public/home.php` and `app/Views/public/registration.php`: public, escaped content and online application form.

All write forms use CSRF tokens. Database operations use prepared statements. Changes to public content, player records, application decisions, and gallery visibility are recorded in the audit log without copying personal details into the log.
