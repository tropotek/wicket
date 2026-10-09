<?php

return [

    /*
    | Allow public self-registration at /register. When off, users are created
    | with `php artisan wicket:create-user` (or by an admin).
    */
    'registration' => (bool) env('WICKET_REGISTRATION', false),

];
