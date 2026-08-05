#!/bin/sh

set -eu

script_directory=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
environment_file=${1:-}

if [ -n "$environment_file" ]; then
    if [ ! -r "$environment_file" ]; then
        echo "Traefik environment file is not readable: $environment_file" >&2
        exit 1
    fi

    set -a
    # This operator-controlled file must contain shell-compatible KEY=value entries.
    . "$environment_file"
    set +a
fi

control_panel_hostname=${HOSTING_CONTROL_PANEL_HOSTNAME:-}
control_panel_upstream=${HOSTING_CONTROL_PANEL_UPSTREAM:-http://host.docker.internal:8080}
certificate_resolver=${TRAEFIK_CERTIFICATE_RESOLVER:-letsencrypt}

case "$control_panel_hostname" in
    ''|.*|*.|*[!A-Za-z0-9.-]*)
        echo "HOSTING_CONTROL_PANEL_HOSTNAME must be a valid hostname." >&2
        exit 1
        ;;
esac

case "$control_panel_upstream" in
    http://*|https://*) ;;
    *)
        echo "HOSTING_CONTROL_PANEL_UPSTREAM must use http:// or https://." >&2
        exit 1
        ;;
esac

case "$control_panel_upstream" in
    *[!A-Za-z0-9.:/_-]*)
        echo "HOSTING_CONTROL_PANEL_UPSTREAM contains unsupported characters." >&2
        exit 1
        ;;
esac

case "$certificate_resolver" in
    ''|*[!A-Za-z0-9_-]*)
        echo "TRAEFIK_CERTIFICATE_RESOLVER contains unsupported characters." >&2
        exit 1
        ;;
esac

output_file="$script_directory/dynamic/control-panel.yaml"
temporary_file="$output_file.tmp"
trap 'rm -f "$temporary_file"' EXIT HUP INT TERM
umask 022

{
    printf '%s\n' 'http:'
    printf '%s\n' '  routers:'
    printf '%s\n' '    control-panel:'
    printf '      rule: "Host(`%s`)"\n' "$control_panel_hostname"
    printf '%s\n' '      entryPoints:'
    printf '%s\n' '        - websecure'
    printf '%s\n' '      service: control-panel'
    printf '%s\n' '      tls:'
    printf '        certResolver: %s\n' "$certificate_resolver"
    printf '%s\n' '  services:'
    printf '%s\n' '    control-panel:'
    printf '%s\n' '      loadBalancer:'
    printf '%s\n' '        servers:'
    printf '          - url: "%s"\n' "$control_panel_upstream"
} > "$temporary_file"

mv "$temporary_file" "$output_file"
trap - EXIT HUP INT TERM
echo "Wrote $output_file for $control_panel_hostname"
