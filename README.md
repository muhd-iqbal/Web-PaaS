# Student Web Hosting Platform

Phases 1–2 provide a Laravel 12 control panel with account registration, plan selection, a customer dashboard, website project CRUD, secure ZIP upload and extraction, private project file management, and a Filament 4 admin panel.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan app:create-admin
php artisan serve
```

The customer application is available at `/`; the administrator panel is at `/admin`.

SQLite is the zero-configuration local default. For the target VPS, set `DB_CONNECTION=mysql` and the matching MariaDB/MySQL host, port, database, username, and password values before migrating.

PHP's `upload_max_filesize` and `post_max_size` must be configured above the largest plan upload limit. The seeded Developer plan accepts 250 MB ZIPs, so production should use at least `upload_max_filesize=260M` and `post_max_size=270M`.

ZIPs are validated for safe paths, regular files, permitted extensions and MIME types, an appropriate root index file, archive size, extracted size, file count, and account storage quota. Validated files are stored privately under an isolated user/project directory.

Docker deployment, hosted databases, billing, bandwidth accounting, and runtime monitoring remain intentionally out of scope. Those capabilities belong to later phases described in `AGENTS.md`.
