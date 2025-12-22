<?php
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'name' => getenv('DB_NAME') ?: 'beanprepared',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'onesignal' => [
        'app_id_ios' => getenv('ONESIGNAL_APP_ID_IOS') ?: '',
        'app_id_android' => getenv('ONESIGNAL_APP_ID_ANDROID') ?: '',
        'rest_api_key' => getenv('ONESIGNAL_REST_API_KEY') ?: '',
    ],
];
