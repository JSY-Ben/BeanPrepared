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
  <link rel="stylesheet" href="css/site.css">
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
    <div class="mb-3 filter-bar">
      <div class="filter-group" aria-label="Filter events">
        <a class="btn btn-sm <?php echo $typeFilter === 'all' ? 'btn-warning' : 'btn-outline-warning'; ?>" href="index.php">All</a>
        <?php foreach ($types as $type): ?>
          <a class="btn btn-sm <?php echo $typeFilter === $type['slug'] ? 'btn-warning' : 'btn-outline-warning'; ?>" href="index.php?type=<?php echo h($type['slug']); ?>">
            <?php echo h($type['name']); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="mb-4">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input
          id="eventSearch"
          class="form-control"
          type="search"
          placeholder="Search events by title or description"
          aria-label="Search events"
        >
      </div>
    </div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div class="btn-group view-toggle" role="group" aria-label="View switch">
        <button class="btn btn-warning" id="viewListBtn" type="button">List</button>
        <button class="btn btn-outline-warning" id="viewCalendarBtn" type="button">Calendar</button>
      </div>
      <div class="text-muted small" id="calendarMonthLabel"></div>
    </div>

    <?php if (count($events) === 0): ?>
      <div class="alert alert-secondary">No events have been published yet.</div>
    <?php else: ?>
      <div id="listView">
        <div class="mb-4 filter-bar" id="dateFilters">
          <div class="filter-group" aria-label="Filter by time window">
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
        <div class="row g-3" id="eventCards">
          <?php foreach ($events as $event): ?>
            <div class="col-12 col-lg-8">
              <article class="card shadow-sm event-card" data-title="<?php echo h(strtolower($event['title'])); ?>" data-description="<?php echo h(strtolower($event['description'] ?? '')); ?>">
                <div class="card-body">
                  <div class="event-meta"><?php echo h(format_datetime($event['starts_at'])); ?></div>
                  <h2 class="h5 mb-2 event-title">
                    <?php echo h($event['title']); ?>
                    <span class="badge text-bg-warning ms-2"><?php echo h($event['type_name']); ?></span>
                  </h2>
                  <?php if (!empty($event['description'])): ?>
                    <p class="mb-0 event-description"><?php echo nl2br(h($event['description'])); ?></p>
                  <?php else: ?>
                    <p class="mb-0 text-muted">No description provided.</p>
                  <?php endif; ?>
                </div>
              </article>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="alert alert-secondary mt-3 d-none" id="noResults">No matching events.</div>
      </div>

      <div id="calendarView" class="d-none">
        <div class="card shadow-sm mb-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <button class="btn btn-sm btn-outline-warning" id="prevMonthBtn" type="button">Prev</button>
              <div class="fw-semibold" id="monthTitle"></div>
              <button class="btn btn-sm btn-outline-warning" id="nextMonthBtn" type="button">Next</button>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered text-center align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Mon</th>
                    <th>Tue</th>
                    <th>Wed</th>
                    <th>Thu</th>
                    <th>Fri</th>
                    <th>Sat</th>
                    <th>Sun</th>
                  </tr>
                </thead>
                <tbody id="calendarGrid"></tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title" id="selectedDateTitle">Select a date</h5>
            <div id="selectedDateEvents" class="text-muted">No events for this date.</div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </main>
  <script>
    const listBtn = document.getElementById('viewListBtn');
    const calendarBtn = document.getElementById('viewCalendarBtn');
    const listView = document.getElementById('listView');
    const calendarView = document.getElementById('calendarView');
    const dateFilters = document.getElementById('dateFilters');
    const eventSearch = document.getElementById('eventSearch');
    const eventCards = document.getElementById('eventCards');
    const noResults = document.getElementById('noResults');
    const monthTitle = document.getElementById('monthTitle');
    const calendarGrid = document.getElementById('calendarGrid');
    const selectedDateTitle = document.getElementById('selectedDateTitle');
    const selectedDateEvents = document.getElementById('selectedDateEvents');
    const calendarMonthLabel = document.getElementById('calendarMonthLabel');
    const prevBtn = document.getElementById('prevMonthBtn');
    const nextBtn = document.getElementById('nextMonthBtn');

    const events = <?php echo json_encode(array_map(static function ($event) {
        return [
            'id' => $event['id'],
            'title' => $event['title'],
            'description' => $event['description'],
            'starts_at' => $event['starts_at'],
            'type_name' => $event['type_name'],
            'type_slug' => $event['type_slug'],
        ];
    }, $events)); ?>;

    const eventsByDate = events.reduce((acc, event) => {
      const key = event.starts_at.slice(0, 10);
      acc[key] = acc[key] || [];
      acc[key].push(event);
      return acc;
    }, {});

    let current = new Date();
    current.setDate(1);
    let selectedDate = null;

    function formatDateLabel(date) {
      return date.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' });
    }

    function renderEventsForDate(dateKey) {
      selectedDate = dateKey;
      if (!dateKey) {
        selectedDateTitle.textContent = 'Select a date';
        selectedDateEvents.textContent = 'No events for this date.';
        return;
      }
      selectedDateTitle.textContent = formatDateLabel(new Date(dateKey));
      const items = eventsByDate[dateKey] || [];
      if (items.length === 0) {
        selectedDateEvents.textContent = 'No events for this date.';
        return;
      }
      selectedDateEvents.innerHTML = items.map((event) => {
        const time = new Date(event.starts_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const description = event.description ? `<div class="text-muted small">${event.description}</div>` : '';
        return `<div class="mb-2"><div class="fw-semibold">${event.title} <span class="badge text-bg-warning">${event.type_name}</span></div><div class="small text-muted">${time}</div>${description}</div>`;
      }).join('');
    }

    function renderCalendar() {
      const year = current.getFullYear();
      const month = current.getMonth();
      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const startOffset = (firstDay.getDay() + 6) % 7;

      monthTitle.textContent = current.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
      calendarMonthLabel.textContent = monthTitle.textContent;

      let html = '';
      let day = 1 - startOffset;
      for (let week = 0; week < 6; week += 1) {
        html += '<tr>';
        for (let i = 0; i < 7; i += 1) {
          const cellDate = new Date(year, month, day);
          const dateKey = cellDate.toISOString().slice(0, 10);
          const isCurrentMonth = cellDate.getMonth() === month;
          const hasEvents = Boolean(eventsByDate[dateKey]);
          const isSelected = selectedDate === dateKey;
          html += `<td class="${isCurrentMonth ? '' : 'text-muted bg-light'} ${isSelected ? 'table-warning' : ''} ${hasEvents ? 'calendar-event' : ''}" data-date="${dateKey}">${cellDate.getDate()}${hasEvents ? '<div class="calendar-dot"></div>' : ''}</td>`;
          day += 1;
        }
        html += '</tr>';
      }
      calendarGrid.innerHTML = html;
    }

    function switchView(mode) {
      if (mode === 'calendar') {
        listView.classList.add('d-none');
        calendarView.classList.remove('d-none');
        if (dateFilters) {
          dateFilters.classList.add('d-none');
        }
        listBtn.classList.remove('btn-warning');
        listBtn.classList.add('btn-outline-warning');
        calendarBtn.classList.add('btn-warning');
        calendarBtn.classList.remove('btn-outline-warning');
        renderCalendar();
      } else {
        calendarView.classList.add('d-none');
        listView.classList.remove('d-none');
        if (dateFilters) {
          dateFilters.classList.remove('d-none');
        }
        listBtn.classList.add('btn-warning');
        listBtn.classList.remove('btn-outline-warning');
        calendarBtn.classList.remove('btn-warning');
        calendarBtn.classList.add('btn-outline-warning');
      }
    }

    if (listBtn && calendarBtn) {
      listBtn.addEventListener('click', () => switchView('list'));
      calendarBtn.addEventListener('click', () => switchView('calendar'));
    }

    if (prevBtn && nextBtn) {
      prevBtn.addEventListener('click', () => {
        current.setMonth(current.getMonth() - 1);
        renderCalendar();
      });
      nextBtn.addEventListener('click', () => {
        current.setMonth(current.getMonth() + 1);
        renderCalendar();
      });
    }

    if (calendarGrid) {
      calendarGrid.addEventListener('click', (event) => {
        const target = event.target.closest('td[data-date]');
        if (!target) return;
        renderEventsForDate(target.dataset.date);
        renderCalendar();
      });
    }

    if (eventSearch) {
      eventSearch.addEventListener('input', applySearchFilter);
    }
  </script>
</body>
</html>
    function applySearchFilter() {
      if (!eventCards) return;
      const query = eventSearch ? eventSearch.value.trim().toLowerCase() : '';
      let visible = 0;
      eventCards.querySelectorAll('.event-card').forEach((card) => {
        const title = card.dataset.title || '';
        const description = card.dataset.description || '';
        const matches = title.includes(query) || description.includes(query);
        card.closest('.col-12').classList.toggle('d-none', !matches);
        if (matches) visible += 1;
      });
      if (noResults) {
        noResults.classList.toggle('d-none', visible > 0);
      }
    }
