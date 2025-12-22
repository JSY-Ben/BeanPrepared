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

function should_include_occurrence(DateTime $occurrence, ?DateTime $rangeStart, ?DateTime $rangeEnd): bool
{
    if ($rangeStart && $occurrence < $rangeStart) {
        return false;
    }
    if ($rangeEnd && $occurrence > $rangeEnd) {
        return false;
    }
    return true;
}

function next_occurrence(DateTime $current, int $interval, string $unit): DateTime
{
    $next = clone $current;
    if ($unit === 'daily') {
        $next->modify('+' . $interval . ' days');
    } elseif ($unit === 'weekly') {
        $next->modify('+' . $interval . ' weeks');
    } else {
        $next->modify('+' . $interval . ' months');
    }
    return $next;
}

function expand_events(array $events, ?DateTime $rangeStart = null, ?DateTime $rangeEnd = null, int $maxOccurrences = 5000): array
{
    $expanded = [];
    foreach ($events as $event) {
        $startsAt = new DateTime($event['starts_at']);
        $endsAt = !empty($event['ends_at']) ? new DateTime($event['ends_at']) : null;
        $durationSeconds = $endsAt ? ($endsAt->getTimestamp() - $startsAt->getTimestamp()) : null;
        $isOneOff = isset($event['is_one_off']) ? ((int) $event['is_one_off'] === 1) : true;
        $repeatInterval = (int) ($event['repeat_interval'] ?? 0);
        $repeatUnit = $event['repeat_unit'] ?? '';
        $repeatUntilRaw = $event['repeat_until'] ?? '';

        if ($isOneOff || $repeatInterval < 1 || $repeatUnit === '' || $repeatUntilRaw === '') {
            if (should_include_occurrence($startsAt, $rangeStart, $rangeEnd)) {
                $expanded[] = $event;
            }
            continue;
        }

        $repeatUntil = new DateTime($repeatUntilRaw . ' 23:59:59');
        $occurrence = clone $startsAt;
        $count = 0;
        while ($occurrence <= $repeatUntil && $count < $maxOccurrences) {
            if ($rangeEnd && $occurrence > $rangeEnd) {
                break;
            }
            if (should_include_occurrence($occurrence, $rangeStart, $rangeEnd)) {
                $item = $event;
                $item['id'] = $event['id'] . '-' . $occurrence->format('YmdHis');
                $item['starts_at'] = $occurrence->format('Y-m-d H:i:s');
                if ($durationSeconds !== null) {
                    $occurrenceEnd = clone $occurrence;
                    $occurrenceEnd->modify('+' . $durationSeconds . ' seconds');
                    $item['ends_at'] = $occurrenceEnd->format('Y-m-d H:i:s');
                }
                $expanded[] = $item;
            }
            $occurrence = next_occurrence($occurrence, $repeatInterval, $repeatUnit);
            $count += 1;
        }
    }
    return $expanded;
}

try {
    if ($method === 'GET' && $path === '/api/notification-types') {
        $stmt = db()->query('SELECT id, slug, name, description FROM notification_types ORDER BY id');
        json_response(200, ['data' => $stmt->fetchAll()]);
    }

    if ($method === 'GET' && $path === '/api/events') {
        $stmt = db()->query('SELECT events.id, events.title, events.description, events.starts_at, events.ends_at, events.website, events.organiser_email, events.organiser_phone, events.is_one_off, events.repeat_interval, events.repeat_unit, events.repeat_until, notification_types.slug AS type_slug FROM events JOIN notification_types ON events.notification_type_id = notification_types.id ORDER BY events.starts_at');
        $events = expand_events($stmt->fetchAll());
        usort($events, static fn ($a, $b) => strcmp($a['starts_at'], $b['starts_at']));
        json_response(200, ['data' => $events]);
    }

    if ($method === 'GET' && $path === '/api/event-submissions') {
        $stmt = db()->query('SELECT id, name, email, phone, starts_at, ends_at, is_organizer, contact_consent, is_one_off, repeat_interval, repeat_unit, repeat_until, status, admin_notes, description, website, created_at FROM event_submissions ORDER BY created_at DESC');
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
        $endsAt = trim($input['ends_at'] ?? '');
        $website = trim($input['website'] ?? '');
        $organiserEmail = trim($input['organiser_email'] ?? '');
        $organiserPhone = trim($input['organiser_phone'] ?? '');
        $isOneOff = $input['is_one_off'] ?? true;
        $repeatInterval = isset($input['repeat_interval']) ? (int) $input['repeat_interval'] : null;
        $repeatUnit = trim($input['repeat_unit'] ?? '');
        $repeatUntil = trim($input['repeat_until'] ?? '');
        $typeSlug = trim($input['notification_type_slug'] ?? '');
        $description = trim($input['description'] ?? '');

        if ($title === '' || $startsAt === '' || $typeSlug === '' || !is_bool($isOneOff)) {
            json_response(422, ['error' => 'title, starts_at, notification_type_slug, and is_one_off are required']);
        }
        if ($endsAt !== '' && strtotime($endsAt) <= strtotime($startsAt)) {
            json_response(422, ['error' => 'ends_at must be after starts_at']);
        }
        if (!$isOneOff) {
            $validUnits = ['daily', 'weekly', 'monthly'];
            if ($repeatInterval === null || $repeatInterval < 1 || $repeatUnit === '' || !in_array($repeatUnit, $validUnits, true) || $repeatUntil === '') {
                json_response(422, ['error' => 'repeat_interval, repeat_unit, and repeat_until are required for repeating events']);
            }
        }

        $pdo = db();
        $typeStmt = $pdo->prepare('SELECT id FROM notification_types WHERE slug = :slug');
        $typeStmt->execute([':slug' => $typeSlug]);
        $type = $typeStmt->fetch();

        if (!$type) {
            json_response(404, ['error' => 'notification type not found']);
        }

        $stmt = $pdo->prepare('INSERT INTO events (title, description, starts_at, ends_at, website, organiser_email, organiser_phone, is_one_off, repeat_interval, repeat_unit, repeat_until, notification_type_id) VALUES (:title, :description, :starts_at, :ends_at, :website, :organiser_email, :organiser_phone, :is_one_off, :repeat_interval, :repeat_unit, :repeat_until, :type_id)');
        $stmt->execute([
            ':title' => $title,
            ':description' => $description ?: null,
            ':starts_at' => $startsAt,
            ':ends_at' => $endsAt ?: null,
            ':website' => $website ?: null,
            ':organiser_email' => $organiserEmail ?: null,
            ':organiser_phone' => $organiserPhone ?: null,
            ':is_one_off' => $isOneOff ? 1 : 0,
            ':repeat_interval' => $isOneOff ? null : $repeatInterval,
            ':repeat_unit' => $isOneOff ? null : $repeatUnit,
            ':repeat_until' => $isOneOff ? null : $repeatUntil,
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
        $endsAt = trim($input['ends_at'] ?? '');
        $isOrganizer = $input['is_organizer'] ?? null;
        $contactConsent = $input['contact_consent'] ?? null;
        $isOneOff = $input['is_one_off'] ?? null;
        $repeatInterval = isset($input['repeat_interval']) ? (int) $input['repeat_interval'] : null;
        $repeatUnit = trim($input['repeat_unit'] ?? '');
        $repeatUntil = trim($input['repeat_until'] ?? '');
        $description = trim($input['description'] ?? '');
        $website = trim($input['website'] ?? '');

        if ($name === '' || $email === '' || $startsAt === '' || $endsAt === '' || $description === '' || !is_bool($isOrganizer) || !is_bool($isOneOff)) {
            json_response(422, ['error' => 'name, email, starts_at, ends_at, description, is_organizer, and is_one_off are required']);
        }
        if (strtotime($endsAt) <= strtotime($startsAt)) {
            json_response(422, ['error' => 'ends_at must be after starts_at']);
        }
        if ($isOrganizer && !is_bool($contactConsent)) {
            json_response(422, ['error' => 'contact_consent is required when organizer is yes']);
        }
        if (!$isOneOff) {
            $validUnits = ['daily', 'weekly', 'monthly'];
            if ($repeatInterval === null || $repeatInterval < 1 || $repeatUnit === '' || !in_array($repeatUnit, $validUnits, true) || $repeatUntil === '') {
                json_response(422, ['error' => 'repeat_interval, repeat_unit, and repeat_until are required for repeating events']);
            }
        }

        $stmt = db()->prepare(
            'INSERT INTO event_submissions (name, email, phone, starts_at, ends_at, is_organizer, contact_consent, is_one_off, repeat_interval, repeat_unit, repeat_until, status, admin_notes, description, website)
             VALUES (:name, :email, :phone, :starts_at, :ends_at, :is_organizer, :contact_consent, :is_one_off, :repeat_interval, :repeat_unit, :repeat_until, :status, :admin_notes, :description, :website)'
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone ?: null,
            ':starts_at' => $startsAt,
            ':ends_at' => $endsAt,
            ':is_organizer' => $isOrganizer ? 1 : 0,
            ':contact_consent' => $contactConsent === null ? null : ($contactConsent ? 1 : 0),
            ':is_one_off' => $isOneOff ? 1 : 0,
            ':repeat_interval' => $isOneOff ? null : $repeatInterval,
            ':repeat_unit' => $isOneOff ? null : $repeatUnit,
            ':repeat_until' => $isOneOff ? null : $repeatUntil,
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
