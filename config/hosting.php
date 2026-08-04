<?php

return [
    'project_disk' => env('PROJECT_FILESYSTEM_DISK', 'project_files'),

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
