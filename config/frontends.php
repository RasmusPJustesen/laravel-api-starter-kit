<?php

return [
    'default' => 'web',
    'clients' => [
        'web' => [
            'url' => env('FRONTEND_WEB_URL', 'http://localhost:3000'),
            'authentication' => 'stateful',
            'origin' => env('FRONTEND_WEB_ORIGIN', 'http://localhost:3000'),
            'password_reset_path' => '/reset-password',
            'login_path' => '/login',
        ],
        'mobile' => [
            'url' => env('FRONTEND_MOBILE_URL'),
            'authentication' => 'stateless',
            'origin' => env('FRONTEND_MOBILE_ORIGIN'),
            'password_reset_path' => '/reset-password',
            'login_path' => '/login',
        ],
    ],
];
