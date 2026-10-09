<?php

return [

    /*
    | Allow public self-registration at /register. When off, users are created
    | with `php artisan wicket:create-user` (or by an admin).
    */
    'registration' => (bool) env('WICKET_REGISTRATION', false),

    /*
    | Failed logins allowed per account (or unknown identifier) + IP before
    | lockout, and how long the lockout lasts.
    */
    'login' => [
        'max_attempts' => (int) env('WICKET_LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('WICKET_LOGIN_LOCKOUT_MINUTES', 15),
    ],

];
