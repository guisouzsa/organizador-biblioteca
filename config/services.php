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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google_books' => [
        'base_url' => env('GOOGLE_BOOKS_BASE_URL', 'https://www.googleapis.com/books/v1'),
        'key' => env('GOOGLE_BOOKS_API_KEY'),
        'timeout' => (int) env('GOOGLE_BOOKS_TIMEOUT', 8),
    ],

    'open_library' => [
        'base_url' => env('OPEN_LIBRARY_BASE_URL', 'https://openlibrary.org'),
        'covers_url' => env('OPEN_LIBRARY_COVERS_URL', 'https://covers.openlibrary.org'),
        'timeout' => (int) env('OPEN_LIBRARY_TIMEOUT', 8),
        'user_agent' => env('OPEN_LIBRARY_USER_AGENT', 'BibliotecaEscolar/1.0'),
        'contact' => env('OPEN_LIBRARY_CONTACT'),
    ],

    'ocr' => [
        'binary' => env('OCR_BINARY', 'tesseract'),
        'language' => env('OCR_LANGUAGE', 'por+eng'),
        'timeout' => (int) env('OCR_TIMEOUT', 20),
    ],

];
