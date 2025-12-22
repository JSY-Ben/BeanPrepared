<?php

require __DIR__ . '/lib/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/Web/beanprepared';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}
$path = rtrim($path, '/');

function json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    if ($method === 'GET' && $path === '/api/notification-types') {
        $stmt = db()->query('SELECT id, slug, name, description FROM notification_types ORDER BY id');
        json_response(200, ['data' => $stmt->fetchAll()]);
    }

    if ($method === 'GET' && $path === '/api/events') {
        $stmt = db()->query('SELECT events.id, events.title, events.description, events.starts_at, notification_types.slug AS type_slug FROM events JOIN notification_types ON events.notification_type_id = notification_types.id ORDER BY events.starts_at');
        json_response(200, ['data' => $stmt->fetchAll()]);
    }

    if ($method === 'GET' && $path === '/api/event-submissions') {
        $stmt = db()->query('SELECT id, name, email, phone, starts_at, is_organizer, status, admin_notes, description, website, created_at FROM event_submissions ORDER BY created_at DESC');
        json_response(200, ['data' => $stmt->fetchAll()]);
    }

    if ($method === 'POST' && $path === '/api/users/register') {
        $input = json_input();
        $externalId = trim($input['external_user_id'] ?? '');
        $platform = trim($input['platform'] ?? '');
        $playerId = trim($input['onesignal_player_id'] ?? '');

        if ($externalId === '' || !in_array($platform, ['ios', 'android'], true)) {
            json_response(422, ['error' => 'external_user_id and platform are required']);
        }

        $pdo = db();
        $stmt = $pdo->prepare('INSERT INTO users (external_user_id, platform, onesignal_player_id) VALUES (:external_user_id, :platform, :player_id) ON DUPLICATE KEY UPDATE platform = VALUES(platform), onesignal_player_id = VALUES(onesignal_player_id)');
        $stmt->execute([
            ':external_user_id' => $externalId,
            ':platform' => $platform,
            ':player_id' => $playerId ?: null,
        ]);

        json_response(200, ['ok' => true]);
    }

    if ($method === 'POST' && $path === '/api/preferences') {
        $input = json_input();
        $externalId = trim($input['external_user_id'] ?? '');
        $typeSlugs = $input['notification_types'] ?? [];
        $leadMinutes = $input['lead_minutes'] ?? [];

        if ($externalId === '') {
            json_response(422, ['error' => 'external_user_id is required']);
        }

        $pdo = db();
        $pdo->beginTransaction();

        $userStmt = $pdo->prepare('SELECT id FROM users WHERE external_user_id = :external_user_id');
        $userStmt->execute([':external_user_id' => $externalId]);
        $user = $userStmt->fetch();

        if (!$user) {
            $pdo->rollBack();
            json_response(404, ['error' => 'user not found']);
        }

        $userId = (int) $user['id'];

        $pdo->prepare('DELETE FROM user_notification_types WHERE user_id = :user_id')->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM user_notification_leads WHERE user_id = :user_id')->execute([':user_id' => $userId]);

        if (is_array($typeSlugs) && count($typeSlugs) > 0) {
            $typeStmt = $pdo->prepare('SELECT id FROM notification_types WHERE slug = :slug');
            $insertTypeStmt = $pdo->prepare('INSERT INTO user_notification_types (user_id, notification_type_id) VALUES (:user_id, :type_id)');
            foreach ($typeSlugs as $slug) {
                $typeStmt->execute([':slug' => $slug]);
                $type = $typeStmt->fetch();
                if ($type) {
                    $insertTypeStmt->execute([
                        ':user_id' => $userId,
                        ':type_id' => $type['id'],
                    ]);
                }
            }
        }

        if (is_array($leadMinutes) && count($leadMinutes) > 0) {
            $insertLeadStmt = $pdo->prepare('INSERT INTO user_notification_leads (user_id, lead_minutes) VALUES (:user_id, :lead_minutes)');
            foreach ($leadMinutes as $lead) {
                $leadInt = (int) $lead;
                if ($leadInt > 0) {
                    $insertLeadStmt->execute([
                        ':user_id' => $userId,
                        ':lead_minutes' => $leadInt,
                    ]);
                }
            }
        }

        $pdo->commit();
        json_response(200, ['ok' => true]);
    }

    if ($method === 'POST' && $path === '/api/events') {
        $input = json_input();
        $title = trim($input['title'] ?? '');
        $startsAt = trim($input['starts_at'] ?? '');
        $typeSlug = trim($input['notification_type_slug'] ?? '');
        $description = trim($input['description'] ?? '');

        if ($title === '' || $startsAt === '' || $typeSlug === '') {
            json_response(422, ['error' => 'title, starts_at, and notification_type_slug are required']);
        }

        $pdo = db();
        $typeStmt = $pdo->prepare('SELECT id FROM notification_types WHERE slug = :slug');
        $typeStmt->execute([':slug' => $typeSlug]);
        $type = $typeStmt->fetch();

        if (!$type) {
            json_response(404, ['error' => 'notification type not found']);
        }

        $stmt = $pdo->prepare('INSERT INTO events (title, description, starts_at, notification_type_id) VALUES (:title, :description, :starts_at, :type_id)');
        $stmt->execute([
            ':title' => $title,
            ':description' => $description ?: null,
            ':starts_at' => $startsAt,
            ':type_id' => $type['id'],
        ]);

        json_response(201, ['ok' => true]);
    }

    if ($method === 'POST' && $path === '/api/event-submissions') {
        $input = json_input();
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $startsAt = trim($input['starts_at'] ?? '');
        $isOrganizer = $input['is_organizer'] ?? null;
        $description = trim($input['description'] ?? '');
        $website = trim($input['website'] ?? '');

        if ($name === '' || $email === '' || $startsAt === '' || $description === '' || !is_bool($isOrganizer)) {
            json_response(422, ['error' => 'name, email, starts_at, description, and is_organizer are required']);
        }

        $stmt = db()->prepare(
            'INSERT INTO event_submissions (name, email, phone, starts_at, is_organizer, status, admin_notes, description, website)
             VALUES (:name, :email, :phone, :starts_at, :is_organizer, :status, :admin_notes, :description, :website)'
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone ?: null,
            ':starts_at' => $startsAt,
            ':is_organizer' => $isOrganizer ? 1 : 0,
            ':status' => 'pending',
            ':admin_notes' => null,
            ':description' => $description,
            ':website' => $website ?: null,
        ]);

        json_response(201, ['ok' => true]);
    }

    json_response(404, ['error' => 'Not found']);
} catch (Throwable $error) {
    json_response(500, ['error' => $error->getMessage()]);
}
