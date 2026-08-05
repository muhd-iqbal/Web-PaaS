# Phase 3 Docker operations

## Local development

The Laravel control panel can be tested locally without running customer website containers. The included Traefik configuration expects a public domain and Let's Encrypt, so complete HTTPS deployment testing is better performed on a staging VPS.

Install and prepare the application:

```bash
composer install
npm install
cp .env.example .env # Only when .env does not already exist.
php artisan key:generate
php artisan migrate
npm run build
touch docker/traefik/logs/access.json
```

Run the application, queue worker, and scheduler in separate terminals:

```bash
php artisan serve
```

```bash
php artisan queue:work --tries=1
```

```bash
php artisan schedule:work
```

The application is available at `http://127.0.0.1:8000` and the admin panel at `http://127.0.0.1:8000/admin`. Create an administrator with `php artisan app:create-admin` when needed.

Verify the monitoring commands:

```bash
php artisan monitoring:collect
php artisan usage:import-traefik
php artisan schedule:list
php artisan test
```

Collecting zero container snapshots is expected until a project has a deployed container. The empty access-log file prevents a false missing-log alert when Traefik is not running locally.

## Live deployment runbook

Configure production environment values, including:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://panel.example.com
HOSTING_BASE_DOMAIN=sites.example.com
HOSTING_URL_SCHEME=https
TRAEFIK_ACCESS_LOG_PATH=/absolute/path/to/web_paas/docker/traefik/logs/access.json
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Point the control-panel hostname and wildcard website hostname to the VPS:

```text
panel.example.com
*.sites.example.com
```

Prepare and start Traefik:

```bash
touch docker/traefik/letsencrypt/acme.json
chmod 600 docker/traefik/letsencrypt/acme.json
touch docker/traefik/logs/access.json
chmod 664 docker/traefik/logs/access.json
docker compose -f docker/traefik/compose.yaml up -d
```

Create `docker/traefik/.env` from its example and set `LETSENCRYPT_EMAIL`, `HOSTING_CONTROL_PANEL_HOSTNAME`, and the optional internal upstream before starting Traefik. The Laravel scheduler user must be able to read `logs/access.json`; do not use broad `777` permissions.

```dotenv
LETSENCRYPT_EMAIL=admin@example.com
HOSTING_CONTROL_PANEL_HOSTNAME=panel.example.com
HOSTING_CONTROL_PANEL_UPSTREAM=http://host.docker.internal:8080
TRAEFIK_CERTIFICATE_RESOLVER=letsencrypt
```

Generate the domain-specific dynamic route before starting or recreating Traefik:

```bash
sh docker/traefik/render-control-panel-config.sh docker/traefik/.env
```

The generated `docker/traefik/dynamic/control-panel.yaml` is ignored by Git and its parent directory is mounted read-only into Traefik. Changing the domain requires updating `docker/traefik/.env`, rerunning the generator, and updating Laravel, Nginx, and DNS. The upstream must be a private host service; port 8080 must not be allowed through the VPS or cloud firewall.

Start the managed database and deploy Laravel:

```bash
docker compose -f docker/database/compose.yaml up -d
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Keep this queue worker supervised:

```bash
php artisan queue:work --sleep=2 --tries=1 --timeout=900
```

Run Laravel's scheduler every minute using cron or an equivalent service:

```cron
* * * * * cd /absolute/path/to/web_paas && php artisan schedule:run >> /dev/null 2>&1
```

After requesting a deployed customer website, verify Phase 6:

```bash
php artisan usage:import-traefik
php artisan monitoring:collect
php artisan schedule:list
```

Confirm that monthly bandwidth appears in the customer dashboard, container health and logs appear on project pages, monitoring alerts appear at `/admin/admin-alerts`, and Traefik writes JSON records to its access log.

## Production checklist

1. Install current Docker Engine and the Compose plugin on the ARM64 VPS.
2. Allow inbound TCP 80 and 443 and keep the Docker daemon/socket inaccessible from the public network.
3. Point the hosting base domain and its wildcard DNS record to the VPS.
4. Configure `docker/traefik/.env`, create `letsencrypt/acme.json` with mode `600`, and start the Traefik Compose service.
5. Configure Laravel's `HOSTING_*`, `TRAEFIK_*`, database, and database-backed queue values.
6. Run migrations and keep a Laravel queue worker supervised.
7. Verify Docker access as the queue-worker user with `docker info`.
8. Start `docker/database/compose.yaml`, configure Laravel's matching database-admin password, and keep Laravel's scheduler running every minute.
9. Ensure the Laravel scheduler user can read `docker/traefik/logs/access.json`, or set `TRAEFIK_ACCESS_LOG_PATH` to its host path.

## Lifecycle

Deploy and redeploy operations run asynchronously. The application validates project state, snapshots the intended hostname/runtime in a deployment record, and marks the project as deploying. The worker then:

1. Builds the selected runtime image if it is absent.
2. Copies validated customer files into an isolated release directory.
3. Creates a project-specific bridge network and attaches Traefik.
4. Replaces the deterministic project container with hardened Docker options and Traefik labels.
5. Waits for the runtime health check before committing the release.

If startup fails, the prior release directory is restored and the failure is retained in deployment history. Restart and suspend use the same queued history. Deleting a deployed project queues removal of its container, network, and release files.

Traefik discovers only explicitly labelled containers. It terminates HTTPS and routes each hostname to port 8080 inside that project's private network. Customer containers are never given the Docker socket or a writable host directory.

## Operational notes

- Changing a project's slug or runtime, uploading a replacement ZIP, or deleting files marks an active project as needing redeployment.
- Project edits and file mutations are blocked while an operation is running.
- The admin panel provides deploy, restart, suspend, recent logs, and immutable deployment history.
- Container logs are bounded to 500 lines for customers and 1,000 lines at the runtime boundary.
- Back up Laravel data, private project files, deployment releases, and `traefik/letsencrypt/acme.json` according to your VPS backup policy.
- Back up the `database-data` Docker volume. Deleting a managed database or its project permanently removes that project's schema and SQL user.

## Managed databases

MariaDB is bound only to the host loopback interface for control-panel administration. PHP website containers with provisioned databases join the shared `hosting_database` network in addition to their project routing network. Each receives a random, project-specific SQL user whose privileges are limited to its own schema.

The plan's database allowance is account-wide. `php artisan databases:refresh-usage` reads schema sizes from `information_schema`; the scheduler runs it every 15 minutes. If total usage exceeds the allowance, every managed database on that account is changed to read-only privileges. Returning access to writable mode may require an administrator to increase the plan limit or reduce database storage directly before refreshing usage.

## Monitoring and bandwidth

Traefik writes structured access records without request headers to `docker/traefik/logs/access.json`. `usage:import-traefik` incrementally checkpoints that file and attributes response bytes to the `project-{id}@docker` router. Monthly bandwidth is account-wide; an alert is raised at the configured warning threshold and active sites are queued for suspension when the plan allowance is exceeded.

`monitoring:collect` samples deployed containers with one-shot Docker stats and inspect calls. CPU, memory, process count, health, restart count, and out-of-memory state are retained for 30 days by default. Open, deduplicated warnings are available under Monitoring alerts in Filament. `monitoring:prune` removes expired snapshots and resolved alerts.

Configure external rotation for the access log using copy-truncate or a rename-and-recreate policy. The importer detects both truncation and inode changes. Do not place access logs or Docker socket access on a public path.
