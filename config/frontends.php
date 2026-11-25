<?php

return [
    'default' => 'web',
    'clients' => [
        'web' => [
            'url' => env('FRONTEND_WEB_URL', 'http://localhost:3000'),
            'password_reset_path' => '/reset-password',
        ],
    ],
];
