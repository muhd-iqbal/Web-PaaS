#!/usr/bin/env bash

set -Eeuo pipefail

repo=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
cd "$repo"

mode=deploy
case "${1:-}" in
    '') ;;
    --check) mode=check ;;
    -h|--help)
        cat <<'EOF'
Usage: ./deploy.sh [--check]

Deploy the application and Docker services, then run production checks.
Use --check to diagnose without changing the deployment.

Required: .env, docker/traefik/.env, docker/database/.env
Logs:     storage/logs/deploy-YYYYMMDD-HHMMSS.log
EOF
        exit 0
        ;;
    *) echo "Unknown option: $1" >&2; exit 2 ;;
esac

mkdir -p storage/logs
log_file="$repo/storage/logs/deploy-$(date -u +%Y%m%d-%H%M%S).log"
touch "$log_file"
exec > >(tee -a "$log_file") 2>&1

issues=0
warnings=0

heading() { printf '\n== %s ==\n' "$1"; }
ok() { printf '[OK] %s\n' "$1"; }
warn() { warnings=$((warnings + 1)); printf '[WARN] %s\n' "$1"; }
issue() { issues=$((issues + 1)); printf '[FAIL] %s\n' "$1"; }
fatal() { printf '[FATAL] %s\nLog: %s\n' "$1" "$log_file" >&2; exit 1; }
have() { command -v "$1" >/dev/null 2>&1; }
require_command() { have "$1" || fatal "Required command is missing: $1"; }
require_file() { [[ -r $1 ]] || fatal "Missing or unreadable configuration: $1"; }
compose() { docker compose "$@"; }

on_error() {
    local code=$?
    trap - ERR
    printf '\n[FATAL] Command failed at line %s (exit %s).\nLog: %s\n' "$1" "$code" "$log_file" >&2
    exit "$code"
}
trap 'on_error "$LINENO"' ERR

env_value() {
    local line value
    line=$(grep -E "^[[:space:]]*$2=" "$1" | tail -n 1 || true)
    value=${line#*=}
    value=${value%$'\r'}
    if [[ $value == \"*\" && $value == *\" ]] || [[ $value == \'*\' && $value == *\' ]]; then
        value=${value:1:${#value}-2}
    fi
    printf '%s' "$value"
}

container_running() {
    [[ $(docker inspect --format '{{.State.Running}}' "$1" 2>/dev/null || true) == true ]]
}

http_status() {
    curl --silent --show-error --output /dev/null --write-out '%{http_code}' \
        --connect-timeout 5 --max-time 15 "$1" 2>/dev/null || true
}

printf 'Student Hosting deployment — %s UTC\n' "$(date -u '+%F %T')"
printf 'Mode: %s\nRepository: %s\nLog: %s\n' "$mode" "$repo" "$log_file"

heading 'Preflight'
[[ $EUID -ne 0 || $mode == check ]] \
    || fatal 'Run as the deployment user (for example ubuntu), not with sudo.'

for command in bash curl docker getent grep php ss; do require_command "$command"; done
docker compose version >/dev/null 2>&1 || fatal 'Docker Compose v2 is unavailable.'
docker info >/dev/null 2>&1 \
    || fatal "$(id -un) cannot access Docker. Add it to the docker group, then sign out and back in."
php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' \
    || fatal "PHP 8.3+ is required; found $(php -r 'echo PHP_VERSION;')."
ok "$(docker --version)"
ok "$(docker compose version)"
ok "PHP $(php -r 'echo PHP_VERSION;')"

require_file .env
require_file docker/traefik/.env
require_file docker/database/.env
panel_hostname=$(env_value docker/traefik/.env HOSTING_CONTROL_PANEL_HOSTNAME)
letsencrypt_email=$(env_value docker/traefik/.env LETSENCRYPT_EMAIL)
database_password=$(env_value docker/database/.env MARIADB_ROOT_PASSWORD)
configured_database_password=$(env_value .env HOSTING_DATABASE_ADMIN_PASSWORD)
app_environment=$(env_value .env APP_ENV)
app_debug=$(env_value .env APP_DEBUG)
app_key=$(env_value .env APP_KEY)

[[ -n $panel_hostname && $panel_hostname != panel.example.com ]] \
    || fatal 'Set HOSTING_CONTROL_PANEL_HOSTNAME in docker/traefik/.env.'
[[ -n $letsencrypt_email && $letsencrypt_email != admin@example.com ]] \
    || fatal 'Set LETSENCRYPT_EMAIL in docker/traefik/.env.'
[[ -n $database_password && $database_password != replace-with-a-long-random-password ]] \
    || fatal 'Set MARIADB_ROOT_PASSWORD in docker/database/.env.'
[[ $configured_database_password == "$database_password" ]] \
    || fatal 'HOSTING_DATABASE_ADMIN_PASSWORD in .env must match docker/database/.env.'
[[ $app_environment == production ]] || fatal 'Set APP_ENV=production in .env.'
[[ $app_debug == false ]] || fatal 'Set APP_DEBUG=false in .env.'
ok "Production configuration is present for $panel_hostname."

compose --env-file docker/traefik/.env -f docker/traefik/compose.yaml config --quiet
compose --env-file docker/database/.env -f docker/database/compose.yaml config --quiet
ok 'Docker Compose configuration is valid.'

if [[ $mode == deploy ]]; then
    heading 'Build and permissions'
    for command in composer npm sudo; do require_command "$command"; done
    sudo -v
    composer install --no-dev --optimize-autoloader --no-interaction
    composer check-platform-reqs --no-dev
    npm ci
    npm run build
    [[ -n $app_key ]] || php artisan key:generate --force
    sudo chown -R "$(id -un):www-data" storage bootstrap/cache
    sudo find storage bootstrap/cache -type d -exec chmod 2775 {} +
    sudo find storage bootstrap/cache -type f -exec chmod 0664 {} +
    sudo install -d -o "$(id -un)" -g www-data -m 0770 storage/app/docker-config
    ok 'Dependencies, assets, key, and Laravel permissions are ready.'

    heading 'Host firewall and Traefik'
    sudo install -d -o root -g root -m 700 docker/traefik/letsencrypt
    sudo touch docker/traefik/letsencrypt/acme.json
    sudo chown root:root docker/traefik/letsencrypt/acme.json
    sudo chmod 600 docker/traefik/letsencrypt/acme.json
    sudo install -d -o root -g www-data -m 750 docker/traefik/logs
    sudo touch docker/traefik/logs/access.json
    sudo chown root:www-data docker/traefik/logs/access.json
    sudo chmod 640 docker/traefik/logs/access.json

    if have ufw && sudo ufw status | grep -q '^Status: active'; then
        sudo ufw allow 80/tcp
        sudo ufw allow 443/tcp
        ok 'UFW allows HTTP and HTTPS.'
    else
        ok 'UFW is inactive or unavailable; no UFW changes were needed.'
    fi

    sh docker/traefik/render-control-panel-config.sh docker/traefik/.env
    compose --env-file docker/traefik/.env -f docker/traefik/compose.yaml up -d
    ok 'Traefik is up to date.'

    heading 'Database and Laravel'
    compose --env-file docker/database/.env -f docker/database/compose.yaml up -d
    database_health=starting
    for _ in {1..30}; do
        database_health=$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' hosting-database 2>/dev/null || true)
        [[ $database_health == healthy ]] && break
        sleep 2
    done
    [[ $database_health == healthy ]] \
        || fatal "Managed MariaDB is not healthy (status: ${database_health:-missing})."
    php artisan migrate --force
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ok 'Database migrations and Laravel caches completed.'

    if have systemctl; then
        if systemctl list-unit-files web-paas-queue.service 2>/dev/null | grep -q web-paas-queue; then
            sudo systemctl restart web-paas-queue.service
            ok 'Queue service restarted.'
        else
            warn 'web-paas-queue.service is not installed; confirm another queue supervisor is running.'
        fi
        if systemctl list-unit-files web-paas-scheduler.timer 2>/dev/null | grep -q web-paas-scheduler; then
            sudo systemctl restart web-paas-scheduler.timer
            ok 'Scheduler timer restarted.'
        else
            warn 'web-paas-scheduler.timer is not installed; confirm cron runs schedule:run every minute.'
        fi
        sudo systemctl reload nginx 2>/dev/null || warn 'Nginx reload failed; run sudo nginx -t.'
    fi
fi

heading 'Runtime checks'
container_running hosting-traefik && ok 'Traefik container is running.' || issue 'Traefik container is not running.'
container_running hosting-database && ok 'Managed database is running.' || issue 'Managed database is not running.'

listening_ports=$(ss -lntH | awk '{print $4}')
for port in 80 443 8080; do
    if grep -Eq "(^|:)$port$" <<< "$listening_ports"; then
        ok "Host port $port is listening."
    else
        issue "Host port $port is not listening."
    fi
done

upstream_status=$(http_status http://127.0.0.1:8080/up)
[[ $upstream_status == 200 ]] \
    && ok 'Laravel responds through Nginx on 127.0.0.1:8080/up.' \
    || issue "Laravel/Nginx returned HTTP ${upstream_status:-000} on port 8080."

local_http_status=$(curl --silent --output /dev/null --write-out '%{http_code}' \
    --connect-timeout 5 --max-time 10 -H "Host: $panel_hostname" http://127.0.0.1/ 2>/dev/null || true)
case "$local_http_status" in
    301|302|307|308) ok 'Local HTTP reaches Traefik and redirects to HTTPS.' ;;
    *) issue "Local Traefik HTTP check returned ${local_http_status:-000}." ;;
esac

dns_ipv4=$(getent ahostsv4 "$panel_hostname" | awk 'NR == 1 {print $1}' || true)
[[ -n $dns_ipv4 ]] && ok "$panel_hostname resolves to $dns_ipv4." || issue "$panel_hostname has no IPv4 DNS result."

public_ipv4=$(curl --silent --ipv4 --connect-timeout 5 --max-time 10 https://api.ipify.org 2>/dev/null || true)
if [[ -n $public_ipv4 ]]; then
    ok "This VPS reports public IPv4 $public_ipv4."
    [[ -z $dns_ipv4 || $dns_ipv4 == "$public_ipv4" ]] \
        || issue "DNS points to $dns_ipv4, but this VPS reports $public_ipv4."
else
    warn 'Could not determine the VPS public IPv4.'
fi

https_status=000
https_attempts=1
[[ $mode == deploy ]] && https_attempts=6
for ((attempt = 1; attempt <= https_attempts; attempt++)); do
    https_status=$(http_status "https://$panel_hostname/up")
    [[ $https_status == 200 ]] && break
    (( attempt < https_attempts )) && sleep 10
done
[[ $https_status == 200 ]] \
    && ok "Public HTTPS passed: https://$panel_hostname/up" \
    || issue "Public HTTPS returned HTTP ${https_status:-000}."

if [[ $https_status == 200 ]]; then
    admin_html=$(curl --silent --show-error --location --connect-timeout 5 --max-time 20 \
        "https://$panel_hostname/admin" 2>/dev/null || true)
    if grep -Fq "http://$panel_hostname" <<< "$admin_html"; then
        issue 'The admin page still generates insecure HTTP URLs; deploy the trusted-proxy configuration and clear Laravel caches.'
    else
        ok 'The admin page contains no mixed-content URLs for its hostname.'
    fi
fi

heading 'Traefik diagnosis (last 200 lines)'
traefik_logs=$(docker logs --tail 200 hosting-traefik 2>&1 || true)
printf '%s\n' "$traefik_logs"
if [[ $https_status != 200 ]]; then
    grep -q 'acme.json: permission denied' <<< "$traefik_logs" \
        && issue 'ACME storage is still denied; check rootless Docker or user-namespace remapping.' || true
    grep -q 'Timeout during connect (likely firewall problem)' <<< "$traefik_logs" \
        && issue 'Let’s Encrypt cannot reach port 80. Add stateful OCI TCP 80/443 ingress from 0.0.0.0/0.' || true
    grep -Eqi 'rateLimited|too many failed authorizations' <<< "$traefik_logs" \
        && issue 'Let’s Encrypt rate limiting is active; wait for the retry time in the error.' || true
    grep -q 'Unable to obtain ACME certificate' <<< "$traefik_logs" \
        && warn 'Recent Traefik history contains an ACME failure; compare its timestamp with this run.' || true
fi

heading 'Summary'
printf 'Issues: %d\nWarnings: %d\nFull log: %s\n' "$issues" "$warnings" "$log_file"
if (( issues > 0 )); then
    printf '\nFix the [FAIL] items, then run ./deploy.sh --check.\n'
    exit 1
fi
printf '\nDeployment is healthy.\n'
