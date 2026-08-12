<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],


    'crystal' => [
        'base'            => env('ASPNET_BASE_API'),
        'form_generate'   => '/api/report/form-generate',
        'ar_export_excel' => '/api/report/ar-export-excel',
        'ar_generate_pdf' => '/api/report/ar-generate',
        'ap_export_excel' => '/api/report/ap-export-excel',
        'ap_generate_pdf' => '/api/report/ap-generate',
        'gl_export_excel' => '/api/report/gl-export-excel',
        'gl_generate_pdf' => '/api/report/gl-generate',
        'history_export_excel' => '/api/report/history-export-excel',
    ],

];
