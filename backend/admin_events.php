<?php

require __DIR__ . '/lib/db.php';

$pdo = db();
$error = '';
$success = '';
$editEvent = null;
$formData = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_datetime(string $value): string
{
    if ($value === '') {
        return '';
    }
    try {
        $dt = new DateTime($value);
        return $dt->format('d/m/Y g:i A');
    } catch (Throwable $error) {
        return $value;
    }
}

function form_value(string $key, ?array $editEvent, array $formData): string
{
    if (array_key_exists($key, $formData)) {
        return (string) $formData[$key];
    }
    if ($editEvent && array_key_exists($key, $editEvent)) {
        return (string) $editEvent[$key];
    }
    return '';
}

function format_repeat(array $event): string
{
    $isOneOff = (int) ($event['is_one_off'] ?? 1) === 1;
    if ($isOneOff) {
        return 'One-off';
    }
    $interval = (int) ($event['repeat_interval'] ?? 0);
    $unit = $event['repeat_unit'] ?? '';
    $until = $event['repeat_until'] ?? '';
    if ($interval < 1 || $unit === '' || $until === '') {
        return 'Repeats';
    }
    $label = $interval === 1 ? $unit : $unit . 's';
    try {
        $untilDate = new DateTime($until);
        $until = $untilDate->format('d/m/Y');
    } catch (Throwable $error) {
    }
    return 'Every ' . $interval . ' ' . $label . ' until ' . $until;
}

$types = $pdo->query('SELECT id, slug, name FROM notification_types ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $startsAt = trim($_POST['starts_at'] ?? '');
        $endsAt = trim($_POST['ends_at'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $organiserEmail = trim($_POST['organiser_email'] ?? '');
        $organiserPhone = trim($_POST['organiser_phone'] ?? '');
        $typeId = (int) ($_POST['notification_type_id'] ?? 0);
        $isOneOffRaw = $_POST['is_one_off'] ?? '';
        $isOneOff = $isOneOffRaw === 'yes' ? 1 : ($isOneOffRaw === 'no' ? 0 : null);
        $repeatInterval = trim($_POST['repeat_interval'] ?? '');
        $repeatUnit = trim($_POST['repeat_unit'] ?? '');
        $repeatUntil = trim($_POST['repeat_until'] ?? '');

        if ($title === '' || $startsAt === '' || $typeId === 0 || $isOneOff === null) {
            $error = 'Title, start time, one-off choice, and notification type are required.';
        } elseif ($endsAt !== '' && strtotime($endsAt) <= strtotime($startsAt)) {
            $error = 'End time must be after the start time.';
        } elseif ($isOneOff === 0 && ($repeatInterval === '' || $repeatUnit === '' || $repeatUntil === '')) {
            $error = 'Please complete the repeat schedule for repeating events.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO events (title, description, starts_at, ends_at, website, organiser_email, organiser_phone, is_one_off, repeat_interval, repeat_unit, repeat_until, notification_type_id) VALUES (:title, :description, :starts_at, :ends_at, :website, :organiser_email, :organiser_phone, :is_one_off, :repeat_interval, :repeat_unit, :repeat_until, :type_id)');
            $stmt->execute([
                ':title' => $title,
                ':description' => $description ?: null,
                ':starts_at' => $startsAt,
                ':ends_at' => $endsAt ?: null,
                ':website' => $website ?: null,
                ':organiser_email' => $organiserEmail ?: null,
                ':organiser_phone' => $organiserPhone ?: null,
                ':is_one_off' => $isOneOff,
                ':repeat_interval' => $isOneOff ? null : (int) $repeatInterval,
                ':repeat_unit' => $isOneOff ? null : $repeatUnit,
                ':repeat_until' => $isOneOff ? null : $repeatUntil,
                ':type_id' => $typeId,
            ]);
            $success = 'Event created.';
        }
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $startsAt = trim($_POST['starts_at'] ?? '');
        $endsAt = trim($_POST['ends_at'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $organiserEmail = trim($_POST['organiser_email'] ?? '');
        $organiserPhone = trim($_POST['organiser_phone'] ?? '');
        $typeId = (int) ($_POST['notification_type_id'] ?? 0);
        $isOneOffRaw = $_POST['is_one_off'] ?? '';
        $isOneOff = $isOneOffRaw === 'yes' ? 1 : ($isOneOffRaw === 'no' ? 0 : null);
        $repeatInterval = trim($_POST['repeat_interval'] ?? '');
        $repeatUnit = trim($_POST['repeat_unit'] ?? '');
        $repeatUntil = trim($_POST['repeat_until'] ?? '');

        if ($id === 0 || $title === '' || $startsAt === '' || $typeId === 0 || $isOneOff === null) {
            $error = 'Title, start time, one-off choice, and notification type are required.';
        } elseif ($endsAt !== '' && strtotime($endsAt) <= strtotime($startsAt)) {
            $error = 'End time must be after the start time.';
        } elseif ($isOneOff === 0 && ($repeatInterval === '' || $repeatUnit === '' || $repeatUntil === '')) {
            $error = 'Please complete the repeat schedule for repeating events.';
        } else {
            $stmt = $pdo->prepare('UPDATE events SET title = :title, description = :description, starts_at = :starts_at, ends_at = :ends_at, website = :website, organiser_email = :organiser_email, organiser_phone = :organiser_phone, is_one_off = :is_one_off, repeat_interval = :repeat_interval, repeat_unit = :repeat_unit, repeat_until = :repeat_until, notification_type_id = :type_id WHERE id = :id');
            $stmt->execute([
                ':title' => $title,
                ':description' => $description ?: null,
                ':starts_at' => $startsAt,
                ':ends_at' => $endsAt ?: null,
                ':website' => $website ?: null,
                ':organiser_email' => $organiserEmail ?: null,
                ':organiser_phone' => $organiserPhone ?: null,
                ':is_one_off' => $isOneOff,
                ':repeat_interval' => $isOneOff ? null : (int) $repeatInterval,
                ':repeat_unit' => $isOneOff ? null : $repeatUnit,
                ':repeat_until' => $isOneOff ? null : $repeatUntil,
                ':type_id' => $typeId,
                ':id' => $id,
            ]);
            $success = 'Event updated.';
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM events WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $success = 'Event deleted.';
        }
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT id, title, description, starts_at, ends_at, website, organiser_email, organiser_phone, is_one_off, repeat_interval, repeat_unit, repeat_until, notification_type_id FROM events WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $editEvent = $stmt->fetch();
}

$events = $pdo->query('SELECT events.id, events.title, events.description, events.starts_at, events.ends_at, events.website, events.organiser_email, events.organiser_phone, events.is_one_off, events.repeat_interval, events.repeat_unit, events.repeat_until, notification_types.name AS type_name, notification_types.id AS type_id FROM events JOIN notification_types ON events.notification_type_id = notification_types.id ORDER BY events.starts_at')->fetchAll();

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BeanPrepared Admin - Events</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  >
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
  <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
    <div class="container">
      <a class="navbar-brand fw-semibold" href="/Web/beanprepared/backend/admin_events.php">BeanPrepared Admin</a>
      <div class="navbar-nav">
        <a class="nav-link" href="/Web/beanprepared/backend/admin_submissions.php">Submissions</a>
        <a class="nav-link active" href="/Web/beanprepared/backend/admin_events.php">Events</a>
        <a class="nav-link" href="/Web/beanprepared/backend/admin_types.php">Event Types</a>
      </div>
    </div>
  </nav>

  <main class="container py-4">
    <div class="row g-4">
      <div class="col-12 col-lg-4">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title mb-3"><?php echo $editEvent ? 'Edit Event' : 'Create Event'; ?></h5>

            <?php if ($error !== ''): ?>
              <div class="alert alert-danger"><?php echo h($error); ?></div>
            <?php elseif ($success !== ''): ?>
              <div class="alert alert-success"><?php echo h($success); ?></div>
            <?php endif; ?>

            <form method="post">
              <input type="hidden" name="action" value="<?php echo $editEvent ? 'update' : 'create'; ?>">
              <?php if ($editEvent): ?>
                <input type="hidden" name="id" value="<?php echo h((string) $editEvent['id']); ?>">
              <?php endif; ?>

              <div class="mb-3">
                <label class="form-label">Title *</label>
                <input class="form-control" name="title" required value="<?php echo h(form_value('title', $editEvent, $formData)); ?>">
              </div>

              <div class="mb-3">
                <label class="form-label">Start Time *</label>
                <input class="form-control" name="starts_at" type="datetime-local" required value="<?php echo h(form_value('starts_at', $editEvent, $formData)); ?>">
              </div>

              <div class="mb-3">
                <label class="form-label">End Time</label>
                <input class="form-control" name="ends_at" type="datetime-local" value="<?php echo h(form_value('ends_at', $editEvent, $formData)); ?>">
              </div>

              <div class="mb-3">
                <label class="form-label">Notification Type *</label>
                <select class="form-select" name="notification_type_id" required>
                  <option value="" disabled <?php echo $editEvent ? '' : 'selected'; ?>>Select...</option>
                  <?php foreach ($types as $type): ?>
                    <option value="<?php echo h((string) $type['id']); ?>" <?php echo ((int) form_value('notification_type_id', $editEvent, $formData) === (int) $type['id']) ? 'selected' : ''; ?>>
                      <?php echo h($type['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <?php
                $oneOffValue = form_value('is_one_off', $editEvent, $formData);
                if ($oneOffValue === '0' || $oneOffValue === 0) {
                    $oneOffValue = 'no';
                } elseif ($oneOffValue === '1' || $oneOffValue === 1) {
                    $oneOffValue = 'yes';
                }
                $repeatIntervalValue = form_value('repeat_interval', $editEvent, $formData);
                $repeatUnitValue = form_value('repeat_unit', $editEvent, $formData);
                $repeatUntilValue = form_value('repeat_until', $editEvent, $formData);
              ?>

              <div class="mb-3">
                <label class="form-label">Is this event a one-off? *</label>
                <select class="form-select" name="is_one_off" id="is_one_off" required>
                  <option value="" disabled <?php echo $oneOffValue === '' ? 'selected' : ''; ?>>Select...</option>
                  <option value="yes" <?php echo $oneOffValue === 'yes' ? 'selected' : ''; ?>>Yes</option>
                  <option value="no" <?php echo $oneOffValue === 'no' ? 'selected' : ''; ?>>No</option>
                </select>
              </div>

              <div class="mb-3 <?php echo $oneOffValue === 'no' ? '' : 'd-none'; ?>" id="repeatSection">
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label">Every *</label>
                    <input class="form-control" name="repeat_interval" type="number" min="1" value="<?php echo h($repeatIntervalValue); ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Frequency *</label>
                    <select class="form-select" name="repeat_unit">
                      <option value="" disabled <?php echo $repeatUnitValue === '' ? 'selected' : ''; ?>>Select...</option>
                      <option value="daily" <?php echo $repeatUnitValue === 'daily' ? 'selected' : ''; ?>>Daily</option>
                      <option value="weekly" <?php echo $repeatUnitValue === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                      <option value="monthly" <?php echo $repeatUnitValue === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Until *</label>
                    <input class="form-control" name="repeat_until" type="date" value="<?php echo h($repeatUntilValue); ?>">
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="4"><?php echo h(form_value('description', $editEvent, $formData)); ?></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label">Website</label>
                <input class="form-control" name="website" type="url" value="<?php echo h(form_value('website', $editEvent, $formData)); ?>">
              </div>

              <div class="mb-3">
                <label class="form-label">Organiser Email</label>
                <input class="form-control" name="organiser_email" type="email" value="<?php echo h(form_value('organiser_email', $editEvent, $formData)); ?>">
              </div>

              <div class="mb-3">
                <label class="form-label">Organiser Phone</label>
                <input class="form-control" name="organiser_phone" value="<?php echo h(form_value('organiser_phone', $editEvent, $formData)); ?>">
              </div>

              <button type="submit" class="btn btn-warning fw-semibold">
                <?php echo $editEvent ? 'Update Event' : 'Create Event'; ?>
              </button>
              <?php if ($editEvent): ?>
                <a class="btn btn-link" href="admin_events.php">Cancel</a>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title mb-3">Events</h5>
            <?php if (count($events) === 0): ?>
              <div class="alert alert-secondary">No events found.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th>Title</th>
                      <th>Start</th>
                      <th>Type</th>
                      <th>End</th>
                      <th>Website</th>
                      <th>Organiser Email</th>
                      <th>Organiser Phone</th>
                      <th>Repeat</th>
                      <th>Description</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($events as $event): ?>
                      <tr>
                        <td><?php echo h($event['title']); ?></td>
                        <td><?php echo h(format_datetime($event['starts_at'])); ?></td>
                        <td><?php echo h($event['type_name']); ?></td>
                        <td><?php echo h(format_datetime($event['ends_at'] ?? '')); ?></td>
                        <td><?php echo h($event['website'] ?? ''); ?></td>
                        <td><?php echo h($event['organiser_email'] ?? ''); ?></td>
                        <td><?php echo h($event['organiser_phone'] ?? ''); ?></td>
                        <td><?php echo h(format_repeat($event)); ?></td>
                        <td><?php echo h($event['description'] ?? ''); ?></td>
                        <td>
                          <a class="btn btn-sm btn-outline-primary" href="admin_events.php?edit=<?php echo h((string) $event['id']); ?>">Edit</a>
                          <form method="post" style="display:inline-block" onsubmit="return confirm('Delete this event?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo h((string) $event['id']); ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
  <script>
    const oneOffSelect = document.getElementById('is_one_off');
    const repeatSection = document.getElementById('repeatSection');
    const repeatInterval = document.querySelector('input[name="repeat_interval"]');
    const repeatUnit = document.querySelector('select[name="repeat_unit"]');
    const repeatUntil = document.querySelector('input[name="repeat_until"]');
    if (oneOffSelect && repeatSection) {
      const toggleRepeat = (value) => {
        const show = value === 'no';
        repeatSection.classList.toggle('d-none', !show);
        if (!show) {
          if (repeatInterval) repeatInterval.value = '';
          if (repeatUnit) repeatUnit.value = '';
          if (repeatUntil) repeatUntil.value = '';
        }
        if (repeatInterval) repeatInterval.required = show;
        if (repeatUnit) repeatUnit.required = show;
        if (repeatUntil) repeatUntil.required = show;
      };
      toggleRepeat(oneOffSelect.value);
      oneOffSelect.addEventListener('change', (event) => toggleRepeat(event.target.value));
    }
  </script>
</body>
</html>
