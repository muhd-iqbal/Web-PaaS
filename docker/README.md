# Phase 3 Docker operations

## Production checklist

1. Install current Docker Engine and the Compose plugin on the ARM64 VPS.
2. Allow inbound TCP 80 and 443 and keep the Docker daemon/socket inaccessible from the public network.
3. Point the hosting base domain and its wildcard DNS record to the VPS.
4. Configure `docker/traefik/.env`, create `letsencrypt/acme.json` with mode `600`, and start the Traefik Compose service.
5. Configure Laravel's `HOSTING_*`, `TRAEFIK_*`, database, and database-backed queue values.
6. Run migrations and keep a Laravel queue worker supervised.
7. Verify Docker access as the queue-worker user with `docker info`.

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
- Container logs are bounded when displayed; centralized monitoring is deferred to Phase 6.
- Back up Laravel data, private project files, deployment releases, and `traefik/letsencrypt/acme.json` according to your VPS backup policy.
