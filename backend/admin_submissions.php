<?php

require __DIR__ . '/lib/db.php';

$statusFilter = $_GET['status'] ?? 'all';
$allowed = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($statusFilter, $allowed, true)) {
    $statusFilter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? 'update';
    $status = $_POST['status'] ?? '';
    $notes = trim($_POST['admin_notes'] ?? '');
    $statusAllowed = ['pending', 'approved', 'rejected'];

    if ($action === 'delete' && $id > 0) {
        $stmt = db()->prepare('DELETE FROM event_submissions WHERE id = :id');
        $stmt->execute([':id' => $id]);
    } elseif ($id > 0 && in_array($status, $statusAllowed, true)) {
        $stmt = db()->prepare('SELECT id, name, email, status FROM event_submissions WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $submission = $stmt->fetch();

        $stmt = db()->prepare('UPDATE event_submissions SET status = :status, admin_notes = :admin_notes WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':admin_notes' => $notes === '' ? null : $notes,
            ':id' => $id,
        ]);

        if ($submission && $submission['status'] !== $status && in_array($status, ['approved', 'rejected'], true)) {
            $email = trim((string) ($submission['email'] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $from = trim((string) (getenv('SUBMISSIONS_FROM_EMAIL') ?: 'no-reply@beanprepared.local'));
                $fromName = trim((string) (getenv('SUBMISSIONS_FROM_NAME') ?: 'BeanPrepared'));
                $subject = $status === 'approved'
                    ? 'Your BeanPrepared event submission is approved'
                    : 'Your BeanPrepared event submission';

                $greetingName = trim((string) ($submission['name'] ?? ''));
                $greeting = $greetingName === '' ? 'Hi,' : 'Hi ' . $greetingName . ',';
                $message = $status === 'approved'
                    ? 'Thanks for submitting your event. It has been approved and will soon be included in the app.'
                    : 'Thanks for submitting your event. This event is not appropriate for the app at this time.';

                $body = implode("\r\n", [
                    $greeting,
                    '',
                    $message,
                    '',
                    'BeanPrepared Team',
                ]);

                $headers = implode("\r\n", [
                    'From: ' . $fromName . ' <' . $from . '>',
                    'Reply-To: ' . $from,
                    'Content-Type: text/plain; charset=UTF-8',
                ]);

                @mail($email, $subject, $body, $headers);
            }
        }
    }

    header('Location: admin_submissions.php?status=' . urlencode($statusFilter));
    exit;
}

$query = 'SELECT id, name, email, phone, starts_at, ends_at, is_organizer, contact_consent, is_one_off, repeat_interval, repeat_unit, repeat_until, status, admin_notes, description, website, created_at FROM event_submissions';
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

function format_datetime(string $value): string
{
    try {
        $dt = new DateTime($value);
        return $dt->format('d/m/Y g:i A');
    } catch (Throwable $error) {
        return $value;
    }
}

function format_date(string $value): string
{
    try {
        $dt = new DateTime($value);
        return $dt->format('d/m/Y');
    } catch (Throwable $error) {
        return $value;
    }
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BeanPrepared Admin - Event Submissions</title>
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
        <a class="nav-link active" href="/Web/beanprepared/backend/admin_submissions.php">Submissions</a>
        <a class="nav-link" href="/Web/beanprepared/backend/admin_events.php">Events</a>
        <a class="nav-link" href="/Web/beanprepared/backend/admin_types.php">Event Types</a>
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
            <th>End Time</th>
            <th>Organiser</th>
            <th>Contact Consent</th>
            <th>Repeats</th>
            <th>Description</th>
            <th>Website</th>
            <th class="submission-status">Status / Notes</th>
          </tr>
          </thead>
          <tbody>
          <?php foreach ($submissions as $submission): ?>
            <tr>
              <td>
                <div><?php echo h(format_datetime($submission['created_at'])); ?></div>
                <div class="text-muted small">ID: <?php echo h((string) $submission['id']); ?></div>
              </td>
              <td><?php echo h($submission['name']); ?></td>
              <td>
                <div><?php echo h($submission['email']); ?></div>
                <?php if (!empty($submission['phone'])): ?>
                  <div class="text-muted small"><?php echo h($submission['phone']); ?></div>
                <?php endif; ?>
              </td>
              <td><?php echo h(format_datetime($submission['starts_at'])); ?></td>
              <td><?php echo h(format_datetime($submission['ends_at'])); ?></td>
              <td>
                <span class="badge text-bg-warning">
                  <?php echo $submission['is_organizer'] ? 'Yes' : 'No'; ?>
                </span>
              </td>
              <td>
                <?php if ($submission['is_organizer']): ?>
                  <span class="badge text-bg-warning">
                    <?php echo $submission['contact_consent'] ? 'Yes' : 'No'; ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted small">N/A</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($submission['is_one_off']): ?>
                  <span class="text-muted small">One-off</span>
                <?php else: ?>
                  <span class="text-muted small">
                    Every <?php echo h((string) $submission['repeat_interval']); ?>
                    <?php echo h(ucfirst($submission['repeat_unit'])); ?> until <?php echo h(format_date($submission['repeat_until'])); ?>
                  </span>
                <?php endif; ?>
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
              <td class="submission-status">
                <form method="post">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?php echo h((string) $submission['id']); ?>">
                  <select class="form-select form-select-sm mb-2" name="status">
                    <option value="pending" <?php echo $submission['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $submission['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $submission['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                  </select>
                  <textarea class="form-control form-control-sm mb-2" name="admin_notes" rows="3" placeholder="Admin notes"><?php echo h($submission['admin_notes'] ?? ''); ?></textarea>
                  <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-warning fw-semibold" type="submit">Save</button>
                    <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="delete" onclick="return confirm('Delete this submission?');">Delete</button>
                  </div>
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
