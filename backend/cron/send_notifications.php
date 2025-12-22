<?php

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/onesignal.php';

$pdo = db();
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$windowPaddingMinutes = 1;

$leadStmt = $pdo->query('SELECT DISTINCT lead_minutes FROM user_notification_leads');
$leads = $leadStmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($leads as $leadMinutes) {
    $leadMinutes = (int) $leadMinutes;
    if ($leadMinutes <= 0) {
        continue;
    }

    $leadInterval = new DateInterval('PT' . $leadMinutes . 'M');
    $paddingInterval = new DateInterval('PT' . $windowPaddingMinutes . 'M');
    $windowStart = $now->add($leadInterval)->sub($paddingInterval);
    $windowEnd = $now->add($leadInterval)->add($paddingInterval);

    $eventStmt = $pdo->prepare(
        'SELECT events.id, events.title, events.starts_at, notification_types.slug AS type_slug
         FROM events
         JOIN notification_types ON events.notification_type_id = notification_types.id
         LEFT JOIN event_notifications_sent sent ON sent.event_id = events.id AND sent.lead_minutes = :lead_minutes
         WHERE sent.event_id IS NULL AND events.starts_at BETWEEN :start_time AND :end_time'
    );

    $eventStmt->execute([
        ':lead_minutes' => $leadMinutes,
        ':start_time' => $windowStart->format('Y-m-d H:i:s'),
        ':end_time' => $windowEnd->format('Y-m-d H:i:s'),
    ]);

    $events = $eventStmt->fetchAll();
    foreach ($events as $event) {
        $userStmt = $pdo->prepare(
            'SELECT users.external_user_id
             FROM users
             JOIN user_notification_types unt ON unt.user_id = users.id
             JOIN notification_types nt ON nt.id = unt.notification_type_id
             JOIN user_notification_leads unl ON unl.user_id = users.id AND unl.lead_minutes = :lead_minutes
             WHERE nt.slug = :type_slug'
        );

        $userStmt->execute([
            ':lead_minutes' => $leadMinutes,
            ':type_slug' => $event['type_slug'],
        ]);

        $users = $userStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$users) {
            continue;
        }

        $config = require __DIR__ . '/../config.php';
        $payload = [
            'app_id' => $config['onesignal']['app_id_android'],
            'include_external_user_ids' => $users,
            'headings' => ['en' => 'BeanPrepared'],
            'contents' => ['en' => $event['title'] . ' starts soon.'],
            'data' => [
                'event_id' => $event['id'],
                'lead_minutes' => $leadMinutes,
            ],
        ];

        $result = onesignal_send($payload);
        if ($result['ok']) {
            $insertStmt = $pdo->prepare('INSERT INTO event_notifications_sent (event_id, lead_minutes) VALUES (:event_id, :lead_minutes)');
            $insertStmt->execute([
                ':event_id' => $event['id'],
                ':lead_minutes' => $leadMinutes,
            ]);
        }
    }
}
