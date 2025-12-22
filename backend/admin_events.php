<?php

require __DIR__ . '/lib/db.php';

$pdo = db();
$error = '';
$success = '';
$editEvent = null;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$types = $pdo->query('SELECT id, slug, name FROM notification_types ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $startsAt = trim($_POST['starts_at'] ?? '');
        $typeId = (int) ($_POST['notification_type_id'] ?? 0);

        if ($title === '' || $startsAt === '' || $typeId === 0) {
            $error = 'Title, start time, and notification type are required.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO events (title, description, starts_at, notification_type_id) VALUES (:title, :description, :starts_at, :type_id)');
            $stmt->execute([
                ':title' => $title,
                ':description' => $description ?: null,
                ':starts_at' => $startsAt,
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
        $typeId = (int) ($_POST['notification_type_id'] ?? 0);

        if ($id === 0 || $title === '' || $startsAt === '' || $typeId === 0) {
            $error = 'Title, start time, and notification type are required.';
        } else {
            $stmt = $pdo->prepare('UPDATE events SET title = :title, description = :description, starts_at = :starts_at, notification_type_id = :type_id WHERE id = :id');
            $stmt->execute([
                ':title' => $title,
                ':description' => $description ?: null,
                ':starts_at' => $startsAt,
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
    $stmt = $pdo->prepare('SELECT id, title, description, starts_at, notification_type_id FROM events WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $editEvent = $stmt->fetch();
}

$events = $pdo->query('SELECT events.id, events.title, events.description, events.starts_at, notification_types.name AS type_name, notification_types.id AS type_id FROM events JOIN notification_types ON events.notification_type_id = notification_types.id ORDER BY events.starts_at')->fetchAll();

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OnTheRock Admin - Events</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  >
  <style>
    :root {
      --otr-ink: #2c261f;
      --otr-muted: #6c6153;
      --otr-paper: #f5f0e6;
      --otr-card: #fffdf8;
      --otr-gold: #c49a2f;
      --otr-gold-strong: #b8871f;
      --otr-border: #eadfca;
      --otr-shadow: 0 12px 30px rgba(44, 38, 31, 0.12);
    }
    body {
      background: radial-gradient(circle at top, #fbf7ef 0%, var(--otr-paper) 48%, #f2eadc 100%);
      color: var(--otr-ink);
    }
    .navbar {
      background: rgba(255, 253, 248, 0.95) !important;
      backdrop-filter: blur(8px);
    }
    .card {
      background: var(--otr-card);
      border: 1px solid var(--otr-border);
      box-shadow: var(--otr-shadow);
    }
    .btn-warning,
    .btn-outline-primary,
    .btn-outline-danger {
      border-radius: 999px;
      font-weight: 700;
    }
    .btn-warning {
      background: var(--otr-gold);
      border-color: var(--otr-gold);
      color: #1f1708;
    }
    .form-control,
    .form-select {
      border-radius: 12px;
      border-color: var(--otr-border);
      background: #fffdf8;
    }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
    <div class="container">
      <a class="navbar-brand fw-semibold" href="/Web/ontherock/backend/admin_events.php">OnTheRock Admin</a>
      <div class="navbar-nav">
        <a class="nav-link" href="/Web/ontherock/backend/admin_submissions.php">Submissions</a>
        <a class="nav-link active" href="/Web/ontherock/backend/admin_events.php">Events</a>
        <a class="nav-link" href="/Web/ontherock/backend/admin_types.php">Event Types</a>
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
                <input class="form-control" name="title" required value="<?php echo h($editEvent['title'] ?? ''); ?>">
              </div>

              <div class="mb-3">
                <label class="form-label">Start Time *</label>
                <input class="form-control" name="starts_at" type="datetime-local" required value="<?php echo h($editEvent['starts_at'] ?? ''); ?>">
              </div>

              <div class="mb-3">
                <label class="form-label">Notification Type *</label>
                <select class="form-select" name="notification_type_id" required>
                  <option value="" disabled <?php echo $editEvent ? '' : 'selected'; ?>>Select...</option>
                  <?php foreach ($types as $type): ?>
                    <option value="<?php echo h((string) $type['id']); ?>" <?php echo ($editEvent && (int) $editEvent['notification_type_id'] === (int) $type['id']) ? 'selected' : ''; ?>>
                      <?php echo h($type['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="4"><?php echo h($editEvent['description'] ?? ''); ?></textarea>
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
                      <th>Description</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($events as $event): ?>
                      <tr>
                        <td><?php echo h($event['title']); ?></td>
                        <td><?php echo h($event['starts_at']); ?></td>
                        <td><?php echo h($event['type_name']); ?></td>
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
</body>
</html>
