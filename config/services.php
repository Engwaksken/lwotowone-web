<?php
return [

    'fcm' => [

        'project_id' => env('FCM_PROJECT_ID'),

        'service_account_json' => env('FCM_SERVICE_ACCOUNT_JSON'),

    ],

    'twilio' => [

        'account_sid' => env('ACCOUNT_SID'),

        'auth_token' => env('AUTH_TOKEN'),

        'from_number' => env('FROM_NUMBER'),

    ],

    'stripe' => [

        'key' => env('STRIPE_KEY'),

        'secret' => env('STRIPE_SECRET'),

        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    ],

];
