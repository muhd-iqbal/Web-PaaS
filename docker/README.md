# Phase 3 Docker operations

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
