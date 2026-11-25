<?php

return [
    'authentication' => env('STARTER_KIT_AUTH', 'both'),
    'registration' => env('STARTER_KIT_REGISTRATION', true),
    'password_reset' => env('STARTER_KIT_PASSWORD_RESET', true),
    'two_factor' => env('STARTER_KIT_TWO_FACTOR', true),
    'account_deletion' => env('STARTER_KIT_ACCOUNT_DELETION', true),
    'challenge_lifetime' => 300,
];
