<?php

return [
    'project_disk' => env('PROJECT_FILESYSTEM_DISK', 'project_files'),

    'deployment' => [
        'base_domain' => env('HOSTING_BASE_DOMAIN', 'sites.example.com'),
        'scheme' => env('HOSTING_URL_SCHEME', 'https'),
        'docker_binary' => env('DOCKER_BINARY', 'docker'),
        'docker_config' => env('DOCKER_CONFIG') ?: storage_path('app/docker-config'),
        'traefik_container' => env('TRAEFIK_CONTAINER_NAME', 'hosting-traefik'),
        'certificate_resolver' => env('TRAEFIK_CERTIFICATE_RESOLVER', 'letsencrypt'),
        'static_image' => env('HOSTING_STATIC_IMAGE', 'student-hosting/static:latest'),
        'php_image' => env('HOSTING_PHP_IMAGE', 'student-hosting/php83:latest'),
        'runtime_root' => base_path('docker/runtimes'),
        'container_port' => 8080,
        'command_timeout' => (int) env('DOCKER_COMMAND_TIMEOUT', 600),
        'log_lines' => (int) env('DOCKER_LOG_LINES', 200),
    ],

    'database' => [
        'admin_connection' => env('HOSTING_DATABASE_ADMIN_CONNECTION', 'hosting_database_admin'),
        'container_host' => env('HOSTING_DATABASE_HOST', 'hosting-database'),
        'container_port' => (int) env('HOSTING_DATABASE_PORT', 3306),
        'docker_network' => env('HOSTING_DATABASE_NETWORK', 'hosting_database'),
    ],

    'monitoring' => [
        'traefik_access_log' => env('TRAEFIK_ACCESS_LOG_PATH') ?: base_path('docker/traefik/logs/access.json'),
        'access_log_batch_lines' => (int) env('MONITORING_ACCESS_LOG_BATCH_LINES', 10000),
        'cpu_warning_percent' => (float) env('MONITORING_CPU_WARNING_PERCENT', 90),
        'memory_warning_percent' => (float) env('MONITORING_MEMORY_WARNING_PERCENT', 90),
        'bandwidth_warning_percent' => (float) env('MONITORING_BANDWIDTH_WARNING_PERCENT', 80),
        'snapshot_retention_days' => (int) env('MONITORING_SNAPSHOT_RETENTION_DAYS', 30),
    ],

    'allowed_extensions' => [
        'static' => [
            'html', 'htm', 'css', 'js', 'mjs', 'json', 'xml', 'txt', 'map', 'webmanifest',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'avif',
            'woff', 'woff2', 'ttf', 'otf', 'eot', 'pdf',
        ],
        'php' => [
            'php', 'html', 'htm', 'css', 'js', 'mjs', 'json', 'xml', 'txt', 'map', 'webmanifest',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'avif',
            'woff', 'woff2', 'ttf', 'otf', 'eot', 'pdf',
        ],
    ],

    'blocked_names' => [
        '.env', '.git', '.gitignore', '.htaccess', '.user.ini', 'web.config',
        'dockerfile', 'docker-compose.yml', 'docker-compose.yaml',
    ],
];
