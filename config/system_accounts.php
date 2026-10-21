<?php

return [
    /*
     | Fixed accounts are authenticated using password hashes stored in .env.
     | They are not inserted into USERS and do not consume license seats.
     |
     | auth_user_code is only the real USERS record Laravel uses to keep the
     | session authenticated. It does not change the displayed login name.
     |
     | HEARTSTRONG is restricted to the dedicated license-management portal.
     | MIRACLE inherits the full menus/roles of the configured permission user.
     */
    'accounts' => [
        'HEARTSTRONG' => [
            'display_name' => 'HEARTSTRONG',
            'password_hash' => env('HEARTSTRONG_PASSWORD_HASH'),
            'account_mode' => 'LICENSE_ADMIN',
            'auth_user_code' => env('HEARTSTRONG_AUTH_TEMPLATE', 'NAYSA'),
            // Deliberately does not inherit normal Financials roles.
            'permission_user_code' => 'HEARTSTRONG',
        ],

        'MIRACLE' => [
            'display_name' => 'MIRACLE',
            'password_hash' => env('MIRACLE_PASSWORD_HASH'),
            'account_mode' => 'SYSTEM_ADMIN',
            'auth_user_code' => env('MIRACLE_AUTH_TEMPLATE', 'NAYSA'),
            'permission_user_code' => env(
                'MIRACLE_PERMISSION_TEMPLATE',
                'NAYSA'
            ),
        ],
    ],
];
