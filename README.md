# Central Volleyball Academy

Local development project for the Central Volleyball Academy public website and academy management application.

## Project status

The project contains a responsive public homepage, editable academy information, coach profiles with experience and qualifications, training programs and events, a local registration workflow with a separate decision-confirmation step, private player records, a moderated photo/video gallery, and protected staff administration. Administrators can manage staff accounts and assign the Administrator or Academy Staff role.

See [development progress](docs/development-progress.md), [the initial architecture](docs/initial-architecture.md), [code walkthrough](docs/code-walkthrough.md), [design-system guide](docs/design-system.md), [database schema notes](docs/database-schema.md), [authentication guide](docs/authentication.md), [security status](docs/security.md), [local setup notes](docs/local-setup.md), [academy feature notes](docs/academy-features.md), [deployment preparation](docs/deployment.md), and [backup and recovery](docs/backup-and-recovery.md) for implementation details and open requirements.

## Academy information

The official name supplied for the project is **Central Volleyball Academy**, and the requested colors are yellow and blue. The official logo, exact color values, contact details, program information, schedules, and other official content still need to be provided. Admin-entered programs and events remain drafts until academy staff approve them; do not publish invented academy information.

## Current development environment

The available XAMPP CLI reports PHP 8.0.30 and MariaDB 10.4.32; both are too old for production use. Composer is not currently installed. The local database is for learning only. Do not enter real player/applicant data until the academy has approved its privacy, guardian, and retention policies. See [local setup notes](docs/local-setup.md) before configuring Apache or adding dependencies. The project source is publicly available at [the Central Volleyball Academy GitHub repository](https://github.com/Seth007-yep/central-volleyball-academy).
