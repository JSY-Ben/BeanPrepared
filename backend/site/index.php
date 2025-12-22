<?php

require __DIR__ . '/../lib/db.php';

$stmt = db()->query('SELECT events.id, events.title, events.description, events.starts_at, notification_types.name AS type_name FROM events JOIN notification_types ON events.notification_type_id = notification_types.id ORDER BY events.starts_at');
$events = $stmt->fetchAll();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_datetime(string $value): string
{
    try {
        $dt = new DateTime($value);
        return $dt->format('D, M j, Y g:i A');
    } catch (Throwable $error) {
        return $value;
    }
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OnTheRock - Upcoming Events</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  >
  <style>
    body {
      background: #f7f4ee;
    }
    .brand-subtitle {
      color: #6a6256;
    }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
    <div class="container">
      <a class="navbar-brand fw-semibold" href="/site/index.php">OnTheRock</a>
      <div class="navbar-nav">
        <a class="nav-link active" href="/site/index.php">Upcoming Events</a>
        <a class="nav-link" href="/site/submit.php">Submit Event</a>
      </div>
    </div>
  </nav>
  <main class="container py-4">
    <div class="mb-4">
      <h1 class="mb-1">Upcoming Events</h1>
      <p class="brand-subtitle">Events curated from the OnTheRock schedule.</p>
    </div>
    <?php if (count($events) === 0): ?>
      <div class="alert alert-secondary">No events have been published yet.</div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($events as $event): ?>
          <div class="col-12 col-lg-8">
            <article class="card shadow-sm">
              <div class="card-body">
                <div class="text-muted small mb-2"><?php echo h(format_datetime($event['starts_at'])); ?></div>
                <h2 class="h5 mb-2">
                  <?php echo h($event['title']); ?>
                  <span class="badge text-bg-warning ms-2"><?php echo h($event['type_name']); ?></span>
                </h2>
                <?php if (!empty($event['description'])): ?>
                  <p class="mb-0"><?php echo nl2br(h($event['description'])); ?></p>
                <?php else: ?>
                  <p class="mb-0 text-muted">No description provided.</p>
                <?php endif; ?>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
