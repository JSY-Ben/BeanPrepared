<?php

require __DIR__ . '/lib/db.php';

$statusFilter = $_GET['status'] ?? 'all';
$allowed = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($statusFilter, $allowed, true)) {
    $statusFilter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $notes = trim($_POST['admin_notes'] ?? '');
    $statusAllowed = ['pending', 'approved', 'rejected'];

    if ($id > 0 && in_array($status, $statusAllowed, true)) {
        $stmt = db()->prepare('UPDATE event_submissions SET status = :status, admin_notes = :admin_notes WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':admin_notes' => $notes === '' ? null : $notes,
            ':id' => $id,
        ]);
    }

    header('Location: admin_submissions.php?status=' . urlencode($statusFilter));
    exit;
}

$query = 'SELECT id, name, email, phone, starts_at, is_organizer, status, admin_notes, description, website, created_at FROM event_submissions';
$params = [];
if ($statusFilter !== 'all') {
    $query .= ' WHERE status = :status';
    $params[':status'] = $statusFilter;
}
$query .= ' ORDER BY created_at DESC';
$stmt = db()->prepare($query);
$stmt->execute($params);
$submissions = $stmt->fetchAll();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OnTheRock Admin - Event Submissions</title>
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
      <a class="navbar-brand fw-semibold" href="/Web/ontherock/backend/admin_events.php">OnTheRock Admin</a>
      <div class="navbar-nav">
        <a class="nav-link active" href="/Web/ontherock/backend/admin_submissions.php">Submissions</a>
        <a class="nav-link" href="/Web/ontherock/backend/admin_events.php">Events</a>
        <a class="nav-link" href="/Web/ontherock/backend/admin_types.php">Event Types</a>
      </div>
    </div>
  </nav>
  <main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
      <div>
        <h1 class="h4 mb-1">Event Submissions</h1>
        <p class="text-muted mb-0"><?php echo count($submissions); ?> submissions</p>
      </div>
      <div class="btn-group flex-wrap" role="group" aria-label="Filter submissions">
        <a class="btn btn-sm <?php echo $statusFilter === 'all' ? 'btn-warning' : 'btn-outline-warning'; ?>" href="?status=all">All</a>
        <a class="btn btn-sm <?php echo $statusFilter === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>" href="?status=pending">Pending</a>
        <a class="btn btn-sm <?php echo $statusFilter === 'approved' ? 'btn-warning' : 'btn-outline-warning'; ?>" href="?status=approved">Approved</a>
        <a class="btn btn-sm <?php echo $statusFilter === 'rejected' ? 'btn-warning' : 'btn-outline-warning'; ?>" href="?status=rejected">Rejected</a>
      </div>
    </div>
    <?php if (count($submissions) === 0): ?>
      <div class="alert alert-secondary">No submissions yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle table-bordered bg-white shadow-sm">
          <thead class="table-light">
          <tr>
            <th>Submitted</th>
            <th>Name</th>
            <th>Contact</th>
            <th>Event Time</th>
            <th>Organizer</th>
            <th>Description</th>
            <th>Website</th>
            <th>Status / Notes</th>
          </tr>
          </thead>
          <tbody>
          <?php foreach ($submissions as $submission): ?>
            <tr>
              <td>
                <div><?php echo h($submission['created_at']); ?></div>
                <div class="text-muted small">ID: <?php echo h((string) $submission['id']); ?></div>
              </td>
              <td><?php echo h($submission['name']); ?></td>
              <td>
                <div><?php echo h($submission['email']); ?></div>
                <?php if (!empty($submission['phone'])): ?>
                  <div class="text-muted small"><?php echo h($submission['phone']); ?></div>
                <?php endif; ?>
              </td>
              <td><?php echo h($submission['starts_at']); ?></td>
              <td>
                <span class="badge text-bg-warning">
                  <?php echo $submission['is_organizer'] ? 'Yes' : 'No'; ?>
                </span>
              </td>
              <td><?php echo nl2br(h($submission['description'])); ?></td>
              <td>
                <?php if (!empty($submission['website'])): ?>
                  <a href="<?php echo h($submission['website']); ?>" target="_blank" rel="noreferrer">
                    <?php echo h($submission['website']); ?>
                  </a>
                <?php else: ?>
                  <span class="text-muted small">N/A</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="post">
                  <input type="hidden" name="id" value="<?php echo h((string) $submission['id']); ?>">
                  <select class="form-select form-select-sm mb-2" name="status">
                    <option value="pending" <?php echo $submission['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $submission['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $submission['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                  </select>
                  <textarea class="form-control form-control-sm mb-2" name="admin_notes" rows="3" placeholder="Admin notes"><?php echo h($submission['admin_notes'] ?? ''); ?></textarea>
                  <button class="btn btn-sm btn-warning fw-semibold" type="submit">Save</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
