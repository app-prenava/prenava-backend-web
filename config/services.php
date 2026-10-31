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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


#addons API
    'airvisual' => [
        'key' => env('AIRVISUAL_API_KEY'),
    ],

    'ml' => [
        'sports' => (file_exists('/.dockerenv') && env('URL_ML_SPORTS') ? str_replace(['127.0.0.1', 'localhost'], 'host.docker.internal', (string) env('URL_ML_SPORTS')) : env('URL_ML_SPORTS')),
        'depression' => (file_exists('/.dockerenv') && env('URL_ML_DEPRESSION') ? str_replace(['127.0.0.1', 'localhost'], 'host.docker.internal', (string) env('URL_ML_DEPRESSION')) : env('URL_ML_DEPRESSION')),
        'anemia' => (file_exists('/.dockerenv') && env('URL_ML_ANEMIA') ? str_replace(['127.0.0.1', 'localhost'], 'host.docker.internal', (string) env('URL_ML_ANEMIA')) : env('URL_ML_ANEMIA')),
        'stunting' => (file_exists('/.dockerenv') ? str_replace(['127.0.0.1', 'localhost'], 'host.docker.internal', (string) env('URL_ML_STUNTING', 'http://127.0.0.1:5000')) : env('URL_ML_STUNTING', 'http://127.0.0.1:5000')),
        'stunting_predict' => (file_exists('/.dockerenv') ? str_replace(['127.0.0.1', 'localhost'], 'host.docker.internal', (string) env('ML_API_URL', 'http://127.0.0.1:5000/predict')) : env('ML_API_URL', 'http://127.0.0.1:5000/predict')),
        'stunting_timeout' => env('ML_STUNTING_TIMEOUT', 90),
        'stunting_connect_timeout' => env('ML_STUNTING_CONNECT_TIMEOUT', 10),
        'rag' => (file_exists('/.dockerenv') ? str_replace(['127.0.0.1', 'localhost'], 'host.docker.internal', (string) env('URL_ML_RAG', 'http://127.0.0.1:8002')) : env('URL_ML_RAG', 'http://127.0.0.1:8002')),
        'food' => (file_exists('/.dockerenv') ? str_replace(['127.0.0.1', 'localhost'], 'host.docker.internal', (string) env('URL_ML_FOOD', 'http://127.0.0.1:9002')) : env('URL_ML_FOOD', 'http://127.0.0.1:9002')),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''),
        'model'   => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'timeout' => env('GEMINI_TIMEOUT', 30),
    ],

    'google' => [
        'client_id'          => env('GOOGLE_CLIENT_ID'),
        'web_client_id'      => env('GOOGLE_CLIENT_ID'),
        'android_client_id'  => env('GOOGLE_ANDROID_CLIENT_ID'),
        'ios_client_id'      => env('GOOGLE_IOS_CLIENT_ID'),
        'allowed_client_ids' => array_values(array_filter([
            env('GOOGLE_CLIENT_ID'),
            env('GOOGLE_ANDROID_CLIENT_ID'),
            env('GOOGLE_IOS_CLIENT_ID'),
        ])),
    ],

    'firecrawl' => [
        'api_key' => env('FIRECRAWL_API_KEY'),
    ],

];
