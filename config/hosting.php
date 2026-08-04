<?php

return [
    'project_disk' => env('PROJECT_FILESYSTEM_DISK', 'project_files'),

    'deployment' => [
        'base_domain' => env('HOSTING_BASE_DOMAIN', 'sites.example.com'),
        'scheme' => env('HOSTING_URL_SCHEME', 'https'),
        'docker_binary' => env('DOCKER_BINARY', 'docker'),
        'traefik_container' => env('TRAEFIK_CONTAINER_NAME', 'hosting-traefik'),
        'certificate_resolver' => env('TRAEFIK_CERTIFICATE_RESOLVER', 'letsencrypt'),
        'static_image' => env('HOSTING_STATIC_IMAGE', 'student-hosting/static:latest'),
        'php_image' => env('HOSTING_PHP_IMAGE', 'student-hosting/php83:latest'),
        'runtime_root' => base_path('docker/runtimes'),
        'container_port' => 8080,
        'command_timeout' => (int) env('DOCKER_COMMAND_TIMEOUT', 600),
        'log_lines' => (int) env('DOCKER_LOG_LINES', 200),
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
