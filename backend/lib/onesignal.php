<?php

function onesignal_send(array $payload): array
{
    $config = require __DIR__ . '/../config.php';
    $key = $config['onesignal']['rest_api_key'];

    if (!$key) {
        return ['ok' => false, 'error' => 'Missing OneSignal REST API key'];
    }

    $ch = curl_init('https://onesignal.com/api/v1/notifications');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $key,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => $error ?: 'Request failed'];
    }

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'response' => json_decode($response, true),
    ];
}
