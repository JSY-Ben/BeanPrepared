<?php

require __DIR__ . '/../backend/lib/db.php';

$typeFilter = $_GET['type'] ?? 'all';
$windowFilter = $_GET['window'] ?? 'all';
$types = db()->query('SELECT id, slug, name FROM notification_types ORDER BY name')->fetchAll();
$validSlugs = array_map(static fn ($type) => $type['slug'], $types);
if ($typeFilter !== 'all' && !in_array($typeFilter, $validSlugs, true)) {
    $typeFilter = 'all';
}
$windowOptions = ['today', 'week', 'month'];
if ($windowFilter !== 'all' && !in_array($windowFilter, $windowOptions, true)) {
    $windowFilter = 'all';
}

$query = 'SELECT events.id, events.title, events.description, events.starts_at, notification_types.name AS type_name, notification_types.slug AS type_slug
          FROM events
          JOIN notification_types ON events.notification_type_id = notification_types.id';
$params = [];
$conditions = [];
if ($typeFilter !== 'all') {
    $conditions[] = 'notification_types.slug = :slug';
    $params[':slug'] = $typeFilter;
}
if ($windowFilter !== 'all') {
    $now = new DateTime();
    $start = clone $now;
    $end = clone $now;

    if ($windowFilter === 'today') {
        $start->setTime(0, 0, 0);
        $end->setTime(23, 59, 59);
    } elseif ($windowFilter === 'week') {
        $day = (int) $start->format('N') - 1;
        $start->modify('-' . $day . ' days')->setTime(0, 0, 0);
        $end = clone $start;
        $end->modify('+6 days')->setTime(23, 59, 59);
    } elseif ($windowFilter === 'month') {
        $start->modify('first day of this month')->setTime(0, 0, 0);
        $end->modify('last day of this month')->setTime(23, 59, 59);
    }

    $conditions[] = 'events.starts_at BETWEEN :start AND :end';
    $params[':start'] = $start->format('Y-m-d H:i:s');
    $params[':end'] = $end->format('Y-m-d H:i:s');
}
if (count($conditions) > 0) {
    $query .= ' WHERE ' . implode(' AND ', $conditions);
}
$query .= ' ORDER BY events.starts_at';
$stmt = db()->prepare($query);
$stmt->execute($params);
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
      <a class="navbar-brand fw-semibold" href="index.php">OnTheRock</a>
      <div class="navbar-nav">
        <a class="nav-link active" href="index.php">Upcoming Events</a>
        <a class="nav-link" href="submit.php">Submit Event</a>
      </div>
    </div>
  </nav>
  <main class="container py-4">
    <div class="mb-4">
      <h1 class="mb-1">Upcoming Events</h1>
      <p class="brand-subtitle">Events curated from the OnTheRock schedule.</p>
    </div>
    <div class="mb-4">
      <div class="btn-group flex-wrap" role="group" aria-label="Filter events">
        <a class="btn btn-sm <?php echo $typeFilter === 'all' ? 'btn-warning' : 'btn-outline-warning'; ?>" href="index.php">All</a>
        <?php foreach ($types as $type): ?>
          <a class="btn btn-sm <?php echo $typeFilter === $type['slug'] ? 'btn-warning' : 'btn-outline-warning'; ?>" href="index.php?type=<?php echo h($type['slug']); ?>">
            <?php echo h($type['name']); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="mb-4">
      <div class="btn-group flex-wrap" role="group" aria-label="Filter by time window">
        <a class="btn btn-sm <?php echo $windowFilter === 'all' ? 'btn-warning' : 'btn-outline-warning'; ?>" href="index.php<?php echo $typeFilter !== 'all' ? '?type=' . h($typeFilter) : ''; ?>">All dates</a>
        <?php foreach ($windowOptions as $option): ?>
          <?php
            $label = $option === 'today' ? 'Today' : ($option === 'week' ? 'This Week' : 'This Month');
            $queryString = 'type=' . h($typeFilter) . '&window=' . h($option);
          ?>
          <a class="btn btn-sm <?php echo $windowFilter === $option ? 'btn-warning' : 'btn-outline-warning'; ?>" href="index.php?<?php echo $queryString; ?>">
            <?php echo h($label); ?>
          </a>
        <?php endforeach; ?>
      </div>
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
