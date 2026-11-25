# Local Setup Notes

## Current machine findings

- XAMPP PHP CLI: `8.0.30` at `C:\xampp\php\php.exe`.
- XAMPP's MySQL client reports MariaDB `10.4.32`; Apache and database binaries are present under `C:\xampp`.
- Composer is not installed or available on `PATH`.
- PHP 8.0 reached end of life on November 26, 2023. The project targets PHP `^8.4`; use a maintained PHP 8.x release for application development and production. Check the [official PHP support schedule](https://www.php.net/supported-versions.php) when selecting the version.
- MariaDB 10.4 maintenance ended June 18, 2024; see [the official MariaDB maintenance schedule](https://mariadb.org/about/).

The local `central_volleyball_academy` database and staff, academy-feature, coach, program, and event migrations are present in XAMPP for development and phpMyAdmin inspection. `bin/configure-local-db.ps1` generated separate app and migration accounts plus the ignored `config/local.php`. The PHP 8.0 CLI is not the project runtime target and must not be used to deploy the application. Do not add PHP 8.4-only features until the local runtime has been upgraded. The bundled MariaDB 10.4 server is unsupported; do not use it for production or real personal data.

The current database records migrations `001_create_staff_identity` through `006_staff_roles_and_coach_experience`. For this already-initialized database, the first migration was recorded once as a baseline; future migrations should use the runner:

```powershell
& 'C:\xampp\php\php.exe' .\bin\migrate.php
```

On a fresh empty local database, run the same command without a baseline; it applies the ordered SQL files and records them in `schema_migrations`.

The app permission script grants the web account the table-level reads and writes needed for staff management, coaches, programs, events, and existing features. It can delete role-mapping rows in `user_roles` only when replacing an account's single role; it cannot delete primary user, member, registration, gallery, coach, program, or event records. Migration `006` does not add a table; it adds the coach experience column and Academy Staff role.

The public app uses a generated `cva_app` database account with table-level permissions. The CLI migration runner uses the separate `cva_migrator` account. On a fresh machine, run the permission script after the migrations so the web account can use the new tables. PowerShell may block local `.ps1` files under the current execution policy; use a process-scoped bypass in the VS Code terminal. This affects only that PowerShell process and does not change the computer's persistent policy:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass -Force
.\bin\grant-app-permissions.ps1
```

The one-time setup script assumes the standard passwordless local XAMPP `root` account; if its credentials were changed, edit the script to supply them securely before running it. It refuses to overwrite an existing `config/local.php` or project database users.

## Configure a local Apache virtual host

After installing a maintained XAMPP/PHP bundle, open the Apache configuration files from the XAMPP installation and:

1. Enable the `mod_rewrite` module if it is not already enabled.
2. Ensure `conf/extra/httpd-vhosts.conf` is included by Apache.
3. Add a local virtual host similar to the example below, adjusting the project path if it changes:

```apache
<VirtualHost *:80>
    ServerName cva.local
    DocumentRoot "C:/Users/Seth/Documents/ChatGPT/volleyball/public"

    <Directory "C:/Users/Seth/Documents/ChatGPT/volleyball/public">
        AllowOverride All
        Require all granted
    </Directory>

    SetEnv APP_ENV local
    SetEnv APP_DEBUG true
</VirtualHost>
```

4. Add `127.0.0.1 cva.local` to the Windows hosts file, then restart Apache.
5. Open `http://cva.local/` in a browser. Only the `public/` folder should be reachable over HTTP.

The virtual host above is for local development only. A public production site needs a maintained runtime, HTTPS, production-safe environment values, restricted database credentials, backups, and deployment-specific server configuration.

## Composer and configuration

After Composer and PHP `^8.4` are available, run `composer install` from the project folder. The current project has no third-party package requirements; Composer provides the PSR-4 autoloader. Until then, `bootstrap/autoload.php` provides a small project-only PSR-4 fallback.

The app reads `APP_ENV` and `APP_DEBUG` from process environment variables using PHP's `getenv()`. It does not read `.env` files. For local Apache, `SetEnv` entries belong in the local virtual-host configuration, which is outside this Git repository. Production secrets should come from the hosting environment or an approved private configuration outside the web root and Git.

Create the initial staff administrator only after the app account and migrations are ready. In the same VS Code terminal, if PowerShell blocks the script, use the same process-scoped bypass shown above, then run:

```powershell
.\bin\create-admin.ps1 -PhpPath 'C:\xampp\php\php.exe'
```

This prompts for an administrator name, email, and a secure password confirmation. It does not create a default account. The workspace has a local-only VS Code setting at `.vscode/settings.json` pointing PHP validation to `C:/xampp/php/php.exe`. Git ignores this file because XAMPP may be installed in a different location on another computer. This setting enables editor diagnostics; it does not make PHP 8.0 a supported runtime.

For gallery video uploads, edit `C:\xampp\php\php.ini` and make sure these limits allow the application's 50 MB video limit plus the multipart form fields:

```ini
upload_max_filesize = 50M
post_max_size = 55M
```

Save the file and restart Apache from the XAMPP Control Panel for the new limits to take effect. The local public registration page is for development only; the academy must approve its privacy, guardian, age-threshold, and retention rules before collecting real applicant information. The age threshold currently defaults to 18 and can be configured with `REGISTRATION_ADULT_AGE` after the academy confirms its rule.
