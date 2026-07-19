<?php

return [
    /*
    | Optional shared secret required on /admin/login (set in production).
    | Leave empty locally to hide the field.
    */
    'login_secret' => env('ADMIN_LOGIN_SECRET'),

    'login_ip_max_attempts' => (int) env('ADMIN_LOGIN_IP_MAX_ATTEMPTS', 10),

    'login_ip_decay_minutes' => (int) env('ADMIN_LOGIN_IP_DECAY_MINUTES', 15),
];
