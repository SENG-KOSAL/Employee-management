<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:3000',      // Next.js dev
        'http://127.0.0.1:3000',
        'http://192.168.56.1:3000', // Local network dev
        'https://yourdomain.com',      // Production domain
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];

//php artisan optimize:clear , not important now 
