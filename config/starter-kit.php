<?php

return [
    'authentication' => env('STARTER_KIT_AUTH', 'both'),
    'registration' => env('STARTER_KIT_REGISTRATION', true),
    'password_reset' => env('STARTER_KIT_PASSWORD_RESET', true),
    'email_verification' => env('STARTER_KIT_EMAIL_VERIFICATION', true),
    'two_factor' => env('STARTER_KIT_TWO_FACTOR', true),
    'require_two_factor' => env('STARTER_KIT_REQUIRE_TWO_FACTOR', false),
    'device_management' => env('STARTER_KIT_DEVICE_MANAGEMENT', true),
    'remember_me' => env('STARTER_KIT_REMEMBER_ME', true),
    'remember_days' => (int) env('AUTH_REMEMBER_DAYS', 30),
    'new_device_notifications' => env('AUTH_NEW_DEVICE_NOTIFICATIONS', false),
    'rate_limits' => [
        'authentication' => (int) env('AUTH_RATE_LIMIT', 5),
        'verification' => (int) env('AUTH_VERIFICATION_RATE_LIMIT', 6),
        'sensitive' => (int) env('AUTH_SENSITIVE_RATE_LIMIT', 5),
        'sensitive-read' => (int) env('AUTH_SENSITIVE_READ_RATE_LIMIT', 30),
    ],
    'account_deletion' => env('STARTER_KIT_ACCOUNT_DELETION', true),
    'challenge_lifetime' => (int) env('AUTH_CHALLENGE_LIFETIME', 300),
    'security_notifications' => env('AUTH_SECURITY_NOTIFICATIONS', true),
    'locales' => array_values(array_filter(array_map('trim', explode(',', env('API_LOCALES', 'en'))))),
];
