# Student Web Hosting Platform

Phase 1 provides a Laravel 12 control panel with account registration, plan selection, a customer dashboard, website project CRUD, and a Filament 4 admin panel.

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

Phase 1 intentionally does not upload files, deploy containers, provision databases, bill customers, or collect runtime usage. Those capabilities belong to later phases described in `AGENTS.md`.
