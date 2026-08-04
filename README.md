# Student Web Hosting Platform

Phases 1–3 provide a Laravel 12 control panel with account registration, plan selection, project and file management, plus queued deployment of isolated static or PHP 8.3 websites to Docker behind Traefik HTTPS routing.

## Local application setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan app:create-admin
php artisan serve
```

The customer application is available at `/`; the administrator panel is at `/admin`. SQLite is the zero-configuration local default. For the target VPS, configure MariaDB/MySQL before migrating.

PHP's `upload_max_filesize` and `post_max_size` must exceed the largest plan upload limit. The seeded Developer plan accepts 250 MB ZIPs, so production should use at least `upload_max_filesize=260M` and `post_max_size=270M`.

ZIPs are validated for safe paths, regular files, permitted extensions and MIME types, a root index file, archive size, extracted size, file count, and account storage quota. Deployments copy validated files to a separate read-only release directory, so edits do not mutate a running website until redeployment succeeds.

## Docker deployment setup

The VPS needs Docker Engine with the Compose plugin. Configure these Laravel environment values:

```dotenv
QUEUE_CONNECTION=database
HOSTING_BASE_DOMAIN=sites.example.com
HOSTING_URL_SCHEME=https
TRAEFIK_CONTAINER_NAME=hosting-traefik
TRAEFIK_CERTIFICATE_RESOLVER=letsencrypt
```

Create DNS `A`/`AAAA` records for `sites.example.com` and `*.sites.example.com` pointing to the VPS. When using Cloudflare, begin with DNS-only records so the Let's Encrypt HTTP challenge can reach Traefik directly.

Start Traefik:

```bash
cd docker/traefik
cp .env.example .env
# Set LETSENCRYPT_EMAIL in .env
touch letsencrypt/acme.json
chmod 600 letsencrypt/acme.json
docker compose up -d
```

Run Laravel's queue continuously under systemd or Supervisor:

```bash
php artisan queue:work --queue=default --timeout=900 --tries=1
```

The operating-system user running the queue must be allowed to use Docker. Treat that user as highly privileged and do not expose its account to customers. Runtime images are built automatically on first use from `docker/runtimes`; both run as non-root users on port 8080. Customer containers receive no published host ports, no Linux capabilities, a read-only root filesystem and website mount, a process limit, and their own Docker bridge network.

See [`docker/README.md`](docker/README.md) for the production checklist and lifecycle details.

Hosted databases, billing, and usage monitoring remain intentionally out of scope for Phase 3.
