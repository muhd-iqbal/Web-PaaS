# Student Web Hosting Platform

Phases 1–5 provide a Laravel 12 control panel with account registration, subscription billing, project and file management, queued Docker deployment, and managed MariaDB databases for PHP projects.

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
# Set LETSENCRYPT_EMAIL and HOSTING_CONTROL_PANEL_HOSTNAME in .env
sudo install -d -o root -g root -m 700 letsencrypt
sudo touch letsencrypt/acme.json
sudo chown root:root letsencrypt/acme.json
sudo chmod 600 letsencrypt/acme.json
sh render-control-panel-config.sh .env
docker compose up -d
```

Traefik routes the configured control-panel hostname to host Nginx at `http://host.docker.internal:8080` by default. Run production Nginx on that internal port and keep it closed in both the VPS and cloud firewalls. To change the panel domain later, update `HOSTING_CONTROL_PANEL_HOSTNAME` in `docker/traefik/.env`, update Laravel's `APP_URL`, update DNS, and recreate Traefik.

Run Laravel's queue continuously under systemd or Supervisor:

```bash
php artisan queue:work --queue=default --timeout=900 --tries=1
```

The operating-system user running the queue must be allowed to use Docker. Treat that user as highly privileged and do not expose its account to customers. Runtime images are built automatically on first use from `docker/runtimes`; both run as non-root users on port 8080. Customer containers receive no published host ports, no Linux capabilities, a read-only root filesystem and website mount, a process limit, and their own Docker bridge network.

See [`docker/README.md`](docker/README.md) for the production checklist and lifecycle details.

## Managed database setup

Start the shared MariaDB service before provisioning databases:

```bash
cd docker/database
cp .env.example .env
# Set a long random MARIADB_ROOT_PASSWORD in .env
docker compose up -d
```

Use the same password for `HOSTING_DATABASE_ADMIN_PASSWORD` in Laravel. The admin port is bound to `127.0.0.1:3307`; customer containers connect through the private `hosting_database` Docker network as `hosting-database:3306`.

Run Laravel's scheduler so account database usage is refreshed and plan quotas are enforced:

```bash
php artisan schedule:work
```

Production may use the normal once-per-minute cron entry instead. Accounts exceeding their shared database allowance are switched to read-only SQL privileges until an administrator increases the allowance or reduces/restores usage. Credentials are encrypted with Laravel's application key and injected into PHP containers on redeployment.

## Stripe subscription billing

Paid plans use Stripe Checkout and Stripe's hosted customer portal. Install dependencies with `composer install`, then configure:

```dotenv
STRIPE_KEY=pk_live_or_test_value
STRIPE_SECRET=sk_live_or_test_value
STRIPE_WEBHOOK_SECRET=whsec_value
```

Create a recurring monthly Stripe Price for each paid plan and enter its `price_...` identifier in the Filament plan editor. Configure the Stripe customer portal to allow payment-method changes, cancellations, and plan switching between the same configured prices.

Register this HTTPS webhook endpoint in Stripe:

```text
https://your-control-panel.example.com/stripe/webhook
```

Subscribe it to `checkout.session.completed`, `customer.subscription.created`, `customer.subscription.updated`, and `customer.subscription.deleted`. Webhook signatures are verified and event IDs are processed idempotently. Paid limits are granted only after Stripe reports `active`, `trialing`, or `past_due`; terminal unpaid/canceled states revoke mutations and queue website suspension.

The existing scheduler also expires internal free trials, so keep `php artisan schedule:work` or the production cron entry running. Billing setup can be tested with Stripe test-mode keys and the Stripe CLI before enabling live mode.

General-purpose resource monitoring and alerting remain intentionally out of scope until Phase 6.
